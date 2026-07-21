<?php
namespace CFF;

if (!defined('ABSPATH')) exit;

/**
 * Single source of truth for values written by the classic editor and REST API.
 */
class Field_Sanitizer {
  public function sanitize($field, $value) {
    if (is_object($value)) $value = (array) $value;

    $type = sanitize_key($field['type'] ?? 'text');

    if ($type === 'number') {
      if ($value === '' || $value === null || !is_numeric($value)) return null;
      return 0 + $value;
    }

    if ($type === 'checkbox') return !empty($value) ? '1' : '0';

    if ($type === 'choice') return $this->sanitize_choice($field, $value);

    if ($type === 'url') {
      return is_scalar($value) ? esc_url_raw((string) $value) : '';
    }

    if ($type === 'link') {
      if (!is_array($value)) return null;
      $mode = sanitize_key($value['mode'] ?? '');
      $mode = in_array($mode, ['internal', 'custom'], true) ? $mode : 'custom';
      $target = sanitize_key($value['target'] ?? '');
      $parameter = $this->sanitize_link_parameter($value['parameter'] ?? '');
      $hash = $this->sanitize_link_hash($value['hash'] ?? '');
      $internal_id = absint($value['internal_id'] ?? 0);
      $url = esc_url_raw($value['url'] ?? '');

      if ($mode === 'internal' && $internal_id && function_exists('get_permalink')) {
        $permalink = get_permalink($internal_id);
        if ($permalink) {
          $url = esc_url_raw($this->build_link_url((string) $permalink, $parameter, $hash));
        }
      }

      return [
        'mode' => $mode,
        'url' => $url,
        'title' => sanitize_text_field($value['title'] ?? ''),
        'target' => $target === '_blank' ? '_blank' : '',
        'internal_id' => $internal_id,
        'post_type_filter' => sanitize_key($value['post_type_filter'] ?? 'any') ?: 'any',
        'parameter' => $parameter,
        'hash' => $hash,
      ];
    }

    if ($type === 'image' || $type === 'file') {
      return $this->sanitize_media_value($field, $value, is_array($value) ? ($value['url'] ?? '') : '');
    }

    if ($type === 'gallery') {
      if (!is_array($value)) return [];
      $ids = [];
      foreach ($value as $key => $item) {
        if ($key === '__cff_present') continue;
        $id = absint(is_array($item) ? ($item['id'] ?? 0) : $item);
        if ($id) $ids[] = $id;
      }
      return array_values(array_unique($ids));
    }

    if ($type === 'relational') {
      $multiple = !empty($field['relational_multiple']);
      if ($multiple) {
        if (!is_array($value)) return [];
        $ids = array_map('absint', array_filter($value, function($item) {
          return $item !== '__cff_rel_empty__';
        }));
        return array_values(array_unique(array_filter($ids)));
      }
      return absint($value);
    }

    if ($type === 'repeater') return $this->sanitize_repeater($field, $value);
    if ($type === 'group') return $this->sanitize_group($field, $value);
    if ($type === 'flexible') return $this->sanitize_flexible($field, $value);

    if ($type === 'date_picker') {
      return $this->sanitize_date($value, false);
    }
    if ($type === 'datetime_picker') {
      return $this->sanitize_date($value, !array_key_exists('datetime_use_time', $field) || !empty($field['datetime_use_time']));
    }

    if ($type === 'color') {
      $color = is_scalar($value) ? sanitize_hex_color((string) $value) : '';
      return $color ?: '';
    }

    if (in_array($type, ['wysiwyg', 'embed', 'shortcode'], true)) {
      return wp_kses_post(is_scalar($value) ? (string) $value : '');
    }

    if ($type === 'textarea') {
      return sanitize_textarea_field(is_scalar($value) ? (string) $value : '');
    }

    return sanitize_text_field(is_scalar($value) ? (string) $value : '');
  }

  private function sanitize_choice($field, $value) {
    $allowed = [];
    foreach ((array) ($field['choices'] ?? []) as $choice) {
      $choice_value = sanitize_text_field($choice['value'] ?? ($choice['label'] ?? ''));
      if ($choice_value !== '') $allowed[$choice_value] = true;
    }

    $display = sanitize_key($field['choice_display'] ?? 'select');
    if ($display === 'checkbox') {
      if (!is_array($value)) return [];
      $clean = [];
      foreach ($value as $item) {
        if ($item === '__cff_choice_empty__') continue;
        $item = sanitize_text_field($item);
        if ($item !== '' && (!$allowed || isset($allowed[$item]))) $clean[] = $item;
      }
      return array_values(array_unique($clean));
    }

    $clean = sanitize_text_field(is_scalar($value) ? (string) $value : '');
    return ($clean !== '' && $allowed && !isset($allowed[$clean])) ? '' : $clean;
  }

  private function sanitize_repeater($field, $value) {
    if (!is_array($value)) return [];
    unset($value['__cff_present']);

    $sub_fields = $this->index_fields($field['sub_fields'] ?? []);
    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $clean = ['__cff_row_id' => $this->row_id($row['__cff_row_id'] ?? '')];
      foreach ($sub_fields as $name => $sub_field) {
        if (!array_key_exists($name, $row)) continue;
        $clean[$name] = $this->sanitize_nested_value($sub_field, $row[$name], $row[$name . '_url'] ?? '');
        $this->append_media_companion_url($clean, $name, $sub_field, $row[$name . '_url'] ?? '', $clean[$name]);
      }
      $rows[] = $clean;
    }

