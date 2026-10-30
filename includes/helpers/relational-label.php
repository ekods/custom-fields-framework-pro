<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/** Labels are plain text in AJAX responses; HTML renderers must still escape them. */
function cff_relational_label($label) {
  return html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
