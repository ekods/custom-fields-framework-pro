<?php
/** Standalone content import integration: real importer, registry and sanitizer; isolated WP persistence. */
define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/class-plugin.php';
require_once __DIR__ . '/../includes/class-field-sanitizer.php';
require_once __DIR__ . '/../includes/class-content-import.php';

class WP_Error {
  private $message;
  public function __construct($code, $message) { $this->message = $message; }
  public function get_error_message() { return $this->message; }
}
class Json_Response extends RuntimeException {
  public $success, $data;
  public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
function __($text, $domain = '') { return $text; }
function apply_filters($name, $value) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-'); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_hex_color($value) { return preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value) ? $value : ''; }
function wp_kses_post($value) { return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value); }
function esc_url_raw($value) { return $value; }
function get_permalink($id) { return 'https://test.invalid/' . $id . '/'; }
function admin_url($path) { return 'https://test.invalid/wp-admin/' . $path; }
function wp_check_invalid_utf8($value, $strip) { return $value; }
function get_current_user_id() { return $GLOBALS['owner']; }
function current_user_can($cap, $id = 0) { return !in_array($cap . ':' . $id, $GLOBALS['denied'], true); }
function get_post_types($args, $output) {
  $type = (object) ['labels' => (object) ['name' => 'Pages'], 'cap' => (object) ['create_posts' => 'edit_posts', 'edit_posts' => 'edit_posts', 'publish_posts' => 'publish_posts']];
  return ['post' => $type, 'page' => $type, 'cff_group' => $type, 'attachment' => $type];
}
function get_post_type_object($type) { return get_post_types([], 'objects')[$type] ?? null; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_posts($args) {
  if ($args['post_type'] === 'cff_group') return [(object) ['ID' => 900], (object) ['ID' => 901]];
  return array_values(array_filter($GLOBALS['posts'], function($post) use ($args) {
    if ($post->post_type !== $args['post_type']) return false;
    if (isset($args['name']) && $post->post_name !== $args['name']) return false;
    if (!in_array($post->post_status, (array) $args['post_status'], true)) return false;
    if (isset($args['meta_query'])) return strpos((string) get_post_meta($post->ID, '_wp_attached_file', true), $args['meta_query'][0]['value']) !== false;
    return true;
  }));
}
function wp_get_theme() { return new class { public function get_page_templates($post, $type) { return ['services.php' => 'Services']; } }; }
function get_page_template_slug($id) { return get_post_meta($id, '_wp_page_template', true); }
function get_post_ancestors($id) { $out = []; while ($id = (get_post($id)->post_parent ?? 0)) { $out[] = $id; if (count($out) > 50) break; } return $out; }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function get_post_meta($id, $key, $single = false) { $values = $GLOBALS['meta'][$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
function metadata_exists($type, $id, $key) { return isset($GLOBALS['meta'][$id][$key]); }
function delete_post_meta($id, $key) { $GLOBALS['writes']++; unset($GLOBALS['meta'][$id][$key]); return true; }
function add_post_meta($id, $key, $value) { $GLOBALS['writes']++; $GLOBALS['meta'][$id][$key][] = wp_unslash($value); return 1; }
function update_post_meta($id, $key, $value) {
  if (($GLOBALS['fail_meta'] ?? '') === $key) return false;
  delete_post_meta($id, $key); return add_post_meta($id, $key, $value);
}
function fixture_post($id, $type = 'page', $slug = '') {
  return $GLOBALS['posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Page ' . $id,
    'post_name' => $slug ?: 'page-' . $id, 'post_content' => 'Original', 'post_excerpt' => '', 'post_parent' => 0,
    'menu_order' => 0, 'post_modified_gmt' => '2026-10-31 00:00:00'];
}
function wp_insert_post($data, $error = false) {
  $GLOBALS['writes']++; $id = ++$GLOBALS['next_post']; fixture_post($id);
  foreach (wp_unslash($data) as $key => $value) $GLOBALS['posts'][$id]->$key = $value;
  $GLOBALS['insert_statuses'][] = $data['post_status']; return $id;
}
function wp_update_post($data, $error = false) {
  $GLOBALS['writes']++;
  if (isset($GLOBALS['sync_hooks']['pll_save_post'])) $GLOBALS['sync_calls']++;
  if (!empty($GLOBALS['fail_core'])) { $GLOBALS['fail_core'] = false; return new WP_Error('write', 'Injected core failure'); }
  foreach (wp_unslash($data) as $key => $value) $GLOBALS['posts'][$data['ID']]->$key = $value;
  return $data['ID'];
}
function wp_delete_post($id, $force) { $GLOBALS['writes']++; unset($GLOBALS['posts'][$id], $GLOBALS['meta'][$id], $GLOBALS['assigned'][$id], $GLOBALS['languages'][$id]); }
function wp_attachment_is($type, $id) { return $type === 'image' && (get_post($id)->post_type ?? '') === 'attachment'; }
function wp_get_attachment_url($id) { return 'https://test.invalid/media/' . $id . '.jpg'; }
function get_object_taxonomies($type) { return ['service', 'language']; }
function get_taxonomy($name) { return in_array($name, ['service', 'category', 'language'], true) ? (object) ['cap' => (object) ['assign_terms' => 'assign_terms']] : false; }
function get_term($id, $taxonomy = '') { $term = $GLOBALS['terms'][$id] ?? null; return $term && (!$taxonomy || $taxonomy === $term->taxonomy) ? $term : null; }
function get_userdata($id) { return $id === 7 ? (object) ['ID' => 7] : null; }
function wp_get_object_terms($id, $tax, $args) { return $GLOBALS['assigned'][$id][$tax] ?? []; }
function wp_set_object_terms($id, $terms, $tax, $append = false) {
  $GLOBALS['writes']++;
  if (!empty($GLOBALS['fail_terms'])) { $GLOBALS['fail_terms'] = false; return new WP_Error('terms', 'Injected terms failure'); }
  $GLOBALS['assigned'][$id][$tax] = $terms; return $terms;
}
function pll_languages_list($args) { return ['en', 'id']; }
function pll_is_translated_post_type($type) { return in_array($type, ['page', 'post'], true); }
function pll_set_post_language($id, $lang) { $GLOBALS['writes']++; $GLOBALS['languages'][$id] = $lang; }
function pll_get_post_language($id, $field = 'slug') { return $GLOBALS['languages'][$id] ?? ''; }
function pll_get_post($id, $lang) { return 0; }
function PLL() { return (object) ['sync' => (object) []]; }
function has_filter($hook, $callback) { return $GLOBALS['sync_hooks'][$hook] ?? false; }
function remove_filter($hook, $callback, $priority) { unset($GLOBALS['sync_hooks'][$hook]); }
function add_filter($hook, $callback, $priority, $args) { $GLOBALS['sync_hooks'][$hook] = $priority; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = true) { if (isset($GLOBALS['options'][$key])) return false; $GLOBALS['options'][$key] = $value; return true; }
function update_option($key, $value, $autoload = true) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function wp_cache_delete($key, $group) {}
function wp_schedule_single_event($time, $hook, $args) { $GLOBALS['scheduled'][] = [$time, $hook, $args]; }
function wp_generate_uuid4() { return 'import-token-' . ++$GLOBALS['uuid']; }
function wp_generate_password($length, $special = true, $extra = false) { return 'row' . ++$GLOBALS['uuid']; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function wp_send_json_success($data) { throw new Json_Response(true, $data); }
function wp_send_json_error($data, $code = 400) { throw new Json_Response(false, $data); }
$wpdb = new class {
  public $options = 'wp_options';
  public function prepare($sql, ...$args) { return $args; }
  public function query($args) {
    if (isset($GLOBALS['options'][$args[0]]) && maybe_serialize($GLOBALS['options'][$args[0]]) === $args[1]) unset($GLOBALS['options'][$args[0]]);
    return 1;
  }
};
foreach (['posts' => [], 'meta' => [], 'terms' => [], 'assigned' => [], 'languages' => [], 'options' => [], 'denied' => [],
  'owner' => 7, 'uuid' => 0, 'writes' => 0, 'assertions' => 0, 'next_post' => 1000, 'insert_statuses' => [], 'scheduled' => [],
  'sync_hooks' => ['pll_save_post' => 10, 'wp_insert_post_data' => 10, 'wp_insert_post_parent' => 10], 'sync_calls' => 0] as $key => $value) $GLOBALS[$key] = $value;
function check($condition, $message) { if (!$condition) throw new RuntimeException('FAIL: ' . $message); $GLOBALS['assertions']++; }
function rejects($callback, $message) {
  try { $callback(); } catch (RuntimeException $e) { check(stripos($e->getMessage(), $message) !== false, 'Error includes ' . $message . ': ' . $e->getMessage()); return; }
  throw new RuntimeException('FAIL: Expected rejection: ' . $message);
}
function json_items($items) { return json_encode(['format' => 'cff-content', 'version' => 1, 'items' => $items]); }
function prepare_job($import, $items, $mode = 'create', $extra = []) {
  $status = $import->command('status');
  if ($status && !in_array($status['phase'], ['completed', 'cancelled'], true)) $import->command('cancel', ['job' => $status['token']]);
  $job = $import->command('upload', ['post_type' => 'page', 'mode' => $mode] + $extra, json_items($items));
  while ($job['phase'] === 'preview') $job = $import->command('preview', ['job' => $job['token']]);
  return $job;
}
function finish_job($import, $job) {
  $job = $import->command('start', ['job' => $job['token']]);
  for ($n = 0; $job['phase'] === 'running' && $n < 110; $n++) $job = $import->command('batch', ['job' => $job['token']]);
  return $job;
}
$plugin = (new ReflectionClass(\CFF\Plugin::class))->newInstanceWithoutConstructor();
$import = new \CFF\Content_Import($plugin);
$fields = [
  ['name' => 'heading', 'type' => 'text'], ['name' => 'count', 'type' => 'number'], ['name' => 'enabled', 'type' => 'checkbox'],
  ['name' => 'group', 'type' => 'group', 'sub_fields' => [['name' => 'title', 'type' => 'text', 'required' => 1], ['name' => 'caption', 'type' => 'textarea']]],
  ['name' => 'rows', 'type' => 'repeater', 'max' => 3, 'sub_fields' => [['name' => 'title', 'type' => 'text', 'required' => 1], ['name' => 'date', 'type' => 'date_picker']]],
  ['name' => 'blocks', 'type' => 'flexible', 'layouts' => [['name' => 'hero', 'sub_fields' => [['name' => 'title', 'type' => 'text']]]]],
  ['name' => 'service', 'type' => 'relational', 'relational_type' => 'taxonomy', 'relational_subtype' => 'service'],
  ['name' => 'related', 'type' => 'relational', 'relational_type' => 'post_type', 'relational_subtype' => 'page', 'relational_multiple' => 1],
  ['name' => 'photo', 'type' => 'image'], ['name' => 'gallery', 'type' => 'gallery'],
  ['name' => 'choice', 'type' => 'choice', 'choices' => [['value' => 'yes'], ['value' => 'no']]],
];
$settings = ['fields' => $fields, 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]]];
$plugin->set_group_settings_cache(900, $settings);
$plugin->set_group_settings_cache(901, ['fields' => [['name' => 'template_heading', 'type' => 'text']], 'location' => [[['param' => 'post_type', 'value' => 'page'], ['param' => 'page_template', 'value' => 'services.php']]]]);
fixture_post(1); fixture_post(149, 'attachment'); fixture_post(2);
$GLOBALS['terms'][149] = (object) ['term_id' => 149, 'taxonomy' => 'service'];
$GLOBALS['terms'][150] = (object) ['term_id' => 150, 'taxonomy' => 'category'];
$options = ['post_type' => 'page', 'mode' => 'create', 'allow_publish' => false];

check(count($import->parse(json_encode(\CFF\Content_Import::sample()))) === 1, 'Download sample parses');
check(count($import->parse("\xEF\xBB\xBF" . json_encode([['post_title' => 'BOM']])) ) === 1, 'UTF-8 BOM accepted');
check(count($import->parse('{"ID":1}')) === 1, 'Single content object accepted');
foreach (['{' => 'Invalid JSON', '{}' => 'non-empty', '{"items":{}}' => 'non-empty', '{"field_groups":[]}' => 'configuration', '{"format":"other","items":[{}]}' => 'Unsupported', '{"version":2,"items":[{}]}' => 'version'] as $raw => $error) rejects(function() use ($import, $raw) { $import->parse($raw); }, $error);
rejects(function() use ($import) { $import->parse(str_repeat(' ', \CFF\Content_Import::MAX_BYTES + 1)); }, '5 MB');
rejects(function() use ($import) { $import->parse(json_items(array_fill(0, 501, ['post_title' => 'x']))); }, '500');
rejects(function() use ($import) { $import->validate_options(['post_type' => 'attachment']); }, 'accessible');
rejects(function() use ($import) { $import->validate_options(['post_type' => 'page', 'mode' => 'delete']); }, 'mode');
$bad_rows = [
  [['post_title' => 'Unknown', 'cff' => ['missing' => 'x']], 'not defined'],
  [['post_type' => 'post', 'post_title' => 'Wrong type'], 'does not match'],
  [['ID' => 149], 'destination ID'], [['post_title' => 'Bad', 'post_status' => 'publish'], 'Publishing'],
  [['post_title' => 'Bad', 'post_status' => 'future'], 'Supported statuses'],
  [['post_title' => 'Bad', 'post_author' => 7], 'Unknown item keys'],
  [['post_title' => 'Bad', 'cff' => ['photo' => 1]], 'media ID'],
  [['post_title' => 'Bad', 'cff' => ['photo' => ['url' => 'https://remote.invalid/a.jpg']]], 'remote URL'],
  [['post_title' => 'Bad', 'cff' => ['service' => 150]], 'configured taxonomy'],
  [['post_title' => 'Bad', 'cff' => ['related' => [149]]], 'relationship source'],
  [['post_title' => 'Bad', 'cff' => ['related' => ['99999999999999999999999999999']]], 'ID range'],
  [['post_title' => 'Bad', 'cff' => ['choice' => 'invalid']], 'choice'],
  [['post_title' => 'Bad', 'cff' => ['count' => []]], ''],
  [['post_title' => 'Bad', 'cff' => ['rows' => [['date' => '2026-10-31']]]], 'Required field missing'],
  [['post_title' => 'Bad', 'cff' => ['rows' => [['title' => 'X', 'date' => '2026-02-31']]]], 'Invalid value'],
  [['post_title' => 'Bad', 'cff' => ['blocks' => [['layout' => 'unknown', 'fields' => []]]]], 'unknown layout'],
  [['post_title' => 'Bad', 'taxonomies' => ['service' => [150]]], 'does not belong'],
  [['post_title' => 'Bad', 'taxonomies' => ['language' => [149]]], 'not assignable'],
  [['post_title' => 'Bad', 'page_template' => 'missing.php'], 'not registered'],
];
// Empty optional numeric input intentionally clears it; it is not an invalid row.
array_splice($bad_rows, 12, 1);
foreach ($bad_rows as $case) rejects(function() use ($import, $options, $case) { $import->prepare_row($case[0], $options); }, $case[1]);
$plan = $import->prepare_row(['post_title' => 'Service', 'page_template' => 'services.php', 'cff' => ['template_heading' => 'Works']], $options);
check($plan['meta']['_cff_template_heading'] === 'Works', 'Planned template matches field groups without writing metadata');
rejects(function() use ($import, $options) { $import->prepare_row(['post_title' => 'Wrong template', 'cff' => ['template_heading' => 'No']], $options); }, 'not defined');

$before = serialize([$GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['assigned']]);
$writes = $GLOBALS['writes'];
$job = prepare_job($import, [[
  'post_title' => 'Imported & Brand', 'post_name' => 'imported-brand', 'post_content' => '<p>C:\\news "quoted"</p><script>bad()</script>',
  'language' => 'en', 'cff' => ['heading' => 'F&B Branding', 'count' => 0, 'enabled' => false, 'service' => 149,
    'related' => [1, 2], 'photo' => 149, 'gallery' => [149], 'group' => ['title' => 'Title'],
    'rows' => [['title' => 'Row']], 'blocks' => [['layout' => 'hero', 'fields' => ['title' => 'Hero']]]],
  'taxonomies' => ['service' => [149]], 'featured_image' => 149,
], ['post_title' => 'Invalid', 'cff' => ['unknown' => 'bad']]]);
check($job['phase'] === 'ready' && $job['valid'] === 1 && $job['invalid'] === 1, 'Preview reports each valid and invalid item');
check($before === serialize([$GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['assigned']]) && $GLOBALS['writes'] === $writes, 'Preview makes no post/meta/taxonomy/language writes');
check(!isset($job['rows'][0]['schema_hash']) && !isset($job['options']) && !isset($job['claimed']), 'Public summary excludes internal plans and raw payload');
rejects(function() use ($import) { $import->command('upload', ['post_type' => 'page'], json_items([['post_title' => 'Other']])); }, 'current import');
rejects(function() use ($import) { $import->command('start', ['job' => 'other-owner']); }, 'another session');
$job = finish_job($import, $job); $id = $job['rows'][0]['target_id'];
check($job['created'] === 1 && $job['phase'] === 'completed', 'Only valid row imports');
check(get_post($id)->post_status === 'draft' && $GLOBALS['insert_statuses'] === ['draft'], 'New content defaults to draft');
check(get_post($id)->post_content === '<p>C:\\news "quoted"</p>', 'Core HTML sanitized and backslashes preserved');
check(get_post_meta($id, '_cff_heading', true) === 'F&B Branding', 'Literal ampersand preserved');
check(get_post_meta($id, '_cff_count', true) === 0 && get_post_meta($id, '_cff_enabled', true) === '0', 'Numeric zero and false checkbox kept');
check(get_post_meta($id, '_cff_service', true) === 149, 'Taxonomy relationship accepts overlapping attachment/term ID in correct namespace');
check(get_post_meta($id, '_cff_rows', true)[0]['title'] === 'Row' && !empty(get_post_meta($id, '_cff_rows', true)[0]['__cff_row_id']), 'Repeater sanitizer creates row IDs');
check(get_post_meta($id, '_cff_blocks', true)[0]['fields']['title'] === 'Hero', 'Flexible layout saved');
check(wp_get_object_terms($id, 'service', []) === [149] && pll_get_post_language($id) === 'en', 'Terms and language assigned');
check($GLOBALS['sync_calls'] === 0 && count($GLOBALS['sync_hooks']) === 3, 'Polylang sync suspended and restored');
check(get_option('cff_content_import_payload_7', null) === null, 'Completed payload removed');
$writes = $GLOBALS['writes']; $import->command('batch', ['job' => $job['token']]);
check($GLOBALS['writes'] === $writes, 'Repeated completed request cannot create duplicates');
$job = prepare_job($import, [['post_title' => 'Imported & Brand', 'post_name' => 'imported-brand']]);
check($job['skip'] === 1 && $job['valid'] === 0, 'Repeat create-only file skips existing slug');

update_post_meta(1, '_cff_group', wp_slash(['title' => 'Keep required title', 'caption' => 'Old caption', 'legacy' => 'C:\\keep']));
update_post_meta(1, '_cff_heading', 'Untouched'); update_post_meta(1, '_cff_rows', [['title' => 'Old row']]);
get_post(1)->post_status = 'publish';
$job = prepare_job($import, [['ID' => 1, 'cff' => ['group' => ['caption' => 'C:\\new "caption"'], 'rows' => [], 'count' => 0]]], 'update');
$job = finish_job($import, $job);
check($job['updated'] === 1 && get_post(1)->post_status === 'publish', 'Existing status preserved when omitted');
check(get_post_meta(1, '_cff_group', true) === ['title' => 'Keep required title', 'caption' => 'C:\\new "caption"', 'legacy' => 'C:\\keep'], 'Group partial update preserves omitted nested and legacy values');
check(get_post_meta(1, '_cff_heading', true) === 'Untouched' && !metadata_exists('post', 1, '_cff_rows'), 'Omitted field preserved and explicit empty repeater removed');
$job = prepare_job($import, [['ID' => 1, 'post_title' => 'Preview title']], 'update');
get_post(1)->post_title = 'Edited after preview'; $writes = $GLOBALS['writes'];
$job = finish_job($import, $job);
check($job['failed'] === 1 && get_post(1)->post_title === 'Edited after preview' && $writes === $GLOBALS['writes'], 'Concurrent destination edit blocks stale import');
$job = prepare_job($import, [['ID' => 1, 'cff' => ['heading' => 'New']]], 'update');
$modified = $settings; $modified['fields'][0]['label'] = 'Changed schema'; $plugin->set_group_settings_cache(900, $modified);
$job = finish_job($import, $job);
check($job['failed'] === 1 && strpos($job['rows'][0]['message'], 'definitions changed') !== false, 'Schema change blocks stale import');
$plugin->set_group_settings_cache(900, $settings);
$job = prepare_job($import, [['post_title' => 'Duplicate'], ['post_title' => 'Duplicate']]);
check($job['valid'] === 1 && $job['invalid'] === 1, 'Duplicate destinations in one file rejected');
$job = prepare_job($import, [['ID' => 1, 'cff' => ['heading' => 'Denied']]], 'update');
$GLOBALS['denied'] = ['edit_post:1']; $job = finish_job($import, $job); $GLOBALS['denied'] = [];
check($job['failed'] === 1 && get_post_meta(1, '_cff_heading', true) === 'Untouched', 'Write rechecks destination permissions');
$job = prepare_job($import, [['post_title' => 'Publish now', 'post_status' => 'publish']], 'create', ['allow_publish' => 1]);
$job = finish_job($import, $job);
check($job['created'] === 1 && get_post($job['rows'][0]['target_id'])->post_status === 'publish' && end($GLOBALS['insert_statuses']) === 'draft', 'Explicit publication only after draft metadata stage');

$backup = [clone $GLOBALS['posts'][1], $GLOBALS['meta'][1], wp_get_object_terms(1, 'service', [])];
$job = prepare_job($import, [['ID' => 1, 'post_title' => 'Roll back', 'cff' => ['heading' => 'Changed'], 'taxonomies' => ['service' => [149]]]], 'update');
$GLOBALS['fail_core'] = true; $job = finish_job($import, $job);
check($job['failed'] === 1 && $job['needs_review'] === 0 && $backup == [$GLOBALS['posts'][1], $GLOBALS['meta'][1], wp_get_object_terms(1, 'service', [])], 'Failed core update restores mapped core, meta and terms');
$job = prepare_job($import, [['post_title' => 'Delete failed new', 'cff' => ['heading' => 'Fail']]]);
$before_count = count($GLOBALS['posts']); $GLOBALS['fail_meta'] = '_cff_heading';
$job = finish_job($import, $job); unset($GLOBALS['fail_meta']);
check($job['failed'] === 1 && count($GLOBALS['posts']) === $before_count, 'Failed new item removes its temporary draft');

$job = prepare_job($import, array_map(function($n) { return ['post_title' => 'Batch ' . $n]; }, range(1, 7)));
$job = $import->command('start', ['job' => $job['token']]); $job = $import->command('batch', ['job' => $job['token']]);
check($job['cursor'] === 5 && $job['created'] === 5 && $job['phase'] === 'running', 'Batch limit is five items');
$job = $import->command('pause', ['job' => $job['token']]); $writes = $GLOBALS['writes'];
$import->command('batch', ['job' => $job['token']]); check($GLOBALS['writes'] === $writes, 'Paused job writes no content');
$job = $import->command('resume', ['job' => $job['token']]); $job = $import->command('batch', ['job' => $job['token']]);
check($job['created'] === 7 && $job['phase'] === 'completed', 'Resume continues cursor without replay');
$job = prepare_job($import, [['post_title' => 'Interrupted item'], ['post_title' => 'Following item']]);
$job = $import->command('start', ['job' => $job['token']]);
$GLOBALS['options']['cff_content_import_job_7']['rows'][0]['state'] = 'writing';
$writes = $GLOBALS['writes']; $job = $import->command('batch', ['job' => $job['token']]);
check($job['needs_review'] === 1 && $job['phase'] === 'paused' && $job['cursor'] === 1 && $GLOBALS['writes'] === $writes, 'Uncertain previous write is never replayed and pauses import');
$job = $import->command('cancel', ['job' => $job['token']]);
check($job['phase'] === 'cancelled' && get_option('cff_content_import_payload_7', null) === null, 'Cancel deletes pending payload');
$GLOBALS['owner'] = 8; check($import->command('status') === [], 'Jobs isolated per user'); $GLOBALS['owner'] = 7;
$GLOBALS['options']['cff_content_import_lock_7'] = ['token' => 'held', 'expires' => time() + 60];
rejects(function() use ($import) { $import->command('status'); }, 'still running');
$GLOBALS['options']['cff_content_import_lock_7']['expires'] = time() - 1;
check($import->command('status')['phase'] === 'cancelled', 'Expired lease recovered');
$GLOBALS['options']['cff_content_import_job_7']['expires'] = time() - 1;
check($import->command('status') === [] && !isset($GLOBALS['options']['cff_content_import_job_7']), 'Expired report removed on access');
check(count($GLOBALS['scheduled']) > 0, 'Uploads schedule expiration cleanup');

$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ([['command' => 'status', 'nonce' => 'bad'], ['command' => 'upload', 'nonce' => 'valid']] as $_POST) {
  try { $import->ajax(); } catch (Json_Response $response) { check(!$response->success, 'AJAX rejects invalid nonce / missing upload'); }
}
$GLOBALS['denied'] = ['manage_options:0']; $_POST = ['command' => 'status', 'nonce' => 'valid'];
try { $import->ajax(); } catch (Json_Response $response) { check(!$response->success, 'AJAX enforces CFF admin capability'); }
$GLOBALS['denied'] = [];

// Exact user-supplied Why EGGHEAD JSON and corresponding installed field definitions.
$why_fields = json_decode(file_get_contents(__DIR__ . '/fixtures/why-egghead-fields.json'), true);
$why_data = json_decode(file_get_contents(__DIR__ . '/fixtures/why-egghead-en.json'), true);
$why_json = json_encode($why_data);
$plugin->set_group_settings_cache(900, $why_fields);
fixture_post(50, 'page', 'why-egghead'); fixture_post(51, 'page', 'kenapa-egghead');
update_post_meta(50, '_wp_page_template', 'templates/page-why-egghead.php');
update_post_meta(51, '_wp_page_template', 'templates/page-why-egghead.php');
get_post(50)->post_status = 'publish'; pll_set_post_language(50, 'en'); pll_set_post_language(51, 'id');
$references = [$why_data['hero']['image']]; foreach ($why_data['work']['items'] as $item) $references[] = $item['image'];
foreach ($references as $index => $reference) {
  fixture_post(200 + $index, 'attachment')->post_status = 'inherit';
  update_post_meta(200 + $index, '_wp_attached_file', '2026/10/' . basename($reference));
}
$page_options = $import->validate_options(['scope' => 'page', 'post_id' => '50', 'policy' => 'replace', 'post_type' => 'post', 'mode' => 'create', 'allow_publish' => 1]);
check($page_options['post_type'] === 'page' && $page_options['mode'] === 'update' && !$page_options['allow_publish'], 'Page scope derives type/mode/status permissions from fixed destination, not input');
rejects(function() use ($import) { $import->validate_options(['scope' => 'page', 'post_id' => 50, 'policy' => 'delete']); }, 'Choose Replace');
rejects(function() use ($import, $page_options) { $import->parse_page('[{"hero":{}}]', $page_options); }, 'one JSON object');
rejects(function() use ($import, $page_options) { $import->parse_page('{"format":"cff-content","items":[]}', $page_options); }, 'bulk import');
rejects(function() use ($import, $page_options) { $import->prepare_page_row(['ID' => 51, 'cff' => []], $page_options); }, 'current page');
$page_rows = $import->parse_page($why_json, $page_options);
$page_plan = $import->prepare_page_row($page_rows[0], $page_options);
check(count($page_plan['mapping']) === 7 && in_array('hero → why_hero', $page_plan['mapping'], true), 'All seven real JSON sections map to current page CFF fields');
check($page_plan['meta']['_cff_why_hero']['image'] === 200 && $page_plan['meta']['_cff_why_work']['items'][4]['image'] === 205, 'Theme-relative image paths resolve uniquely to existing media');
check($page_plan['meta']['_cff_why_hero']['citation'] === $why_data['hero']['citation'], 'Inline HTML preserved by actual rich-field definitions');
check(count($page_plan['meta']['_cff_why_questions']['items']) === 9, 'Actual nine-question repeater imported');
check($page_plan['warnings'] === [], 'Known image matches produce no warnings');
$page_input = ['scope' => 'page', 'post_id' => 50, 'policy' => 'replace'];
$before = serialize([$GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['languages']]);
$job = $import->command('upload', $page_input, $why_json);
$job = $import->command('preview', $page_input + ['job' => $job['token']]);
check($job['total'] === 1 && $job['valid'] === 1 && $before === serialize([$GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['languages']]), 'Exact JSON previews as one existing page without writes');
check($import->command('status', ['scope' => 'page', 'post_id' => 51]) === [], 'One page cannot load another page import');
rejects(function() use ($import, $job) { $import->command('start', ['scope' => 'page', 'post_id' => 51, 'job' => $job['token']]); }, 'another session');
$job = $import->command('start', $page_input + ['job' => $job['token']]);
$job = $import->command('batch', $page_input + ['job' => $job['token']]);
check($job['updated'] === 1 && $job['created'] === 0 && get_post(50)->post_title === 'Page 50' && get_post(50)->post_status === 'publish', 'Page import changes CFF only and cannot create or publish content');
check(!metadata_exists('post', 51, '_cff_why_hero') && pll_get_post_language(51) === 'id', 'Other page/translation remains untouched');
check(get_post_meta(50, '_cff_why_contact', true)['contact_form'] === $why_data['contact']['contact_form'], 'Shortcode quotes preserved');
check(get_option('cff_content_import_payload_7_page_50', null) === null, 'Page payload removed after completion');

$saved = get_post_meta(50, '_cff_why_hero', true); $saved['title'] = 'Keep edited title'; $saved['intro'] = ''; $saved['quote'] = '   ';
update_post_meta(50, '_cff_why_hero', wp_slash($saved));
$saved_questions = get_post_meta(50, '_cff_why_questions', true); $saved_questions['items'][0]['answer'] = 'Keep edited answer';
update_post_meta(50, '_cff_why_questions', wp_slash($saved_questions));
$fill_options = $page_options; $fill_options['policy'] = 'fill_empty';
$page_plan = $import->prepare_page_row($page_rows[0], $fill_options);
check($page_plan['meta']['_cff_why_hero']['title'] === 'Keep edited title' && $page_plan['meta']['_cff_why_hero']['intro'] === $why_data['hero']['intro'] && $page_plan['meta']['_cff_why_hero']['quote'] === $why_data['hero']['quote'], 'Fill empty fills blank and whitespace subfields while keeping edited text');
check(!isset($page_plan['meta']['_cff_why_questions']), 'Fill empty preserves entire existing repeater and section');
$job = $import->command('upload', ['scope' => 'page', 'post_id' => 50, 'policy' => 'fill_empty'], $why_json);
$job = $import->command('preview', $page_input + ['job' => $job['token']]);
$job = $import->command('start', $page_input + ['job' => $job['token']]);
$job = $import->command('batch', $page_input + ['job' => $job['token']]);
check($job['updated'] === 1 && get_post_meta(50, '_cff_why_hero', true)['title'] === 'Keep edited title' && get_post_meta(50, '_cff_why_questions', true)['items'][0]['answer'] === 'Keep edited answer', 'Fill-only write preserves existing text and repeater data');
$page_plan = $import->prepare_page_row($page_rows[0], $fill_options);
check($page_plan['action'] === 'skip', 'Repeated fill-only import has nothing to change');
$page_plan = $import->prepare_page_row($page_rows[0], $page_options);
check($page_plan['meta']['_cff_why_hero']['title'] === $why_data['hero']['title'] && $page_plan['meta']['_cff_why_questions']['items'][0]['answer'] === $why_data['questions']['items'][0]['answer'], 'Replace overwrites supplied text and repeater rows');

$missing_media = $why_data; $missing_media['hero']['image'] = 'images/not-in-library.jpg'; $missing_media['work']['items'][0]['image'] = 'images/not-in-library.jpg';
$page_plan = $import->prepare_page_row($import->parse_page(json_encode($missing_media), $page_options)[0], $page_options);
check(count($page_plan['warnings']) === 2 && $page_plan['meta']['_cff_why_hero']['image'] === 200 && $page_plan['meta']['_cff_why_work']['items'][0]['image'] === 201, 'Missing media warnings preserve existing media in the same field/row position');
$page_options_51 = $page_options; $page_options_51['post_id'] = 51;
$page_plan = $import->prepare_page_row($import->parse_page(json_encode($missing_media), $page_options_51)[0], $page_options_51);
check(!isset($page_plan['meta']['_cff_why_hero']['image']) && !isset($page_plan['meta']['_cff_why_work']['items'][0]['image']), 'Missing media without prior values is excluded while text imports');
update_post_meta(51, '_cff_why_hero', ['title' => 'Keep section without media']);
$unresolved_only = $import->prepare_page_row(['ID' => 51, 'cff' => ['hero' => ['image' => 'images/only-missing-image.jpg']]], $page_options_51);
check($unresolved_only['action'] === 'skip' && $unresolved_only['meta'] === [], 'Unresolved-only group never becomes an implicit clear of existing text');
$job = $import->command('upload', $page_input, json_encode($missing_media));
$job = $import->command('preview', $page_input + ['job' => $job['token']]);
fixture_post(299, 'attachment')->post_status = 'inherit'; update_post_meta(299, '_wp_attached_file', '2026/10/not-in-library.jpg');
$job = $import->command('start', $page_input + ['job' => $job['token']]); $job = $import->command('batch', $page_input + ['job' => $job['token']]);
check($job['failed'] === 1 && strpos($job['rows'][0]['message'], 'media matches changed') !== false, 'New media match after preview requires another review before writing');
rejects(function() use ($import, $page_options) { $import->prepare_page_row(['ID' => 50, 'cff' => ['hero' => [], 'why_hero' => []]], $page_options); }, 'Multiple JSON sections');
rejects(function() use ($import, $page_options) { $import->prepare_page_row(['ID' => 50, 'cff' => ['unknown' => []]], $page_options); }, 'No matching');
$ambiguous = $why_fields; $other_hero = $ambiguous['fields'][0]; $other_hero['name'] = 'other_hero'; $ambiguous['fields'][] = $other_hero;
$plugin->set_group_settings_cache(900, $ambiguous);
rejects(function() use ($import, $page_options) { $import->prepare_page_row(['ID' => 50, 'cff' => ['hero' => []]], $page_options); }, 'Ambiguous');
$plugin->set_group_settings_cache(900, $why_fields);
$GLOBALS['denied'] = ['edit_post:50']; rejects(function() use ($import, $page_input) { $import->command('status', $page_input); }, 'accessible'); $GLOBALS['denied'] = [];
$GLOBALS['options']['cff_content_import_job_7_page_50']['expires'] = time() - 1;
$expired_token = $GLOBALS['options']['cff_content_import_job_7_page_50']['token'];
$import->expire(7, $expired_token, 50);
check(get_option('cff_content_import_job_7_page_50', null) === null, 'Cron expires the correct page-scoped job');

// Numeric zero and false are intentional; missing numeric values are filled.
$plugin->set_group_settings_cache(900, $settings);
update_post_meta(1, '_cff_count', 0); update_post_meta(1, '_cff_enabled', '0');
$zero_options = $import->validate_options(['scope' => 'page', 'post_id' => 1, 'policy' => 'fill_empty']);
$zero_plan = $import->prepare_page_row(['ID' => 1, 'cff' => ['count' => 20, 'enabled' => true]], $zero_options);
check($zero_plan['action'] === 'skip', 'Fill empty does not overwrite number zero or unchecked checkbox');
$zero_plan = $import->prepare_page_row(['ID' => 1, 'cff' => ['group' => []]], array_merge($zero_options, ['policy' => 'replace']));
check($zero_plan['meta']['_cff_group'] === [], 'Explicit empty group replacement clears the group');
echo 'Content import: ' . $GLOBALS['assertions'] . " checks passed.\n";