    $max = max(0, intval($field['max'] ?? 0));
    return $max > 0 ? array_slice($rows, 0, $max) : $rows;
  }

  private function sanitize_group($field, $value) {
    if (!is_array($value)) return [];
    $clean = [];
    foreach ($this->index_fields($field['sub_fields'] ?? []) as $name => $sub_field) {
      if (!array_key_exists($name, $value)) continue;
      $clean[$name] = $this->sanitize_nested_value($sub_field, $value[$name], $value[$name . '_url'] ?? '');
      $this->append_media_companion_url($clean, $name, $sub_field, $value[$name . '_url'] ?? '', $clean[$name]);
    }
    return $clean;
  }

  private function sanitize_flexible($field, $value) {
    if (!is_array($value)) return [];
    $layouts = [];
    foreach ((array) ($field['layouts'] ?? []) as $layout) {
      $name = sanitize_key($layout['name'] ?? '');
      if ($name) $layouts[$name] = $layout;
    }

    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $layout_name = sanitize_key($row['layout'] ?? '');
      if (!$layout_name || !isset($layouts[$layout_name])) continue;
      $submitted = is_array($row['fields'] ?? null) ? $row['fields'] : [];
      $fields = [];
      foreach ($this->index_fields($layouts[$layout_name]['sub_fields'] ?? []) as $name => $sub_field) {
        if (!array_key_exists($name, $submitted)) continue;
        $fields[$name] = $this->sanitize_nested_value($sub_field, $submitted[$name], $submitted[$name . '_url'] ?? '');
        $this->append_media_companion_url($fields, $name, $sub_field, $submitted[$name . '_url'] ?? '', $fields[$name]);
      }
      $rows[] = [
        'layout' => $layout_name,
        '__cff_row_id' => $this->row_id($row['__cff_row_id'] ?? ''),
        'fields' => $fields,
      ];
    }
    return $rows;
  }

  private function sanitize_nested_value($field, $value, $companion_url = '') {
    $type = sanitize_key($field['type'] ?? 'text');
    if ($type === 'image' || $type === 'file') {
      return $this->sanitize_media_value($field, $value, $companion_url);
    }
    return $this->sanitize($field, $value);
  }

  private function sanitize_media_value($field, $value, $companion_url = '') {
    $type = sanitize_key($field['type'] ?? 'file');
    $id = absint(is_array($value) ? ($value['id'] ?? 0) : $value);
    $url = is_scalar($companion_url) ? esc_url_raw((string) $companion_url) : '';

    if ($url !== '' && function_exists('attachment_url_to_postid')) {
      $url_id = absint(attachment_url_to_postid($url));
      if ($url_id) $id = $url_id;
    }

    if (
      $type === 'image'
      && $id
      && function_exists('wp_attachment_is')
      && !wp_attachment_is('image', $id)
      && !wp_attachment_is('video', $id)
    ) {
      return 0;
    }

    return $id;
  }

  private function append_media_companion_url(&$clean, $name, $field, $companion_url, $id) {
    $type = sanitize_key($field['type'] ?? 'text');
    if ($type !== 'image' && $type !== 'file') return;

    $url = is_scalar($companion_url) ? esc_url_raw((string) $companion_url) : '';
    if (!$url && absint($id) && function_exists('wp_get_attachment_url')) {
      $url = (string) wp_get_attachment_url(absint($id));
    }

    $clean[$name . '_url'] = absint($id) ? $url : '';
  }

  private function sanitize_date($value, $with_time) {
    if (!is_scalar($value)) return '';
    $value = trim((string) $value);
    if ($value === '') return '';
    $format = $with_time ? 'Y-m-d\TH:i' : 'Y-m-d';
    $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
    return ($date && $date->format($format) === $value) ? $value : '';
  }

  private function index_fields($fields) {
    $indexed = [];
    foreach ((array) $fields as $field) {
      $name = sanitize_key($field['name'] ?? '');
      if ($name) $indexed[$name] = $field;
    }
    return $indexed;
  }

  private function row_id($value) {
    $value = sanitize_key($value);
    return $value ?: 'row_' . wp_generate_password(12, false, false);
  }

  private function sanitize_link_parameter($value) {
    if (!is_scalar($value)) return '';
    $value = sanitize_text_field((string) $value);
    return ltrim(trim($value), "?& \t\n\r\0\x0B");
  }

  private function sanitize_link_hash($value) {
    if (!is_scalar($value)) return '';
    $value = sanitize_text_field((string) $value);
    return ltrim(trim($value), "# \t\n\r\0\x0B");
  }

  private function build_link_url($base_url, $parameter = '', $hash = '') {
    $base_url = (string) $base_url;
    $parameter = $this->sanitize_link_parameter($parameter);
    $hash = $this->sanitize_link_hash($hash);

    if ($hash !== '') {
      $base_url = preg_replace('/#.*$/', '', $base_url);
    }

    if ($parameter !== '') {
      $base_url .= (strpos($base_url, '?') === false ? '?' : '&') . $parameter;
    }

    if ($hash !== '') {
      $base_url .= '#' . $hash;
    }

    return $base_url;
  }
}
