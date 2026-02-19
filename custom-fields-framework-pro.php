<?php
/**
 * Plugin Name: Custom Fields Framework Pro
 * Description: Custom fields with Field Groups, Repeater, Flexible Content, Location rules, and CFF-compatible frontend helpers.
 * Version: 0.15.3
 * Author: Eko Dwi Saputro
 * Text Domain: https://theteamtheteam.com
 */

if (!defined('ABSPATH')) exit;

define('CFFP_VERSION', '0.15.3');
define('CFFP_FILE', __FILE__);
define('CFFP_DIR', plugin_dir_path(__FILE__));
define('CFFP_URL', plugin_dir_url(__FILE__));

require_once CFFP_DIR . 'includes/bootstrap.php';

register_activation_hook(__FILE__, function(){
  \CFF\Activation::run();
});

register_deactivation_hook(__FILE__, function(){
  \CFF\Deactivation::run();
});

add_action('plugins_loaded', function(){
  \CFF\Plugin::instance();
});

// ✅ Updater (admin only)
if (is_admin()) {
  require_once CFFP_DIR . 'includes/class-updater.php';

  add_action('admin_init', function(){
    if (class_exists('CFF_Github_Public_Updater')) {
      new CFF_Github_Public_Updater(
        CFFP_FILE,
        'ekods/custom-fields-framework-pro',
        'custom-fields-framework-pro.zip'
      );
    }
  });
}
