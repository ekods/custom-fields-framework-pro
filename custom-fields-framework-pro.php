<?php
/**
 * Plugin Name: Custom Fields Framework Pro
 * Description: ACF-like custom fields with Field Groups, Repeater, Flexible Content, Location rules, and ACF-compatible frontend helpers.
 * Version: 0.13.3
 * Author: CFF
 * Text Domain: cff
 */
if (!defined('ABSPATH')) exit;

define('CFFP_VERSION', '0.13.3');
define('CFFP_FILE', __FILE__);
define('CFFP_DIR', plugin_dir_path(__FILE__));
define('CFFP_URL', plugin_dir_url(__FILE__));

require_once CFFP_DIR . 'includes/bootstrap.php';

// ✅ load updater (admin only)
if (is_admin()) {
  require_once CFFP_DIR . 'includes/class-updater.php';
}

register_activation_hook(__FILE__, function(){
  require_once CFFP_DIR . 'includes/bootstrap.php';
  \CFF\Activation::run();
});

register_deactivation_hook(__FILE__, function(){
  require_once CFFP_DIR . 'includes/bootstrap.php';
  \CFF\Deactivation::run();
});

add_action('plugins_loaded', function(){
  \CFF\Plugin::instance();

  // ✅ init updater (self-hosted)
  if (is_admin() && class_exists('CFF_Pro_Updater')) {
    $license = get_option('cffp_license_key', ''); // kalau kamu pakai license
    new CFF_Pro_Updater(
      CFFP_FILE,                       // wajib file utama
      'custom-fields-framework-pro',   // slug folder plugin
      'https://updates.domainmu.com/wp-plugin/update', // endpoint update
      $license
    );
  }
});
