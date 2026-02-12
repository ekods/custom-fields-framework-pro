<?php
require_once __DIR__ . '/../helpers/field-group-columns.php';

// admin list table untuk CPT cff_group
add_filter('manage_edit-cff_group_columns', function($cols){
  return [
    'cb'          => $cols['cb'],
    'title'       => __('Title', 'cff'),
    'cff_key'     => __('Key', 'cff'),
    'cff_location'=> __('Location', 'cff'),
    'cff_fields'  => __('Fields', 'cff'),
  ];
}, 20);

add_action('manage_cff_group_posts_custom_column', function($col, $post_id){
  switch ($col) {
    case 'cff_key':
      $key = get_post_meta($post_id, 'cff_group_key', true);
      if (!$key) $key = 'group_' . $post_id;
      echo '<code>' . esc_html($key) . '</code>';
      break;

    case 'cff_location':
      echo \CFF\cffp_field_group_location_summary($post_id);
      break;

    case 'cff_fields':
      echo \CFF\cffp_field_group_fields_count($post_id);
      break;
  }
}, 10, 2);

add_filter('post_row_actions', function($actions, $post){
  if ($post && $post->post_type === 'cff_group' && current_user_can('manage_options')) {
    $url = wp_nonce_url(
      admin_url('edit.php?post_type=cff_group&cff_export_group=' . $post->ID),
      'cff_export_group_' . $post->ID
    );
    $actions['cff_export_group'] = '<a href="' . esc_url($url) . '">' . esc_html__('Export JSON', 'cff') . '</a>';
  }
  return $actions;
}, 10, 2);

/**
 * Ringkas location rules jadi label ACF-like.
 * Sesuaikan dengan format rules plugin kamu.
 */
function cff_admin_location_summary($loc){
  if (empty($loc)) return '—';

  // Contoh asumsi format:
  // [ [ ['param'=>'post_type','operator'=>'==','value'=>'page'] ] , ...]
  // atau versi kamu sendiri. Yang penting output ringkas.
  $types = [];

  if (is_array($loc)) {
    foreach ($loc as $group) {
      foreach ((array)$group as $rule) {
        $param = $rule['param'] ?? '';
        $val   = $rule['value'] ?? '';
        if ($param === 'post_type' && $val) $types[] = $val;
      }
    }
  }

  $types = array_values(array_unique(array_filter($types)));

  if (!$types) return __('Various', 'cff');

  // ACF pakai label plural
  $labels = [];
  foreach ($types as $pt) {
    $obj = get_post_type_object($pt);
    $labels[] = $obj && !empty($obj->labels->name) ? $obj->labels->name : ucfirst($pt);
  }

  return count($labels) === 1 ? $labels[0] : __('Various', 'cff');
}
