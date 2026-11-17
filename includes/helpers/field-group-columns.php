<?php
namespace CFF;
if (!defined('ABSPATH')) exit;


function cffp_field_group_fields_total($post_id) {
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

  return count($fields);
}

/** One-line location: icon + "Posts, Pages" when every rule targets a post type, the single rule, or "Various". */
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

  $descriptors = [];
  $post_types = [];
  $params = [];
  $only_post_types = true;
  foreach ($location as $group) {
    foreach ((array) $group as $rule) {
      $item = cffp_format_location_descriptor($rule);
      if (!$item) continue;
      $descriptors[$item] = true;
      $params[$rule['param'] ?? 'post_type'] = true;
      if (($rule['param'] ?? 'post_type') === 'post_type' && ($rule['operator'] ?? '==') === '==') {
        $post_types[$rule['value']] = true;
      } else {
        $only_post_types = false;
      }
    }
  }

  if (!$descriptors) return '<span class="cff-col-empty" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__('No location rules', 'cff') . '</span>';

  $all = implode('; ', array_keys($descriptors));
  if ($only_post_types) {
    $labels = [];
    foreach (array_keys($post_types) as $pt) {
      $obj = get_post_type_object($pt);
      $labels[] = $obj && !empty($obj->labels->name) ? $obj->labels->name : ucfirst($pt);
    }
    $icon = 'dashicons-admin-post';
    if (count($post_types) === 1) {
      $pt = array_keys($post_types)[0];
      $obj = get_post_type_object($pt);
      $menu_icon = $obj && is_string($obj->menu_icon ?? null) ? $obj->menu_icon : '';
      if (strpos($menu_icon, 'dashicons-') === 0) $icon = $menu_icon;
      elseif ($pt === 'page') $icon = 'dashicons-admin-page';
    }
    $text = implode(', ', $labels);
  } elseif (count($descriptors) === 1) {
    $param_icons = [
      'page' => 'dashicons-admin-page',
      'post' => 'dashicons-admin-post',
      'page_template' => 'dashicons-media-default',
      'options_page' => 'dashicons-admin-generic',
    ];
    $icon = $param_icons[array_keys($params)[0]] ?? 'dashicons-location';
    $text = $all;
  } else {
    $icon = 'dashicons-location';
    $text = __('Various', 'cff');
  }

  $html = '<span class="cff-col-meta" title="' . esc_attr($all) . '">';
  $html .= '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>';
  $html .= '<span>' . esc_html($text) . '</span>';
  if ($text === __('Various', 'cff')) {
    $html .= '<span class="screen-reader-text">: ' . esc_html($all) . '</span>';
  }
  return $html . '</span>';
}

function cffp_format_location_descriptor($rule) {
  if (!is_array($rule)) return '';
  if (($rule['operator'] ?? '==') === '!=') {
    $rule['operator'] = '==';
    $label = cffp_format_location_descriptor($rule);
    return $label !== '' ? sprintf(__('Not %s', 'cff'), $label) : '';
  }
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
