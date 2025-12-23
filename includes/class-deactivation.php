<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Deactivation {
  public static function run() {
    flush_rewrite_rules();
  }
}
