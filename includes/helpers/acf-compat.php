<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/**
 * ACF-compatible helpers (minimal but real for repeater + flexible).
 * Data is stored in post meta keys: _cff_{field_name}
 */
$GLOBALS['cff_row_stack'] = [];

function cff_meta_key($name) { return '_cff_' . sanitize_key($name); }
function cff_format_value($val, $format_value = true) {
  if (!$format_value) return $val;
  if (is_array($val)) {
    $out = [];
    foreach ($val as $k => $v) {
      if (is_array($v)) {
        $out[$k] = cff_format_value($v, true);
        continue;
      }

      if (is_numeric($v) && is_string($k)) {
        $id = (int) $v;
        if ($id) {
          $url_key = $k . '_url';
          $url = (isset($val[$url_key]) && is_string($val[$url_key])) ? $val[$url_key] : '';
          if (!$url) $url = wp_get_attachment_url($id);
          if ($url) {
            $out[$k] = [
              'id' => $id,
              'url' => $url,
            ];
            continue;
          }
        }
      }

      $out[$k] = $v;
    }
    return $out;
  }

  if (is_numeric($val)) {
    $id = (int) $val;
    if ($id) {
      $url = wp_get_attachment_url($id);
      if ($url) {
      return [
        'id' => $id,
        'url' => $url,
      ];
      }
    }
  }
  return $val;
}

if (!function_exists('get_field')) {
  function get_field($selector, $post_id = false, $format_value = true) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!$post_id) return null;
    $val = get_post_meta($post_id, cff_meta_key($selector), true);
    return cff_format_value($val, $format_value);
  }
}

if (!function_exists('the_field')) {
  function the_field($selector, $post_id = false) {
    $v = get_field($selector, $post_id, true);
    if (is_scalar($v)) echo esc_html($v);
  }
}

if (!function_exists('have_rows')) {
  function have_rows($selector, $post_id = false) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $data = get_field($selector, $post_id, true);
    if (!is_array($data) || !count($data)) return false;

    $key = $post_id . ':' . sanitize_key($selector);
    if (!isset($GLOBALS['cff_row_stack'][$key])) {
      $GLOBALS['cff_row_stack'][$key] = [
        'rows' => array_values($data),
        'i' => -1,
        'current' => null,
      ];
    }
    $st = &$GLOBALS['cff_row_stack'][$key];
    return ($st['i'] + 1) < count($st['rows']);
  }
}

if (!function_exists('the_row')) {
  function the_row() {
    // Find the most recent stack entry
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return false;

    $st = &$GLOBALS['cff_row_stack'][$key];
    $st['i']++;
    $st['current'] = $st['rows'][$st['i']] ?? null;
    return $st['current'] !== null;
  }
}

if (!function_exists('get_row_layout')) {
  function get_row_layout() {
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return null;
    $cur = $GLOBALS['cff_row_stack'][$key]['current'];
    if (is_array($cur) && isset($cur['layout'])) return $cur['layout'];
    return null;
  }
}

if (!function_exists('get_sub_field')) {
  function get_sub_field($selector, $format_value = true) {
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return null;
    $cur = $GLOBALS['cff_row_stack'][$key]['current'];
    if (!is_array($cur)) return null;

    // Flexible stores subfields under ['fields']
    if (isset($cur['fields']) && is_array($cur['fields'])) {
      $val = $cur['fields'][sanitize_key($selector)] ?? null;
      return cff_format_value($val, $format_value);
    }
    // Repeater rows store direct subkeys
    $val = $cur[sanitize_key($selector)] ?? null;
    return cff_format_value($val, $format_value);
  }
}

if (!function_exists('the_sub_field')) {
  function the_sub_field($selector) {
    $v = get_sub_field($selector);
    if (is_scalar($v)) echo esc_html($v);
  }
}
