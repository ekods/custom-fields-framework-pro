<?php
/** Standalone regressions for data integrity, typed formatting, query ordering and updater retries. */
define('ABSPATH', __DIR__ . '/');
error_reporting(E_ALL);
set_error_handler(function($level, $message, $file, $line) {
  if (!(error_reporting() & $level)) return false;
  throw new ErrorException($message, 0, $level, $file, $line);
});
$GLOBALS['hooks'] = [];
$GLOBALS['posts'] = [];
$GLOBALS['meta'] = [];
$GLOBALS['term_meta'] = [];
$GLOBALS['options'] = [];
$GLOBALS['denied'] = [];
$GLOBALS['site_transients'] = [];
$GLOBALS['now'] = 1000;
$GLOBALS['remote_calls'] = 0;
$GLOBALS['media_lookups'] = 0;
$GLOBALS['meta_reads'] = 0;
$checks = 0;

class WP_Error {}
function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_attr__($value, $domain = '') { return esc_html($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url_raw($value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function wp_kses_post($value) { return $value; }
function absint($value) { return abs((int) $value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function wp_generate_password($length, $special = false, $extra = false) { return str_repeat('a', $length); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid">'; }
function current_user_can($cap, $id = 0) { return !in_array($cap . ':' . $id, $GLOBALS['denied'], true); }
function add_action($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$name][$priority][] = [$callback, $accepted]; }
function add_filter($name, $callback, $priority = 10, $accepted = 1) { add_action($name, $callback, $priority, $accepted); }
function do_action($name, ...$args) {
  $hooks = $GLOBALS['hooks'][$name] ?? [];
  ksort($hooks);
  foreach ($hooks as $callbacks) foreach ($callbacks as $item) call_user_func_array($item[0], array_slice($args, 0, $item[1]));
}
function apply_filters($name, $value, ...$args) {
  $hooks = $GLOBALS['hooks'][$name] ?? [];
  ksort($hooks);
  foreach ($hooks as $callbacks) foreach ($callbacks as $item) $value = call_user_func_array($item[0], array_slice(array_merge([$value], $args), 0, $item[1]));
  return $value;
}
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_term($id, $taxonomy = '') {
  $term = $GLOBALS['fixture_terms'][$id] ?? null;
  return $term && (!$taxonomy || $term->taxonomy === $taxonomy) ? $term : null;
}
function get_posts($args) {
  return array_values(array_filter($GLOBALS['posts'], function($post) use ($args) {
    $types = (array) ($args['post_type'] ?? 'post');
    $statuses = (array) ($args['post_status'] ?? 'publish');
    return in_array($post->post_type, $types, true) && ($statuses === ['any'] || in_array($post->post_status, $statuses, true));
  }));
}
function get_post_meta($id, $key, $single = false) {
  $GLOBALS['meta_reads']++;
  $values = $GLOBALS['meta'][$id][$key] ?? [];
  return $single ? ($values[0] ?? '') : $values;
}
function add_post_meta($id, $key, $value) {
  $GLOBALS['meta'][$id][$key][] = wp_unslash($value);
  do_action('added_post_meta', 1, $id, $key, $value);
  return 1;
}
function update_post_meta($id, $key, $value) {
  $event = isset($GLOBALS['meta'][$id][$key]) ? 'updated_post_meta' : 'added_post_meta';
  $GLOBALS['meta'][$id][$key] = [wp_unslash($value)];
  do_action($event, 1, $id, $key, $value);
  return true;
}
function delete_post_meta($id, $key) {
  unset($GLOBALS['meta'][$id][$key]);
  do_action('deleted_post_meta', [1], $id, $key, '');
  return true;
}
function get_term_meta($id, $key, $single = false) { return $GLOBALS['term_meta'][$id][$key] ?? ''; }
function update_term_meta($id, $key, $value) { $GLOBALS['term_meta'][$id][$key] = wp_unslash($value); }
function delete_term_meta($id, $key) { unset($GLOBALS['term_meta'][$id][$key]); }
function wp_get_attachment_url($id) { $GLOBALS['media_lookups']++; return (int) $id === 42 ? 'https://example.test/photo.jpg' : false; }
function attachment_url_to_postid($url) { return $url === 'https://example.test/migrated.jpg' ? 84 : 0; }
function wp_get_attachment_image($id, $size, $icon, $attrs) { return '<img>'; }
function wp_list_pluck($items, $field) { return array_map(function($item) use ($field) { return $item->$field; }, $items); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function get_the_ID() { return 1; }
function get_page_template_slug($id) { return get_post_meta($id, '_wp_page_template', true); }
function get_post_types($args, $output) { return ['post']; }
function get_post_type_object($type) { return in_array($type, ['post', 'project'], true) ? (object) ['name' => $type] : null; }
function register_rest_field($type, $name, $config) { $GLOBALS['rest_fields'][$type][$name] = $config; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function get_bloginfo($key) { return 'test'; }
function get_site_transient($key) {
  $entry = $GLOBALS['site_transients'][$key] ?? null;
  return $entry && $entry['expires'] > $GLOBALS['now'] ? $entry['value'] : false;
}
function set_site_transient($key, $value, $ttl) {
  $GLOBALS['site_transients'][$key] = ['value' => $value, 'expires' => $GLOBALS['now'] + $ttl];
}
function wp_remote_get($url, $args) { $GLOBALS['remote_calls']++; $GLOBALS['remote_args'] = $args; return $GLOBALS['remote_response']; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function check($condition, $message) {
  global $checks;
  if (!$condition) throw new RuntimeException($message);
  $checks++;
}
function fixture_post($id, $type = 'post') {
  $GLOBALS['posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Fixture ' . $id];
}

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/class-updater.php';
$plugin = CFF\Plugin::instance();
fixture_post(1);
fixture_post(900, 'cff_group');
$sub_fields = [
  ['name' => 'count', 'type' => 'number'], ['name' => 'image', 'type' => 'image'],
  ['name' => 'caption', 'type' => 'text'],
  ['name' => 'children', 'type' => 'repeater', 'sub_fields' => [['name' => 'count', 'type' => 'number']]],
];
$fields = [
  ['name' => 'path', 'type' => 'text'], ['name' => 'qty', 'type' => 'number'],
  ['name' => 'image', 'type' => 'image'], ['name' => 'file', 'type' => 'file'],
  ['name' => 'related', 'type' => 'relational'], ['name' => 'related_many', 'type' => 'relational', 'relational_multiple' => true],
  ['name' => 'gallery', 'type' => 'gallery'], ['name' => 'group', 'type' => 'group', 'sub_fields' => $sub_fields],
  ['name' => 'rows', 'type' => 'repeater', 'sub_fields' => $sub_fields],
  ['name' => 'blocks', 'type' => 'flexible', 'layouts' => [['name' => 'hero', 'sub_fields' => $sub_fields]]],
];
$settings = ['fields' => $fields, 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]]];
update_post_meta(900, '_cff_settings', wp_slash($settings));

// Term edits must authenticate the CFF form and preserve omitted fields.
$terms = new CFF\Term_Meta_Manager();
$original = ['cffp_term_image_id' => 42, 'cffp_term_short_description' => 'Short', 'cffp_term_description' => 'Long'];
$GLOBALS['term_meta'][10] = $original;
foreach ([[], ['cffp_term_image_id' => ''], ['cff_term_meta_nonce' => 'invalid', 'cffp_term_description' => '']] as $input) {
  $_POST = $input;
  $terms->save_term_fields(10);
  check($GLOBALS['term_meta'][10] === $original, 'Non-CFF/invalid-nonce term save preserves metadata');
}
$_POST = ['cff_term_meta_nonce' => 'valid', 'cffp_term_short_description' => wp_slash('C:\\terms\\text')];
$terms->save_term_fields(10);
check($GLOBALS['term_meta'][10]['cffp_term_short_description'] === 'C:\\terms\\text', 'Term text retains backslashes');
check($GLOBALS['term_meta'][10]['cffp_term_image_id'] === 42 && $GLOBALS['term_meta'][10]['cffp_term_description'] === 'Long', 'Partial term save preserves omitted fields');
$GLOBALS['denied'] = ['edit_term:10'];
$_POST = ['cff_term_meta_nonce' => 'valid', 'cffp_term_description' => ''];
$terms->save_term_fields(10);
check($GLOBALS['term_meta'][10]['cffp_term_description'] === 'Long', 'Term-specific capability is required');
$GLOBALS['denied'] = [];
$_POST = ['cff_term_meta_nonce' => 'valid', 'cffp_term_image_id' => '', 'cffp_term_short_description' => '', 'cffp_term_description' => ''];
$terms->save_term_fields(10);
check($GLOBALS['term_meta'][10] === [], 'Explicit empty CFF fields can be cleared');
ob_start(); $terms->render_term_fields_add('category'); $html = ob_get_clean();
check(strpos($html, 'name="cff_term_meta_nonce"') !== false, 'Add form includes CFF nonce');
ob_start(); $terms->render_term_fields_edit((object) ['term_id' => 10], 'category'); $html = ob_get_clean();
check(strpos($html, 'name="cff_term_meta_nonce"') !== false, 'Edit form includes CFF nonce');

// Classic and REST writes must round-trip nested strings through WP unslashing.
$text = 'C:\\reports\\sample "quoted" {"regex":"\\\\w+"}';
$saver = new CFF\Content_Field_Saver($plugin);
$_POST = ['cff_content_nonce' => 'valid', 'cff_values' => wp_slash(['path' => $text, 'group' => ['caption' => $text], 'rows' => [['caption' => $text]]])];
require __DIR__ . '/../includes/render.php';
$saver->save(1, get_post(1));
check(get_post_meta(1, '_cff_path', true) === $text, 'Classic text round-trips backslashes and quotes');
check(get_post_meta(1, '_cff_group', true)['caption'] === $text, 'Classic group text round-trips');
check(get_post_meta(1, '_cff_rows', true)[0]['caption'] === $text, 'Classic repeater text round-trips');
$rest = new CFF\Rest_Fields($plugin);
check($rest->update_payload(1, 'post', ['path' => $text, 'group' => ['caption' => $text], 'rows' => [['caption' => $text]]]) === true, 'REST write succeeds');
check(get_post_meta(1, '_cff_path', true) === $text && get_post_meta(1, '_cff_group', true)['caption'] === $text && get_post_meta(1, '_cff_rows', true)[0]['caption'] === $text, 'REST nested strings round-trip');

// Formatting uses field definitions, even when a number is also a media ID.
foreach (['qty' => 42, 'path' => '42', 'image' => 42, 'file' => 42, 'related' => 42, 'related_many' => [42], 'gallery' => [42]] as $name => $value) {
  update_post_meta(1, '_cff_' . $name, $value);
}
$GLOBALS['media_lookups'] = 0;
check(CFF\get_field('qty', 1) === 42 && CFF\get_field('path', 1) === '42', 'Number and numeric text retain their types');
check(CFF\get_field('related', 1) === 42 && CFF\get_field('related_many', 1) === [42] && CFF\get_field('gallery', 1) === [42], 'Relational and gallery IDs remain IDs');
check($GLOBALS['media_lookups'] === 0, 'Non-media fields never perform attachment lookups');
check(CFF\get_field('image', 1) === ['id' => 42, 'url' => 'https://example.test/photo.jpg'], 'Image formats as id/url');
check(CFF\get_field('file', 1)['id'] === 42 && CFF\get_field('image', 1, false) === 42, 'File formatting and raw image reads work');
check(CFF\cff_format_value(42) === 42, 'Untyped numeric values are never guessed as media');
check(CFF\cff_format_value(['id' => 42, 'url' => 'https://example.test/migrated.jpg'], true, ['type' => 'image']) === ['id' => 84, 'url' => 'https://example.test/migrated.jpg'], 'Typed media retains migrated URL-to-ID resolution');
$row = ['count' => 42, 'image' => 42, 'image_url' => 'https://old.test/photo.jpg', 'caption' => '42', 'children' => [['count' => 42], ['count' => 7]]];
update_post_meta(1, '_cff_group', wp_slash($row));
update_post_meta(1, '_cff_rows', wp_slash([$row]));
update_post_meta(1, '_cff_blocks', wp_slash([['layout' => 'hero', 'fields' => $row]]));
foreach ([CFF\get_field('group', 1), CFF\get_field('rows', 1)[0], CFF\get_field('blocks', 1)[0]['fields']] as $formatted) {
  check($formatted['count'] === 42 && $formatted['caption'] === '42' && $formatted['children'][0]['count'] === 42, 'Nested scalar types survive formatting');
  check($formatted['image']['id'] === 42 && !isset($formatted['image_url']), 'Nested media formats once without leaking companion URL');
}

// REST schemas match both relational cardinalities and stay nested correctly.
$single = $rest->build_schema_for_field($fields[4]);
$multiple = $rest->build_schema_for_field($fields[5]);
check($single['type'] === 'integer' && $single['minimum'] === 0, 'Single relational schema accepts an ID or zero to clear');
check($multiple['type'] === 'array' && $multiple['items'] === ['type' => 'integer', 'minimum' => 1], 'Multiple relational schema accepts positive IDs');
$nested_schema = $rest->build_schema_for_field(['type' => 'repeater', 'sub_fields' => [$fields[5]]]);
check($nested_schema['items']['properties']['related_many']['type'] === 'array', 'Nested relational schema retains cardinality');
$rest->register();
check($GLOBALS['rest_fields']['post']['cff']['schema']['properties']['related_many']['type'] === 'array', 'Registered REST schema includes relational arrays');
$rest->update_payload(1, 'post', ['related_many' => [42, 42, 7]]);
check(get_post_meta(1, '_cff_related_many', true) === [42, 7], 'Relational REST writes store sanitized unique IDs');

// Flexible relational fields must use the AJAX select renderer in saved and new rows.
$relational_layout = ['name' => 'related_content', 'sub_fields' => [
  ['name' => 'related', 'type' => 'relational', 'relational_type' => 'post_type', 'relational_subtype' => 'project'],
  ['name' => 'related_many', 'type' => 'relational', 'relational_type' => 'post', 'relational_multiple' => true],
]];
foreach ([0, '__INDEX__'] as $index) {
  ob_start();
  CFF\render_flexible_row('blocks', [$relational_layout], ['related_content' => $relational_layout], ['__cff_row_id' => 'fixture_row', 'layout' => 'related_content', 'fields' => ['related' => 1, 'related_many' => [1]]], $index, 1);
  $html = ob_get_clean();
  check(substr_count($html, 'cff-relational-ajax cff-select2') === 2, 'Saved and template flexible rows render relational selects');
  check(strpos($html, 'data-relational-subtype="project"') !== false && strpos($html, 'data-relational-type="post_type"') !== false, 'Flexible relational selects retain their AJAX source');
  check(strpos($html, 'name="cff_values[blocks][' . $index . '][fields][related_many][]" multiple') !== false, 'Flexible multiple relational selects retain array names and multiple mode');
  check(strpos($html, '<option value="1" selected>Fixture 1</option>') !== false, 'Saved flexible relational selections retain labels');
}

// Invalidation is exercised through registered WP actions, including deletion.
check(CFF\get_field('path', 1, false) === '42', 'Prime value cache');
update_post_meta(1, '_cff_path', 'after');
check(CFF\get_field('path', 1, false) === 'after', 'Update invalidates value cache');
delete_post_meta(1, '_cff_path');
check(CFF\get_field('path', 1, false) === '', 'Delete invalidates value cache');
add_post_meta(1, '_cff_path', 'added');
check(CFF\get_field('path', 1, false) === 'added', 'Add invalidates value cache');
CFF\cff_get_cached_group_settings(900);
CFF\cff_get_field_aliases('renamed');
$settings['fields'][] = ['name' => 'renamed', 'type' => 'text', 'aliases' => ['previous']];
update_post_meta(900, '_cff_settings', wp_slash($settings));
update_post_meta(1, '_cff_previous', 'Alias value');
check(CFF\get_field('renamed', 1) === 'Alias value', 'Group updates invalidate definitions and alias cache');
$settings['location'] = [[['param' => 'page_template', 'operator' => '==', 'value' => 'custom.php']]];
update_post_meta(900, '_cff_settings', wp_slash($settings));
check($plugin->get_field_definitions_for_post(get_post(1)) === [], 'Location change invalidates group matching');
update_post_meta(1, '_wp_page_template', 'custom.php');
check(isset($plugin->get_field_definitions_for_post(get_post(1))['qty']), 'Template meta update refreshes context matching');
$GLOBALS['posts'][900]->post_status = 'draft';
do_action('clean_post_cache', 900);
check($plugin->get_field_definitions_for_post(get_post(1)) === [], 'Group status changes invalidate published group cache');
$GLOBALS['posts'][900]->post_status = 'publish';
do_action('clean_post_cache', 900);
check(isset($plugin->get_field_definitions_for_post(get_post(1))['qty']), 'Republished groups become available immediately');

// Loop work stays linear and raw subfield reads remain raw.
$rows = array_fill(0, 100, $row);
update_post_meta(1, '_cff_rows', wp_slash($rows));
$GLOBALS['media_lookups'] = 0;
$count = 0;
while (CFF\have_rows('rows', 1)) {
  CFF\the_row();
  check(CFF\get_sub_field('count') === 42, 'Loop numeric subfield remains numeric');
  check(CFF\get_sub_field('image', false) === 42, 'Raw loop media remains an ID');
  check(CFF\get_sub_field('image')['id'] === 42, 'Loop media formats when requested');
  $count++;
}
check($count === 100 && $GLOBALS['media_lookups'] === 100, '100 rows require exactly 100 requested media lookups');
$count = 0;
while (CFF\have_rows('rows', 1)) { CFF\the_row(); $count++; }
check($count === 100, 'Completed repeaters can be looped again');
update_post_meta(1, '_cff_rows', wp_slash([$row, $row]));
$children = [];
$parents = 0;
while (CFF\have_rows('rows', 1)) {
  CFF\the_row();
  while (CFF\have_rows('children', 1)) { CFF\the_row(); $children[] = CFF\get_sub_field('count'); }
  check(CFF\get_sub_field('count') === 42, 'Nested loop restores parent context');
  $parents++;
}
check($parents === 2 && $children === [42, 7, 42, 7], 'Nested repeater iterates every child of each parent');
$parents = 0;
while (CFF\have_rows('rows', '1')) {
  CFF\the_row();
  while (CFF\have_rows('children', 1)) { CFF\the_row(); break; }
  $parents++;
}
check($parents === 2 && $GLOBALS['cff_row_stack'] === [], 'Parent resumes after an early child break and releases loop state');
while (CFF\have_rows('blocks', 1)) {
  CFF\the_row();
  check(CFF\get_row_layout() === 'hero' && CFF\get_sub_field('image')['id'] === 42, 'Flexible loop uses layout-specific subfields');
}

// Execute the actual generated term ordering SQL with missing/duplicate meta rows.
$GLOBALS['options']['cffp_reorder_taxonomies'] = ['category'];
$GLOBALS['wpdb'] = (object) ['termmeta' => 'termmeta'];
$args = apply_filters('get_terms_args', ['orderby' => 'name', 'order' => 'ASC', 'fields' => 'all'], ['category']);
check(!isset($args['meta_key']) && !empty($args['cffp_order_terms']), 'Reordering does not filter terms by meta existence');
$base = ['join' => '', 'orderby' => 'ORDER BY t.name', 'order' => 'ASC'];
$clauses = apply_filters('terms_clauses', $base, ['category'], $args);
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE terms (term_id INTEGER, name TEXT); CREATE TABLE termmeta (term_id INTEGER, meta_key TEXT, meta_value TEXT)');
$db->exec("INSERT INTO terms VALUES (1,'Alpha'),(2,'Beta'),(3,'Gamma'); INSERT INTO termmeta VALUES (1,'cffp_term_order','5'),(1,'cffp_term_order','9'),(3,'cffp_term_order','1')");
$sql = 'SELECT t.term_id FROM terms t ' . $clauses['join'] . ' ' . $clauses['orderby'] . ' ' . $clauses['order'];
check(array_map('intval', $db->query($sql)->fetchAll(PDO::FETCH_COLUMN)) === [2, 3, 1], 'New term is included and duplicate meta does not duplicate results');
check(array_map('intval', $db->query($sql . ' LIMIT 1 OFFSET 1')->fetchAll(PDO::FETCH_COLUMN)) === [3], 'Term pagination remains stable');
$desc = apply_filters('terms_clauses', $base, ['category'], array_merge($args, ['order' => 'DESC']));
$sql = 'SELECT t.term_id FROM terms t ' . $desc['join'] . ' ' . $desc['orderby'] . ' ' . $desc['order'];
check(array_map('intval', $db->query($sql)->fetchAll(PDO::FETCH_COLUMN)) === [1, 3, 2], 'Descending reorder keeps missing terms');
check(apply_filters('terms_clauses', $base, ['category'], array_merge($args, ['fields' => 'count'])) === $base, 'Count queries stay untouched');
check(apply_filters('get_terms_args', ['orderby' => 'count'], ['category']) === ['orderby' => 'count'], 'Explicit alternative sorting is preserved');
check(apply_filters('get_terms_args', ['orderby' => 'name'], ['post_tag']) === ['orderby' => 'name'], 'Other taxonomies retain their ordering');

// GitHub failure backoff works across instances, and expires without a real wait.
$file = '/plugins/custom-fields-framework-pro/custom-fields-framework-pro.php';
$baseline = (object) ['checked' => [plugin_basename($file) => '2.5.26']];
$GLOBALS['remote_response'] = new WP_Error();
$updater = new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip');
$updater->inject_update(clone $baseline);
$updater->inject_update(clone $baseline);
check($GLOBALS['remote_calls'] === 1 && $GLOBALS['remote_args']['timeout'] === 5, 'Failed updater request is memoized and has a short timeout');
$updater = new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip');
$updater->inject_update(clone $baseline);
check($GLOBALS['remote_calls'] === 1, 'Negative cache prevents retries across updater instances');
$GLOBALS['now'] += CFF_Github_Public_Updater::FAILURE_TRANSIENT_TTL + 1;
$GLOBALS['remote_response'] = ['status' => 200, 'body' => json_encode(['tag_name' => 'v2.5.27', 'assets' => [['name' => 'custom-fields-framework-pro.zip', 'browser_download_url' => 'https://example.test/cff.zip']]])];
$updater = new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip');
$updated = $updater->inject_update(clone $baseline);
check($GLOBALS['remote_calls'] === 2 && $updated->response[plugin_basename($file)]->new_version === '2.5.27', 'Updater recovers after failure cache expires');
$updater = new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip');
$updater->inject_update(clone $baseline);
check($GLOBALS['remote_calls'] === 2, 'Successful release uses the persistent cache');
foreach ([['status' => 429, 'body' => '{}'], ['status' => 200, 'body' => '{invalid'], ['status' => 200, 'body' => '{}']] as $response) {
  $GLOBALS['site_transients'] = [];
  $GLOBALS['remote_response'] = $response;
  $calls = $GLOBALS['remote_calls'];
  (new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip'))->inject_update(clone $baseline);
  (new CFF_Github_Public_Updater($file, 'ekods/custom-fields-framework-pro', 'custom-fields-framework-pro.zip'))->inject_update(clone $baseline);
  check($GLOBALS['remote_calls'] === $calls + 1, 'HTTP/invalid release failures are negatively cached');
}

// Native JSON import must keep the relation's object namespace. Post and term IDs can overlap.
$sanitize_import = new ReflectionMethod(CFF\Plugin::class, 'sanitize_group_settings');
$sanitize_import->setAccessible(true);
$imported = $sanitize_import->invoke($plugin, [
  'presentation' => ['style' => 'standard', 'position' => 'normal', 'label_placement' => 'top', 'instruction_placement' => 'below_labels'],
  'fields' => [[
    'key' => 'fld_service_import', 'name' => 'capability_service', 'label' => '00 — Service', 'type' => 'relational',
    'relational_type' => 'taxonomy', 'relational_subtype' => 'service', 'relational_post_type' => 'work',
    'relational_display' => 'select', 'relational_multiple' => false,
  ], [
    'key' => 'fld_related_work_import', 'name' => 'related_work', 'type' => 'relational',
    'relational_type' => 'post_type', 'relational_subtype' => 'project', 'relational_display' => 'checkbox', 'relational_multiple' => true,
  ]],
  'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]],
]);
$service_field = $imported['fields'][0];
check(($service_field['relational_type'] ?? null) === 'taxonomy' && ($service_field['relational_subtype'] ?? null) === 'service', 'Import discarded the taxonomy source and changed Service into a Post relationship');
check(($service_field['relational_post_type'] ?? null) === 'work' && ($service_field['relational_display'] ?? null) === 'select' && $service_field['relational_multiple'] === false, 'Import lost relational editor settings');
$work_field = $imported['fields'][1];
check($work_field['relational_type'] === 'post_type' && $work_field['relational_subtype'] === 'project' && $work_field['relational_display'] === 'checkbox' && $work_field['relational_multiple'] === true, 'Import changed custom post type or multiple relation settings');
$GLOBALS['posts'][149] = (object) ['ID' => 149, 'post_type' => 'attachment', 'post_title' => '814306f0f546ac0f927cc34f736fb49b14b4f0eb'];
$GLOBALS['fixture_terms'][149] = (object) ['term_id' => 149, 'taxonomy' => 'service', 'name' => 'Packaging'];
ob_start();
CFF\render_relational_input('cff_values[capability_service]', $service_field['relational_type'], $service_field['relational_subtype'], $service_field['relational_display'], 149, false, '');
$import_html = ob_get_clean();
check(strpos($import_html, 'data-relational-type="taxonomy"') !== false && strpos($import_html, 'data-relational-subtype="service"') !== false, 'Imported field renders the wrong AJAX namespace');
check(strpos($import_html, '<option value="149" selected>Packaging</option>') !== false && strpos($import_html, '814306f0') === false, 'Imported taxonomy label resolved an unrelated post with the same ID');
echo 'Runtime integrity: ' . $checks . " checks passed.\n";
