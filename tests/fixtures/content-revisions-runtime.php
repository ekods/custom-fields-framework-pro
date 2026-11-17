<?php
/** Real WordPress metadata/hooks over an isolated in-memory SQLite table. No site database is loaded. */
$core = getenv('WP_ADMIN_TEST_ROOT');
if (!$core || !is_file($core . '/wp-includes/meta.php')) throw new RuntimeException('Set WP_ADMIN_TEST_ROOT to WordPress source.');
define('ABSPATH', rtrim($core, '/') . '/');
define('WPINC', 'wp-includes');
define('ARRAY_A', 'ARRAY_A');
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/meta.php';
require ABSPATH . WPINC . '/cache.php';
function is_multisite() { return false; }
function wp_suspend_cache_addition() { return false; }
wp_cache_init();
error_reporting(E_ALL);
set_error_handler(function($level, $message, $file, $line) {
  if (!(error_reporting() & $level)) return false;
  throw new ErrorException($message, 0, $level, $file, $line);
});

class Revision_Test_DB {
  public $postmeta = 'postmeta';
  public $insert_id = 0;
  public $fail_key = null;
  private $db;
  public function __construct() {
    $this->db = new PDO(getenv('CFF_REVISION_TEST_DSN') ?: 'sqlite::memory:');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->exec('CREATE TABLE IF NOT EXISTS postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT)');
  }
  public function prepare($sql, ...$args) {
    if (is_array($args[0] ?? null)) $args = $args[0];
    return preg_replace_callback('/%[sd]/', function($match) use (&$args) {
      $value = array_shift($args);
      return $match[0] === '%d' ? (string) (int) $value : $this->db->quote((string) $value);
    }, $sql);
  }
  public function get_col($sql) { return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN); }
  public function get_var($sql) { return $this->db->query($sql)->fetchColumn(); }
  public function get_results($sql, $format) { return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
  public function query($sql) { return $this->db->exec($sql); }
  public function insert($table, $data) {
    if ($this->fail_key === $data['meta_key']) { $this->fail_key = null; return false; }
    $stmt = $this->db->prepare('INSERT INTO postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)');
    $stmt->execute(array_values($data));
    $this->insert_id = (int) $this->db->lastInsertId();
    return $stmt->rowCount();
  }
  public function update($table, $data, $where) {
    if ($this->fail_key === $where['meta_key']) { $this->fail_key = null; return false; }
    $conditions = implode(' AND ', array_map(function($key) { return "$key = ?"; }, array_keys($where)));
    $stmt = $this->db->prepare('UPDATE postmeta SET meta_value = ? WHERE ' . $conditions);
    $stmt->execute(array_merge(array_values($data), array_values($where)));
    return $stmt->rowCount();
  }
}
$wpdb = new Revision_Test_DB();
$GLOBALS['fixture_posts'] = [];
$GLOBALS['fixture_denied'] = [];
$GLOBALS['fixture_options'] = [];
$GLOBALS['fixture_locks'] = [];
$GLOBALS['fixture_user_id'] = 7;
$GLOBALS['fixture_missing_users'] = [];
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function absint($value) { return abs((int) $value); }
function wp_is_numeric_array($value) { return is_array($value) && !array_filter(array_keys($value), 'is_string'); }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function maybe_unserialize($value) {
  if (!is_string($value) || !preg_match('/^(a|O|s|i|b|d|N)[:;]/', $value)) return $value;
  $result = @unserialize($value);
  return $result !== false || $value === 'b:0;' ? $result : $value;
}
function get_post($id) { return $GLOBALS['fixture_posts'][$id] ?? null; }
function get_posts($args) { return []; }
function get_post_type($id) { return get_post($id)->post_type ?? false; }
function get_post_type_object($type) { return (object) ['show_ui' => true]; }
function current_user_can($cap, $id = 0) { return !in_array((int) $id, $GLOBALS['fixture_denied'], true); }
function get_current_user_id() { return $GLOBALS['fixture_user_id']; }
function get_post_meta($id, $key = '', $single = false) { return get_metadata('post', $id, $key, $single); }
function add_post_meta($id, $key, $value) { return add_metadata('post', $id, $key, $value); }
function update_post_meta($id, $key, $value) { return update_metadata('post', $id, $key, $value); }
function delete_post_meta($id, $key, $value = '') { return delete_metadata('post', $id, $key, $value); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_check_post_lock($id) { return $GLOBALS['fixture_locks'][$id] ?? false; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-' . $action; }
function wp_create_nonce($action) { return 'valid-' . $action; }
function get_userdata($id) { return !$id || in_array($id, $GLOBALS['fixture_missing_users'], true) ? false : (object) ['display_name' => 'Editor ' . $id]; }
function get_date_from_gmt($value, $format) { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format($format); }
function human_time_diff($from, $to) { return max(1, (int) (($to - $from) / 60)) . ' minutes'; }
function get_avatar_url($id, $args = []) { return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="#ccc"/><circle cx="20" cy="14" r="7" fill="white"/><path d="M6 40v-4a14 14 0 0128 0v4" fill="white"/></svg>'); }
function admin_url($path) { return '/' . $path; }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function get_edit_post_link($id, $context = '') { return '/?editor=1&post_id=' . $id; }
function add_submenu_page(...$args) { $GLOBALS['fixture_pages'][] = $args; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException($message, $args['response'] ?? 500); }
function get_option($key, $default = false) { return $GLOBALS['fixture_options'][$key] ?? $default; }
function add_meta_box(...$args) { $GLOBALS['fixture_boxes'][] = $args; }
class Revision_Test_Response extends RuntimeException {
  public $payload;
  public $status;
  public function __construct($success, $data, $status) { $this->payload = ['success' => $success, 'data' => $data]; $this->status = $status; }
}
function wp_send_json_success($data, $status = 200) { throw new Revision_Test_Response(true, $data, $status); }
function wp_send_json_error($data, $status = 200) { throw new Revision_Test_Response(false, $data, $status); }
require __DIR__ . '/../../includes/class-plugin.php';
require __DIR__ . '/../../includes/class-content-revisions.php';
require __DIR__ . '/../../includes/class-language-clone.php';
function revision_fixture_post($id, $type = 'page') {
  $GLOBALS['fixture_posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Content ' . $id];
}
$plugin = (new ReflectionClass(CFF\Plugin::class))->newInstanceWithoutConstructor();
$revisions = new CFF\Content_Revisions($plugin);

