<?php
/**
 * Plugin Name: Custom Fields Framework Pro
 * Description: ACF-like custom fields with Field Groups, Repeater, Flexible Content, Location rules, and ACF-compatible frontend helpers.
 * Version: 0.13.3
 * Author: CFF
 * Text Domain: cff
 */
if (!defined('ABSPATH')) exit;

define('CFFP_VERSION', '0.13.4');
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

  if (is_admin()) {
    require_once CFFP_DIR . 'includes/class-updater.php';
    add_action('plugins_loaded', function(){
      new CFF_Github_Public_Updater(
        CFFP_FILE,
        'ekods/custom-fields-framework-pro',
        'custom-fields-framework-pro.zip'
      );
    });
  }
});
