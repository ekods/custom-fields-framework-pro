// Repeater editing aids: row titles, the row-count toolbar with Collapse all / Expand all, explained limits, and the
// reveal of required fields hidden in collapsed rows. Renders real markup with tests/fixtures/repeater-markup.php and
// runs the real assets/post.js and assets/post.css over core's admin assets.
// Requires Puppeteer and a local WordPress core: WP_ADMIN_TEST_ROOT=/path/to/wordpress node tests/repeater-ui.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {execFileSync} = require('node:child_process');

const root = path.resolve(__dirname, '..');
const core = process.env.WP_ADMIN_TEST_ROOT || process.env.TK_WP_CORE;
assert.ok(core && fs.existsSync(path.join(core, 'wp-includes/js/jquery/jquery.min.js')), 'Set WP_ADMIN_TEST_ROOT to a WordPress installation');
const exists = file => fs.existsSync(path.join(core, file));
const scheme = ['modern', 'fresh', 'blue'].find(name => exists(`wp-admin/css/colors/${name}/colors.min.css`));
const buttons = ['wp-includes/css/buttons.min.css', 'wp-admin/css/buttons.min.css'].find(exists);
assert.ok(scheme && buttons, 'Core ships button and admin color scheme stylesheets');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const markup = (layout, label = '', max = 0, min = 0) => execFileSync(process.env.TK_PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/repeater-markup.php'), layout, label, String(max), String(min)], {encoding: 'utf8'});

const styles = ['wp-includes/css/dashicons.min.css', 'wp-admin/css/common.min.css', buttons, 'wp-admin/css/forms.min.css', `wp-admin/css/colors/${scheme}/colors.min.css`].map(file => '/core/' + file).concat(['/cff/assets/admin.css', '/cff/assets/post.css']);
const scripts = ['jquery/jquery.min.js', 'jquery/ui/core.min.js', 'jquery/ui/widget.min.js', 'jquery/ui/mouse.min.js', 'jquery/ui/sortable.min.js'].filter(file => exists('wp-includes/js/' + file)).map(file => '/core/wp-includes/js/' + file);
const html = body => `<!doctype html><html><head><meta charset="utf-8">${styles.map(href => `<link rel="stylesheet" href="${href}">`).join('')}</head><body class="wp-admin wp-core-ui">`
    + `<form id="post" method="post" action="javascript:void(0)"><div class="wrap cff-admin"><div class="cff-metabox">${body}</div></div><button type="submit" id="save">Update</button></form>`
    + scripts.map(src => `<script src="${src}"></script>`).join('')
    // post.js expects wp.media/wp.editor to exist; none of them is used by these scenarios.
    + '<script>window.wp = window.wp || {media: function(){ return {on(){}, open(){}, state(){ return {get(){ return {toJSON(){ return []; }}; }}; }}; }, editor: {initialize(){}, remove(){}}}; window.ajaxurl = "/ajax";</script>'
    + '<script src="/cff/assets/post.js"></script></body></html>';

(async () => {
    const {default: puppeteer} = await import('puppeteer');
    const pages = {};
    const server = http.createServer((request, response) => {
        const url = decodeURIComponent(request.url.split('?')[0]);
        if (pages[url]) { response.writeHead(200, {'Content-Type': 'text/html'}); response.end(pages[url]); return; }
        const file = url.startsWith('/core/') ? path.join(core, url.slice(6)) : path.join(root, url.replace(/^\/cff\//, ''));
        fs.readFile(file, (error, data) => {
            if (error) { response.writeHead(404); response.end(); return; }
            const type = {'.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.woff2': 'font/woff2'}[path.extname(file)] || 'application/octet-stream';
            response.writeHead(200, {'Content-Type': type}); response.end(data);
        });
    }).listen(0, '127.0.0.1');
    await new Promise(resolve => server.on('listening', resolve));
    const browser = await puppeteer.launch({headless: true});
    let checks = 0;
    const check = (condition, message) => { assert.ok(condition, message); checks++; };
    const open = async (name, body) => {
        pages['/' + name] = html(body);
        const page = await browser.newPage();
        page.errors = [];
        page.on('pageerror', error => page.errors.push(error.message));
        await page.setViewport({width: 1000, height: 900});
        await page.goto('http://127.0.0.1:' + server.address().port + '/' + name, {waitUntil: 'networkidle0'});
        return page;
    };
    const titles = page => page.$$eval('.cff-rep-row-title', nodes => nodes.map(node => node.textContent));
    const collapsed = page => page.$$eval('.cff-rep-row', nodes => nodes.map(node => node.classList.contains('is-collapsed')));
    const marked = page => page.$$eval('.cff-rep-row', nodes => nodes.map(node => node.classList.contains('has-invalid')));
    const count = page => page.$eval('.cff-rep-count', node => node.textContent);
    try {
        // Without a Row Label Field the first filled text or textarea field names the row; PHP and JS agree.
        let page = await open('a.html', markup('default'));
        check(JSON.stringify(await titles(page)) === JSON.stringify(['Row 1: Brand Strategy', 'Row 2: Only a summary', 'Row 3']), 'rows are titled from the first filled text field');
        check(await count(page) === '3 rows' && await page.$eval('.cff-rep-bulk', node => !node.hidden), 'toolbar shows the row count and the bulk buttons');
        check(await page.$eval('.cff-rep-expand-all', node => node.disabled) && await page.$eval('.cff-rep-collapse-all', node => !node.disabled), 'Expand all is disabled while nothing is collapsed');
        await page.click('.cff-rep-collapse-all');
        check((await collapsed(page)).every(Boolean) && await page.$$eval('.cff-rep-toggle', nodes => nodes.every(node => node.getAttribute('aria-expanded') === 'false')), 'Collapse all collapses every row and updates aria-expanded');
        check(JSON.stringify(await marked(page)) === JSON.stringify([false, true, true]), 'collapsed rows with an empty required Title are marked');
        check(await page.$eval('.cff-rep-collapse-all', node => node.disabled) && await page.$eval('.cff-rep-expand-all', node => !node.disabled), 'bulk button states follow');
        await page.click('.cff-rep-expand-all');
        check((await collapsed(page)).every(value => !value), 'Expand all opens every row');
        await page.click('.cff-rep-row:nth-child(2) .cff-rep-toggle');
        check(JSON.stringify(await collapsed(page)) === JSON.stringify([false, true, false]) && await page.$eval('.cff-rep-expand-all', node => !node.disabled), 'toggling one row keeps the toolbar current');
        await page.click('.cff-rep-row:nth-child(2) .cff-rep-toggle');

        // Titles follow typing.
        await page.type('.cff-rep-row:nth-child(3) input[name$="[title]"]', 'Packaging'); await sleep(250);
        check((await titles(page))[2] === 'Row 3: Packaging', 'the title follows what is typed');
        await page.$eval('.cff-rep-row:nth-child(1) input[name$="[title]"]', node => { node.value = ''; node.dispatchEvent(new Event('input', {bubbles: true})); }); await sleep(250);
        check((await titles(page))[0] === 'Row 1: x', 'clearing the field falls back to the next filled text field');
        await page.$eval('.cff-rep-row:nth-child(1) textarea', node => { node.value = 'A'.repeat(90); node.dispatchEvent(new Event('input', {bubbles: true})); }); await sleep(250);
        check((await titles(page))[0].length === 'Row 1: '.length + 60 && (await titles(page))[0].endsWith('…'), 'long values are shortened to 60 characters');
        await page.close();

        // A configured label field wins and also follows typing.
        page = await open('c.html', markup('default', 'summary'));
        check(JSON.stringify(await titles(page)) === JSON.stringify(['Row 1: x', 'Row 2: Only a summary', 'Row 3']), 'a configured Row Label Field decides the title');
        await page.type('.cff-rep-row:nth-child(3) textarea', 'Typed'); await sleep(250);
        check((await titles(page))[2] === 'Row 3: Typed', 'a configured label updates while typing');
        await page.close();

        // A required field in a collapsed row: Update must open the row and focus the field, not stay silent.
        page = await open('d.html', markup('default'));
        await page.type('.cff-rep-row:nth-child(2) input[name$="[title]"]', 'Second'); // only the collapsed row stays invalid
        await page.evaluate(() => { window.submitted = false; document.getElementById('post').addEventListener('submit', () => { window.submitted = true; }); });
        await page.click('.cff-rep-row:nth-child(3) .cff-rep-toggle');
        check((await collapsed(page))[2], 'the row with the empty required field is collapsed');
        await page.click('#save'); await sleep(300);
        const result = await page.evaluate(() => ({submitted: window.submitted, open: !document.querySelector('.cff-rep-row:nth-child(3)').classList.contains('is-collapsed'), focus: document.activeElement && document.activeElement.name, marked: document.querySelector('.cff-rep-row:nth-child(3)').classList.contains('has-invalid')}));
        check(result.submitted === false && result.open && result.focus === 'cff_values[items][2][title]', 'Update opens the collapsed row and focuses the empty required field');
        check(result.marked, 'the row stays marked as needing attention');
        await page.close();

        // Limits are stated, and disabled buttons say why.
        page = await open('e1.html', markup('default', '', 3, 0));
        check(await count(page) === '3 / 3 rows — maximum reached', 'the maximum is stated');
        check(await page.$eval('.cff-rep-add', node => node.disabled && node.title === 'Maximum number of rows reached'), 'the disabled Add row button says why');
        check(await page.$$eval('.cff-rep-clone', nodes => nodes.every(node => node.disabled && node.title === 'Maximum number of rows reached')), 'duplicate buttons say why');
        check(await page.$$eval('.cff-rep-remove', nodes => nodes.every(node => !node.disabled && node.title === 'Remove row')), 'remove buttons keep their own tooltip');
        await page.close();
        page = await open('e2.html', markup('default', '', 0, 3));
        check(await count(page) === '3 rows — minimum 3', 'the minimum is stated');
        check(await page.$$eval('.cff-rep-remove', nodes => nodes.every(node => node.disabled && node.title === 'Minimum number of rows reached')), 'remove buttons say why at the minimum');
        await page.close();
        page = await open('e3.html', markup('default', '', 5, 0));
        check(await count(page) === '3 / 5 rows' && await page.$eval('.cff-rep-add', node => !node.disabled && !node.title), 'below the limits nothing is disabled or annotated');
        await page.click('.cff-rep-add'); await page.click('.cff-rep-add'); await sleep(200);
        check(await count(page) === '5 / 5 rows — maximum reached' && await page.$eval('.cff-rep-add', node => node.disabled && node.title === 'Maximum number of rows reached'), 'adding rows updates the count and reaches the limit');
        check(await page.$$eval('.cff-rep-clone', nodes => nodes.every(node => node.disabled)), 'duplicate buttons disable at the limit');
        await page.close();

        // The Simple layout keeps its minimal header: count yes, accordion no.
        page = await open('f.html', markup('simple'));
        check(await count(page) === '3 rows' && await page.$eval('.cff-rep-bulk', node => node.hidden), 'Simple shows the count but no collapse buttons');
        check(await page.$$eval('.cff-rep-toggle', nodes => nodes.length) === 0, 'Simple still has no toggle');
        check(page.errors.length === 0, 'no JavaScript errors: ' + page.errors.join('; '));
        await page.close();
        console.log('PASS: ' + checks + ' repeater checks (row titles, toolbar and Collapse/Expand all, explained limits, required fields revealed in collapsed rows)');
    } finally { await browser.close(); server.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
