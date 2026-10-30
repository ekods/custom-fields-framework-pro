<?php
/** Isolated dropdown label regressions; no site database or HTTP requests. */
define('ABSPATH', __DIR__ . '/');
error_reporting(E_ALL);
set_error_handler(function($level, $message) { if (error_reporting() & $level) throw new RuntimeException($message); return false; });
$checks = 0;
$label = 'F&amp;B Branding';
$allowed = true;
$valid_nonce = true;
class LabelJsonResponse extends Exception {
  public $data;
  public $status;
  public function __construct($data, $status) { parent::__construct('fixture'); $this->data = $data; $this->status = $status; }
}
function check_label($condition, $message) { if (!$condition) throw new RuntimeException($message); $GLOBALS['checks']++; }
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return $value; }
function check_ajax_referer($action, $field) { if (!$GLOBALS['valid_nonce']) throw new LabelJsonResponse([], 403); }
function current_user_can($capability) { return $GLOBALS['allowed']; }
function wp_send_json_error($data, $status = 200) { throw new LabelJsonResponse($data, $status); }
function wp_send_json_success($data) { throw new LabelJsonResponse($data, 200); }
function taxonomy_exists($taxonomy) { return true; }
function post_type_exists($post_type) { return true; }
function get_post_type_object($post_type) { return (object) ['name' => $post_type]; }
function is_wp_error($value) { return false; }
function get_post($id) { return (object) ['ID' => (int) $id, 'post_title' => $GLOBALS['label']]; }
function get_term($id, $taxonomy = '') { return (object) ['term_id' => (int) $id, 'name' => $GLOBALS['label']]; }
function get_userdata($id) { return (object) ['ID' => (int) $id, 'display_name' => $GLOBALS['label']]; }
function get_posts($args) { return [get_post(7)]; }
function get_terms($args) { return [get_term(7)]; }
function get_users($args) { return [get_userdata(7)]; }
require __DIR__ . '/../includes/class-ajax-controller.php';
require __DIR__ . '/../includes/render.php';

$controller = new CFF\Ajax_Controller();
function ajax_label($type) {
  $_POST = ['relational_type' => $type, 'relational_subtype' => $type === 'taxonomy' ? 'service' : 'capability', 'q' => '', 'page' => 1];
  try { $GLOBALS['controller']->ajax_search_relational(); }
  catch (LabelJsonResponse $response) { return $response; }
  throw new RuntimeException('AJAX failed to respond');
}
foreach (['post', 'page', 'post_and_page', 'post_type', 'taxonomy', 'user'] as $type) {
  foreach (['F&amp;B Branding' => 'F&B Branding', 'F&#038;B Branding' => 'F&B Branding',
    'F&#x26;B Branding' => 'F&B Branding', 'Food & Beverage' => 'Food & Beverage',
    'Caf&eacute; &quot;Brand&quot; &#8217;' => 'Café "Brand" ’'] as $encoded => $expected) {
    $GLOBALS['label'] = $encoded;
    $response = ajax_label($type);
    check_label($response->status === 200 && $response->data['results'][0] === ['id' => 7, 'text' => $expected], 'AJAX label/ID mismatch: ' . $type);
    check_label($response->data['pagination'] === ['more' => false], 'Pagination changed');
    foreach ([['select', false], ['select', true], ['checkbox', true], ['radio', false], ['fallback', false]] as $mode) {
      ob_start();
      CFF\render_relational_input('service', $type, $type === 'taxonomy' ? 'service' : 'capability', $mode[0], $mode[1] ? [7] : 7, $mode[1], '');
      $html = ob_get_clean();
      $document = new DOMDocument();
      $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
      check_label(strpos($document->textContent, $expected) !== false && strpos($html, '&amp;amp;') === false, 'Saved label double escaped: ' . $type . '/' . $mode[0]);
      check_label(strpos($html, 'value="7"') !== false, 'Stored relationship ID changed');
    }
  }
}
// Decoding never turns a stored label into executable HTML in server-rendered controls.
$GLOBALS['label'] = '&lt;img src=x onerror=alert(1)&gt; &amp; &lt;script&gt;alert(1)&lt;/script&gt;';
foreach ([['select', false], ['select', true], ['checkbox', true], ['radio', false], ['fallback', false]] as $mode) {
  ob_start(); CFF\render_relational_input('service', 'post', '', $mode[0], $mode[1] ? [7] : 7, $mode[1], ''); $html = ob_get_clean();
  check_label(strpos($html, '<img') === false && strpos($html, '<script') === false && strpos($html, '&lt;img') !== false, 'Decoded label became HTML');
}
$allowed = false; check_label(ajax_label('post')->status === 403, 'Permission guard changed');
$allowed = true; $valid_nonce = false; check_label(ajax_label('post')->status === 403, 'Nonce guard changed');
echo 'Relational labels: ' . $checks . " checks passed.\n";
