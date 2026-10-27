<?php
/** Standalone regressions for frontend visibility and retained admin workflows. */
define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/class-dynamic-content-manager.php';
require_once __DIR__ . '/../includes/class-term-meta-manager.php';
require_once __DIR__ . '/../includes/class-plugin.php';
require_once __DIR__ . '/../includes/helpers/admin-ui.php';

$GLOBALS['options'] = [];
$GLOBALS['post_types'] = [];
$GLOBALS['taxonomies'] = [];
$GLOBALS['rules'] = [];
$GLOBALS['hooks'] = [];
$GLOBALS['flushes'] = 0;
$checks = 0;

function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_attr__($value, $domain = '') { return esc_attr($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return $value; }
function esc_url_raw($value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_title($value) { return str_replace(' ', '-', sanitize_key($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function register_post_type($key, $args) { $GLOBALS['post_types'][$key] = $args; }
function register_taxonomy($key, $types, $args) { $GLOBALS['taxonomies'][$key] = $args + ['object_type' => $types]; }
function add_rewrite_rule($regex, $query, $priority) { $GLOBALS['rules'][$regex] = $query; }
function flush_rewrite_rules($hard = true) { $GLOBALS['flushes']++; }
function add_action($hook, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter($hook, $callback, $priority = 10, $accepted = 1) { add_action($hook, $callback, $priority, $accepted); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function is_admin() { return $GLOBALS['admin'] ?? false; }
function is_singular() { return ($GLOBALS['view'] ?? '') === 'single'; }
function is_post_type_archive() { return ($GLOBALS['view'] ?? '') === 'archive'; }
function is_tax() { return ($GLOBALS['view'] ?? '') === 'taxonomy'; }
function get_post_type() { return $GLOBALS['queried_type']; }
function get_query_var($key) { return $GLOBALS['queried_type']; }
function get_queried_object() { return (object) ['taxonomy' => $GLOBALS['queried_type']]; }
function status_header($status) { $GLOBALS['status'] = $status; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function remove_action($hook, $callback) { $GLOBALS['removed_hooks'][$hook][] = $callback; }
function current_user_can($capability) { return true; }
function check_admin_referer($action, $key) { return true; }
function wp_nonce_field($action, $name, $referer = true, $echo = true) { return ''; }
function settings_errors($key) {}
function add_settings_error($setting, $code, $message, $type) {}
function cff_render_header_branding() {}
function cff_render_page_hero($title, $description, $icon) {}
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function get_post_types($args, $output) { return get_registered_objects('post_types', $args, $output); }
function get_taxonomies($args, $output) { return get_registered_objects('taxonomies', $args, $output); }
function get_registered_objects($kind, $args, $output) {
  $found = [];
  foreach ($GLOBALS[$kind] as $key => $object) {
    foreach ($args as $property => $expected) {
      if (($object[$property] ?? null) !== $expected) continue 2;
    }
    $object['labels'] = (object) $object['labels'];
    $found[$key] = $output === 'names' ? $key : (object) ($object + ['name' => $key]);
  }
  return $found;
}
function check($condition, $message) {
  global $checks;
  if (!$condition) throw new RuntimeException($message);
  $checks++;
}

$base = ['public' => true, 'has_archive' => true, 'show_in_rest' => true, 'slug' => 'records'];
$GLOBALS['options']['cffp_post_types'] = [
  'visible' => $base,
  'hidden' => ['hide_frontend' => true, 'query_var' => true] + $base,
  'hidden_private' => ['hide_frontend' => true, 'public' => false, 'show_ui' => false, 'show_in_rest' => false] + $base,
  'single_blocked' => ['block_single' => true] + $base,
];
$GLOBALS['options']['cffp_taxonomies'] = [
  'visible_terms' => ['post_types' => ['visible']] + $base,
  'hidden_terms' => ['hide_frontend' => true, 'query_var' => true, 'post_types' => ['hidden']] + $base,
];
$manager = new CFF\Dynamic_Content_Manager();
$manager->register_dynamic_cpts();
$manager->register_dynamic_taxonomies();
foreach (['hidden', 'hidden_private'] as $key) {
  $type = $GLOBALS['post_types'][$key];
  foreach (['public', 'publicly_queryable', 'has_archive', 'rewrite', 'query_var', 'show_in_nav_menus'] as $flag) {
    check($type[$flag] === false, "$key must disable $flag");
  }
  check($type['show_ui'] && $type['exclude_from_search'], "$key keeps admin and excludes search");
}
check($GLOBALS['post_types']['hidden']['show_in_rest'], 'REST remains independently enabled');
check(!$GLOBALS['post_types']['hidden_private']['show_in_rest'], 'REST remains independently disabled');
check($GLOBALS['post_types']['visible']['public'] && $GLOBALS['post_types']['visible']['has_archive'], 'Existing public CPTs keep their views');
check(!$GLOBALS['post_types']['single_blocked']['publicly_queryable'] && $GLOBALS['post_types']['single_blocked']['has_archive'], 'No single view retains its archive behavior');
check(count($GLOBALS['rules']) === 2, 'Hidden post types create no additional archive rewrites');
foreach (['public', 'publicly_queryable', 'rewrite', 'query_var', 'show_in_nav_menus'] as $flag) {
  check($GLOBALS['taxonomies']['hidden_terms'][$flag] === false, "Hidden taxonomy must disable $flag");
}
check($GLOBALS['taxonomies']['hidden_terms']['show_ui'], 'Hidden taxonomy keeps admin UI');
check($GLOBALS['taxonomies']['hidden_terms']['object_type'] === ['hidden'], 'Hidden taxonomy retains post type assignments');
check($GLOBALS['taxonomies']['visible_terms']['rewrite']['slug'] === 'records', 'Existing taxonomy routes stay enabled');
check(!isset(get_post_types(['public' => true], 'objects')['hidden']), 'Public type discovery used by sitemaps excludes hidden CPTs');
check(!isset(get_taxonomies(['public' => true], 'objects')['hidden_terms']), 'Public taxonomy discovery used by sitemaps excludes hidden terms');

$terms = new CFF\Term_Meta_Manager();
$terms->register_term_meta_fields();
check(isset($GLOBALS['hooks']['hidden_terms_edit_form_fields'], $GLOBALS['hooks']['edited_hidden_terms']), 'Hidden taxonomy keeps CFF term fields and save hooks');
$manager->flush_pending_rewrite_rules();
check($GLOBALS['flushes'] === 0, 'Normal requests never flush rewrites');
$manager->schedule_rewrite_flush();
$manager->flush_pending_rewrite_rules();
$manager->flush_pending_rewrite_rules();
check($GLOBALS['flushes'] === 1 && !get_option('cffp_flush_rewrite_rules'), 'Definition changes flush once after registration');

$GLOBALS['wp_query'] = new class {
  public function set_404() { $GLOBALS['is_404'] = true; }
};
foreach (['single' => 'hidden', 'archive' => 'hidden', 'taxonomy' => 'hidden_terms'] as $view => $type) {
  $GLOBALS['view'] = $view;
  $GLOBALS['queried_type'] = $type;
  $GLOBALS['status'] = 200;
  $manager->block_hidden_frontend_views();
  check($GLOBALS['status'] === 404 && $GLOBALS['is_404'] && $GLOBALS['no_cache'], "Direct hidden $view requests return 404");
}
$GLOBALS['queried_type'] = 'visible_terms';
$GLOBALS['status'] = 200;
$manager->block_hidden_frontend_views();
check($GLOBALS['status'] === 200, 'Public taxonomy archives remain accessible');
$GLOBALS['admin'] = true;
$GLOBALS['queried_type'] = 'hidden_terms';
$manager->block_hidden_frontend_views();
check($GLOBALS['status'] === 200, 'Admin requests stay accessible');
$GLOBALS['admin'] = false;

$plugin = (new ReflectionClass(CFF\Plugin::class))->newInstanceWithoutConstructor();
foreach (['post_types', 'taxonomies'] as $kind) {
  $method = new ReflectionMethod($plugin, 'sanitize_import_' . $kind);
  $method->setAccessible(true);
  $definitions = $method->invoke($plugin, ['hidden' => ['hide_frontend' => true] + $base, 'visible' => $base]);
  check($definitions['hidden']['hide_frontend'] === true, "JSON import preserves hidden $kind");
  check($definitions['visible']['hide_frontend'] === false, "Older JSON keeps default $kind visibility");
}

$_GET = ['edit' => 'hidden'];
$_POST = [];
ob_start();
$plugin->page_post_types();
$html = ob_get_clean();
check(strpos($html, 'name="cpt_hide_frontend" value="1" checked') !== false, 'CPT editor restores the saved checkbox');
check(strpos($html, 'data-visibility="hidden"') !== false && strpos($html, 'Admin only') !== false, 'CPT list displays frontend visibility');
$_GET = ['edit' => 'hidden_terms'];
ob_start();
$plugin->page_taxonomies();
$html = ob_get_clean();
check(strpos($html, 'name="hide_frontend" value="1" checked') !== false, 'Taxonomy editor restores the saved toggle');
check(strpos($html, 'value="hidden" selected') !== false, 'Taxonomy editor retains hidden post type selections');
$_POST = ['cffp_tax_action' => 'update', 'tax_key' => 'hidden_terms', 'hide_frontend' => '1', 'public' => 'on', 'post_types' => ['hidden']];
ob_start();
$plugin->page_taxonomies();
ob_end_clean();
check(get_option('cffp_taxonomies')['hidden_terms']['hide_frontend'], 'Taxonomy form saves enabled visibility toggle');
unset($_POST['hide_frontend']);
ob_start();
$plugin->page_taxonomies();
$html = ob_get_clean();
check(!get_option('cffp_taxonomies')['hidden_terms']['hide_frontend'], 'Taxonomy form can restore frontend visibility');
check(strpos($html, 'name="hide_frontend" value="1" checked') === false, 'Taxonomy form immediately reflects the saved value');

// Define Polylang APIs after testing the non-Polylang registration path.
if (!function_exists('pll_current_language')) {
  function pll_current_language($field) { return 'en'; }
  function pll_default_language($field) { return 'en'; }
  function pll_home_url($language) { return 'https://example.test/'; }
  function home_url($path) { return 'https://example.test' . $path; }
  function trailingslashit($value) { return rtrim($value, '/') . '/'; }
  function pll_languages_list($args) { return ['en', 'id']; }
  function taxonomy_exists($taxonomy) { return isset($GLOBALS['taxonomies'][$taxonomy]); }
  function get_taxonomy($taxonomy) { return (object) $GLOBALS['taxonomies'][$taxonomy]; }
}
$GLOBALS['options']['cffp_taxonomies']['hidden_terms']['hide_frontend'] = true;
$manager->register_dynamic_cpts();
$manager->register_dynamic_taxonomies();
$GLOBALS['rules'] = [];
$plugin->add_polylang_rewrite_rules();
foreach ($GLOBALS['rules'] as $query) {
  check(strpos($query, 'post_type=hidden') === false && strpos($query, 'hidden_terms=') === false, 'Polylang never recreates hidden frontend routes');
}

echo "Frontend visibility: $checks checks passed.\n";
