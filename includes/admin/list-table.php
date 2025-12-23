<?php
// admin list table untuk CPT cff_group
add_filter('manage_edit-cff_group_columns', function($cols){
  // reset biar urutannya mirip ACF
  return [
    'cb'          => $cols['cb'],
    'title'       => __('Title', 'cff'),
    'cff_desc'    => __('Description', 'cff'),
    'cff_key'     => __('Key', 'cff'),
    'cff_location'=> __('Location', 'cff'),
    'cff_fields'  => __('Fields', 'cff'),
  ];
}, 20);

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
      $count = is_array($fields) ? count($fields) : 0;
      echo '<strong>' . (int)$count . '</strong>';
      break;
  }
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
