// Actual PHP admin view + production script, browser fixtures only, no WordPress database or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
(async () => {
  const {default: puppeteer} = await import('puppeteer');
  const browser = await puppeteer.launch({headless: true});
  try {
    const page = await browser.newPage();
    const root = path.resolve(__dirname, '..');
    const html = execFileSync(process.env.CFFP_PHP || 'php', [path.join(__dirname, 'fixtures/content-import-page.php')], {encoding: 'utf8'});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.setRequestInterception(true);
    page.on('request', request => request.respond({status: 200, contentType: 'text/html', body: html}));
    await page.goto('https://cff.test/');
    await page.addStyleTag({content: fs.readFileSync(root + '/assets/content-import.css', 'utf8')});
    await page.evaluate(() => {
      window.CFFContentImport = {ajax: '/wp-admin/admin-ajax.php', nonce: 'test-nonce'};
      window.fixture = {calls: [], state: {}, concurrent: 0, max: 0, hold: false, loseResponse: false, writes: 0};
      window.fetch = async (url, options) => {
        const f = window.fixture, command = options.body.get('command');
        f.calls.push(command); f.concurrent++; f.max = Math.max(f.max, f.concurrent);
        try {
          if (options.body.get('nonce') !== 'test-nonce' || options.body.get('action') !== 'cff_content_import') throw new Error('Missing security parameters');
          if (f.hold && command === 'batch') await new Promise(resolve => { f.release = resolve; });
          if (command === 'upload') f.state = {token: 'fixture-token', phase: 'preview', total: 2, preview_cursor: 0, cursor: 0, valid: 0, rows: []};
          if (command === 'preview') Object.assign(f.state, {phase: 'ready', preview_cursor: 2, valid: 2, rows: [
            {index: 1, title: '<img src=x onerror="window.injected=true"> & Brand', state: 'valid', action: 'create', url: 'javascript:alert(1)', fields: ['_cff_heading']},
            {index: 2, title: 'Second', state: 'valid', action: 'create', url: 'http://['}
          ]});
          if (command === 'start' || command === 'resume') f.state.phase = 'running';
          if (command === 'pause') f.state.phase = 'paused';
          if (command === 'cancel') f.state.phase = 'cancelled';
          if (command === 'batch') {
            f.writes++; f.state.cursor++; f.state.created = f.state.cursor; f.state.valid--;
            f.state.rows[f.state.cursor - 1].state = 'created';
            if (f.state.cursor === f.state.total) f.state.phase = 'completed';
            if (f.loseResponse) { f.loseResponse = false; throw new Error('Connection lost after server saved item'); }
          }
          return {ok: true, json: async () => ({success: true, data: JSON.parse(JSON.stringify(f.state))})};
        } finally { f.concurrent--; }
      };
    });
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/js/content-import.js', 'utf8')});
    await page.waitForFunction(() => fixture.calls.includes('status') && !document.querySelector('#cff-import-upload').disabled);
    await page.$eval('#cff-import-file', input => {
      const transfer = new DataTransfer(); transfer.items.add(new File(['{"items":[{"post_title":"Hello"}]}'], 'content.json', {type: 'application/json'})); input.files = transfer.files;
    });
    await page.click('#cff-import-upload');
    await page.waitForFunction(() => !document.querySelector('#cff-import-start').hidden && !document.querySelector('#cff-import-start').disabled);
    assert.equal(await page.evaluate(() => fixture.writes), 0, 'Upload/preview must not write content');
    assert.equal(await page.$eval('#cff-import-rows', node => node.querySelectorAll('img,script,a').length), 0, 'Unsafe labels/URLs must not become markup or links');
    assert.match(await page.$eval('#cff-import-rows', node => node.textContent), /<img.*& Brand/);
    await page.evaluate(() => { fixture.hold = true; });
    await page.click('#cff-import-start');
    await page.waitForFunction(() => typeof fixture.release === 'function');
    await page.click('#cff-import-pause');
    await page.evaluate(() => { fixture.hold = false; fixture.release(); });
    await page.waitForFunction(() => fixture.state.phase === 'paused' && !document.querySelector('#cff-import-continue').disabled);
    assert.equal(await page.evaluate(() => fixture.writes), 1, 'Pause takes effect after current batch');
    assert.equal(await page.evaluate(() => fixture.max), 1, 'Requests never overlap');
    await page.evaluate(() => { fixture.loseResponse = true; });
    await page.click('#cff-import-continue');
    await page.waitForFunction(() => document.querySelector('#cff-import-message').textContent.includes('Connection lost'));
    assert.equal(await page.evaluate(() => fixture.state.phase), 'completed', 'After lost write response, UI reads saved completion');
    assert.equal(await page.evaluate(() => fixture.writes), 2, 'Lost response never replays batch');
    assert.equal(await page.evaluate(() => fixture.calls.at(-1)), 'status', 'Only state query follows response loss');
    assert.equal(await page.$eval('#cff-import-file', node => node.disabled), false, 'Completed import enables new upload');
    const report = await page.evaluate(async () => {
      let blob;
      URL.createObjectURL = value => { blob = value; return 'blob:fixture'; };
      HTMLAnchorElement.prototype.click = function() {};
      document.querySelector('#cff-import-report').click();
      return JSON.parse(await blob.text());
    });
    assert.equal(report.created, 2); assert.equal(report.token, undefined, 'Download report excludes job token');
    // Restore an active job as if the page reopened: status must not start/resume writes automatically.
    await page.evaluate(() => { fixture.state.phase = 'running'; fixture.calls = []; });
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/js/content-import.js', 'utf8')});
    await page.waitForFunction(() => fixture.calls.length > 0 && !document.querySelector('#cff-import-continue').hidden);
    assert.deepEqual(await page.evaluate(() => fixture.calls), ['status'], 'Page restore is read-only');

    // The primary workflow imports one raw page object into the page selected in its editor.
    const singleHtml = execFileSync(process.env.CFFP_PHP || 'php', [path.join(__dirname, 'fixtures/content-import-page.php'), 'page'], {encoding: 'utf8'});
    await page.setContent(singleHtml);
    await page.evaluate(() => {
      window.CFFContentImport = {ajax: '/wp-admin/admin-ajax.php', nonce: 'page-nonce', postId: 50};
      window.pageFixture = {calls: [], state: {}, file: null, prompts: [], writes: 0};
      window.confirm = text => { pageFixture.prompts.push(text); return true; };
      window.fetch = async (url, options) => {
        const body = options.body, command = body.get('command'), f = pageFixture;
        if (body.get('scope') !== 'page' || body.get('post_id') !== '50' || body.get('nonce') !== 'page-nonce') throw new Error('Page context missing');
        f.calls.push(command);
        if (command === 'upload') {
          if (body.get('policy') !== 'replace') throw new Error('Replacement choice was not sent');
          f.file = JSON.parse(await body.get('content_json').text());
          f.state = {token: 'page-token', phase: 'preview', total: 1, preview_cursor: 0, cursor: 0, rows: []};
        }
        if (command === 'preview') Object.assign(f.state, {phase: 'ready', valid: 1, preview_cursor: 1, rows: [
          {index: 1, title: 'Why EGGHEAD', action: 'update', state: 'valid', policy: 'replace', mapping: ['hero → why_hero', 'questions → why_questions'], warnings: ['why_hero.image: existing media kept <img src=x onerror="window.injected=true">']}
        ]});
        if (command === 'start') f.state.phase = 'running';
        if (command === 'batch') { f.writes++; f.state.phase = 'completed'; f.state.cursor = 1; f.state.updated = 1; }
        return {ok: true, json: async () => ({success: true, data: JSON.parse(JSON.stringify(f.state))})};
      };
    });
    await page.addStyleTag({content: fs.readFileSync(root + '/assets/content-import.css', 'utf8')});
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/js/content-import.js', 'utf8')});
    await page.waitForFunction(() => pageFixture.calls.includes('status') && !document.querySelector('#cff-import-upload').disabled);
    assert.equal(await page.$eval('#cff-import-policy', node => node.value), 'fill_empty', 'Single page defaults to filling empty fields');
    assert.equal(await page.$('#cff-import-type'), null, 'Single page destination type cannot be changed in the import form');
    assert.match(await page.$eval('.cff-import-destination', node => node.textContent), /Why EGGHEAD.*#50/s);
    assert.match(await page.$eval('.cff-import-destination a', node => node.href), /post=50&action=edit/);
    await page.select('#cff-import-policy', 'replace');
    const sourceContent = fs.readFileSync(path.join(__dirname, 'fixtures/why-egghead-en.json'), 'utf8');
    await page.$eval('#cff-import-file', (input, json) => {
      const transfer = new DataTransfer(); transfer.items.add(new File([json], 'why-egghead-en.json', {type: 'application/json'})); input.files = transfer.files;
    }, sourceContent);
    await page.click('#cff-import-upload');
    await page.waitForFunction(() => !document.querySelector('#cff-import-start').hidden && !document.querySelector('#cff-import-start').disabled);
    assert.equal(await page.evaluate(() => pageFixture.writes), 0, 'Current-page preview is read-only');
    assert.equal(await page.evaluate(() => pageFixture.file.questions.items.length), 9, 'Raw source object is uploaded without a bulk envelope');
    assert.match(await page.$eval('#cff-import-rows', node => node.textContent), /hero → why_hero/);
    assert.match(await page.$eval('#cff-import-rows', node => node.textContent), /existing media kept/);
    assert.equal(await page.$eval('#cff-import-rows', node => node.querySelectorAll('img,script').length), 0, 'Media warnings render as text');
    await page.click('#cff-import-start');
    await page.waitForFunction(() => pageFixture.state.phase === 'completed' && !document.querySelector('#cff-import-upload').disabled);
    assert.equal(await page.evaluate(() => pageFixture.writes), 1, 'Single-page workflow performs exactly one update');
    assert.match(await page.evaluate(() => pageFixture.prompts[0]), /Replace supplied fields/);
    const selectorHtml = execFileSync(process.env.CFFP_PHP || 'php', [path.join(__dirname, 'fixtures/content-import-page.php'), 'select'], {encoding: 'utf8'});
    await page.setContent(selectorHtml);
    assert.equal(await page.$('#cff-content-import-form'), null, 'Menu first requires a destination, not a bulk upload');
    assert.equal(await page.$$eval('tbody a', nodes => nodes.filter(node => node.href.includes('post_id=')).length), 2, 'Menu offers one-page destinations');
    const editorHtml = execFileSync(process.env.CFFP_PHP || 'php', [path.join(__dirname, 'fixtures/content-import-page.php'), 'editor'], {encoding: 'utf8'});
    assert.match(editorHtml, /page=cff-import-content&amp;post_id=50/, 'Editor import button fixes current page ID');
    assert.deepEqual(errors, [], 'No uncaught browser errors');
    console.log('Content import UI: page selection, editor context, policies, raw JSON, mappings, media warnings, single-page import, bulk compatibility, pause/resume and response loss passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
