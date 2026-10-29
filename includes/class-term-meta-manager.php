<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Term_Meta_Manager {
  public function register_term_meta_fields() {
    $taxonomies = get_taxonomies(['public'=>true], 'names') + get_taxonomies(['show_ui'=>true], 'names');
    foreach ($taxonomies as $tax) {
      add_action($tax . '_add_form_fields', [$this, 'render_term_fields_add'], 10, 1);
      add_action($tax . '_edit_form_fields', [$this, 'render_term_fields_edit'], 10, 2);
      add_action('created_' . $tax, [$this, 'save_term_fields'], 10, 2);
      add_action('edited_' . $tax, [$this, 'save_term_fields'], 10, 2);
    }
  }

  public function render_term_fields_add($taxonomy) {
    wp_nonce_field('cff_term_meta_save', 'cff_term_meta_nonce');
    echo '<div class="form-field cff-term-field">';
    echo '<label>Image</label>';
    echo '<div class="cff-term-image">';
    echo '<input type="hidden" class="cff-term-image-id" name="cffp_term_image_id" value="">';
    echo '<div class="cff-term-image-preview">' . $this->term_image_preview(0) . '</div>';
    echo '<p><button type="button" class="button cff-term-image-select">Select</button> ';
    echo '<button type="button" class="button cff-term-image-clear">Clear</button></p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-field cff-term-field">';
    echo '<label>Short Description</label>';
    echo '<textarea name="cffp_term_short_description" rows="3"></textarea>';
    echo '</div>';

    echo '<div class="form-field cff-term-field">';
    echo '<label>Description</label>';
    echo '<textarea name="cffp_term_description" rows="5"></textarea>';
    echo '</div>';
  }

  public function render_term_fields_edit($term, $taxonomy) {
    $image_id = (int) get_term_meta($term->term_id, 'cffp_term_image_id', true);
    $short = (string) get_term_meta($term->term_id, 'cffp_term_short_description', true);
    $desc = (string) get_term_meta($term->term_id, 'cffp_term_description', true);

    echo '<tr class="form-field cff-term-field"><th scope="row"><label>Image</label></th><td>';
    wp_nonce_field('cff_term_meta_save', 'cff_term_meta_nonce');
    echo '<div class="cff-term-image">';
    echo '<input type="hidden" class="cff-term-image-id" name="cffp_term_image_id" value="' . esc_attr($image_id) . '">';
    echo '<div class="cff-term-image-preview">' . $this->term_image_preview($image_id) . '</div>';
    echo '<p><button type="button" class="button cff-term-image-select">Select</button> ';
    echo '<button type="button" class="button cff-term-image-clear">Clear</button></p>';
    echo '</div>';
    echo '</td></tr>';

    echo '<tr class="form-field cff-term-field"><th scope="row"><label>Short Description</label></th><td>';
    echo '<textarea name="cffp_term_short_description" rows="3">' . esc_textarea($short) . '</textarea>';
    echo '</td></tr>';

    echo '<tr class="form-field cff-term-field"><th scope="row"><label>Description</label></th><td>';
    echo '<textarea name="cffp_term_description" rows="5">' . esc_textarea($desc) . '</textarea>';
    echo '</td></tr>';
  }

  public function save_term_fields($term_id, $tt_id = 0) {
    if (!isset($_POST['cff_term_meta_nonce']) || !is_string($_POST['cff_term_meta_nonce'])) return;
    if (!wp_verify_nonce(wp_unslash($_POST['cff_term_meta_nonce']), 'cff_term_meta_save')) return;
    if (!current_user_can('edit_term', $term_id)) return;

    // Quick edit, REST, importers, and partial submissions must preserve omitted fields.
    foreach (['cffp_term_image_id', 'cffp_term_short_description', 'cffp_term_description'] as $key) {
      if (!array_key_exists($key, $_POST) || !is_scalar($_POST[$key])) continue;
      $value = wp_unslash($_POST[$key]);
      $value = $key === 'cffp_term_image_id' ? absint($value) : wp_kses_post($value);
      if ($value === '' || $value === 0) {
        delete_term_meta($term_id, $key);
      } else {
        update_term_meta($term_id, $key, wp_slash($value));
      }
    }
  }

  private function term_image_preview($image_id) {
    if ($image_id) {
      $img = wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'cff-term-thumb']);
      if ($img) return $img;
    }
    return '<span class="description">No image selected</span>';
  }
}
