<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-activation.php';
require_once __DIR__ . '/class-deactivation.php';
require_once __DIR__ . '/class-plugin.php';
require_once __DIR__ . '/helpers/acf-compat.php';
require_once __DIR__ . '/helpers/field-group-columns.php';


add_action('admin_init', function(){
  // columns header
  add_filter('manage_edit-cff_group_columns', function($cols){
    return [
      'cb'           => $cols['cb'] ?? 'cb',
      'title'        => __('Title', 'cff'),
      'cff_key'      => __('Key', 'cff'),
      'cff_location' => __('Location', 'cff'),
      'cff_fields'   => __('Fields', 'cff'),
    ];
  }, 20);

  // columns content (FIX: closure, jadi nggak kena masalah namespace)
  add_action('manage_cff_group_posts_custom_column', function($col, $post_id){
    switch ($col) {
      case 'cff_key':
        $key = get_post_meta($post_id, 'cff_group_key', true);
        if (!$key) $key = 'group_' . $post_id;
        echo '<code>' . esc_html($key) . '</code>';
        break;

      case 'cff_location':
        echo cffp_field_group_location_summary($post_id);
        break;

      case 'cff_fields':
        echo cffp_field_group_fields_count($post_id);
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
    case 'cff_key':
      $key = get_post_meta($post_id, 'cff_group_key', true);
      if (!$key) $key = 'group_' . $post_id;
      echo '<code>' . esc_html($key) . '</code>';
      break;

    case 'cff_location':
      echo cffp_field_group_location_summary($post_id);
      break;

    case 'cff_fields':
      echo cffp_field_group_fields_count($post_id);
      break;
  }
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
