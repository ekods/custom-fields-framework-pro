<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Ajax_Controller {
  public function ajax_search_posts() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    $type = sanitize_key($_POST['post_type'] ?? 'post');
    $exclude = isset($_POST['exclude']) ? sanitize_text_field(wp_unslash($_POST['exclude'])) : '';
    $ex = array_filter(array_map('intval', explode(',', $exclude)));
    $posts = get_posts([
      's' => $q,
      'post_type' => $type,
      'post_status' => 'publish',
      'numberposts' => 20,
      'post__not_in' => $ex,
    ]);
    $out = [];
    foreach ($posts as $p) {
      $pt = get_post_type($p->ID);
      $pt_obj = $pt ? get_post_type_object($pt) : null;
      $pt_label = $pt_obj && !empty($pt_obj->labels->singular_name) ? $pt_obj->labels->singular_name : $pt;
      $label = trim($pt_label . ' - ' . $p->post_title);
      $out[] = [
        'id' => $p->ID,
        'text' => $label,
        'title' => $p->post_title,
        'meta' => '',
        'url' => get_permalink($p->ID),
      ];
    }
    wp_send_json_success($out);
  }

  public function ajax_search_relational() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $type = sanitize_key($_POST['relational_type'] ?? 'post');
    $subtype = sanitize_key($_POST['relational_subtype'] ?? '');
    $query = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
    $page = max(1, absint($_POST['page'] ?? 1));
    $limit = 20;
    $results = [];
    $more = false;

    if ($type === 'taxonomy') {
      if (!$subtype || !taxonomy_exists($subtype)) wp_send_json_error(['message' => __('Invalid taxonomy.', 'cff')], 400);
      $terms = get_terms([
        'taxonomy' => $subtype,
        'hide_empty' => false,
        'search' => $query,
        'number' => $limit + 1,
        'offset' => ($page - 1) * $limit,
        'orderby' => 'name',
      ]);
      if (is_wp_error($terms)) wp_send_json_error(['message' => $terms->get_error_message()], 400);
      $more = count($terms) > $limit;
      foreach (array_slice($terms, 0, $limit) as $term) {
        $results[] = ['id' => $term->term_id, 'text' => $term->name];
      }
    } elseif ($type === 'user') {
      if (!current_user_can('list_users')) {
        wp_send_json_error(['message' => __('You are not allowed to list users.', 'cff')], 403);
      }
      $users = get_users([
        'number' => $limit + 1,
        'offset' => ($page - 1) * $limit,
        'orderby' => 'display_name',
        'search' => $query === '' ? '' : '*' . $query . '*',
        'search_columns' => ['user_login', 'user_nicename', 'display_name'],
        'fields' => ['ID', 'display_name'],
      ]);
      $more = count($users) > $limit;
      foreach (array_slice($users, 0, $limit) as $user) {
        $results[] = ['id' => $user->ID, 'text' => $user->display_name];
      }
    } else {
      $post_types = ['post'];
      if ($type === 'page') $post_types = ['page'];
      if ($type === 'post_and_page') $post_types = ['post', 'page'];
      if ($type === 'post_type') {
        if (!$subtype || !post_type_exists($subtype)) wp_send_json_error(['message' => __('Invalid post type.', 'cff')], 400);
        $post_types = [$subtype];
      }
      if (!in_array($type, ['post', 'page', 'post_and_page', 'post_type'], true)) {
        wp_send_json_error(['message' => __('Invalid relational type.', 'cff')], 400);
      }

      $posts = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        's' => $query,
        'posts_per_page' => $limit + 1,
        'offset' => ($page - 1) * $limit,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
      ]);
      $more = count($posts) > $limit;
      foreach (array_slice($posts, 0, $limit) as $item) {
        $results[] = ['id' => $item->ID, 'text' => $item->post_title ?: __('(no title)', 'cff')];
      }
    }

    wp_send_json_success(['results' => $results, 'pagination' => ['more' => $more]]);
  }

  public function ajax_get_templates() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $theme = wp_get_theme();
    $templates = $theme->get_page_templates();
    $out = [['id'=>'default','text'=>'Default Template','meta'=>'']];

    foreach ($templates as $file => $name) {
      $out[] = [
        'id' => $file,
        'text' => $name,
        'meta' => $file,
      ];
    }

    wp_send_json_success($out);
  }

  public function ajax_get_post_types() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $post_types = get_post_types(['show_ui' => true], 'objects');
    $out = [];
    foreach ($post_types as $post_type) {
      if ($post_type->name === 'cff_group') continue;
      $out[] = [
        'id' => $post_type->name,
        'text' => $post_type->labels->singular_name,
        'meta' => $post_type->name,
      ];
    }
    wp_send_json_success($out);
  }
}
