// Real WordPress add/edit checks against the disposable loopback UI runtime.
// PLUGIN_UI_RUNTIME=/tmp/nexa-ui-runtime node tests/definition-editor-ui.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

(async () => {
  const origin = process.env.PLUGIN_UI_ORIGIN || 'http://127.0.0.1:8897';
  assert.equal(new URL(origin).hostname, '127.0.0.1', 'Use only the isolated loopback runtime');
  const output = fs.mkdtempSync(path.join(os.tmpdir(), 'cff-definition-editors-'));
  const {default: puppeteer} = await import('puppeteer');
  const browser = await puppeteer.launch({headless: true});
  let checks = 0;
  function check(actual, expected, message) { assert.deepEqual(actual, expected, message); checks++; }
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.setViewport({width: 1440, height: 1000});
    await page.goto(origin + '/wp-login.php', {waitUntil: 'networkidle0'});
    await page.type('#user_login', 'ui_review');
    await page.type('#user_pass', process.env.PLUGIN_UI_PASSWORD || 'ui-review-local-2026!');
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('#wp-submit')]);
    const admin = async query => {
      await page.goto(origin + '/wp-admin/admin.php?' + query, {waitUntil: 'networkidle0'});
      assert.doesNotMatch(await page.$eval('body', node => node.textContent), /critical error|Fatal error/i);
    };
    const fill = async (selector, value) => {
      await page.$eval(selector, input => { input.value = ''; input.dispatchEvent(new Event('input', {bubbles: true})); });
      await page.type(selector, value);
    };
    async function layout(name) {
      check(await page.$$('.cff-collection .wp-list-table').then(nodes => nodes.length), 0, 'List must not appear above editor');
      check(await page.$$('.cff-definition-form').then(nodes => nodes.length), 1, 'One definition form');
      check(await page.$eval('.cff-editor-footer button[type=submit]', button => button.form.matches('[data-cff-editor]')), true, 'Sidebar save owns the definition form');
      check(await page.$eval('.cff-definition-form', form => form.querySelectorAll('[name*="nonce"]').length), 1, 'Original form nonce retained');
      for (const width of [1440, 1024, 782, 390, 320]) {
        await page.setViewport({width, height: 1000});
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, name + ': overflow at ' + width);
        check(await page.$$eval('.cff-definition-box', panels => panels.every(panel => {
          const box = panel.getBoundingClientRect();
          return [...panel.querySelectorAll('input:not([type=hidden]), select, button')].every(control => {
            if (!control.getClientRects().length || control.closest('.select2-hidden-accessible')) return true;
            const rect = control.getBoundingClientRect();
            return rect.left >= box.left - 1 && rect.right <= box.right + 1;
          });
        })), true, name + ': input exceeds panel at ' + width);
        const positions = await page.evaluate(() => {
          const main = document.querySelector('.cff-editor-main').getBoundingClientRect();
          const side = document.querySelector('.cff-editor-sidebar').getBoundingClientRect();
          const workspace = document.querySelector('#poststuff').getBoundingClientRect();
          return {aligned: Math.abs(main.top - side.top) < 2, beside: side.left >= main.right, stacked: side.top >= main.bottom,
            fillsWidth: Math.abs(main.left - workspace.left) < 2 && Math.abs(side.right - workspace.right) < 2};
        });
        check(width > 850 ? positions.aligned && positions.beside : positions.stacked, true, name + ': column alignment at ' + width);
        check(positions.fillsWidth, true, name + ': unused sidebar margin at ' + width);
        if (width === 1440 || width === 390) {
          await page.evaluate(() => window.scrollTo(0, 0));
          await page.screenshot({path: path.join(output, name + '-' + width + '.png'), fullPage: true});
        }
      }
      await page.setViewport({width: 1440, height: 1000});
    }
    for (const kind of ['post', 'taxonomy']) {
      const isPost = kind === 'post';
      const slug = isPost ? 'cff-post-types' : 'cff-taxonomies';
      const key = isPost ? 'ui_editor_entry' : 'ui_editor_taxonomy';
      await admin('page=' + slug);
      check(await page.$('#cff-definition-editor'), null, 'List should only contain the list');
      const add = '.tk-hero-action a';
      check(await page.$eval(add, link => new URL(link.href).searchParams.get('action')), 'add', 'Add opens its own view');
      await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click(add)]);
      await layout(kind + '-add');
      const singular = isPost ? '#cff-cpt-singular' : '#cff-tax-singular';
      const plural = isPost ? '#cff-cpt-plural' : '#cff-tax-plural';
      await fill(singular, isPost ? 'UI Editor Entry' : 'UI Editor Taxonomy');
      await fill(plural, isPost ? 'UI Editor Entries' : 'UI Editor Taxonomies');
      if (!isPost) {
        await fill('#cff-tax-key', key);
        await page.select('#cff-tax-types', 'post', 'page');
      }
      check(await page.$eval('.cff-save-status', node => node.classList.contains('is-dirty')), true, 'Dirty status follows original inputs');
      await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-editor-footer button[type=submit]')]);
      await admin('page=' + slug + '&edit=' + key);
      await layout(kind + '-edit');
      check(await page.$eval(plural, input => input.value), isPost ? 'UI Editor Entries' : 'UI Editor Taxonomies', 'Created labels persist');
      const rest = isPost ? '[name=cpt_rest]' : '[name=show_in_rest]';
      check(await page.$eval(rest, input => input.checked), true, 'REST setting preserved');
      if (!isPost) check(await page.$eval('#cff-tax-types', select => [...select.selectedOptions].map(option => option.value).sort()), ['page', 'post'], 'Connected post types persist');
      await fill(plural, isPost ? 'Updated Editor Entries' : 'Updated Editor Taxonomies');
      await page.click(isPost ? '[name=cpt_hide_frontend]' : '[name=hide_frontend]');
      await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-editor-footer button[type=submit]')]);
      await admin('page=' + slug + '&edit=' + key);
      check(await page.$eval(plural, input => input.value), isPost ? 'Updated Editor Entries' : 'Updated Editor Taxonomies', 'Edited labels persist');
      check(await page.$eval(isPost ? '[name=cpt_hide_frontend]' : '[name=hide_frontend]', input => input.checked), true, 'Visibility persists');
      check(await page.$eval(rest, input => input.checked), true, 'Visibility does not reset REST');
      await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('.cff-editor-footer a')]);
      check(await page.$('#cff-definition-editor'), null, 'Cancel returns to the list');
      check(new URL(page.url()).searchParams.get('page'), slug, 'URL remains a CFF page');
    }
    await admin('page=cff-post-types#cff-definition-editor');
    await page.waitForSelector('.cff-definition-form');
    check(new URL(page.url()).searchParams.get('action'), 'add', 'Legacy add fragment remains usable');
    check(errors, [], 'Browser JavaScript errors');
    console.log('PASS: ' + checks + ' CFF add/edit layout, form, create/update/cancel and compatibility checks; previews: ' + output);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
