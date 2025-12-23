<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-activation.php';
require_once __DIR__ . '/class-deactivation.php';
require_once __DIR__ . '/class-plugin.php';
require_once __DIR__ . '/helpers/acf-compat.php';


add_action('admin_init', function(){
  // columns header
  add_filter('manage_edit-cff_group_columns', function($cols){
    return [
      'cb'           => $cols['cb'] ?? 'cb',
      'title'        => __('Title', 'cff'),
      'cff_desc'     => __('Description', 'cff'),
      'cff_key'      => __('Key', 'cff'),
      'cff_location' => __('Location', 'cff'),
      'cff_fields'   => __('Fields', 'cff'),
    ];
  }, 20);

  // columns content (FIX: closure, jadi nggak kena masalah namespace)
  add_action('manage_cff_group_posts_custom_column', function($col, $post_id){
    switch ($col) {
      case 'cff_desc':
        $desc = get_post_meta($post_id, 'cff_group_description', true);
        echo esc_html($desc ?: '—');
        break;

      case 'cff_key':
        $key = get_post_meta($post_id, 'cff_group_key', true);
        if (!$key) $key = 'group_' . $post_id;
        echo '<code>' . esc_html($key) . '</code>';
        break;

      case 'cff_location':
        $loc = get_post_meta($post_id, 'cff_location_rules', true);
        if (is_string($loc)) {
          $tmp = json_decode($loc, true);
          if (json_last_error() === JSON_ERROR_NONE) $loc = $tmp;
        }
        echo esc_html(cffp_location_summary($loc));
        break;

      case 'cff_fields':
        $fields = get_post_meta($post_id, 'cff_fields', true);
        if (is_string($fields)) {
          $tmp = json_decode($fields, true);
          if (json_last_error() === JSON_ERROR_NONE) $fields = $tmp;
        }
        echo '<strong>' . (int)(is_array($fields) ? count($fields) : 0) . '</strong>';
        break;
    }
  }, 10, 2);
});


function cffp_cff_group_columns($cols){
  return [
    'cb'           => $cols['cb'] ?? 'cb',
    'title'        => __('Title', 'cff'),
    'cff_desc'     => __('Description', 'cff'),
    'cff_key'      => __('Key', 'cff'),
    'cff_location' => __('Location', 'cff'),
    'cff_fields'   => __('Fields', 'cff'),
  ];
}

function cffp_cff_group_column_content($col, $post_id){
  switch ($col) {
    case 'cff_desc':
      $desc = get_post_meta($post_id, 'cff_group_description', true);
      echo esc_html($desc ?: '—');
      break;

    case 'cff_key':
      $key = get_post_meta($post_id, 'cff_group_key', true);
      if (!$key) $key = 'group_' . $post_id;
      echo '<code>' . esc_html($key) . '</code>';
      break;

    case 'cff_location':
      $loc = get_post_meta($post_id, 'cff_location_rules', true);
      if (is_string($loc)) {
        $tmp = json_decode($loc, true);
        if (json_last_error() === JSON_ERROR_NONE) $loc = $tmp;
      }
      echo esc_html(cffp_location_summary($loc));
      break;

    case 'cff_fields':
      $fields = get_post_meta($post_id, 'cff_fields', true);
      if (is_string($fields)) {
        $tmp = json_decode($fields, true);
        if (json_last_error() === JSON_ERROR_NONE) $fields = $tmp;
      }
      $count = is_array($fields) ? count($fields) : 0;
      echo '<strong>' . (int)$count . '</strong>';
      break;
  }
}

function cffp_location_summary($loc){
  if (empty($loc)) return '—';

  $types = [];
  if (is_array($loc)) {
    foreach ($loc as $group) {
      foreach ((array)$group as $rule) {
        if (($rule['param'] ?? '') === 'post_type' && !empty($rule['value'])) {
          $types[] = $rule['value'];
        }
      }
    }
  }
  $types = array_values(array_unique(array_filter($types)));
  if (!$types) return __('Various', 'cff');

  $labels = [];
  foreach ($types as $pt) {
    $obj = get_post_type_object($pt);
    $labels[] = $obj && !empty($obj->labels->name) ? $obj->labels->name : ucfirst($pt);
  }
  return count($labels) === 1 ? $labels[0] : __('Various', 'cff');
}

function cffp_cff_group_sortable_columns($cols){
  return $cols; // placeholder kalau nanti mau sortable
}


// add_action('admin_notices', function(){
//   if (!function_exists('get_current_screen')) return;
//
//   $screen = get_current_screen();
//   if ($screen && $screen->id === 'edit-cff_group') {
//     echo '<div class="notice notice-info"><p>CFFP list table hooks loaded ✅</p></div>';
//   }
// });
