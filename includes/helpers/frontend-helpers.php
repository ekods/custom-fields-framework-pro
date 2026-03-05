<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

if (!function_exists(__NAMESPACE__ . '\cff_resolve_post_id')) {
  function cff_resolve_post_id($post_id = 0) {
    $post_id = absint($post_id);
    if ($post_id) return $post_id;
    return absint(get_queried_object_id() ?: get_the_ID());
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_value')) {
  function cff_get_value($field_name, $post_id = 0, $default = null, $format_value = true) {
    $field_name = sanitize_key($field_name);
    if (!$field_name) return $default;

    $post_id = cff_resolve_post_id($post_id);
    if (!$post_id) return $default;

    $value = function_exists(__NAMESPACE__ . '\get_field')
      ? get_field($field_name, $post_id, $format_value)
      : get_post_meta($post_id, '_cff_' . $field_name, true);

    if ($value === null || $value === '' || (is_array($value) && !$value)) {
      return $default;
    }
    return $value;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_has_value')) {
  function cff_has_value($field_name, $post_id = 0) {
    $value = cff_get_value($field_name, $post_id, null, false);
    if (is_array($value)) return !empty($value);
    return $value !== null && $value !== '';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_text')) {
  function cff_get_text($field_name, $post_id = 0, $default = '') {
    $value = cff_get_value($field_name, $post_id, $default, true);
    return is_scalar($value) ? (string) $value : $default;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_image_url')) {
  function cff_get_image_url($field_name, $post_id = 0, $size = 'full', $default = '') {
    $value = cff_get_value($field_name, $post_id, null, true);
    if (is_array($value)) {
      $attachment_id = absint($value['id'] ?? 0);
      if ($attachment_id) {
        $image = wp_get_attachment_image_src($attachment_id, $size);
        if (!empty($image[0])) return (string) $image[0];
      }
      if (!empty($value['url']) && is_string($value['url'])) return $value['url'];
    }
    if (is_numeric($value)) {
      $image = wp_get_attachment_image_src(absint($value), $size);
      if (!empty($image[0])) return (string) $image[0];
    }
    return $default;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_file_url')) {
  function cff_get_file_url($field_name, $post_id = 0, $default = '') {
    $value = cff_get_value($field_name, $post_id, null, true);
    if (is_array($value) && !empty($value['url']) && is_string($value['url'])) {
      return $value['url'];
    }
    if (is_numeric($value)) {
      $url = wp_get_attachment_url(absint($value));
      if ($url) return (string) $url;
    }
    return $default;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_repeater_rows')) {
  function cff_get_repeater_rows($field_name, $post_id = 0) {
    $value = cff_get_value($field_name, $post_id, [], true);
    return is_array($value) ? array_values($value) : [];
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_group_value')) {
  function cff_get_group_value($group_field, $sub_field, $post_id = 0, $default = null) {
    $group = cff_get_value($group_field, $post_id, [], true);
    if (!is_array($group)) return $default;
    $sub_field = sanitize_key($sub_field);
    if (!$sub_field || !array_key_exists($sub_field, $group)) return $default;
    return $group[$sub_field];
  }
}
