<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Dynamic_Content_Manager {
  public function register_dynamic_cpts() {
    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs)) return;

    foreach ($defs as $key => $def) {
      $key = sanitize_key($key);
      if (!$key) continue;

      $singular = sanitize_text_field($def['singular'] ?? ucfirst($key));
      $plural = sanitize_text_field($def['plural'] ?? $singular . 's');
      $slug = sanitize_title($def['slug'] ?? $key);
      $singular = $this->i18n_value($def, 'singular_i18n', $singular);
      $plural = $this->i18n_value($def, 'plural_i18n', $plural);
      if (!$this->polylang_active()) {
        $slug = $this->i18n_slug($def, 'slug_i18n', $slug);
      }
      $public = !empty($def['public']);
      $has_archive = !empty($def['has_archive']);
      $show_in_rest = !empty($def['show_in_rest']);
      $supports = (isset($def['supports']) && is_array($def['supports']))
        ? array_values(array_map('sanitize_key', $def['supports']))
        : ['title','editor'];

      $menu_icon_raw = isset($def['menu_icon']) ? trim((string) $def['menu_icon']) : '';
      $menu_icon = '';
      if ($menu_icon_raw !== '') {
        if (strpos($menu_icon_raw, 'dashicons-') === 0) {
          $menu_icon = preg_replace('/[^a-z0-9\-_]/i', '', $menu_icon_raw);
        } else {
          $menu_icon = esc_url_raw($menu_icon_raw);
        }
      }

      register_post_type($key, [
        'labels' => [
          'name' => $plural,
          'singular_name' => $singular,
          'add_new_item' => sprintf(__('Add New %s','cff'), $singular),
          'edit_item' => sprintf(__('Edit %s','cff'), $singular),
        ],
        'public' => $public,
        'has_archive' => $has_archive,
        'rewrite' => !empty($def['block_single']) ? false : ['slug'=>$slug],
        'publicly_queryable' => $public && empty($def['block_single']),
        'show_in_rest' => $show_in_rest,
        'show_ui' => array_key_exists('show_ui',$def) ? (bool)$def['show_ui'] : $public,
        'query_var' => array_key_exists('query_var',$def)
          ? (bool)$def['query_var']
          : (!empty($def['block_single']) ? false : true),
        'show_admin_column' => array_key_exists('show_admin_column',$def) ? (bool)$def['show_admin_column'] : true,
        'supports' => $supports,
        'taxonomies' => (isset($def['taxonomies']) && is_array($def['taxonomies']))
          ? array_map('sanitize_key',$def['taxonomies'])
          : [],
        'menu_position' => isset($def['menu_position']) ? max(0, intval($def['menu_position'])) : 25,
        'menu_icon' => $menu_icon ?: 'dashicons-admin-post',
      ]);

      if ($public && $has_archive) {
        $this->add_archive_rewrite_rules($key, $slug);
      }

      if (!empty($def['list_thumbnail']) && in_array('thumbnail', $supports, true)) {
        $this->register_admin_thumbnail_column($key);
      }
      if (!empty($def['block_single'])) {
        $this->register_block_single_views($key);
      }
    }
  }

  public function register_dynamic_taxonomies() {
    $defs = get_option('cffp_taxonomies', []);
    if (!is_array($defs)) return;

    foreach ($defs as $key => $def) {
      $tax = sanitize_key($key);
      if (!$tax) continue;

      $singular = sanitize_text_field($def['singular'] ?? ucfirst($tax));
      $plural = sanitize_text_field($def['plural'] ?? $singular . 's');
      $singular = $this->i18n_value($def, 'singular_i18n', $singular);
      $plural = $this->i18n_value($def, 'plural_i18n', $plural);
      $public = !empty($def['public']);
      $hier = !empty($def['hierarchical']);
      $show_in_rest = !empty($def['show_in_rest']);
      $ptypes = isset($def['post_types']) && is_array($def['post_types'])
        ? array_map('sanitize_key',$def['post_types'])
        : ['post'];

      register_taxonomy($tax, $ptypes, [
        'labels' => [
          'name' => $plural,
          'singular_name' => $singular,
          'add_new_item' => sprintf(__('Add New %s','cff'), $singular),
          'edit_item' => sprintf(__('Edit %s','cff'), $singular),
        ],
        'public' => $public,
        'hierarchical' => $hier,
        'show_in_rest' => $show_in_rest,
        'show_ui' => array_key_exists('show_ui',$def) ? (bool)$def['show_ui'] : $public,
        'query_var' => array_key_exists('query_var',$def) ? (bool)$def['query_var'] : true,
        'show_admin_column' => array_key_exists('show_admin_column',$def) ? (bool)$def['show_admin_column'] : true,
        'rewrite' => [
          'slug' => $this->polylang_active()
            ? sanitize_title($def['slug'] ?? $tax)
            : $this->i18n_slug($def, 'slug_i18n', sanitize_title($def['slug'] ?? $tax)),
          'with_front' => !empty($def['with_front']),
        ],
      ]);
    }
  }

  public function remove_post_views_id() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit' || empty($screen->post_type)) return;
    if (!$this->is_block_single_post_type($screen->post_type)) return;
    echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("post-views");if(el){el.removeAttribute("id");}});})();</script>';
  }

  private function add_archive_rewrite_rules($post_type, $slug) {
    $archive_slug = $slug ?: $post_type;
    $archive_slug = trim((string) $archive_slug, '/');
    if ($archive_slug === '') return;

    add_rewrite_rule(
      '^' . preg_quote($archive_slug, '/') . '/?$',
      'index.php?post_type=' . $post_type,
      'top'
    );
    add_rewrite_rule(
      '^' . preg_quote($archive_slug, '/') . '/page/([0-9]+)/?$',
      'index.php?post_type=' . $post_type . '&paged=$matches[1]',
      'top'
    );
  }

  private function register_admin_thumbnail_column($post_type) {
    $label = __('Thumbnail', 'cff');
    add_filter("manage_{$post_type}_posts_columns", function($columns) use ($label) {
      if (isset($columns['cff_thumbnail'])) {
        return $columns;
      }
      $output = [];
      foreach ($columns as $slug => $name) {
        $output[$slug] = $name;
        if ($slug === 'cb') {
          $output['cff_thumbnail'] = $label;
        }
      }
      if (!isset($output['cff_thumbnail'])) {
        $output = array_merge(['cff_thumbnail' => $label], $output);
      }
      return $output;
    });

    add_action('admin_head', function() use ($post_type) {
      $screen = function_exists('get_current_screen') ? get_current_screen() : null;
      if (!$screen || $screen->base !== 'edit' || $screen->post_type !== $post_type) {
        return;
      }
      echo '<style>.wp-list-table th.column-cff_thumbnail, .wp-list-table td.column-cff_thumbnail {width:100px;}</style>';
    });

    add_action("manage_{$post_type}_posts_custom_column", function($column, $post_id) {
      if ($column !== 'cff_thumbnail') return;
      $image = get_the_post_thumbnail($post_id, [80, 80], ['style' => 'width:80px;height:auto;display:block;object-fit:cover;']);
      if ($image) {
        echo '<div style="width:80px;height:80px;display:flex;align-items:center;justify-content:center;">' . $image . '</div>';
      } else {
        echo '<div style="width:80px;height:80px;display:flex;align-items:center;justify-content:center;"><span class="cff-muted">—</span></div>';
      }
    }, 10, 2);
  }

  private function register_block_single_views($post_type) {
    add_filter("manage_{$post_type}_posts_columns", function($columns) {
      foreach (array_keys($columns) as $key) {
        if ($key === 'cb') continue;
        if (stripos($key, 'view') !== false) {
          unset($columns[$key]);
        }
      }
      return $columns;
    }, 15);

    add_action('admin_head', function() use ($post_type) {
      $screen = function_exists('get_current_screen') ? get_current_screen() : null;
      if (!$screen || $screen->base !== 'edit' || $screen->post_type !== $post_type) {
        return;
      }
      echo '<style>.wp-list-table th[class*="column-view"], .wp-list-table td[class*="column-view"] {display:none!important;}</style>';
    });
  }

  private function is_block_single_post_type($post_type) {
    $def = $this->get_cpt_definition($post_type);
    return !empty($def['block_single']);
  }

  private function get_cpt_definition($post_type) {
    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs)) return null;
    if (!isset($defs[$post_type]) || !is_array($defs[$post_type])) return null;
    return $defs[$post_type];
  }

  private function i18n_value($def, $key, $fallback) {
    $lang = $this->current_lang();
    $map = isset($def[$key]) && is_array($def[$key]) ? $def[$key] : [];
    if ($lang && !empty($map[$lang])) return sanitize_text_field($map[$lang]);
    return $fallback;
  }

  private function i18n_slug($def, $key, $fallback) {
    $lang = $this->current_lang();
    $map = isset($def[$key]) && is_array($def[$key]) ? $def[$key] : [];
    if ($lang && !empty($map[$lang])) return sanitize_title($map[$lang]);
    return $fallback;
  }

  private function current_lang() {
    if (function_exists('pll_current_language')) {
      $lang = pll_current_language('slug');
      if ($lang) return $lang;
    }
    return '';
  }

  private function polylang_active() {
    return function_exists('pll_current_language');
  }
}
