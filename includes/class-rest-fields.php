<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Rest_Fields {
  private $plugin;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function register() {
    $post_types = get_post_types(['show_in_rest' => true], 'names');
    if (!is_array($post_types) || !$post_types) return;

    foreach ($post_types as $post_type) {
      $post_type = sanitize_key($post_type);
      if (!$post_type) continue;
      if (in_array($post_type, ['cff_group', 'cff_options', 'revision'], true)) continue;

      $writable = (bool) apply_filters('cff_rest_fields_writable', true, $post_type);
      $schema_properties = $this->build_schema_properties_for_post_type($post_type);

      register_rest_field($post_type, 'cff', [
        'get_callback' => function($object) use ($post_type) {
          $post_id = absint($object['id'] ?? 0);
          if (!$post_id) return [];
          return $this->get_payload($post_id, $post_type);
        },
        'update_callback' => $writable ? function($value, $object) use ($post_type) {
          $post_id = absint($object->ID ?? 0);
          if (!$post_id) {
            return new \WP_Error('cff_rest_invalid_post', __('Invalid post object.', 'cff'), ['status' => 400]);
          }
          return $this->update_payload($post_id, $post_type, $value);
        } : null,
        'schema' => [
          'description' => __('Custom Fields Framework values.', 'cff'),
          'type' => 'object',
          'context' => ['view', 'edit'],
          'readonly' => !$writable,
          'properties' => $schema_properties,
          'additionalProperties' => true,
        ],
      ]);
    }
  }

  public function get_payload($post_id, $post_type) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== $post_type) return [];

    $definitions = $this->plugin->get_field_definitions_for_post($post);
    if (!$definitions) {
      $definitions = $this->get_definitions_for_post_type($post_type);
    }

    $format_value = (bool) apply_filters('cff_rest_fields_format_value', true, $post_type, $post_id);
    $out = [];
    foreach ($definitions as $name => $field) {
      $name = sanitize_key($name);
      if (!$name) continue;

      if ($format_value && function_exists(__NAMESPACE__ . '\get_field')) {
        $out[$name] = get_field($name, $post_id, true);
      } else {
        $out[$name] = get_post_meta($post_id, $this->plugin->meta_key($name), true);
      }
    }
    return $out;
  }

  public function update_payload($post_id, $post_type, $value) {
    if (is_object($value)) {
      $value = (array) $value;
    }
    if (!is_array($value)) {
      return new \WP_Error('cff_rest_invalid_payload', __('CFF payload must be an object.', 'cff'), ['status' => 400]);
    }
    if (!current_user_can('edit_post', $post_id)) {
      return new \WP_Error('cff_rest_forbidden', __('You are not allowed to edit this post.', 'cff'), ['status' => 403]);
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $post_type) {
      return new \WP_Error('cff_rest_invalid_post_type', __('Post type mismatch.', 'cff'), ['status' => 400]);
    }

    $definitions = $this->plugin->get_field_definitions_for_post($post);
    if (!$definitions) {
      $definitions = $this->get_definitions_for_post_type($post_type);
    }
    if (!$definitions) return true;

    $readonly_fields = array_values(array_filter(array_map('sanitize_key', (array) apply_filters('cff_rest_fields_readonly', [], $post_type, $post_id))));
    $readonly_map = array_fill_keys($readonly_fields, true);

    foreach ($value as $field_name => $raw_field_value) {
      $field_name = sanitize_key($field_name);
      if (!$field_name || !isset($definitions[$field_name])) continue;
      if (isset($readonly_map[$field_name])) continue;

      $sanitized = $this->sanitize_field_value($definitions[$field_name], $raw_field_value);
      $meta_key = $this->plugin->meta_key($field_name);
      if ($sanitized === null || $sanitized === '' || (is_array($sanitized) && empty($sanitized))) {
        delete_post_meta($post_id, $meta_key);
      } else {
        update_post_meta($post_id, $meta_key, $sanitized);
      }
    }

    return true;
  }

  public function get_definitions_for_post_type($post_type) {
    $post_type = sanitize_key($post_type);
    if (!$post_type) return [];

    $probe = (object) [
      'ID' => 0,
      'post_type' => $post_type,
    ];

    $groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'publish',
      'numberposts' => -1,
      'no_found_rows' => true,
    ]);

    $definitions = [];
    foreach ($groups as $group) {
      $settings = get_post_meta($group->ID, '_cff_settings', true);
      $location = is_array($settings['location'] ?? null) ? $settings['location'] : [];
      if (!$this->plugin->rest_match_location($probe, $location)) continue;
      foreach ((array) ($settings['fields'] ?? []) as $field) {
        $name = sanitize_key($field['name'] ?? '');
        if ($name) $definitions[$name] = $field;
      }
    }
    return $definitions;
  }

  public function build_schema_properties_for_post_type($post_type) {
    $definitions = $this->get_definitions_for_post_type($post_type);
    $properties = [];
    foreach ($definitions as $name => $field) {
      $name = sanitize_key($name);
      if (!$name) continue;
      $properties[$name] = $this->build_schema_for_field($field);
    }
    return $properties;
  }

  public function build_schema_for_field($field) {
    $type = sanitize_key($field['type'] ?? 'text');
    $schema = [
      'description' => sanitize_text_field($field['label'] ?? $field['name'] ?? $type),
    ];

    if ($type === 'number') {
      $schema['type'] = 'number';
      return $schema;
    }
    if ($type === 'checkbox') {
      $schema['type'] = 'boolean';
      return $schema;
    }
    if ($type === 'choice') {
      $display = sanitize_key($field['choice_display'] ?? 'select');
      $choices = [];
      foreach ((array) ($field['choices'] ?? []) as $choice) {
        $value = sanitize_text_field($choice['value'] ?? ($choice['label'] ?? ''));
        if ($value !== '') $choices[] = $value;
      }
      $choices = array_values(array_unique($choices));
      if ($display === 'checkbox') {
        $schema['type'] = 'array';
        $schema['items'] = ['type' => 'string'];
        if ($choices) $schema['items']['enum'] = $choices;
      } else {
        $schema['type'] = 'string';
        if ($choices) $schema['enum'] = $choices;
      }
      return $schema;
    }
    if ($type === 'link') {
      $schema['type'] = 'object';
      $schema['properties'] = [
        'url' => ['type' => 'string'],
        'title' => ['type' => 'string'],
        'target' => ['type' => 'string'],
      ];
      return $schema;
    }
    if ($type === 'image' || $type === 'file') {
      $schema['type'] = 'object';
      $schema['properties'] = [
        'id' => ['type' => 'integer'],
        'url' => ['type' => 'string'],
      ];
      return $schema;
    }
    if ($type === 'gallery') {
      $schema['type'] = 'array';
      $schema['items'] = ['type' => 'integer'];
      return $schema;
    }
    if ($type === 'group') {
      $schema['type'] = 'object';
      $schema['properties'] = [];
      foreach ((array) ($field['sub_fields'] ?? []) as $sub) {
        $sub_name = sanitize_key($sub['name'] ?? '');
        if (!$sub_name) continue;
        $schema['properties'][$sub_name] = $this->build_schema_for_field($sub);
      }
      return $schema;
    }
    if ($type === 'repeater') {
      $schema['type'] = 'array';
      $item_schema = ['type' => 'object', 'properties' => []];
      foreach ((array) ($field['sub_fields'] ?? []) as $sub) {
        $sub_name = sanitize_key($sub['name'] ?? '');
        if (!$sub_name) continue;
        $item_schema['properties'][$sub_name] = $this->build_schema_for_field($sub);
      }
      $schema['items'] = $item_schema;
      $min = max(0, intval($field['min'] ?? 0));
      $max = max(0, intval($field['max'] ?? 0));
      if ($min > 0) $schema['minItems'] = $min;
      if ($max > 0) $schema['maxItems'] = $max;
      return $schema;
    }
    if ($type === 'flexible') {
      $schema['type'] = 'array';
      $schema['items'] = [
        'type' => 'object',
        'properties' => [
          'layout' => ['type' => 'string'],
          'fields' => ['type' => 'object'],
        ],
      ];
      return $schema;
    }

    $schema['type'] = 'string';
    return $schema;
  }

  public function sanitize_field_value($field, $value) {
    if (is_object($value)) {
      $value = (array) $value;
    }
    $type = sanitize_key($field['type'] ?? 'text');

    if ($type === 'number') {
      if ($value === '' || $value === null) return null;
      return is_numeric($value) ? 0 + $value : null;
    }
    if ($type === 'checkbox') {
      return !empty($value) ? '1' : '0';
    }
    if ($type === 'choice') {
      $display = sanitize_key($field['choice_display'] ?? 'select');
      if ($display === 'checkbox') {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $item) {
          $item = sanitize_text_field($item);
          if ($item !== '') $out[] = $item;
        }
        return array_values(array_unique($out));
      }
      return sanitize_text_field(is_scalar($value) ? (string) $value : '');
    }
    if ($type === 'url') {
      return is_scalar($value) ? esc_url_raw((string) $value) : '';
    }
    if ($type === 'link') {
      if (!is_array($value)) return null;
      return [
        'url' => esc_url_raw($value['url'] ?? ''),
        'title' => sanitize_text_field($value['title'] ?? ''),
        'target' => sanitize_text_field($value['target'] ?? ''),
      ];
    }
    if ($type === 'image' || $type === 'file') {
      if (is_array($value)) {
        if (isset($value['id'])) return absint($value['id']);
        return null;
      }
      return absint($value);
    }
    if ($type === 'gallery') {
      if (!is_array($value)) return [];
      $out = [];
      foreach ($value as $item) {
        $id = absint(is_array($item) ? ($item['id'] ?? 0) : $item);
        if ($id) $out[] = $id;
      }
      return array_values(array_unique($out));
    }
    if ($type === 'repeater') {
      if (is_object($value)) $value = (array) $value;
      if (!is_array($value)) return [];
      $rows = [];
      $sub_map = [];
      foreach ((array) ($field['sub_fields'] ?? []) as $sub) {
        $sub_name = sanitize_key($sub['name'] ?? '');
        if ($sub_name) $sub_map[$sub_name] = $sub;
      }
      foreach ($value as $row) {
        if (!is_array($row)) continue;
        $clean_row = [];
        $row_id = sanitize_key($row['__cff_row_id'] ?? '');
        $clean_row['__cff_row_id'] = $row_id ?: ('row_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12));
        foreach ($sub_map as $sub_name => $sub_field) {
          if (!array_key_exists($sub_name, $row)) continue;
          $clean_row[$sub_name] = $this->sanitize_field_value($sub_field, $row[$sub_name]);
        }
        if ($clean_row) $rows[] = $clean_row;
      }
      return $rows;
    }
    if ($type === 'group') {
      if (is_object($value)) $value = (array) $value;
      if (!is_array($value)) return [];
      $clean = [];
      foreach ((array) ($field['sub_fields'] ?? []) as $sub) {
        $sub_name = sanitize_key($sub['name'] ?? '');
        if (!$sub_name || !array_key_exists($sub_name, $value)) continue;
        $clean[$sub_name] = $this->sanitize_field_value($sub, $value[$sub_name]);
      }
      return $clean;
    }
    if ($type === 'flexible') {
      if (is_object($value)) $value = (array) $value;
      if (!is_array($value)) return [];
      $layout_map = [];
      foreach ((array) ($field['layouts'] ?? []) as $layout) {
        $layout_name = sanitize_key($layout['name'] ?? '');
        if ($layout_name) $layout_map[$layout_name] = $layout;
      }
      $rows = [];
      foreach ($value as $row) {
        if (is_object($row)) $row = (array) $row;
        if (!is_array($row)) continue;
        $layout_name = sanitize_key($row['layout'] ?? '');
        if (!$layout_name || !isset($layout_map[$layout_name])) continue;
        $row_id = sanitize_key($row['__cff_row_id'] ?? '');
        $row_fields = $row['fields'] ?? [];
        if (is_object($row_fields)) $row_fields = (array) $row_fields;
        if (!is_array($row_fields)) $row_fields = [];
        $clean_fields = [];
        foreach ((array) ($layout_map[$layout_name]['sub_fields'] ?? []) as $sub) {
          $sub_name = sanitize_key($sub['name'] ?? '');
          if (!$sub_name || !array_key_exists($sub_name, $row_fields)) continue;
          $clean_fields[$sub_name] = $this->sanitize_field_value($sub, $row_fields[$sub_name]);
        }
        $rows[] = [
          'layout' => $layout_name,
          '__cff_row_id' => $row_id ?: ('row_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12)),
          'fields' => $clean_fields,
        ];
      }
      return $rows;
    }

    if ($type === 'wysiwyg' || $type === 'embed') {
      return wp_kses_post(is_scalar($value) ? (string) $value : '');
    }
    return sanitize_text_field(is_scalar($value) ? (string) $value : '');
  }
}
