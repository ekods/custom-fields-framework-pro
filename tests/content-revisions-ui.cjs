// Real PHP revision handlers + real WordPress metadata, with an isolated temporary SQLite database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const {spawn} = require('node:child_process');

(async function () {
  const {default: puppeteer} = await import('puppeteer');
  const core = process.env.WP_ADMIN_TEST_ROOT;
  assert.ok(core && fs.existsSync(path.join(core, 'wp-includes/meta.php')), 'Set WP_ADMIN_TEST_ROOT to WordPress source');
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'cff-revisions-ui-'));
  const socket = net.createServer().listen(0, '127.0.0.1');
  await new Promise(resolve => socket.on('listening', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const base = 'http://127.0.0.1:' + port;
  const server = spawn(process.env.TK_PHP_BINARY || 'php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'fixtures/content-revisions-page.php')], {
    env: {...process.env, CFF_REVISION_TEST_DSN: 'sqlite:' + path.join(temp, 'meta.sqlite')}, stdio: ['ignore', 'pipe', 'pipe']
  });
  let output = '';
  server.stderr.on('data', chunk => { output += chunk; });
  let browser;
  let page;
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  try {
    for (let attempt = 0; attempt < 60; attempt++) {
      try { if ((await fetch(base)).ok) break; } catch (error) {}
      await new Promise(resolve => setTimeout(resolve, 100));
      if (attempt === 59) throw new Error('PHP server did not start: ' + output);
    }
    browser = await puppeteer.launch({headless: true});
    page = await browser.newPage();
    page.setDefaultTimeout(10000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({width: 1280, height: 900});
    await page.goto(base, {waitUntil: 'networkidle0'});
    const ready = async () => {
      await page.waitForFunction(() => document.querySelector('#cff-revision-to')?.options.length >= 2
        && document.querySelector('.cff-revisions-results').getAttribute('aria-busy') === 'false', {polling: 100});
    };
    const open = async () => {
      await page.click('.cff-revisions-open');
      await page.waitForSelector('.cff-revisions-history-dialog[open] .cff-revisions-history-date');
      await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revisions-history-compare')]);
      await ready();
    };
    const popupReady = async () => {
      await page.waitForFunction(() => document.querySelector('.cff-revisions-history-dialog')?.open
        && document.querySelector('.cff-revisions-history-body').getAttribute('aria-busy') === 'false', {polling: 100});
    };
    await page.click('.cff-revisions-open');
    await popupReady();
    check(await page.$eval('.cff-revisions-history-dialog', node => node.open && node.getAttribute('aria-labelledby') === 'cff-revisions-history-title'), 'Editor history button opens an accessible Revisions popup');
    check(await page.$eval('.cff-revisions-history-close', node => node.textContent.trim() === '×' && node.getAttribute('aria-label') === 'Close revisions'), 'Popup close action displays only X and retains an accessible name');
    check(await page.$$eval('.cff-revisions-history-item', nodes => nodes.length === 2 && !nodes[0].textContent.includes('Captured by')), 'Popup lists only this ID’s revisions, newest first');
    check(await page.$eval('.cff-revisions-history-item', node => !!node.querySelector('img') && node.textContent.includes('Editor 8') && node.textContent.includes('ago')), 'Popup rows include avatar, editing user and relative time');
    check(await page.$eval('.cff-revisions-history-date time', node => /^[A-Za-z]+ \d{1,2}, \d{4} @ \d{2}:\d{2}:\d{2}$/.test(node.textContent) && node.dateTime.endsWith('Z')), 'Popup date link shows full local month and exact time with an ISO datetime');
    const popupLinks = await page.$$eval('.cff-revisions-history-date', nodes => nodes.map(node => ({id: node.dataset.revisionId, href: node.href})));
    check(popupLinks.every(link => new URL(link.href).searchParams.get('post_id') === '123' && new URL(link.href).searchParams.get('revision_id') === link.id), 'Every date link keeps its own content ID and selected revision');
    check(await page.$eval('.cff-revisions-history-dialog', node => node.contains(document.activeElement)), 'Keyboard focus enters the popup');
    check(await page.$eval('.cff-revisions-history-retry', node => getComputedStyle(node).display === 'none'), 'Successful history loads hide the retry button despite WordPress button styling');
    await page.keyboard.press('Escape');
    check(await page.$eval('.cff-revisions-history-dialog', node => !node.open) && await page.evaluate(() => document.activeElement.classList.contains('cff-revisions-open')), 'Escape closes the popup and returns focus to the editor trigger');
    await page.click('.cff-revisions-open');
    await popupReady();
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revisions-history-item:last-child .cff-revisions-history-date')]);
    await ready();
    check(await page.$eval('#cff-revision-to', node => node.value === '0') && new URL(page.url()).searchParams.get('revision_id') === popupLinks[1].id, 'Clicking an older date selects that exact revision on the To side');
    check(await page.$eval('.cff-revision-restore', node => !node.disabled), 'Opening a date does not restore content automatically');
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revisions-back')]);
    let holdNextHistory = false;
    let failNextHistory = false;
    let pendingHistory;
    const interceptHistory = async request => {
      if (new URL(request.url()).pathname === '/ajax' && new URLSearchParams(request.postData()).get('mode') === 'history') {
        if (holdNextHistory) { holdNextHistory = false; pendingHistory = request; return; }
        if (failNextHistory) {
          failNextHistory = false;
          await request.respond({status: 500, contentType: 'application/json', body: JSON.stringify({success: false, data: {message: 'Fixture history unavailable'}})});
          return;
        }
      }
      await request.continue();
    };
    await page.setRequestInterception(true);
    page.on('request', interceptHistory);
    holdNextHistory = true;
    await Promise.all([page.waitForRequest(request => new URL(request.url()).pathname === '/ajax' && new URLSearchParams(request.postData()).get('mode') === 'history'), page.click('.cff-revisions-open')]);
    await page.waitForFunction(() => document.querySelector('.cff-revisions-history-body').getAttribute('aria-busy') === 'true');
    await page.click('.cff-revisions-history-close');
    await page.click('.cff-revisions-open');
    await popupReady();
    assert.ok(pendingHistory, 'A history response was held for the close/reopen test');
    await Promise.all([page.waitForResponse(response => response.request() === pendingHistory), pendingHistory.continue()]);
    check(await page.$$eval('.cff-revisions-history-item', nodes => nodes.length === 2), 'A delayed response from a closed popup cannot duplicate or replace the reopened list');
    await page.click('.cff-revisions-history-close');
    failNextHistory = true;
    await page.click('.cff-revisions-open');
    await popupReady();
    check(await page.$eval('.cff-revisions-history-body [role="alert"]', node => node.textContent.includes('Fixture history unavailable')), 'Popup displays failed history requests without stale rows');
    check(await page.$eval('.cff-revisions-history-retry', node => getComputedStyle(node).display !== 'none'), 'A failed load exposes the retry action');
    await page.click('.cff-revisions-history-retry');
    await popupReady();
    check(await page.$$eval('.cff-revisions-history-item', nodes => nodes.length === 2), 'Try again reloads history successfully');
    await page.click('.cff-revisions-history-close');
    page.off('request', interceptHistory);
    await page.setRequestInterception(false);
    await page.setViewport({width: 390, height: 320});
    await page.click('.cff-revisions-open');
    await popupReady();
    check(await page.evaluate(() => {
      const dialog = document.querySelector('.cff-revisions-history-dialog').getBoundingClientRect();
      const body = document.querySelector('.cff-revisions-history-body');
      const close = document.querySelector('.cff-revisions-history-close').getBoundingClientRect();
      return dialog.left >= 0 && dialog.right <= innerWidth && dialog.bottom <= innerHeight
        && body.scrollHeight > body.clientHeight && close.top >= dialog.top && close.bottom <= dialog.bottom;
    }), 'Popup body scrolls on small screens while the Close button stays accessible');
    const closeTop = await page.$eval('.cff-revisions-history-close', node => node.getBoundingClientRect().top);
    await page.hover('.cff-revisions-history-body');
    await page.mouse.wheel({deltaY: 300});
    await page.waitForFunction(() => document.querySelector('.cff-revisions-history-body').scrollTop > 0, {polling: 100});
    check(await page.$eval('.cff-revisions-history-close', (node, top) => Math.abs(node.getBoundingClientRect().top - top) < 1, closeTop), 'Mouse-wheel scrolling moves the revision list while the X remains fixed');
    await page.$eval('.cff-revisions-history-body', node => { node.scrollTop = 0; });
    await page.focus('.cff-revisions-history-body');
    await page.keyboard.press('PageDown');
    await page.waitForFunction(() => document.querySelector('.cff-revisions-history-body').scrollTop > 0, {polling: 100});
    check(true, 'Focusable revision list supports keyboard scrolling');
    await page.keyboard.press('Escape');
    await page.setViewport({width: 1280, height: 900});
    const settle = async () => {
      await page.waitForFunction(() => document.querySelector('.cff-revisions-results').getAttribute('aria-busy') === 'false'
        && !document.querySelector('[role="status"]').textContent.includes('Loading'), {polling: 100});
    };
    const tooltipPosition = async () => page.evaluate(() => {
      const range = document.querySelector('#cff-revision-to-range');
      const tooltip = document.querySelector('#cff-revision-to-tooltip');
      const inputRect = range.getBoundingClientRect();
      const tipRect = tooltip.getBoundingClientRect();
      const arrow = parseFloat(getComputedStyle(tooltip).getPropertyValue('--cff-tooltip-arrow'));
      const center = inputRect.left + 22 + (inputRect.width - 44) * (Number(range.value) / Number(range.max));
      return tipRect.left >= inputRect.left - 1 && tipRect.right <= inputRect.right + 1
        && Math.abs(tipRect.left + arrow - center) < 2 && tipRect.bottom < inputRect.top;
    });
    const markersHaveGutters = async () => page.evaluate(() => {
      const cells = [...document.querySelectorAll('td.diff-addedline, td.diff-deletedline')];
      return cells.length > 0 && cells.every(cell => {
        const marker = cell.querySelector(':scope > .dashicons, :scope > .cff-diff-marker');
        if (!marker) return false;
        const box = cell.getBoundingClientRect();
        const icon = marker.getBoundingClientRect();
        if (icon.left < box.left || icon.right > box.right) return false;
        const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT, {acceptNode(node) {
          return node.textContent.trim() && !node.parentElement.closest('.screen-reader-text, .dashicons, .cff-diff-marker') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }});
        let node;
        while ((node = walker.nextNode())) {
          const range = document.createRange();
          range.selectNodeContents(node);
          if ([...range.getClientRects()].some(rect => rect.width && rect.left < icon.right + 3)) return false;
        }
        return true;
      });
    });
    await open();
    check(await page.$eval('h1', node => node.textContent.includes('Compare CFF Revisions of') && node.textContent.includes('Content 123')), 'Full revision page opens with the selected content title');
    check(new URL(page.url()).searchParams.get('post_id') === '123' && await page.$('dialog') === null, 'Revision page has its own per-ID URL instead of a modal');
    check(await page.$eval('.cff-revision-restore', node => node.disabled), 'Current revision cannot be restored unnecessarily');
    check(await page.$eval('.cff-revision-to .cff-revision-person', node => node.textContent.includes('Current revision by Editor 8')), 'To header names the user who changed the selected version');
    check(await page.$eval('.cff-revision-person time', node => /\d{2}:\d{2}:\d{2} WIB/.test(node.textContent) && node.parentElement.textContent.includes('ago')), 'Header displays exact local date/time, timezone and relative time');
    check(await page.$eval('.cff-revision-from .cff-revision-person', node => node.textContent.includes('original editor and change time unavailable')), 'Captured baseline is honestly distinguished from known author history');
    const text = await page.$eval('.cff-revisions-results', node => node.textContent);
    check(text.includes('rows[0].nested[0].label') && text.includes('Original nested'), 'Comparison displays nested field paths and values');
    check(await page.$('.diff-addedline') && await page.$('.diff-deletedline'), 'WordPress comparison uses green added and red removed columns');
    check(text.includes('<img src=x onerror=') && !(await page.evaluate(() => window.injected)), 'Saved markup remains escaped text');
    check(await markersHaveGutters(), 'Diff markers have a reserved gutter even when core revisions.css loads after CFF');
    check(await page.$eval('#cff-revision-to-tooltip', node => node.textContent.includes('Revision by Editor 8') && node.textContent.includes('ago')), 'Timeline speech bubble shows the selected author and time');
    check(await tooltipPosition(), 'Tooltip arrow points to the handle at the final revision without overflowing');
    check(await page.$eval('#cff-revision-to-range', node => node.getAttribute('aria-describedby') === 'cff-revision-to-tooltip'), 'Native keyboard slider is associated with its identity tooltip');
    const desktopLayout = await page.evaluate(() => {
      const rect = selector => document.querySelector(selector).getBoundingClientRect();
      const from = rect('.cff-revision-from');
      const to = rect('.cff-revision-to');
      const fromSelect = rect('#cff-revision-from');
      const toSelect = rect('#cff-revision-to');
      const actions = rect('.cff-revisions-actions');
      return {aligned: Math.abs(from.top - to.top) < 1 && Math.abs(from.width - to.width) < 1,
        selectors: Math.abs(fromSelect.top - toSelect.top) < 1,
        actions: actions.top >= Math.max(fromSelect.bottom, toSelect.bottom),
        compact: getComputedStyle(document.querySelector('table.diff')).borderCollapse === 'collapse'};
    });
    check(desktopLayout.aligned && desktopLayout.selectors, 'From and To use equal columns with aligned full-width selectors');
    check(desktopLayout.actions, 'Restore and its note sit in a separate footer below both versions');
    check(desktopLayout.compact, 'Diff rows have no separated gutters between empty or removed lines');
    if (process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP) await page.screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP, fullPage: true});
    check(await page.$eval('#cff-revision-from', node => node.disabled), 'Normal mode compares adjacent versions automatically');
    await page.click('#cff-compare-any');
    await settle();
    check(await page.$eval('#cff-revision-from', node => !node.disabled) && await page.$eval('.cff-revision-from-range', node => !node.hidden), 'Compare any two revisions enables independent source and target controls');
    await page.select('#cff-revision-from', '1');
    await settle();
    check(await page.$eval('[role="status"]', node => node.textContent.includes('No differences')), 'Can select the same version on both sides');
    await page.select('#cff-revision-to', '0');
    await settle();
    check(await page.$eval('.cff-revision-restore', node => !node.disabled), 'Restore applies to the To revision');
    await page.focus('#cff-revision-to-range');
    await page.keyboard.press('ArrowRight');
    await settle();
    check(await page.$eval('#cff-revision-to', node => node.value === '1'), 'Timeline supports keyboard navigation and synchronizes version selectors');
    await page.click('.cff-revision-previous');
    await settle();
    check(await page.$eval('#cff-revision-to', node => node.value === '0'), 'Previous button moves through versions');
    check(await tooltipPosition(), 'Tooltip arrow stays anchored at the first revision');
    await page.click('.cff-revision-next');
    await settle();
    check(await page.$eval('#cff-revision-to', node => node.value === '1'), 'Next button moves through versions');
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revisions-back')]);
    await page.type('#title', ' unsaved');
    await page.click('.cff-revisions-open');
    await page.waitForFunction(() => document.querySelector('[role="alert"]').textContent.includes('Save your editor changes'));
    check(await page.$('#title'), 'Unsaved classic editor changes prevent navigation away to history');
    await page.goto(base + '/?block=1', {waitUntil: 'networkidle0'});
    await page.evaluate(() => {
      const row = document.createElement('input');
      row.type = 'hidden';
      row.name = 'cff_values[rows][0][answer]';
      row.value = 'An unsaved row added without a change event';
      document.querySelector('#metaboxes').append(row);
    });
    await page.click('.cff-revisions-open');
    await page.waitForFunction(() => document.querySelector('[role="alert"]').textContent.includes('Save your editor changes'));
    check(await page.$('#metaboxes'), 'Gutenberg metabox form also protects unsaved structural row changes');
    await page.goto(base, {waitUntil: 'networkidle0'});
    await page.setViewport({width: 390, height: 844});
    await open();
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Revision page has no horizontal overflow on mobile');
    check(await tooltipPosition(), 'Tooltip resizes and remains anchored inside the mobile timeline');
    check(await page.evaluate(() => {
      const from = document.querySelector('.cff-revision-from').getBoundingClientRect();
      const to = document.querySelector('.cff-revision-to').getBoundingClientRect();
      return to.top >= from.bottom && Math.abs(from.left - to.left) < 1;
    }), 'From and To stack in full-width rows on mobile');
    check(await page.$$eval('table.diff', nodes => nodes.every(node => node.scrollWidth <= node.clientWidth + 1)), 'Diff tables wrap long content inside the mobile columns');
    check(await markersHaveGutters(), 'Diff markers never overlap wrapped text on mobile');
    if (process.env.CFF_REVISIONS_SCREENSHOT) await page.screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT, fullPage: true});
    await page.select('#cff-revision-to', '0');
    await settle();
    page.once('dialog', dialog => dialog.dismiss());
    await page.click('.cff-revision-restore');
    check(new URL(page.url()).searchParams.get('page') === 'cff-content-revisions', 'Cancelling restore keeps the comparison open');
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revision-restore')]);
    check(await page.$eval('#title', node => node.value === 'Original C:\\draft\\quoted "title"'), 'Restore persists selected values and returns to their editor');
    await open();
    check(await page.$$eval('#cff-revision-to option', nodes => nodes.length === 3), 'Restored version is recorded without losing the pre-restore version');
    check(await page.$eval('.cff-revision-to .cff-revision-person', node => node.textContent.includes('Editor 7') && node.textContent.includes('Restored')), 'Restored revision records the restoring user separately');
    await page.setViewport({width: 1280, height: 900});
    await page.goto(base + '/admin.php?page=cff-content-revisions&post_id=123&extra_history=1', {waitUntil: 'networkidle0'});
    await ready();
    check(await page.$$eval('.cff-revision-to-range .cff-revision-ticks span', nodes => nodes.length === 3), 'Five revisions generate the three interior segment marks');
    await page.select('#cff-revision-to', '2');
    await settle();
    check(await tooltipPosition(), 'Identity bubble follows a middle revision');
    await page.focus('#cff-revision-to-range');
    if (process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP) {
      await page.screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP, fullPage: true});
      await (await page.$('.cff-revisions-navigation')).screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP.replace(/\.png$/, '-slider.png')});
    }
    await page.keyboard.press('Home');
    await settle();
    check(await page.$eval('#cff-revision-to-range', node => node.value === '0'), 'Custom handle preserves native Home key navigation');
    await page.keyboard.press('End');
    await settle();
    check(await page.$eval('#cff-revision-to-range', node => node.value === '4'), 'Custom handle preserves native End key navigation');
    const slider = await page.$eval('#cff-revision-to-range', node => {
      const rect = node.getBoundingClientRect();
      return {left: rect.left + 22, right: rect.right - 22, y: rect.top + rect.height / 2};
    });
    await page.mouse.move(slider.right, slider.y);
    await page.mouse.down();
    await page.mouse.move((slider.left + slider.right) / 2, slider.y, {steps: 5});
    await page.mouse.up();
    await settle();
    check(await page.$eval('#cff-revision-to-range', node => node.value === '2'), 'Circular handle supports pointer dragging between revision marks');
    // Partial restore uses the actual checkbox UI and preserves all unselected fields.
    await page.select('#cff-revision-to', '1');
    await settle();
    await page.click('.cff-revisions-partial summary');
    check(await page.$eval('.cff-revision-restore-selected', node => node.disabled), 'Empty field selection cannot start a restore');
    await page.click('.cff-revision-field-options input[value="field:rows"]');
    check(await page.$eval('.cff-revision-restore-selected', node => !node.disabled), 'Selecting a section enables partial restore');
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revision-restore-selected')]);
    check(await page.$eval('#title', node => node.value === 'Timeline version 4'), 'Checkbox partial restore keeps the unselected current title');
    check(await page.$eval('#row-answer', node => node.value === 'New answer'), 'Selected repeater section is restored from the To revision');
    await open();
    check(await page.$eval('.cff-revision-to .cff-revision-person', node => node.textContent.includes('Restored fields')), 'Partial revision is identified in its author summary');
    await page.select('#cff-revision-to', '0');
    await settle();
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-revision-restore-unit[data-field="field:title"]')]);
    check(await page.$eval('#title', node => node.value === 'Original C:\\draft\\quoted "title"'), 'Per-section action restores only its named field');
    check(await page.$eval('#row-answer', node => node.value === 'New answer'), 'Per-field action leaves the other restored section untouched');
    await open();
    const summaries = await page.evaluate(async () => {
      const body = new URLSearchParams({action: 'cff_content_revisions', mode: 'history', post_id: '123', nonce: 'valid-cff_content_revisions_123'});
      return (await (await fetch('/ajax', {method: 'POST', body})).json()).data.items;
    });
    check(summaries.length === 7 && summaries[0].restored_fields[0] === 'field:title', 'Partial restore creates a complete per-ID revision with the selected field scope');
    await page.select('#cff-revision-user', '8');
    await page.click('.cff-revisions-filters button[type="submit"]');
    await settle();
    check(await page.$$eval('#cff-revision-to option', nodes => nodes.length === 2), 'User filter limits the slider and dropdowns to matching revisions');
    check(await page.$eval('.cff-revision-to .cff-revision-person strong', node => node.textContent === 'Editor 8'), 'Filtered selection retains the correct editing user');
    const date = summaries.find(item => item.user_id === 8).local_date;
    await page.evaluate(date => {
      document.querySelector('#cff-revision-date-from').value = date;
      document.querySelector('#cff-revision-date-to').value = date;
    }, date);
    await page.click('.cff-revisions-filters button[type="submit"]');
    await settle();
    check(await page.$$eval('#cff-revision-to option', nodes => nodes.length === 2), 'User and inclusive local-day filters combine correctly');
    await page.evaluate(() => {
      document.querySelector('#cff-revision-date-from').value = '1900-01-01';
      document.querySelector('#cff-revision-date-to').value = '1900-01-01';
    });
    await page.click('.cff-revisions-filters button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('[role="status"]').textContent.includes('No revisions match'), {polling: 100});
    check(await page.$eval('.cff-revisions-workspace', node => node.hidden), 'No matching revisions hide stale comparisons and restore controls');
    check(await page.$eval('.cff-revision-restore', node => node.disabled) && await page.$eval('.cff-revision-field-options', node => !node.children.length), 'Empty filter result clears the previous restore target and field selection');
    await page.click('.cff-revision-reset-filters');
    await settle();
    check(await page.$$eval('#cff-revision-to option', nodes => nodes.length === 7), 'Reset restores the complete history for the current ID');
    await page.evaluate(() => {
      document.querySelector('#cff-revision-date-from').value = '2026-11-10';
      document.querySelector('#cff-revision-date-to').value = '2026-11-09';
    });
    await page.click('.cff-revisions-filters button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('[role="alert"]').textContent.includes('From date must'), {polling: 100});
    check(await page.$eval('.cff-revisions-workspace', node => node.hidden), 'Invalid date order is reported without leaving a stale restore available');
    await page.click('.cff-revision-reset-filters');
    await settle();
    await page.setViewport({width: 390, height: 844});
    await page.select('#cff-revision-to', '0');
    await settle();
    await page.click('.cff-revisions-partial summary');
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Filter form and partial restore options fit mobile widths');
    if (process.env.CFF_REVISIONS_SCREENSHOT) await page.screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT, fullPage: true});
    await page.goto(base + '/admin.php?page=cff-content-revisions&post_id=456', {waitUntil: 'networkidle0'});
    await page.waitForFunction(() => document.querySelector('[role="status"]').textContent.includes('No revisions yet'), {polling: 100});
    check(await page.$eval('h1', node => node.textContent.includes('Content 456')), 'Other content ID has its own empty history page');
    await page.goto(base + '/?editor=1&post_id=456', {waitUntil: 'networkidle0'});
    await page.click('.cff-revisions-open');
    await popupReady();
    check(await page.$eval('.cff-revisions-history-body', node => node.textContent.includes('No revisions yet'))
      && await page.$$eval('.cff-revisions-history-item', nodes => !nodes.length), 'An empty popup cannot show history belonging to another content ID');
    check(await page.$eval('.cff-revisions-history-compare', node => node.hidden), 'Empty history does not offer misleading revision links');
    await page.keyboard.press('Escape');
    await page.goto(base + '/admin.php?page=cff-content-revisions&post_id=123&revision_id=deleted-or-foreign-revision', {waitUntil: 'networkidle0'});
    await page.waitForFunction(() => document.querySelector('[role="alert"]').textContent.includes('no longer available'), {polling: 100});
    check(await page.$eval('.cff-revisions-workspace', node => node.hidden), 'Missing or foreign revision links never silently select a different version');
    await page.setViewport({width: 1280, height: 900});
    await page.goto(base + '/?editor=1&post_id=123', {waitUntil: 'networkidle0'});
    await page.click('.cff-revisions-open');
    await popupReady();
    if (process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP) await (await page.$('.cff-revisions-history-dialog')).screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT_DESKTOP.replace(/\.png$/, '-popup.png')});
    await page.setViewport({width: 390, height: 844});
    check(await page.evaluate(() => {
      const dialog = document.querySelector('.cff-revisions-history-dialog');
      return dialog.scrollWidth <= dialog.clientWidth && document.documentElement.scrollWidth <= innerWidth;
    }), 'Popup revision rows wrap names and linked dates without mobile overflow');
    if (process.env.CFF_REVISIONS_SCREENSHOT) await page.screenshot({path: process.env.CFF_REVISIONS_SCREENSHOT.replace(/\.png$/, '-popup.png'), fullPage: true});
    check(errors.length === 0, 'No browser runtime errors: ' + errors.join('; '));
    console.log('Content revisions UI: ' + checks + ' checks passed (desktop/mobile, real AJAX compare/restore, isolation).');
  } catch (error) {
    if (page) error.message += '\nPage: ' + await page.$eval('body', node => node.textContent.slice(0, 5000));
    error.message += '\nServer: ' + output.slice(-3000);
    throw error;
  } finally {
    if (browser) await browser.close();
    server.kill();
    await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve));
    fs.rmSync(temp, {recursive: true, force: true});
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
