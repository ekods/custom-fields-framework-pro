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
      if (in_array($post_type, ['cff_group', 'revision'], true)) continue;

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
    if (!$definitions) return true;

    $readonly_fields = array_values(array_filter(array_map('sanitize_key', (array) apply_filters('cff_rest_fields_readonly', [], $post_type, $post_id))));
    $readonly_map = array_fill_keys($readonly_fields, true);

    foreach ($value as $field_name => $raw_field_value) {
      $field_name = sanitize_key($field_name);
      if (!$field_name || !isset($definitions[$field_name])) continue;
      if (isset($readonly_map[$field_name])) continue;

      $sanitized = $this->sanitize_field_value($definitions[$field_name], $raw_field_value);
      $meta_key = $this->plugin->meta_key($field_name);
      $is_media_field = in_array(sanitize_key($definitions[$field_name]['type'] ?? ''), ['image', 'file'], true);
      if ($is_media_field) {
        delete_post_meta($post_id, $this->plugin->meta_key($field_name . '_url'));
      }
      if (
        $sanitized === null
        || $sanitized === ''
        || (is_array($sanitized) && empty($sanitized))
        || ($is_media_field && !absint($sanitized))
      ) {
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
    $this->sort_groups_by_setting_order($groups);

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

  private function sort_groups_by_setting_order(&$groups) {
    if (!is_array($groups) || count($groups) < 2) return;

    usort($groups, function($a, $b) {
      $settings_a = get_post_meta($a->ID ?? 0, '_cff_settings', true);
      $settings_b = get_post_meta($b->ID ?? 0, '_cff_settings', true);
      $order_a = is_array($settings_a) ? intval($settings_a['presentation']['order'] ?? 0) : 0;
      $order_b = is_array($settings_b) ? intval($settings_b['presentation']['order'] ?? 0) : 0;
      if ($order_a !== $order_b) return $order_a <=> $order_b;

      $title_compare = strcasecmp($a->post_title ?? '', $b->post_title ?? '');
      if ($title_compare !== 0) return $title_compare;

      return intval($a->ID ?? 0) <=> intval($b->ID ?? 0);
    });
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
        'mode' => ['type' => 'string', 'enum' => ['internal', 'custom']],
        'url' => ['type' => 'string'],
        'title' => ['type' => 'string'],
        'target' => ['type' => 'string'],
        'internal_id' => ['type' => 'integer'],
        'post_type_filter' => ['type' => 'string'],
        'parameter' => ['type' => 'string'],
        'hash' => ['type' => 'string'],
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
    return $this->plugin->field_sanitizer()->sanitize($field, $value);
  }
}
