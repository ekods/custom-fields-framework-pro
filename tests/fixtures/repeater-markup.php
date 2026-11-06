<?php
// Renders a repeater with the real CFF renderer for tests/repeater-ui.cjs.
// Usage: php tests/fixtures/repeater-markup.php <layout> [row-label-field] [max] [min]
// The WordPress stubs below are the ones tests/runtime-integrity.php uses to load the renderer.
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
function checked($value, $expected = true, $echo = true) { $result = (string) $value === (string) $expected ? 'checked="checked"' : ''; if ($echo) echo $result; return $result; }
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
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; return true; }
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


require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/render.php';
if (!function_exists('wp_rand')) { function wp_rand($min = 0, $max = 0) { return mt_rand((int) $min, (int) $max ?: 99999); } }

$subs = array(
  array('name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => 1, 'key' => 'fld_title'),
  array('name' => 'summary', 'type' => 'textarea', 'label' => 'Summary', 'key' => 'fld_summary'),
);
$rows = array(array('title' => 'Brand Strategy', 'summary' => 'x'), array('title' => '', 'summary' => 'Only a summary'), array('title' => '', 'summary' => ''));
$layout = $argv[1] ?? 'default'; $label = $argv[2] ?? ''; $max = (int) ($argv[3] ?? 0); $min = (int) ($argv[4] ?? 0);
echo '<div class="cff-repeater" data-field="items" data-min="' . $min . '" data-max="' . $max . '" data-layout="' . $layout . '" data-row-label="' . $label . '" data-collapsed-default="0">';
echo '<input type="hidden" class="cff-rep-present" name="cff_values[items][__cff_present]" value="1">';
echo '<div class="cff-rep-rows ui-sortable">';
foreach ($rows as $i => $row) { \CFF\render_repeater_row('items', $subs, $row, $i, 1, null, $layout, $label, false, 'items', array()); }
echo '</div>';
echo '<p class="cff-rep-add-wrap"><button type="button" class="button cff-rep-add">Add row</button></p>';
echo '<template class="cff-rep-template">'; \CFF\render_repeater_row('items', $subs, array(), '__INDEX__', 1, null, $layout, $label, false, 'items', array()); echo '</template>';
echo '</div>';
