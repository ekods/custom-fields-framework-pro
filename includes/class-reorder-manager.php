<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Reorder_Manager {
  private $plugin;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function ajax_reorder_get_posts() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $pt = sanitize_key($_POST['post_type'] ?? '');
    if (!$pt || !post_type_exists($pt)) wp_send_json_error(['message'=>'Invalid post_type'], 400);

    $posts = get_posts([
      'post_type' => $pt,
      'post_status' => 'any',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'numberposts' => -1,
      'no_found_rows' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ]);

    $post_type_object = get_post_type_object($pt);
    if ($post_type_object && !empty($post_type_object->hierarchical)) {
      $posts = $this->sort_posts_hierarchically($posts);
    }

    $out = [];
    foreach ($posts as $p) {
      $out[] = [
        'id' => $p->ID,
        'title' => $p->post_title ?: '(no title)',
        'status' => $p->post_status,
        'parent' => (int) $p->post_parent,
        'depth' => (int) ($p->cffp_depth ?? 0),
      ];
    }
    wp_send_json_success($out);
  }

  public function ajax_reorder_save_posts() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $pt = sanitize_key($_POST['post_type'] ?? '');
    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('absint', $_POST['order']) : [];
    if (!$pt || !post_type_exists($pt) || !$order) wp_send_json_error(['message'=>'Invalid payload'], 400);

    $posts = get_posts([
      'post_type' => $pt,
      'post_status' => 'any',
      'post__in' => $order,
      'numberposts' => -1,
      'orderby' => 'post__in',
      'no_found_rows' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ]);

    $posts_by_id = [];
    foreach ($posts as $post) {
      $posts_by_id[(int) $post->ID] = $post;
    }

    $parent_counts = [];
    foreach ($posts as $post) {
      $parent_counts[(int) $post->post_parent] = 0;
    }

    $updates = [];
    foreach ($order as $id) {
      $id = absint($id);
      if (!$id) continue;

      $post = $posts_by_id[$id] ?? null;
      if (!$post || $post->post_type !== $pt) continue;

      $parent_id = (int) $post->post_parent;
      $menu_order = $parent_counts[$parent_id] ?? 0;
      $updates[$id] = $menu_order;
      $parent_counts[$parent_id] = $menu_order + 1;
    }

    $this->persist_post_menu_order($updates);

    $enabled = get_option('cffp_reorder_post_types', []);
    if (!is_array($enabled)) $enabled = [];
    if (!in_array($pt, $enabled, true)) {
      $enabled[] = $pt;
      update_option('cffp_reorder_post_types', $enabled);
    }

    wp_send_json_success(['count' => count($order)]);
  }

  public function ajax_reorder_get_terms() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $tax = sanitize_key($_POST['taxonomy'] ?? '');
    if (!$tax || !taxonomy_exists($tax)) wp_send_json_error(['message'=>'Invalid taxonomy'], 400);

    $terms = get_terms([
      'taxonomy' => $tax,
      'hide_empty' => false,
      'update_term_meta_cache' => true,
    ]);
    if (is_wp_error($terms)) wp_send_json_error(['message'=>'Failed to load terms'], 500);

    $term_orders = [];
    foreach ($terms as $term) {
      $term_orders[(int) $term->term_id] = (int) get_term_meta($term->term_id, 'cffp_term_order', true);
    }

    usort($terms, function($a, $b) use ($term_orders) {
      $oa = $term_orders[(int) $a->term_id] ?? 0;
      $ob = $term_orders[(int) $b->term_id] ?? 0;
      if ($oa === $ob) return strcasecmp($a->name, $b->name);
      return ($oa < $ob) ? -1 : 1;
    });

    $out = [];
    foreach ($terms as $t) {
      $out[] = [
        'id' => $t->term_id,
        'title' => $t->name,
        'count' => $t->count,
      ];
    }
    wp_send_json_success($out);
  }

  public function ajax_reorder_save_terms() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $tax = sanitize_key($_POST['taxonomy'] ?? '');
    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('absint', $_POST['order']) : [];
    if (!$tax || !taxonomy_exists($tax) || !$order) wp_send_json_error(['message'=>'Invalid payload'], 400);

    foreach ($order as $i => $id) {
      if ($id) update_term_meta($id, 'cffp_term_order', $i);
    }

    $enabled = get_option('cffp_reorder_taxonomies', []);
    if (!is_array($enabled)) $enabled = [];
    if (!in_array($tax, $enabled, true)) {
      $enabled[] = $tax;
      update_option('cffp_reorder_taxonomies', $enabled);
    }

    wp_send_json_success(['count' => count($order)]);
  }

  public function ajax_reorder_get_groups() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'any',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
      'no_found_rows' => true,
    ]);

    if (is_wp_error($groups)) wp_send_json_error(['message' => 'Failed to load field groups'], 500);

    $this->sort_field_groups_by_setting_order($groups);

    $out = [];
    foreach ($groups as $g) {
      $settings = $this->plugin->get_group_settings($g->ID);
      $presentation = is_array($settings['presentation'] ?? []) ? $settings['presentation'] : [];
      $out[] = [
        'id' => $g->ID,
        'title' => $g->post_title ? $g->post_title : '(no title)',
        'status' => $g->post_status,
        'order' => intval($presentation['order'] ?? 0),
      ];
    }

    wp_send_json_success($out);
  }

  public function ajax_reorder_save_groups() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) wp_send_json_error(['message'=>'Forbidden'], 403);

    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('absint', $_POST['order']) : [];
    if (!$order) wp_send_json_error(['message'=>'Invalid payload'], 400);

    foreach ($order as $i => $id) {
      if (!$id) continue;
      $group = get_post($id);
      if (!$group || $group->post_type !== 'cff_group') continue;
      $settings = $this->plugin->get_group_settings($id);
      $presentation = isset($settings['presentation']) && is_array($settings['presentation']) ? $settings['presentation'] : [];
      $presentation['order'] = $i;
      $settings['presentation'] = $this->plugin->sanitize_presentation($presentation);
      update_post_meta($id, '_cff_settings', $settings);
      $this->plugin->set_group_settings_cache($id, $settings);
    }

    wp_send_json_success(['count' => count($order)]);
  }

  private function persist_post_menu_order($updates) {
    if (!$updates) return;

    if (count($updates) < 100) {
      foreach ($updates as $id => $menu_order) {
        wp_update_post(['ID' => $id, 'menu_order' => $menu_order]);
      }
      return;
    }

    global $wpdb;
    foreach (array_chunk($updates, 200, true) as $chunk) {
      $case = [];
      $case_values = [];
      $ids = [];
      foreach ($chunk as $id => $menu_order) {
        $case[] = 'WHEN %d THEN %d';
        $case_values[] = absint($id);
        $case_values[] = intval($menu_order);
        $ids[] = absint($id);
      }
      if (!$ids) continue;

      $id_placeholders = implode(',', array_fill(0, count($ids), '%d'));
      $sql = "UPDATE {$wpdb->posts} SET menu_order = CASE ID " . implode(' ', $case)
        . " ELSE menu_order END WHERE ID IN ({$id_placeholders})";
      $wpdb->query($wpdb->prepare($sql, array_merge($case_values, $ids)));
      foreach ($ids as $id) clean_post_cache($id);
    }
  }

  private function sort_posts_hierarchically(array $posts) {
    if (!$posts) return [];

    $children = [];
    $indexed = [];

    foreach ($posts as $post) {
      $post_id = (int) $post->ID;
      $indexed[$post_id] = $post;
      $parent_id = (int) $post->post_parent;
      $children[$parent_id][] = $post;
    }

    $sorted = [];
    $visited = [];
    $walk = function($parent_id, $depth) use (&$walk, &$children, &$sorted, &$visited, &$indexed) {
      if (empty($children[$parent_id])) return;
      foreach ($children[$parent_id] as $child) {
        $child_id = (int) $child->ID;
        if (isset($visited[$child_id])) continue;
        $visited[$child_id] = true;
        $child->cffp_depth = $depth;
        $sorted[] = $child;
        $walk($child_id, $depth + 1);
      }
    };

    $walk(0, 0);

    foreach ($indexed as $post_id => $post) {
      if (isset($visited[$post_id])) continue;
      $post->cffp_depth = 0;
      $sorted[] = $post;
      $walk($post_id, 1);
    }

    return $sorted;
  }

  private function sort_field_groups_by_setting_order(&$groups) {
    if (!is_array($groups) || count($groups) < 2) return;

    usort($groups, function($a, $b) {
      $settings_a = $this->plugin->get_group_settings($a->ID ?? 0);
      $settings_b = $this->plugin->get_group_settings($b->ID ?? 0);
      $super_a = !empty($settings_a['presentation']['super_priority']) ? 1 : 0;
      $super_b = !empty($settings_b['presentation']['super_priority']) ? 1 : 0;
      if ($super_a !== $super_b) return $super_b <=> $super_a;

      $order_a = intval($settings_a['presentation']['order'] ?? 0);
      $order_b = intval($settings_b['presentation']['order'] ?? 0);
      if ($order_a !== $order_b) return $order_a <=> $order_b;

      $title_compare = strcasecmp($a->post_title ?? '', $b->post_title ?? '');
      if ($title_compare !== 0) return $title_compare;

      return intval($a->ID ?? 0) <=> intval($b->ID ?? 0);
    });
  }
}
