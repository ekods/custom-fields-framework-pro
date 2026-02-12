<?php
namespace CFF;
if (!defined('ABSPATH')) exit;


function cffp_field_group_fields_count($post_id) {
  $settings = get_post_meta($post_id, '_cff_settings', true);
  $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];

  if (!$fields) {
    $legacy = get_post_meta($post_id, 'cff_fields_json', true);
    if (is_string($legacy)) {
      $decoded = json_decode($legacy, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $fields = $decoded;
      }
    }
  }

  return '<strong>' . intval(count($fields)) . '</strong>';
}

function cffp_field_group_location_summary($post_id) {
  $settings = get_post_meta($post_id, '_cff_settings', true);
  $location = isset($settings['location']) && is_array($settings['location']) ? $settings['location'] : [];

  if (!$location) {
    $raw = get_post_meta($post_id, 'cff_location_rules', true);
    if (is_string($raw)) {
      $tmp = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $raw = $tmp;
      }
    }
    if (is_array($raw)) {
      $location = $raw;
    }
  }

  $labels = [];
  foreach ($location as $group) {
    $group_items = [];
    foreach ((array)$group as $rule) {
      $item = cffp_format_location_descriptor($rule);
      if ($item) {
        $group_items[] = $item;
      }
    }
    if ($group_items) {
      foreach ($group_items as $item_label) {
        if (!in_array($item_label, $labels, true)) {
          $labels[] = $item_label;
        }
      }
    }
  }

  if (!$labels) return '—';

  $html = '<ul class="cffp-location-summary">';
  foreach ($labels as $label) {
    $html .= '<li>' . esc_html($label) . '</li>';
  }
  $html .= '</ul>';
  return $html;
}

function cffp_format_location_descriptor($rule) {
  if (!is_array($rule)) return '';
  $param = $rule['param'] ?? 'post_type';
  $value = $rule['value'] ?? '';

  if (!$value) return '';

  if ($param === 'post_type') {
    $obj = get_post_type_object($value);
    $label = $obj && !empty($obj->labels->name) ? $obj->labels->name : ucfirst($value);
    return __('Post Type', 'cff') . ' - ' . $label;
  }

  if ($param === 'post' || $param === 'page') {
    $post = get_post((int)$value);
    $type_label = ($param === 'post') ? __('Post', 'cff') : __('Page', 'cff');
    $title = $post && !is_wp_error($post) ? ($post->post_title ?: '#' . $post->ID) : $value;
    return $type_label . ' - ' . $title;
  }

  if ($param === 'page_template') {
    return __('Page Template', 'cff') . ' - ' . $value;
  }

  return ucfirst(str_replace('_', ' ', $param)) . ' - ' . $value;
}
