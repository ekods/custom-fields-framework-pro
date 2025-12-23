<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Activation {
  public static function run() {
    // Register CPT and flush.
    Plugin::register_cpt();
    flush_rewrite_rules();
  }
}
