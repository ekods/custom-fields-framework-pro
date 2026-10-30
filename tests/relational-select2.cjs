// Real Select2 + jQuery, isolated fixtures: no WordPress database or external HTTP.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const {default: puppeteer} = await import('puppeteer');
  const browser = await puppeteer.launch({headless: true});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent('<form><select id="single" class="cff-select2 cff-relational-ajax" name="service"><option value=""></option></select>' +
      '<select id="multiple" class="cff-select2 cff-relational-ajax" name="services[]" multiple><option value="9" selected>Corporate Branding</option></select></form>');
    const jquery = process.env.CFFP_JQUERY || '/Users/ekodwisaputro/Sites/localhost/ekodwis/wp-includes/js/jquery/jquery.min.js';
    await page.addScriptTag({content: fs.readFileSync(jquery, 'utf8')});
    const root = path.resolve(__dirname, '..');
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/vendor/select2/select2.min.js', 'utf8')});
    await page.evaluate(() => {
      window.CFFP = {ajax: '/fixture-ajax', nonce: 'fixture'};
      // Reproduce selection corruption when another integration changes Select2's global renderer.
      jQuery.fn.select2.defaults.set('templateSelection', () => '814306f0f546ac0f927cc34f736fb49b14b4f0eb');
      jQuery.fn.select2.defaults.set('escapeMarkup', text => text);
      // A generic integration may already have initialized this control with AJAX before CFF runs.
      jQuery('#single').select2({ajax: {processResults: response => response.data, transport: function(){ return {abort: function(){}}; }}});
    });
    await page.addScriptTag({content: fs.readFileSync(root + '/assets/js/select2-init.js', 'utf8')});
    await page.waitForFunction(() => jQuery('#single').data('select2'));
    const result = await page.evaluate(() => {
      const assert = (condition, message) => { if (!condition) throw new Error(message); };
      const $ = jQuery;
      assert($('#single').data('select2').options.get('cffRelationalLabels'), 'Existing AJAX instance was not upgraded to CFF label rendering');
      const select = (id, item) => {
        const instance = $(id).data('select2');
        const process = instance.options.get('ajax').processResults;
        const response = process.call(instance.dataAdapter, {success: true, data: {results: [item], pagination: {more: true}}});
        assert(response.pagination.more, 'Pagination was lost');
        instance.trigger('select', {data: response.results[0]});
        return {instance, item: response.results[0]};
      };
      const chosen = select('#single', {id: 7, text: 'F&amp;B Branding'});
      assert($('#single').val() === '7', 'Relationship ID changed');
      assert($('#single').next().find('.select2-selection__rendered').text() === 'F&B Branding', 'Selected label became a hash or encoded entity');
      // Simulate mutation of Select2 cached text by a third-party handler after selection.
      chosen.item.text = '814306f0f546ac0f927cc34f736fb49b14b4f0eb';
      chosen.instance.selection.update([chosen.item]);
      assert($('#single').next().find('.select2-selection__rendered').text() === 'F&B Branding', 'Cached label mutation changed selection text');
      select('#single', {id: 8, text: 'Packaging'});
      assert($('#single').val() === '8' && $('#single').next().text().includes('Packaging'), 'Switching selection lost label/ID');
      select('#multiple', {id: 7, text: 'Campaign &amp; Launch'});
      assert($('#multiple').val().join(',') === '9,7', 'Multiple relationship values changed');
      assert($('#multiple').next().text().includes('Campaign & Launch'), 'Multiple selection label stayed encoded');
      assert($('#single').next().text().includes('Packaging'), 'Another field overwrote its label cache');
      select('#single', {id: 11, text: '&lt;img src=x onerror="window.cffXss=true"&gt; &amp; Label'});
      assert($('#single').next().find('img,script').length === 0 && !window.cffXss, 'Decoded markup was executed');
      assert($('#single').next().text().includes('<img'), 'Markup label was not shown as text');
      const before = $('#single').data('select2');
      cffInitSelect2(document); assert($('#single').data('select2') === before, 'Repeated init replaced the active instance');
      $('#single').val(null).trigger('change');
      assert(!$('#single').val() && !$('#single').next().text().includes('814306'), 'Clearing selection left a stale hash');
      // CFF copyRepeaterRowValues explicitly copies .val(): jQuery.clone alone omits live multi-selection state.
      const clone = $('#multiple').clone().val($('#multiple').val()).removeAttr('id').appendTo('form');
      cffInitSelect2(clone);
      assert(clone.data('select2') !== $('#multiple').data('select2'), 'Cloned field reused source instance');
      assert(clone.next().text().includes('Campaign & Launch'), 'Cloned selection lost its label: ' + JSON.stringify({source: $('#multiple').val(), cloned: clone.val(), options: clone.html(), rendered: clone.next().text()}));
      const data = new FormData(document.querySelector('form'));
      assert(!Array.from(data.values()).some(value => /Branding|Packaging|814306/.test(value)), 'Display labels/hashes were submitted instead of IDs');
      return {selected: $('#multiple').val(), label: $('#multiple').next().text()};
    });
    assert.deepEqual(errors, []);
    console.log('PASS: real Select2 selected labels, entity decoding, global template isolation, mutated cache, single/multiple, clear, clone, IDs and markup safety', result);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
