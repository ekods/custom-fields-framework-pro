// Exercise the actual Egghead integration after it destroys and recreates CFF's Select2.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const {default: puppeteer} = await import('puppeteer');
  const browser = await puppeteer.launch({headless: true});
  try {
    const root = path.resolve(__dirname, '..');
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent(`<form id="post"><select name="post_lang_choice"><option value="en">English</option><option value="id">Indonesian</option></select>
      <div class="cff-field" data-field-name="capability_service"><select id="service" name="cff_values[capability_service]"
      class="cff-select2 cff-relational-ajax" data-relational-type="taxonomy" data-relational-subtype="service"><option value=""></option></select></div>
      <select id="work" name="cff_values[hero][slider][0][work]" class="cff-select2 cff-relational-ajax"
      data-relational-type="post_type" data-relational-subtype="work"><option value=""></option></select></form>`);
    const jquery = process.env.CFFP_JQUERY || '/Users/ekodwisaputro/Sites/localhost/ekodwis/wp-includes/js/jquery/jquery.min.js';
    const theme = process.env.CFFP_THEME_SCRIPT || '/Users/ekodwisaputro/Sites/localhost/thinkegghead/wp-content/themes/egghead/assets/js/admin-capabilities-child.js';
    await page.addScriptTag({content: fs.readFileSync(jquery, 'utf8')});
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/vendor/select2/select2.min.js', 'utf8')});
    await page.evaluate(() => {
      window.CFFP = {ajax: '/cff-endpoint', nonce: 'cff-nonce'};
      window.cstCapabilityService = {ajax: '/theme-endpoint', nonce: 'theme-nonce', postId: 42, language: 'en', selectWork: 'Select Work', selectService: 'Select service first'};
      window.fixtureRequests = [];
      jQuery.ajax = function(options) {
        fixtureRequests.push({url: options.url, data: options.data});
        const pending = jQuery.Deferred();
        const request = pending.promise(); request.abort = function() {};
        const items = options.data.kind === 'work' ? [{id: 21, text: 'Work &amp; Brand'}]
          : [{id: 7, text: 'F&amp;B Branding'}, {id: 8, text: 'Packaging'}];
        setTimeout(() => pending.resolve({success: true, data: {results: items, pagination: {more: false}}}), 0);
        return request;
      };
    });
    const initializer = process.env.CFFP_SELECT2_INIT || root + '/assets/js/select2-init.js';
    await page.addScriptTag({content: fs.readFileSync(initializer, 'utf8')});
    await page.waitForFunction(() => jQuery('#service').data('select2'));
    await page.evaluate(() => {
      window.beforeTheme = jQuery('#service').data('select2');
      // A hash-producing global template reproduces the user's visible symptom after theme reinitialization.
      jQuery.fn.select2.defaults.set('templateSelection', () => '814306f0f546ac0f927cc34f736fb49b14b4f0eb');
      jQuery.fn.select2.defaults.set('escapeMarkup', value => value);
    });
    await page.addScriptTag({content: fs.readFileSync(theme, 'utf8')});
    await page.waitForFunction(() => {
      const instance = jQuery('#service').data('select2');
      return instance && instance !== window.beforeTheme && instance.options.get('ajax').url === '/theme-endpoint';
    });
    await page.evaluate(() => jQuery('#service').select2('open'));
    await page.waitForFunction(() => document.querySelector('.select2-results')?.textContent.includes('Branding'), {timeout: 5000});
    const options = await page.$eval('.select2-results', el => el.textContent);
    assert(options.includes('F&B Branding') && !options.includes('&amp;'), 'Theme results bypass CFF label normalization: ' + options);
    await page.evaluate(() => {
      const option = Array.from(document.querySelectorAll('.select2-results__option')).find(el => el.textContent === 'F&B Branding');
      jQuery(option).trigger('mouseup');
    });
    await page.waitForFunction(() => jQuery('#service').val() === '7');
    assert.equal(await page.$eval('#service + .select2 .select2-selection__rendered', el => el.textContent.replace('×', '')), 'F&B Branding');
    assert.equal(await page.$eval('#work', el => el.disabled), false);
    await page.evaluate(() => jQuery('#work').select2('open'));
    await page.waitForFunction(() => document.querySelector('.select2-results')?.textContent.includes('Work & Brand'), {timeout: 5000});
    await page.evaluate(() => jQuery(Array.from(document.querySelectorAll('.select2-results__option')).find(el => el.textContent === 'Work & Brand')).trigger('mouseup'));
    const evidence = await page.evaluate(() => {
      const instance = jQuery('#service').data('select2');
      cffInitSelect2(document);
      if (instance !== jQuery('#service').data('select2') || instance.options.get('ajax').url !== '/theme-endpoint') throw new Error('CFF refresh replaced the theme provider');
      return {requests: fixtureRequests, service: jQuery('#service').val(), work: jQuery('#work').val(), values: Array.from(new FormData(document.querySelector('form')).entries())};
    });
    assert.equal(evidence.service, '7'); assert.equal(evidence.work, '21');
    assert(evidence.requests.every(r => r.url === '/theme-endpoint' && r.data.action === 'cst_capability_service_options' && r.data.nonce === 'theme-nonce' && r.data.post_id === 42 && r.data.language === 'en'));
    assert(evidence.requests.some(r => r.data.kind === 'work' && r.data.service === '7'));
    assert(evidence.values.some(([name, value]) => name === 'cff_values[capability_service]' && value === '7'));
    await page.evaluate(() => jQuery('[name="post_lang_choice"]').val('id').trigger('change'));
    assert.equal(await page.$eval('#work', el => el.disabled), true);
    await page.evaluate(() => jQuery('#service').select2('open'));
    await page.waitForFunction(() => fixtureRequests.some(r => r.data.kind === 'service' && r.data.language === 'id'), {timeout: 5000});
    assert.deepEqual(errors, []);
    console.log('PASS: actual Egghead theme reinitialization, AJAX result labels, selected labels, provider preservation, service/language filters, dependent Work clearing and submitted IDs');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
