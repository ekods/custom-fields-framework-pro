<?php
/** Standalone regression tests; WordPress/Polylang persistence is simulated in memory. */
define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/class-plugin.php';
require_once __DIR__ . '/../includes/class-field-sanitizer.php';
require_once __DIR__ . '/../includes/class-language-clone.php';

class WP_Error {
  private $message;
  public function __construct($code, $message) { $this->message = $message; }
  public function get_error_message() { return $this->message; }
}
class Json_Response extends RuntimeException {
  public $data;
  public $success;
  public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
function __($text, $domain = '') { return $text; }
function apply_filters($name, $value) { return $value; }
function PLL() { return $GLOBALS['polylang']; }
function has_filter($hook, $callback) { return $GLOBALS['sync_hooks'][$hook] ?? false; }
function remove_filter($hook, $callback, $priority) { unset($GLOBALS['sync_hooks'][$hook]); }
function add_filter($hook, $callback, $priority, $args) { $GLOBALS['sync_hooks'][$hook] = $priority; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function esc_url_raw($value) { return $value; }
function get_permalink($id) { return 'https://test.invalid/' . $id . '/'; }
function get_current_user_id() { return 7; }
function current_user_can($cap, $id = 0) { return !in_array($cap . ':' . $id, $GLOBALS['denied'], true); }
function get_post_types($args, $output) {
  $type = (object) ['cap' => (object) ['create_posts' => 'edit_posts', 'publish_posts' => 'publish_posts']];
  return ['post' => $type, 'page' => $type, 'cff_group' => $type, 'attachment' => $type, 'disabled' => $type];
}
function get_post_type_object($type) { return get_post_types([], 'objects')[$type] ?? null; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_posts($args) {
  if ($args['post_type'] === 'cff_group') return [(object) ['ID' => 900]];
  $GLOBALS['last_query'] = $args;
  return array_keys(array_filter($GLOBALS['posts'], function ($post) use ($args) {
    return in_array($post->post_type, $args['post_type'], true) && pll_get_post_language($post->ID) === $args['lang']
      && in_array($post->post_status, $args['post_status'], true);
  }));
}
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function maybe_unserialize($value) {
  if (!is_string($value)) return $value;
  $decoded = @unserialize($value);
  return $decoded !== false || $value === 'b:0;' ? $decoded : $value;
}
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function get_post_meta($id, $key = '', $single = false) {
  $meta = $GLOBALS['meta'][$id] ?? [];
  if ($key === '') return $meta;
  $values = array_map('maybe_unserialize', $meta[$key] ?? []);
  return $single ? ($values[0] ?? '') : $values;
}
function add_post_meta($id, $key, $value) {
  if (($GLOBALS['fail_meta'] ?? '') === $key) return false;
  $GLOBALS['meta'][$id][$key][] = maybe_serialize(wp_unslash($value));
  return 1;
}
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); return true; }
function update_post_meta($id, $key, $value) { delete_post_meta($id, $key); return add_post_meta($id, $key, $value); }
function wp_insert_post($data, $error = false) {
  $data = wp_unslash($data);
  $id = ++$GLOBALS['next_post'];
  $data['ID'] = $id;
  $GLOBALS['posts'][$id] = (object) $data;
  return $id;
}
function wp_update_post($data, $error = false) {
  if (isset($GLOBALS['sync_hooks']['pll_save_post'])) {
    $GLOBALS['sync_calls']++;
    // Model the source overwrite that Polylang core-field synchronization can cause.
    $GLOBALS['posts'][1]->post_parent = 999;
  }
  foreach (wp_unslash($data) as $key => $value) $GLOBALS['posts'][$data['ID']]->$key = $value;
  return $data['ID'];
}
function wp_delete_post($id, $force) { unset($GLOBALS['posts'][$id], $GLOBALS['meta'][$id], $GLOBALS['post_languages'][$id]); }
function get_the_title($id) { return get_post($id)->post_title; }
function get_edit_post_link($id, $context) { return 'https://test.invalid/wp-admin/post.php?post=' . $id; }
function pll_languages_list($args) { return ['id', 'en', 'fr']; }
function pll_is_translated_post_type($type) { return in_array($type, ['post', 'page'], true); }
function pll_get_post_language($id, $field = 'slug') { return $GLOBALS['post_languages'][$id] ?? ''; }
function pll_set_post_language($id, $language) { $GLOBALS['post_languages'][$id] = $language; }
function pll_get_post_translations($id) { return $GLOBALS['post_translations'][$id] ?? [pll_get_post_language($id) => $id]; }
function pll_save_post_translations($translations) { foreach ($translations as $id) $GLOBALS['post_translations'][$id] = $translations; }
function pll_get_post($id, $language) { return pll_get_post_translations($id)[$language] ?? 0; }
function get_object_taxonomies($type) { return ['category', 'post_format', 'language', 'post_translations']; }
function get_taxonomy($taxonomy) { return (object) ['cap' => (object) ['manage_terms' => 'manage_categories', 'assign_terms' => 'assign_terms']]; }
function wp_get_object_terms($id, $taxonomy, $args) { return $GLOBALS['assigned'][$id][$taxonomy] ?? []; }
function wp_set_object_terms($id, $terms, $taxonomy) { $GLOBALS['assigned'][$id][$taxonomy] = $terms; return $terms; }
function get_term($id) { return $GLOBALS['terms'][$id] ?? null; }
function get_term_meta($id) { return $GLOBALS['term_meta'][$id] ?? []; }
function add_term_meta($id, $key, $value) { $GLOBALS['term_meta'][$id][$key][] = maybe_serialize(wp_unslash($value)); return 1; }
function wp_insert_term($name, $taxonomy, $args) {
  $id = ++$GLOBALS['next_term'];
  $GLOBALS['terms'][$id] = (object) (['term_id' => $id, 'name' => $name, 'taxonomy' => $taxonomy] + $args);
  return ['term_id' => $id];
}
function wp_delete_term($id, $taxonomy) { unset($GLOBALS['terms'][$id]); }
function pll_is_translated_taxonomy($taxonomy) { return $taxonomy === 'category'; }
function pll_get_term_language($id, $field = 'slug') { return $GLOBALS['term_languages'][$id] ?? ''; }
function pll_set_term_language($id, $language) { $GLOBALS['term_languages'][$id] = $language; }
function pll_get_term_translations($id) { return $GLOBALS['term_translations'][$id] ?? [pll_get_term_language($id) => $id]; }
function pll_save_term_translations($translations) { foreach ($translations as $id) $GLOBALS['term_translations'][$id] = $translations; }
function pll_get_term($id, $language) { return pll_get_term_translations($id)[$language] ?? 0; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = true) {
  if (isset($GLOBALS['options'][$key])) return false;
  $GLOBALS['options'][$key] = $value;
  return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function wp_generate_uuid4() { return 'test-job-' . ++$GLOBALS['next_job']; }
function check_ajax_referer($action, $key) { if (($_POST[$key] ?? '') !== 'valid') throw new RuntimeException('Invalid nonce'); }
function wp_send_json_success($data) { throw new Json_Response(true, $data); }
function wp_send_json_error($data, $code = 400) { throw new Json_Response(false, $data); }
function ajax($callback, $input) {
  $_POST = $input + ['nonce' => 'valid'];
  try { $callback(); } catch (Json_Response $response) { return $response; }
  throw new RuntimeException('Missing JSON response');
}
function check($condition, $message) {
  if (!$condition) throw new RuntimeException('FAIL: ' . $message);
  $GLOBALS['assertions']++;
}
function fixture_post($id, $language = 'id', $parent = 0) {
  $GLOBALS['posts'][$id] = (object) [
    'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent,
    'post_date' => '2026-10-01 12:30:00', 'post_date_gmt' => '2026-10-01 05:30:00',
    'post_title' => 'Page ' . $id, 'post_name' => 'page-' . $id, 'post_content' => '<!-- wp:html -->C:\\news "quoted"<!-- /wp:html -->',
    'post_content_filtered' => '', 'post_excerpt' => 'Summary', 'menu_order' => 9, 'post_password' => 'secret',
    'comment_status' => 'closed', 'ping_status' => 'closed',
  ];
  pll_set_post_language($id, $language);
}
foreach (['posts' => [], 'meta' => [], 'post_languages' => [], 'post_translations' => [],
  'terms' => [], 'term_meta' => [], 'term_languages' => [], 'term_translations' => [], 'assigned' => [],
  'options' => [], 'transients' => [], 'denied' => [], 'next_post' => 1000, 'next_term' => 100, 'next_job' => 0, 'assertions' => 0] as $key => $value) {
  $GLOBALS[$key] = $value;
}
$GLOBALS['polylang'] = (object) ['sync' => (object) []];
$GLOBALS['sync_hooks'] = ['pll_save_post' => 10, 'wp_insert_post_data' => 10, 'wp_insert_post_parent' => 10];
$GLOBALS['sync_calls'] = 0;

$plugin = (new ReflectionClass(\CFF\Plugin::class))->newInstanceWithoutConstructor();
$clone = new \CFF\Language_Clone($plugin);
$all = array_keys($clone->options());
$selection = ['source' => 'id', 'target' => 'en', 'post_types' => ['page'], 'parts' => $all];
check($clone->available(), 'Detect Polylang APIs');
check(is_wp_error($clone->validate_selection(array_merge($selection, ['target' => 'id']))), 'Reject identical languages');
check(is_wp_error($clone->validate_selection(array_merge($selection, ['target' => 'unknown']))), 'Reject unknown language');
check(is_wp_error($clone->validate_selection(array_merge($selection, ['post_types' => ['cff_group', 'attachment', 'disabled']]))), 'Reject internal/untranslated post types');
check(is_wp_error($clone->validate_selection(array_merge($selection, ['parts' => []]))), 'Require clone options');
fixture_post(1, 'id', 2); fixture_post(2); fixture_post(3); fixture_post(33, 'en'); fixture_post(43, 'fr');
pll_save_post_translations(['id' => 3, 'en' => 33, 'fr' => 43]);
$fields = [
  ['name' => 'group', 'type' => 'group', 'sub_fields' => [['name' => 'related', 'type' => 'relational', 'relational_multiple' => true]]],
  ['name' => 'rows', 'type' => 'repeater', 'sub_fields' => [['name' => 'related', 'type' => 'relational']]],
  ['name' => 'blocks', 'type' => 'flexible', 'layouts' => [['name' => 'hero', 'sub_fields' => [['name' => 'related', 'type' => 'relational']]]]],
  ['name' => 'link', 'type' => 'link'],
  ['name' => 'category', 'type' => 'relational', 'relational_type' => 'taxonomy'],
];
update_post_meta(900, '_cff_settings', wp_slash(['fields' => $fields, 'location' => [[['param' => 'page', 'operator' => '==', 'value' => 1]]]]));
update_post_meta(1, '_cff_group', wp_slash(['related' => [2, 3, 99999], 'unknown' => 'Preserve C:\\images "verbatim"']));
update_post_meta(1, '_cff_rows', wp_slash([['__cff_row_id' => 'row_123', 'related' => 2, 'caption' => 'Keep']]));
update_post_meta(1, '_cff_blocks', wp_slash([['layout' => 'hero', '__cff_row_id' => 'row_456', 'fields' => ['related' => 3, 'image' => 55]]]));
update_post_meta(1, '_cff_link', wp_slash(['mode' => 'internal', 'internal_id' => 2, 'parameter' => 'ref=clone', 'hash' => 'top', 'title' => 'Go']));
update_post_meta(1, '_cff_category', 10);
update_post_meta(1, '_cff_hidden_sections', ['hero' => 1]);
update_post_meta(1, '_cff_hidden_sections_local', 'id');
update_post_meta(1, '_cff_group_field_order_900', ['rows', 'group']);
update_post_meta(1, '_thumbnail_id', 55);
update_post_meta(1, '_wp_page_template', 'custom.php');
update_post_meta(1, '_edit_lock', 'locked');
update_post_meta(1, '_pll_language', 'id');
add_post_meta(1, 'multi', wp_slash('C:\\a "one"'));
add_post_meta(1, 'multi', wp_slash('C:\\b "two"'));
$GLOBALS['terms'][10] = (object) ['term_id' => 10, 'name' => 'Child', 'taxonomy' => 'category', 'slug' => 'child', 'description' => 'Description', 'parent' => 11];
$GLOBALS['terms'][11] = (object) ['term_id' => 11, 'name' => 'Parent', 'taxonomy' => 'category', 'slug' => 'parent', 'description' => '', 'parent' => 0];
$GLOBALS['terms'][15] = (object) ['term_id' => 15, 'taxonomy' => 'post_format'];
$GLOBALS['term_languages'] = [10 => 'id', 11 => 'id'];
$GLOBALS['term_meta'][10] = ['cffp_term_description' => ['C:\\category "quoted"']];
$GLOBALS['assigned'][1] = ['category' => [10], 'post_format' => [15], 'language' => [99]];
$before = serialize([$GLOBALS['posts'][1], $GLOBALS['meta'][1], $GLOBALS['posts'][33], $GLOBALS['assigned'][1]]);

$preview = ajax([$clone, 'ajax_prepare'], $selection);
check($preview->success && $preview->data['total'] === 3 && $preview->data['existing'] === 1, 'Preview all source content and existing translations');
check($GLOBALS['last_query']['suppress_filters'] === false && $GLOBALS['last_query']['lang'] === 'id', 'Query uses explicit source language with filters enabled');
check(count($GLOBALS['posts']) === 5, 'Preview does not create posts');
$token = $preview->data['job'];
$lock = 'cff_clone_lock_' . md5('id:en');
add_option($lock, time() + 300);
$locked = ajax([$clone, 'ajax_batch'], ['job' => $token]);
check(!$locked->success, 'Concurrent requests cannot clone the same language pair');
delete_option($lock);
do {
  $response = ajax([$clone, 'ajax_batch'], ['job' => $token]);
  check($response->success, 'Batch succeeded');
} while ($response->data['phase'] !== 'done');
$target = pll_get_post(1, 'en');
$parent = pll_get_post(2, 'en');
check($response->data['created'] === 2 && $response->data['skipped'] === 1 && !$response->data['failed'], 'Correct final counts');
check(get_post($target)->post_status === 'publish' && get_post($target)->post_author === 7, 'Published sources create published translations owned by current user');
check(get_post($target)->post_date === get_post(1)->post_date && get_post($target)->post_date_gmt === get_post(1)->post_date_gmt, 'Publication dates preserved');
check(get_post($target)->post_content === get_post(1)->post_content, 'Block markup, quotes and backslashes survive');
check(get_post($target)->post_parent === $parent && get_post($target)->menu_order === 9, 'Parent created later is remapped in second phase');
check(get_post_meta($target, '_cff_group', true) === ['related' => [$parent, 33, 99999], 'unknown' => 'Preserve C:\\images "verbatim"'], 'Nested relations map while unmatched references and unknown fields remain intact');
check(get_post_meta($target, '_cff_rows', true)[0] === ['__cff_row_id' => 'row_123', 'related' => $parent, 'caption' => 'Keep'], 'Repeater row IDs and contents survive');
check(get_post_meta($target, '_cff_blocks', true)[0]['fields'] === ['related' => 33, 'image' => 55], 'Flexible layouts remap relations and preserve media');
check(get_post_meta($target, '_cff_link', true)['url'] === get_permalink($parent) . '?ref=clone#top', 'Internal link resolves target translation and keeps query/hash');
check(get_post_meta($target, '_cff_hidden_sections_local', true) === 'en', 'Hidden-section marker uses target language');
check(get_post_meta($target, '_cff_group_field_order_900', true) === ['rows', 'group'], 'Field order copied');
check(get_post_meta($target, '_thumbnail_id', true) === 55, 'Featured image reused');
check(get_post_meta($target, 'multi', false) === get_post_meta(1, 'multi', false), 'Multiple metadata rows preserve quotes and backslashes');
check(get_post_meta($target, '_wp_page_template', true) === 'custom.php', 'Page template copied');
check(get_post_meta($target, '_edit_lock', true) === '' && get_post_meta($target, '_pll_language', true) === '', 'Editor locks and internal language metadata excluded');
$category = pll_get_term(10, 'en');
check($GLOBALS['assigned'][$target]['category'] === [$category], 'Translated category assigned');
check(get_term($category)->parent === pll_get_term(11, 'en'), 'Taxonomy parent translated');
check($GLOBALS['term_meta'][$category] === $GLOBALS['term_meta'][10], 'Term fields copied verbatim');
check($GLOBALS['assigned'][$target]['post_format'] === [15] && !isset($GLOBALS['assigned'][$target]['language']), 'Shared taxonomy retained; language taxonomy excluded');
check(get_post_meta($target, '_cff_category', true) === $category, 'Taxonomy CFF relation remapped');
check(pll_get_post_translations(3) === ['id' => 3, 'en' => 33, 'fr' => 43], 'Existing multilingual group unchanged');
check($before === serialize([$GLOBALS['posts'][1], $GLOBALS['meta'][1], $GLOBALS['posts'][33], $GLOBALS['assigned'][1]]), 'Source and existing target data untouched');
check($GLOBALS['sync_calls'] === 0 && count($GLOBALS['sync_hooks']) === 3, 'Core-field sync is suspended for hierarchy updates and restored afterward');
check(isset($plugin->get_field_definitions_for_post(get_post($target))['group']), 'Page-specific field groups appear on cloned translation');
check(!$plugin->rest_match_location(get_post($target), [[['param' => 'page', 'operator' => '!=', 'value' => 1]]]), 'Negative page rules also apply to translations');
$again = ajax([$clone, 'ajax_batch'], ['job' => $token]);
check($again->success && $again->data['created'] === 2 && count($GLOBALS['posts']) === 7, 'Repeated completed request is idempotent');

// Every checkbox controls its own data independently.
foreach ($all as $part) {
  fixture_post(200 + array_search($part, $all, true));
  $id = 200 + array_search($part, $all, true);
  foreach ($GLOBALS['meta'][1] as $key => $values) $GLOBALS['meta'][$id][$key] = $values;
  $GLOBALS['assigned'][$id] = $GLOBALS['assigned'][1];
  $job = $clone->validate_selection(array_merge($selection, ['parts' => [$part]]));
  $copy = $clone->clone_post($id, $job);
  check(is_int($copy) && $copy > 0, 'Single checkbox creates translation: ' . $part);
  check((get_post_meta($copy, '_cff_rows', true) !== '') === ($part === 'cff'), 'CFF checkbox isolation: ' . $part);
  check((get_post_meta($copy, '_wp_page_template', true) !== '') === ($part === 'meta'), 'Meta checkbox isolation: ' . $part);
  check((get_post_meta($copy, '_thumbnail_id', true) !== '') === ($part === 'thumbnail'), 'Thumbnail checkbox isolation: ' . $part);
  check((get_post($copy)->post_content !== '') === ($part === 'content'), 'Content checkbox isolation: ' . $part);
  check((get_post($copy)->post_excerpt !== '') === ($part === 'excerpt'), 'Excerpt checkbox isolation: ' . $part);
  check((get_post($copy)->post_title === get_post($id)->post_title) === ($part === 'title'), 'Title checkbox isolation: ' . $part);
  check(!empty($GLOBALS['assigned'][$copy]) === ($part === 'terms'), 'Taxonomy checkbox isolation: ' . $part);
}
fixture_post(400);
$GLOBALS['denied'][] = 'edit_post:400';
check(is_wp_error($clone->clone_post(400, $clone->validate_selection($selection))), 'Per-post edit permission enforced');
$GLOBALS['denied'] = ['manage_options:0'];
check(!ajax([$clone, 'ajax_prepare'], $selection)->success, 'Admin capability enforced');
$GLOBALS['denied'] = [];
try { ajax([$clone, 'ajax_prepare'], $selection + ['nonce' => 'invalid']); check(false, 'Nonce must fail'); }
catch (RuntimeException $error) { check($error->getMessage() === 'Invalid nonce', 'Nonce enforced'); }
fixture_post(500);
update_post_meta(500, 'failure', 'test');
$GLOBALS['fail_meta'] = 'failure';
$count = count($GLOBALS['posts']);
check(is_wp_error($clone->clone_post(500, $clone->validate_selection($selection))), 'Copy failure is reported');
check(count($GLOBALS['posts']) === $count && !pll_get_post(500, 'en'), 'Failed clone is cleaned up');
check(!ajax([$clone, 'ajax_batch'], ['job' => 'expired'])->success, 'Expired/foreign jobs rejected');
$GLOBALS['fail_meta'] = '';
$batch_ids = range(600, 612);
foreach ($batch_ids as $index => $id) {
  fixture_post($id);
  $GLOBALS['posts'][$id]->post_status = ['publish', 'draft', 'pending', 'private', 'future'][$index % 5];
  if ($GLOBALS['posts'][$id]->post_status === 'future') {
    $GLOBALS['posts'][$id]->post_date = '2099-11-01 12:30:00';
    $GLOBALS['posts'][$id]->post_date_gmt = '2099-11-01 05:30:00';
  }
}
$GLOBALS['denied'][] = 'edit_post:601';
$job = $clone->validate_selection(array_merge($selection, ['parts' => ['title']])) + [
  'ids' => $batch_ids, 'cursor' => 0, 'phase' => 'clone', 'created' => [], 'skipped' => 0, 'failed' => 0, 'errors' => [],
];
$rounds = 0;
do {
  $clone->process_batch($job);
  $rounds++;
  check($rounds < 10, 'Batch cursor makes forward progress');
} while ($job['phase'] !== 'done');
check(count($job['created']) === 12 && $job['failed'] === 1 && count($job['errors']) === 1, 'Multiple batches continue after a failed item across all supported statuses');
check($rounds === 6 && $job['cursor'] === 0, 'Clone and finalization finish across multiple batch boundaries');
foreach ($job['created'] as $source_id => $copy_id) {
  check(get_post($copy_id)->post_status === get_post($source_id)->post_status, 'Source status preserved: ' . get_post($source_id)->post_status);
  check(get_post($copy_id)->post_date === get_post($source_id)->post_date && get_post($copy_id)->post_date_gmt === get_post($source_id)->post_date_gmt, 'Source dates preserved through finalization: ' . $source_id);
}
foreach (['publish', 'private', 'future', 'draft', 'pending'] as $index => $status) {
  $id = 700 + $index;
  fixture_post($id);
  $GLOBALS['posts'][$id]->post_status = $status;
  $GLOBALS['denied'] = ['publish_posts:0'];
  $count = count($GLOBALS['posts']);
  $copy = $clone->clone_post($id, $clone->validate_selection($selection));
  if (in_array($status, ['publish', 'private', 'future'], true)) {
    check(is_wp_error($copy) && count($GLOBALS['posts']) === $count, 'Publishing permission required for ' . $status);
  } else {
    check(is_int($copy) && get_post($copy)->post_status === $status, 'Publishing permission not required for ' . $status);
  }
}
$GLOBALS['denied'] = [];
// Existing translation pages may already have titles while their content/CFF is empty.
$fill_selection = $selection + ['fill_existing' => '1'];
fixture_post(800, 'id', 2);
fixture_post(801, 'en');
fixture_post(802, 'fr');
pll_save_post_translations(['id' => 800, 'en' => 801, 'fr' => 802]);
$GLOBALS['posts'][801]->post_title = 'Judul terjemahan';
$GLOBALS['posts'][801]->post_name = 'translated-page';
$GLOBALS['posts'][801]->post_content = " \n ";
$GLOBALS['posts'][801]->post_excerpt = '';
$GLOBALS['posts'][801]->post_status = 'draft';
$GLOBALS['posts'][801]->post_date = '2026-10-20 10:00:00';
$GLOBALS['posts'][801]->post_date_gmt = '2026-10-20 03:00:00';
$GLOBALS['posts'][801]->menu_order = 0;
update_post_meta(800, '_cff_rows', wp_slash([['__cff_row_id' => 'source_row', 'related' => 2, 'caption' => 'C:\\fill "verbatim"']]));
update_post_meta(800, '_cff_group', ['related' => [2]]);
update_post_meta(800, '_cff_hidden_sections', ['hero' => 1]);
update_post_meta(800, '_thumbnail_id', 55);
update_post_meta(801, '_thumbnail_id', 0);
update_post_meta(800, '_wp_page_template', 'custom.php');
update_post_meta(800, 'zero', 'replace?');
update_post_meta(800, 'disabled', 'replace?');
update_post_meta(800, 'empty', 'fill');
update_post_meta(801, '_cff_rows', []);
update_post_meta(801, '_cff_group', ['related' => [3], 'caption' => 'Existing translated group']);
update_post_meta(801, 'zero', '0');
update_post_meta(801, 'disabled', false);
update_post_meta(801, 'empty', '');
update_post_meta(900, '_cff_settings', wp_slash(['fields' => $fields, 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]]]));
$plugin->set_group_settings_cache(900, get_post_meta(900, '_cff_settings', true));
$GLOBALS['assigned'][800] = ['category' => [10], 'post_format' => [15]];
$GLOBALS['assigned'][801] = ['post_format' => [77]];
$source_before = serialize([$GLOBALS['posts'][800], $GLOBALS['meta'][800], $GLOBALS['posts'][802]]);
$target_before = clone get_post(801);
$count_before = count($GLOBALS['posts']);
$snapshot = $GLOBALS['meta'][801];
$fill_preview = ajax([$clone, 'ajax_prepare'], $fill_selection);
check($fill_preview->success && $fill_preview->data['fillable'] > 0, 'Preview counts existing translations with empty selected data');
check($snapshot === $GLOBALS['meta'][801] && count($GLOBALS['posts']) === $count_before, 'Fill preview does not change existing pages');
$job = $clone->validate_selection($fill_selection) + [
  'ids' => [800], 'cursor' => 0, 'phase' => 'clone', 'created' => [], 'updated' => [], 'updated_details' => [], 'skipped' => 0, 'failed' => 0, 'errors' => [],
];
do { $clone->process_batch($job); } while ($job['phase'] !== 'done');
check(!$job['failed'] && $job['created'] === [] && $job['updated'] === [800 => 801], 'Existing empty page is counted as filled, not created');
set_transient('cff_language_clone_7_' . $fill_preview->data['job'], $job, \CFF\Language_Clone::JOB_TTL);
$fill_summary = ajax([$clone, 'ajax_batch'], ['job' => $fill_preview->data['job']]);
check($fill_summary->success && $fill_summary->data['updated'] === 1 && $fill_summary->data['created'] === 0 && $fill_summary->data['progress'] == 100, 'AJAX summary reports filled pages separately from newly created pages');
check(count($GLOBALS['posts']) === $count_before && pll_get_post(800, 'en') === 801, 'Existing page ID and translation link reused without duplication');
check(get_post(801)->post_content === get_post(800)->post_content && get_post(801)->post_excerpt === 'Summary', 'Empty body/excerpt filled verbatim');
check(get_post(801)->post_title === $target_before->post_title && get_post(801)->post_name === $target_before->post_name, 'Existing translated title and slug preserved');
check(get_post(801)->post_status === 'draft' && get_post(801)->post_date === $target_before->post_date && get_post(801)->post_date_gmt === $target_before->post_date_gmt, 'Existing publication status and dates preserved');
check(get_post_meta(801, '_cff_rows', true)[0] === ['__cff_row_id' => 'source_row', 'related' => $parent, 'caption' => 'C:\\fill "verbatim"'], 'Empty repeater filled and copied references remapped');
check(get_post_meta(801, '_cff_group', true) === ['related' => [3], 'caption' => 'Existing translated group'], 'Existing filled CFF group and its references untouched during finalization');
check(get_post_meta(801, '_cff_hidden_sections_local', true) === 'en', 'Filled hidden-section data receives target language marker');
check(get_post_meta(801, '_thumbnail_id', true) === 55 && get_post_meta(801, '_wp_page_template', true) === 'custom.php', 'Missing featured image and template filled');
check(get_post_meta(801, 'zero', true) === '0' && get_post_meta(801, 'disabled', true) === false, 'Zero and false values preserved');
check(get_post_meta(801, 'empty', true) === 'fill', 'Empty stored meta filled');
check($GLOBALS['assigned'][801]['category'] === [$category] && $GLOBALS['assigned'][801]['post_format'] === [77], 'Empty taxonomy filled and existing taxonomy preserved');
check(get_post(801)->post_parent === $parent && get_post(801)->menu_order === 9, 'Empty hierarchy filled');
check($source_before === serialize([$GLOBALS['posts'][800], $GLOBALS['meta'][800], $GLOBALS['posts'][802]]), 'Source and third language preserved when filling');
check($GLOBALS['sync_calls'] === 0 && count($GLOBALS['sync_hooks']) === 3, 'Existing core updates suspend and restore Polylang core-field sync');
check($clone->clone_post(800, $clone->validate_selection($fill_selection)) === 0, 'Repeated fill is a no-op after all selected data is filled');

