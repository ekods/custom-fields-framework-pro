<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/**
 * Server-side validation of submitted cff_values against field definitions.
 * Errors are keyed by the input name prefix (e.g. "cff_values[team][0][email]") so the editor can
 * place each message on the matching field.
 */
class Field_Validator {
  private $errors = [];
  private $skip = [];

  /**
   * @param array $fields Field definitions.
   * @param mixed $values Submitted cff_values.
   * @param array $skip   Paths hidden by conditional logic; they and their children are not validated.
   * @return array path => message
   */
  public function validate(array $fields, $values, array $skip = []) {
    $this->errors = [];
    $this->skip = array_values(array_filter($skip, function($path) { return is_string($path) && $path !== ''; }));
    $values = is_array($values) ? $values : [];

    foreach ($fields as $field) {
      if (!is_array($field) || empty($field['name']) || !empty($field['hide'])) continue;
      $name = sanitize_key($field['name']);
      $this->validate_field($field, $values[$name] ?? null, 'cff_values[' . $name . ']');
    }

    return apply_filters('cff/validate_values', $this->errors, $fields, $values);
  }

  private function validate_field(array $field, $value, $path) {
    if ($this->is_skipped($path) || !empty($field['hide'])) return;

    $type = sanitize_key($field['type'] ?? 'text');
    $label = trim((string) ($field['label'] ?? '')) ?: (string) ($field['name'] ?? '');
    $required = !empty($field['required']);

    if (in_array($type, ['message', 'tab', 'accordion'], true)) return;

    if ($type === 'repeater') {
      $rows = $this->rows($value);
      $count = count($rows);
      $min = max(0, (int) ($field['min'] ?? 0));
      $max = max(0, (int) ($field['max'] ?? 0));
      if ($required && $count === 0) {
        $this->error($path, sprintf(__('%s requires at least one row.', 'cff'), $label));
      } elseif ($min > 0 && $count < $min) {
        $this->error($path, sprintf(_n('%1$s requires at least %2$d row.', '%1$s requires at least %2$d rows.', $min, 'cff'), $label, $min));
      }
      if ($max > 0 && $count > $max) {
        $this->error($path, sprintf(_n('%1$s allows at most %2$d row.', '%1$s allows at most %2$d rows.', $max, 'cff'), $label, $max));
      }
      foreach ($rows as $index => $row) {
        $this->validate_children($field['sub_fields'] ?? [], is_array($row) ? $row : [], $path . '[' . $index . ']');
      }
      return;
    }

    if ($type === 'group') {
      $this->validate_children($field['sub_fields'] ?? [], is_array($value) ? $value : [], $path);
      return;
    }

    if ($type === 'flexible') {
      $rows = $this->rows($value);
      if ($required && !$rows) {
        $this->error($path, sprintf(__('%s requires at least one layout.', 'cff'), $label));
      }
      $layouts = [];
      foreach ((array) ($field['layouts'] ?? []) as $layout) {
        if (is_array($layout) && !empty($layout['name'])) $layouts[$layout['name']] = $layout;
      }
      foreach ($rows as $index => $row) {
        $layout = is_array($row) ? sanitize_key($row['layout'] ?? '') : '';
        if (!isset($layouts[$layout])) continue;
        $row_fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
        $this->validate_children($layouts[$layout]['sub_fields'] ?? [], $row_fields, $path . '[' . $index . '][fields]');
      }
      return;
    }

    $empty = $this->is_empty($type, $value);
    if ($empty) {
      if ($required) $this->error($path, sprintf(__('%s is required.', 'cff'), $label));
      return;
    }

    $scalar = is_scalar($value) ? trim((string) $value) : '';
    if ($type === 'email' && !is_email($scalar)) {
      $this->error($path, sprintf(__('%s must be a valid email address.', 'cff'), $label));
    } elseif ($type === 'url' && !$this->is_valid_url($scalar)) {
      $this->error($path, sprintf(__('%s must be a valid URL.', 'cff'), $label));
    } elseif (in_array($type, ['number', 'range'], true)) {
      if (!is_numeric($scalar)) {
        $this->error($path, sprintf(__('%s must be a number.', 'cff'), $label));
      } elseif ($type === 'range') {
        $min = isset($field['range_min']) && is_numeric($field['range_min']) ? (float) $field['range_min'] : 0;
        $max = isset($field['range_max']) && is_numeric($field['range_max']) ? (float) $field['range_max'] : 100;
        if ((float) $scalar < $min || (float) $scalar > $max) {
          $this->error($path, sprintf(__('%1$s must be between %2$s and %3$s.', 'cff'), $label, $min + 0, $max + 0));
        }
      }
    }
  }

  private function validate_children($fields, array $values, $prefix) {
    foreach ((array) $fields as $field) {
      if (!is_array($field) || empty($field['name'])) continue;
      $name = sanitize_key($field['name']);
      $this->validate_field($field, $values[$name] ?? null, $prefix . '[' . $name . ']');
    }
  }

  /** Repeater/flexible rows keep their submitted index so error paths match the input names. */
  private function rows($value) {
    if (!is_array($value)) return [];
    $rows = [];
    foreach ($value as $key => $row) {
      if (is_int($key) || ctype_digit((string) $key)) $rows[$key] = $row;
    }
    return $rows;
  }

  private function is_empty($type, $value) {
    if ($type === 'link') {
      return !is_array($value) || (trim((string) ($value['url'] ?? '')) === '' && empty($value['internal_id']));
    }
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        if ($key === '__cff_present') continue;
        if (is_array($item) ? !$this->is_empty('', $item) : trim((string) $item) !== '') return false;
      }
      return true;
    }
    $text = trim((string) $value);
    if (in_array($type, ['checkbox', 'image', 'file'], true)) return $text === '' || $text === '0';
    if ($type === 'wysiwyg') return trim(str_replace('&nbsp;', ' ', wp_strip_all_tags($text))) === '';
    return $text === '';
  }

  /** URL fields also accept site-relative links such as /about or #contact. */
  private function is_valid_url($url) {
    if (preg_match('~^(/|#|\?)~', $url)) return true;
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
  }

  private function is_skipped($path) {
    foreach ($this->skip as $skip) {
      if (strpos($path, $skip) === 0) return true;
    }
    return false;
  }

  private function error($path, $message) {
    if (!isset($this->errors[$path])) $this->errors[$path] = $message;
  }
}
