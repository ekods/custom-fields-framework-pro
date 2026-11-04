<?php
/** WordPress list-table environment for the existing isolated visibility fixture. */
if (PHP_SAPI !== 'cli') exit;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=cff-post-types';
function _e($text) { echo $text; }
function _x($text, $context) { return $text; }
function _n($one, $many, $count) { return $count === 1 ? $one : $many; }
function esc_attr_e($text) { echo esc_attr($text); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function absint($value) { return abs((int) $value); }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, $args); }
function apply_filters($hook, $value, ...$args) { return $value; }
function wp_doing_ajax() { return true; }
function wp_removable_query_args() { return []; }
function set_url_scheme($url) { return $url; }
function remove_query_arg($keys, $url = null) { return add_query_arg(array_fill_keys((array) $keys, false), $url); }
function get_user_setting($key, $default = '') { return $default; }
function get_column_headers($screen) { return []; }
function get_hidden_columns($screen) { return []; }
function number_format_i18n($value) { return number_format($value); }
function selected($a, $b, $echo = true) { $html = $a === $b ? 'selected' : ''; if ($echo) echo $html; return $html; }
function _admin_search_query() { echo esc_attr($_REQUEST['s'] ?? ''); }
function submit_button($text, $type = 'primary', $name = 'submit', $wrap = true, $attrs = []) { echo '<button>' . esc_html($text) . '</button>'; }
function get_post_type_object($type) { return (object) ['labels' => (object) ['name' => $type]]; }
class Visibility_Table_Screen {
  public $id = 'visibility-fixture';
  public $base = 'admin';
  public function render_screen_reader_content($kind) {}
}
function convert_to_screen($screen) { return new Visibility_Table_Screen(); }