fixture_post(810); fixture_post(811, 'en');
pll_save_post_translations(['id' => 810, 'en' => 811]);
$GLOBALS['posts'][811]->post_content = '';
update_post_meta(810, '_cff_rows', [['caption' => 'Selected source']]);
$fill_content_only = array_merge($fill_selection, ['parts' => ['content']]);
$result = $clone->clone_post(810, $clone->validate_selection($fill_content_only));
check(is_array($result) && get_post(811)->post_content === get_post(810)->post_content && get_post_meta(811, '_cff_rows', true) === '', 'Existing fill respects selected data checkboxes');
$GLOBALS['posts'][811]->post_content = '';
check($clone->clone_post(810, $clone->validate_selection($selection)) === 0 && get_post(811)->post_content === '', 'Unchecking fill-existing retains explicit skip mode');
$GLOBALS['denied'] = ['edit_post:811'];
check(is_wp_error($clone->clone_post(810, $clone->validate_selection($fill_content_only))) && get_post(811)->post_content === '', 'Existing target edit permission enforced');
$GLOBALS['denied'] = [];
$GLOBALS['posts'][811]->post_status = 'trash';
check($clone->clone_post(810, $clone->validate_selection($fill_content_only)) === 0, 'Trashed translations are not filled or restored');

fixture_post(820); fixture_post(821, 'en');
pll_save_post_translations(['id' => 820, 'en' => 821]);
$GLOBALS['posts'][821]->post_content = '';
update_post_meta(820, 'first', 'Copied first');
update_post_meta(820, 'broken', 'Fail second');
update_post_meta(821, 'first', '');
update_post_meta(821, 'keep', 'Protected');
$existing_before = [clone get_post(821), $GLOBALS['meta'][821]];
$GLOBALS['fail_meta'] = 'broken';
$count_before = count($GLOBALS['posts']);
check(is_wp_error($clone->clone_post(820, $clone->validate_selection($fill_selection))), 'Failed existing fill is reported');
check(count($GLOBALS['posts']) === $count_before && pll_get_post(820, 'en') === 821 && $existing_before == [get_post(821), $GLOBALS['meta'][821]], 'Failure restores touched empty meta without deleting existing page or changing its core content');
$GLOBALS['fail_meta'] = '';
fixture_post(830); fixture_post(831, 'en');
pll_save_post_translations(['id' => 830, 'en' => 831]);
update_post_meta(830, '_cff_rows', [['__cff_row_id' => 'real_row', 'related' => 2]]);
update_post_meta(831, '_cff_rows', [['__cff_row_id' => 'empty_row', 'related' => 0]]);
update_post_meta(830, '_cff_blocks', [['layout' => 'hero', '__cff_row_id' => 'real_block', 'fields' => ['related' => 2]]]);
update_post_meta(831, '_cff_blocks', [['layout' => 'hero', '__cff_row_id' => 'empty_block', 'fields' => ['related' => 0]]]);
$result = $clone->clone_post(830, $clone->validate_selection(array_merge($fill_selection, ['parts' => ['cff']])));
check(is_array($result) && get_post_meta(831, '_cff_rows', true) === get_post_meta(830, '_cff_rows', true), 'Repeater skeleton with only row IDs and empty relation is filled');
check(get_post_meta(831, '_cff_blocks', true) === get_post_meta(830, '_cff_blocks', true), 'Empty flexible layout skeleton is filled despite its layout name');
echo 'Language Clone: ' . $GLOBALS['assertions'] . " assertions passed.\n";
