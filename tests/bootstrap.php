<?php
define('ABSPATH', __DIR__ . '/');

function sanitize_key($value) {
  return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}
function sanitize_text_field($value) {
  return trim(strip_tags((string) $value));
}
function sanitize_textarea_field($value) {
  return trim(strip_tags((string) $value));
}
function sanitize_hex_color($value) {
  return preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', (string) $value) ? strtolower($value) : null;
}
function esc_url_raw($value) {
  return filter_var((string) $value, FILTER_VALIDATE_URL) ? (string) $value : '';
}
function wp_kses_post($value) {
  return strip_tags((string) $value, '<a><b><strong><em><p><br><div><span>');
}
function absint($value) {
  return abs((int) $value);
}
function wp_generate_password($length = 12) {
  return str_repeat('a', $length);
}

require_once dirname(__DIR__) . '/includes/class-field-sanitizer.php';
