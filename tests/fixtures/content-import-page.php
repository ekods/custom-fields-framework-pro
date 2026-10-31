<?php
/** Render the actual admin view without loading WordPress or a database. */
define('ABSPATH', __DIR__);
require __DIR__ . '/../../includes/class-plugin.php';
require __DIR__ . '/../../includes/class-content-import.php';
$fixture_mode = $argv[1] ?? 'bulk';
$_GET = $fixture_mode === 'bulk' ? ['bulk' => 1] : ($fixture_mode === 'page' ? ['post_id' => 50] : []);
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function esc_html_e($text, $domain = '') { echo esc_html($text); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function selected($value, $candidate) { if ($value === $candidate) echo 'selected'; }
function get_post($id) { return in_array((int) $id, [50, 51], true) ? (object) ['ID' => (int) $id, 'post_type' => 'page', 'post_title' => (int) $id === 50 ? 'Why EGGHEAD' : 'Kenapa EGGHEAD', 'post_status' => 'publish'] : null; }
function get_posts($args) { return [get_post(50), get_post(51)]; }
function get_page_template_slug($id) { return 'templates/page-why-egghead.php'; }
function pll_get_post_language($id, $field = 'slug') { return (int) $id === 50 ? 'en' : 'id'; }
function current_user_can($cap) { return true; }
function apply_filters($name, $value) { return $value; }
function get_post_types($args, $output) { return ['page' => (object) ['labels' => (object) ['name' => 'Pages'], 'cap' => (object) ['create_posts' => 'edit_pages', 'edit_posts' => 'edit_pages']]]; }
function admin_url($path) { return 'https://cff.test/wp-admin/' . $path; }
function wp_nonce_url($url, $nonce) { return $url . '&_wpnonce=fixture'; }
eval('namespace CFF; function cff_render_header_branding() { echo "<p>Custom Fields Framework Pro</p>"; } function cff_render_page_hero($title, $description, $icon) { echo "<h1>" . esc_html($title) . "</h1><p>" . esc_html($description) . "</p>"; }');
$plugin = (new ReflectionClass(\CFF\Plugin::class))->newInstanceWithoutConstructor();
echo '<!doctype html><html><head><meta charset="utf-8"><title>Import Content fixture</title></head><body>';
$import = new \CFF\Content_Import($plugin);
if ($fixture_mode === 'editor') $import->editor_box_content(get_post(50)); else $import->page();
echo '</body></html>';
