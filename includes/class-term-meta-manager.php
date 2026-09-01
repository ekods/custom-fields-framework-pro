<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Term_Meta_Manager {
  public function register_term_meta_fields() {
    $taxonomies = get_taxonomies(['public'=>true], 'names');
    foreach ($taxonomies as $tax) {
      add_action($tax . '_add_form_fields', [$this, 'render_term_fields_add'], 10, 1);
      add_action($tax . '_edit_form_fields', [$this, 'render_term_fields_edit'], 10, 2);
      add_action('created_' . $tax, [$this, 'save_term_fields'], 10, 2);
      add_action('edited_' . $tax, [$this, 'save_term_fields'], 10, 2);
    }
  }

  public function render_term_fields_add($taxonomy) {
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
    if (!current_user_can('manage_categories')) return;
    $image_id = isset($_POST['cffp_term_image_id']) ? absint($_POST['cffp_term_image_id']) : 0;
    $short = isset($_POST['cffp_term_short_description']) ? wp_kses_post(wp_unslash($_POST['cffp_term_short_description'])) : '';
    $desc = isset($_POST['cffp_term_description']) ? wp_kses_post(wp_unslash($_POST['cffp_term_description'])) : '';

    if ($image_id) {
      update_term_meta($term_id, 'cffp_term_image_id', $image_id);
    } else {
      delete_term_meta($term_id, 'cffp_term_image_id');
    }

    if ($short !== '') {
      update_term_meta($term_id, 'cffp_term_short_description', $short);
    } else {
      delete_term_meta($term_id, 'cffp_term_short_description');
    }

    if ($desc !== '') {
      update_term_meta($term_id, 'cffp_term_description', $desc);
    } else {
      delete_term_meta($term_id, 'cffp_term_description');
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
