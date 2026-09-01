<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Content_Field_Saver {
  private $plugin;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function save($post_id, $post) {
    if ($post->post_type === 'cff_group') return;
    if (!isset($_POST['cff_content_nonce']) || !wp_verify_nonce($_POST['cff_content_nonce'], 'cff_content_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $copy_all_to_translations = !empty($_POST['cff_copy_all_to_translations_trigger']);
    $copy_field_to_translations = isset($_POST['cff_copy_field_to_translations']) ? wp_unslash((string) $_POST['cff_copy_field_to_translations']) : '';

    $this->save_field_order($post_id);

    $definitions = $this->plugin->get_field_definitions_for_post($post);
    $this->save_hidden_sections($post_id, $definitions);

    if (!isset($_POST['cff_values']) || !is_array($_POST['cff_values'])) {
      $this->maybe_copy_to_translations($post_id, $copy_all_to_translations, $copy_field_to_translations);
      return;
    }

    $vals = wp_unslash($_POST['cff_values']);
    foreach ($vals as $name => $value) {
      $this->save_field_value($post_id, $definitions, $vals, $name, $value);
    }

    $this->maybe_copy_to_translations($post_id, $copy_all_to_translations, $copy_field_to_translations);
  }

  private function save_field_order($post_id) {
    $active_field_orders = isset($_POST['cff_group_field_order_active']) && is_array($_POST['cff_group_field_order_active'])
      ? wp_unslash($_POST['cff_group_field_order_active'])
      : [];

    if (!isset($_POST['cff_group_field_order']) || !is_array($_POST['cff_group_field_order'])) {
      return;
    }

    foreach ((array) $_POST['cff_group_field_order'] as $group_id => $raw_order) {
      $group_id = absint($group_id);
      if (!$group_id) continue;

      $meta_key = '_cff_group_field_order_' . $group_id;
      $custom_meta_key = '_cff_group_field_order_custom_' . $group_id;
      if (empty($active_field_orders[$group_id])) {
        delete_post_meta($post_id, $meta_key);
        delete_post_meta($post_id, $custom_meta_key);
        continue;
      }

      $items = [];
      $raw_order = wp_unslash($raw_order);
      if (is_array($raw_order)) {
        $items = array_map('sanitize_key', $raw_order);
      } elseif (is_string($raw_order)) {
        $items = array_map('sanitize_key', explode(',', $raw_order));
      }
      $items = array_values(array_filter(array_unique($items)));

      if ($items) {
        update_post_meta($post_id, $meta_key, $items);
        update_post_meta($post_id, $custom_meta_key, '1');
      } else {
        delete_post_meta($post_id, $meta_key);
        delete_post_meta($post_id, $custom_meta_key);
      }
    }
  }

  private function save_hidden_sections($post_id, $definitions) {
    $posted_hidden_sections = isset($_POST['cff_hidden_sections']) && is_array($_POST['cff_hidden_sections'])
      ? wp_unslash($_POST['cff_hidden_sections'])
      : [];
    $hidden_sections = [];
    foreach ($posted_hidden_sections as $section_name => $hidden) {
      $section_name = sanitize_key($section_name);
      if (!$section_name || !isset($definitions[$section_name])) continue;
      if (!empty($hidden)) {
        $hidden_sections[$section_name] = true;
      }
    }

    if ($hidden_sections) {
      update_post_meta($post_id, '_cff_hidden_sections', $hidden_sections);
      update_post_meta($post_id, '_cff_hidden_sections_local', $this->hidden_sections_marker($post_id));
      return;
    }

    delete_post_meta($post_id, '_cff_hidden_sections');
    delete_post_meta($post_id, '_cff_hidden_sections_local');
  }

  private function hidden_sections_marker($post_id) {
    $marker = '1';
    if (function_exists('pll_get_post_language')) {
      $post_lang = pll_get_post_language($post_id, 'slug');
      if (is_string($post_lang) && $post_lang !== '') {
        $marker = sanitize_key($post_lang);
      }
    }
    return $marker;
  }

  private function save_field_value($post_id, $definitions, $vals, $name, $value) {
    $name = sanitize_key($name);
    if (!$name || !isset($definitions[$name])) return;

    $key = $this->plugin->meta_key($name);
    $field = $definitions[$name];
    $is_media_field = in_array(($field['type'] ?? ''), ['image', 'file'], true);
    if ($is_media_field && isset($vals[$name . '_url'])) {
      $value = [
        'id' => $value,
        'url' => $vals[$name . '_url'],
      ];
    }

    $value = $this->plugin->field_sanitizer()->sanitize($field, $value);

    if ($is_media_field) {
      delete_post_meta($post_id, $this->plugin->meta_key($name . '_url'));
      if (!absint($value)) {
        delete_post_meta($post_id, $key);
        return;
      }
    }

    if (($field['type'] ?? '') === 'group' && is_array($value)) {
      $existing = get_post_meta($post_id, $key, true);
      if (is_array($existing)) $value = deep_merge_assoc($existing, $value);
    }

    if ($value === null || $value === '' || (is_array($value) && !$value)) {
      delete_post_meta($post_id, $key);
      return;
    }

    update_post_meta($post_id, $key, $value);
  }

  private function maybe_copy_to_translations($post_id, $copy_all, $copy_field) {
    if ($copy_all) {
      cff_copy_values_to_polylang_translations($post_id);
    } elseif ($copy_field) {
      cff_copy_field_to_polylang_translations($this->plugin, $post_id, $copy_field);
    }
  }
}
