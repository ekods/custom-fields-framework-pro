// CFF page headings use the same hero design as Tool Kits, and the buttons inside the hero are plain WordPress buttons.
// Renders the real cff_render_page_hero() under core's admin CSS and the real assets/admin.css and assets/management.css.
// Requires Puppeteer and a local WordPress core: WP_ADMIN_TEST_ROOT=/path/to/wordpress node tests/hero-ui.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {execFileSync} = require('node:child_process');

const root = path.resolve(__dirname, '..');
const core = process.env.WP_ADMIN_TEST_ROOT || process.env.TK_WP_CORE;
assert.ok(core && fs.existsSync(path.join(core, 'wp-admin/css/common.min.css')), 'Set WP_ADMIN_TEST_ROOT to a WordPress installation');
const firstExisting = list => list.find(file => fs.existsSync(path.join(core, file)));
const scheme = ['modern', 'fresh', 'blue'].find(name => fs.existsSync(path.join(core, 'wp-admin/css/colors', name, 'colors.min.css')));
const buttons = firstExisting(['wp-includes/css/buttons.min.css', 'wp-admin/css/buttons.min.css']);
assert.ok(scheme && buttons, 'Core ships button and admin color scheme stylesheets');

const php = `
define('ABSPATH', '/tmp/');
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
require ${JSON.stringify(path.join(root, 'includes/helpers/admin-ui.php'))};
\\CFF\\cff_render_page_hero('Content overview', 'Everything you need to add, organize, and update your website content.', 'dashicons-cff', '<a class="button button-primary" href="#">Add post type</a> <a class="button" href="#">Import</a>');
`;
const hero = execFileSync(process.env.TK_PHP_BINARY || 'php', ['-r', php], {encoding: 'utf8'});

const base = ['/core/wp-includes/css/dashicons.min.css', '/core/wp-admin/css/common.min.css', '/core/' + buttons, '/core/wp-admin/css/forms.min.css', `/core/wp-admin/css/colors/${scheme}/colors.min.css`, '/cff/assets/admin-menu.css', '/cff/assets/admin.css'];
const pages = {
    // Management screens load management.css after admin.css; every other CFF screen loads admin.css alone.
    '/management.html': {sheets: [...base, '/cff/assets/management.css'], wrapper: 'wrap tk-wrap cff-admin cff-management'},
    '/other.html': {sheets: base, wrapper: 'wrap tk-wrap cff-admin'},
};
for (const page of Object.values(pages)) {
    page.html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${page.sheets.map(href => `<link rel="stylesheet" href="${href}">`).join('')}`
        // WordPress defines its accent at :root; CFF may override it for its own screens.
        + '<style>:root{--wp-admin-theme-color:#3858e9}body{margin:0;padding:20px}</style></head><body class="wp-admin wp-core-ui">'
        + `<div class="${page.wrapper}">${hero}</div><div id="control"><a class="button button-primary" href="#">Primary</a> <a class="button" href="#">Secondary</a></div></body></html>`;
}

(async () => {
    const {default: puppeteer} = await import('puppeteer');
    const server = http.createServer((request, response) => {
        const url = decodeURIComponent(request.url.split('?')[0]);
        if (pages[url]) { response.writeHead(200, {'Content-Type': 'text/html'}); response.end(pages[url].html); return; }
        const file = url.startsWith('/core/') ? path.join(core, url.slice(6)) : path.join(root, url.replace(/^\/cff\//, ''));
        fs.readFile(file, (error, data) => {
            if (error) { response.writeHead(404); response.end(); return; }
            const type = {'.css': 'text/css', '.svg': 'image/svg+xml', '.woff2': 'font/woff2'}[path.extname(file)] || 'application/octet-stream';
            response.writeHead(200, {'Content-Type': type}); response.end(data);
        });
    }).listen(0, '127.0.0.1');
    await new Promise(resolve => server.on('listening', resolve));
    const url = name => 'http://127.0.0.1:' + server.address().port + name;
    const browser = await puppeteer.launch({headless: true});
    let checks = 0;
    const check = (condition, message) => { assert.ok(condition, message); checks++; };
    // Shape, size and typography of a button; colour is checked separately against the accent variable.
    const shape = ['backgroundImage', 'borderTopWidth', 'borderRadius', 'fontSize', 'fontWeight', 'height', 'paddingLeft', 'boxShadow', 'lineHeight'];
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewport({width: 1280, height: 900});
        for (const name of Object.keys(pages)) {
            await page.goto(url(name), {waitUntil: 'networkidle0'});
            const result = await page.evaluate(shape => {
                const style = element => getComputedStyle(element);
                const pick = element => Object.fromEntries(shape.map(property => [property, style(element)[property]]));
                const heroButtons = [...document.querySelectorAll('.tk-hero-action .button')];
                const icon = style(document.querySelector('.tk-hero-icon'));
                return {
                    heroStyle: style(document.querySelector('.tk-hero')),
                    gradient: style(document.querySelector('.tk-hero')).backgroundImage.includes('gradient'),
                    radius: style(document.querySelector('.tk-hero')).borderRadius,
                    iconSize: icon.width, iconGradient: icon.backgroundImage.includes('gradient'), iconColor: style(document.querySelector('.tk-hero-icon .dashicons')).color,
                    title: style(document.querySelector('.tk-hero-title')).fontSize,
                    accent: style(heroButtons[0]).getPropertyValue('--wp-admin-theme-color').trim(),
                    buttons: heroButtons.map(button => ({primary: button.classList.contains('button-primary'), shape: pick(button), color: style(button).color, background: style(button).backgroundColor, border: style(button).borderTopColor})),
                    control: {primary: pick(document.querySelector('#control .button-primary')), secondary: pick(document.querySelector('#control .button:not(.button-primary)'))},
                };
            }, shape);
            check(result.gradient && result.radius === '14px', name + ': hero is the gradient card with 14px corners');
            check(result.iconSize === '54px' && result.iconGradient && result.iconColor === 'rgb(255, 255, 255)', name + ': hero icon is the filled 54px tile with a white icon');
            check(result.title === '27px', name + ': hero title is 27px');
            const hex = result.accent.replace('#', '');
            const accent = `rgb(${parseInt(hex.slice(0, 2), 16)}, ${parseInt(hex.slice(2, 4), 16)}, ${parseInt(hex.slice(4, 6), 16)})`;
            check(result.buttons.length === 2, name + ': hero shows both action buttons');
            for (const button of result.buttons) {
                const label = name + ': hero ' + (button.primary ? 'primary' : 'secondary') + ' button';
                const control = button.primary ? result.control.primary : result.control.secondary;
                const different = shape.filter(property => button.shape[property] !== control[property]);
                check(different.length === 0, label + ' matches the WordPress default (differs in: ' + different.map(property => property + ' ' + button.shape[property] + ' vs ' + control[property]).join(', ') + ')');
                check(button.primary ? button.background === accent : button.color === accent && button.border === accent, label + ' takes its colour from --wp-admin-theme-color');
            }
            await page.setViewport({width: 390, height: 800});
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), name + ': no horizontal overflow at 390px');
            await page.setViewport({width: 1280, height: 900});
        }
        check(errors.length === 0, 'No JavaScript errors: ' + errors.join('; '));
        console.log('PASS: ' + checks + ' hero checks (gradient card and icon tile, WordPress-default action buttons on management and other screens, mobile layout)');
    } finally { await browser.close(); server.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
