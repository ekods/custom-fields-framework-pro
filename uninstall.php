<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Uploaded content and progress are temporary, even when persistent CFF content is retained.
global $wpdb;
wp_unschedule_hook('cff_content_import_expire');
$import_option_keys = $wpdb->get_col($wpdb->prepare(
  "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
  $wpdb->esc_like('cff_content_import_') . '%'
));
foreach ($import_option_keys as $import_option_key) delete_option($import_option_key);

$keep_data = get_option('cffp_keep_data_on_uninstall', 1);
if ((int) $keep_data === 1) {
  return;
}

global $wpdb;

$option_keys = [
  'cffp_post_types',
  'cffp_taxonomies',
  'cffp_global_settings_post_id',
  'cffp_block_sidebar_enabled',
  'cffp_hide_section_controls',
  'cffp_keep_data_on_uninstall',
  'cffp_reorder_post_types',
  'cffp_reorder_taxonomies',
];

foreach ($option_keys as $option_key) {
  delete_option($option_key);
  delete_site_option($option_key);
}

$github_release_transient = 'cffp_github_release_latest_' . md5('https://api.github.com/repos/ekods/custom-fields-framework-pro/releases/latest');
delete_site_transient($github_release_transient);

$internal_posts = get_posts([
  'post_type' => ['cff_group', 'cff_options'],
  'post_status' => 'any',
  'numberposts' => -1,
  'fields' => 'ids',
  'no_found_rows' => true,
]);

foreach ($internal_posts as $post_id) {
  wp_delete_post((int) $post_id, true);
}

$cff_meta_like = $wpdb->esc_like('_cff_') . '%';
$wpdb->query(
  $wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
    $cff_meta_like
  )
);

$wpdb->query(
  $wpdb->prepare(
    "DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
    'cffp_term_order'
  )
);
