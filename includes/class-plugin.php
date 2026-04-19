<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Plugin {
  private static $instance = null;
  private $tools_page = null;
  private $rest_fields = null;

  public static function instance() {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  private function tools_page() {
    if ($this->tools_page === null) {
      $this->tools_page = new Tools_Page($this);
    }
    return $this->tools_page;
  }

  private function rest_fields() {
    if ($this->rest_fields === null) {
      $this->rest_fields = new Rest_Fields($this);
    }
    return $this->rest_fields;
  }

  public static function register_cpt() {
    register_post_type('cff_group', [
      'labels' => [
        'name' => __('Field Groups', 'cff'),
        'singular_name' => __('Field Group', 'cff'),
        'add_new_item' => __('Add New Field Group', 'cff'),
        'edit_item' => __('Edit Field Group', 'cff'),
        'search_items' => __('Search Field Groups', 'cff'),
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false,
      'supports' => ['title'],
      'menu_icon' => 'dashicons-feedback',
      'capability_type' => 'post',
    ]);
  }

  public static function register_options_cpt() {
    register_post_type('cff_options', [
      'labels' => [
        'name' => __('Global Settings', 'cff'),
        'singular_name' => __('Global Setting', 'cff'),
      ],
      'public' => false,
      'show_ui' => false,
      'show_in_menu' => false,
      'supports' => ['title'],
      'capability_type' => 'post',
    ]);
  }

  public function register_dynamic_cpts() {
    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs)) return;

    foreach ($defs as $key => $def) {
      $key = sanitize_key($key);
      if (!$key) continue;

      $singular = sanitize_text_field($def['singular'] ?? ucfirst($key));
      $plural   = sanitize_text_field($def['plural'] ?? $singular . 's');
      $slug     = sanitize_title($def['slug'] ?? $key);
      $singular = $this->i18n_value($def, 'singular_i18n', $singular);
      $plural   = $this->i18n_value($def, 'plural_i18n', $plural);
      if (!$this->polylang_active()) {
        $slug = $this->i18n_slug($def, 'slug_i18n', $slug);
      }
      $public   = !empty($def['public']);
      $has_archive = !empty($def['has_archive']);
      $show_in_rest = !empty($def['show_in_rest']);
      $supports = (isset($def['supports']) && is_array($def['supports']))
        ? array_values(array_map('sanitize_key',$def['supports']))
        : ['title','editor'];

      // ✅ ambil dari option yg tersimpan
      $menu_icon_raw = isset($def['menu_icon']) ? trim((string)$def['menu_icon']) : '';
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
        $archive_slug = $slug ?: $key;
        $archive_slug = trim((string) $archive_slug, '/');
        if ($archive_slug !== '') {
          add_rewrite_rule(
            '^' . preg_quote($archive_slug, '/') . '/?$',
            'index.php?post_type=' . $key,
            'top'
          );
          add_rewrite_rule(
            '^' . preg_quote($archive_slug, '/') . '/page/([0-9]+)/?$',
            'index.php?post_type=' . $key . '&paged=$matches[1]',
            'top'
          );
        }
      }

      if (!empty($def['list_thumbnail']) && in_array('thumbnail', $supports, true)) {
        $this->register_admin_thumbnail_column($key);
      }
      if (!empty($def['block_single'])) {
        $this->register_block_single_views($key);
      }
    }
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

  public function remove_post_views_id() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit' || empty($screen->post_type)) return;
    if (!$this->is_block_single_post_type($screen->post_type)) return;
    echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("post-views");if(el){el.removeAttribute("id");}});})();</script>';
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

  public function register_dynamic_taxonomies() {
    $defs = get_option('cffp_taxonomies', []);
    if (!is_array($defs)) return;

    foreach ($defs as $key => $def) {
      $tax = sanitize_key($key);
      if (!$tax) continue;

      $singular = sanitize_text_field($def['singular'] ?? ucfirst($tax));
      $plural   = sanitize_text_field($def['plural'] ?? $singular . 's');
      $singular = $this->i18n_value($def, 'singular_i18n', $singular);
      $plural   = $this->i18n_value($def, 'plural_i18n', $plural);
      $public   = !empty($def['public']);
      $hier     = !empty($def['hierarchical']);
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

  private function __construct() {
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('init', [__CLASS__, 'register_options_cpt'], 15);
    add_action('init', [$this, 'register_dynamic_cpts'], 20);
    add_action('init', [$this, 'register_dynamic_taxonomies'], 25);
    add_action('init', [$this, 'register_term_meta_fields'], 30);
    add_action('init', [$this, 'add_polylang_rewrite_rules'], 100);

    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('add_meta_boxes', [$this, 'meta_boxes']);
    add_action('save_post_cff_group', [$this, 'save_group'], 10, 2);
    add_action('save_post_cff_options', [$this, 'save_global_ui_settings'], 10, 2);

    add_action('add_meta_boxes', [$this, 'content_meta_boxes'], 20, 2);
    add_action('save_post', [$this, 'save_content_fields'], 10, 2);

    add_action('admin_enqueue_scripts', [$this, 'assets']);
    add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
    add_action('admin_head-edit.php', [$this, 'remove_post_views_id']);
    add_action('admin_notices', [$this, 'maybe_render_copy_to_translations_notice']);
    add_action('admin_init', [$this, 'handle_export_tools']);
    add_action('admin_init', [$this, 'handle_export_group']);
    add_action('admin_init', [$this, 'handle_export_acf_data']);
    add_action('rest_api_init', [$this, 'register_rest_fields']);
    add_action('pre_get_posts', [$this, 'apply_reorder_post_types']);
    add_filter('get_terms_args', [$this, 'apply_reorder_terms_args'], 10, 2);
    add_filter('category_rewrite_rules', [$this, 'category_rewrite_rules']);
    add_filter('category_link', [$this, 'category_link_no_base'], 10, 2);
    add_filter('pll_translate_post_type_rewrite_slug', [$this, 'pll_translate_post_type_rewrite_slug'], 10, 3);
    add_filter('pll_translate_taxonomy_rewrite_slug', [$this, 'pll_translate_taxonomy_rewrite_slug'], 10, 3);
    add_filter('pll_the_language_link', [$this, 'pll_fix_archive_lang_link'], 10, 3);
    add_filter('pll_the_language_link', [$this, 'pll_fix_taxonomy_lang_link'], 10, 3);
    add_filter('redirect_post_location', [$this, 'add_copy_notice_flag_to_redirect'], 10, 2);

    add_action('wp_ajax_cff_search_posts', [$this, 'ajax_search_posts']);
    add_action('wp_ajax_cff_get_templates', [$this, 'ajax_get_templates']);
    add_action('wp_ajax_cff_get_post_types', [$this, 'ajax_get_post_types']);
    add_action('wp_ajax_cff_reorder_get_posts', [$this, 'ajax_reorder_get_posts']);
    add_action('wp_ajax_cff_reorder_save_posts', [$this, 'ajax_reorder_save_posts']);
    add_action('wp_ajax_cff_reorder_get_terms', [$this, 'ajax_reorder_get_terms']);
    add_action('wp_ajax_cff_reorder_save_terms', [$this, 'ajax_reorder_save_terms']);
    add_action('wp_ajax_cff_reorder_get_groups', [$this, 'ajax_reorder_get_groups']);
    add_action('wp_ajax_cff_reorder_save_groups', [$this, 'ajax_reorder_save_groups']);
    add_action('admin_post_cff_duplicate_post', [$this, 'admin_post_duplicate_post']);
    add_filter('post_row_actions', [$this, 'filter_duplicate_row_action'], 10, 2);
    add_filter('post_row_actions', [$this, 'ensure_quick_edit_row_action'], 99, 2);
    add_filter('page_row_actions', [$this, 'ensure_quick_edit_row_action'], 99, 2);
    add_filter('quick_edit_enabled_for_post_type', [$this, 'disable_quick_edit_for_groups'], 10, 2);
    add_filter('bulk_actions-edit-cff_group', [$this, 'filter_group_bulk_actions']);
    add_filter('single_template', [$this, 'filter_slug_based_single_template']);
    add_filter('archive_template', [$this, 'filter_slug_based_archive_template']);
    add_filter('nav_menu_css_class', [$this, 'filter_nav_menu_css_class'], 10, 4);
  }

  public function admin_menu() {
    $cap = 'manage_options';
    add_menu_page(__('Custom Fields', 'cff'), __('Custom Fields', 'cff'), $cap, 'cff', [$this,'page_dashboard'], 'dashicons-feedback', 58);
    add_submenu_page('cff', __('Field Groups','cff'), __('Field Groups','cff'), $cap, 'edit.php?post_type=cff_group');
    add_submenu_page('cff', __('Post Types','cff'), __('Post Types','cff'), $cap, 'cff-post-types', [$this,'page_post_types']);
    add_submenu_page('cff', __('Taxonomies','cff'), __('Taxonomies','cff'), $cap, 'cff-taxonomies', [$this,'page_taxonomies']);
    add_submenu_page('cff', __('Global Settings','cff'), __('Global Settings','cff'), $cap, 'cff-global-settings', [$this,'page_global_settings']);
    add_submenu_page('cff', __('Reorder','cff'), __('Reorder','cff'), $cap, 'cff-reorder', [$this,'page_reorder']);
    add_submenu_page('cff', __('Export / Import','cff'), __('Export / Import','cff'), $cap, 'cff-tools', [$this,'page_tools']);
    add_submenu_page('cff', __('Documentation','cff'), __('Documentation','cff'), $cap, 'cff-docs', [$this,'page_docs']);

    $page_obj = get_post_type_object('page');
    if ($page_obj && !empty($page_obj->show_ui)) {
      $page_label = $page_obj->labels->name ?? __('Pages', 'cff');
      add_submenu_page(
        'edit.php?post_type=page',
        __('Reorder', 'cff'),
        __('Reorder', 'cff'),
        $cap,
        'cff-reorder-page',
        function() use ($page_label) {
          $this->page_reorder_post_type('page', $page_label);
        }
      );
    }

    $post_types = get_post_types(['show_ui' => true, '_builtin' => false], 'objects');
    foreach ($post_types as $post_type => $obj) {
      if ($post_type === 'cff_group') continue;
      $submenu_slug = 'cff-reorder-' . $post_type;
      add_submenu_page(
        'edit.php?post_type=' . $post_type,
        __('Reorder', 'cff'),
        __('Reorder', 'cff'),
        $cap,
        $submenu_slug,
        function() use ($post_type, $obj) {
          $label = $obj->labels->name ?? ucfirst($post_type);
          $this->page_reorder_post_type($post_type, $label);
        }
      );
    }
  }

  public function page_dashboard() {
    echo '<div class="wrap cff-admin"><h1>Custom Fields Framework Pro</h1>';
    echo '<p>Version: <strong>' . esc_html(CFFP_VERSION) . '</strong></p>';
    echo '<p>Manage field groups at <a href="'.esc_url(admin_url('edit.php?post_type=cff_group')).'">Field Groups</a>.</p>';

    $pts = get_post_types(['public'=>true], 'objects');
    echo '<hr><h2>All Post Types</h2>';
    echo '<p class="description">Reference list (built-in + your CPT). Use these keys in Location Rules (Post Type).</p>';
    echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Label</th><th>Menu</th><th>REST</th><th>Archive</th></tr></thead><tbody>';
    foreach ($pts as $pt) {
      $menu = $pt->show_ui ? '<a href="'.esc_url(admin_url('edit.php?post_type='.$pt->name)).'">Open</a>' : '—';
      echo '<tr>';
      echo '<td><code>'.esc_html($pt->name).'</code></td>';
      echo '<td>'.esc_html($pt->labels->name).'</td>';
      echo '<td>'.$menu.'</td>';
      echo '<td>'.($pt->show_in_rest ? 'Yes' : 'No').'</td>';
      echo '<td>'.($pt->has_archive ? 'Yes' : 'No').'</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }

  private function ensure_global_settings_post() {
    $saved_id = absint(get_option('cffp_global_settings_post_id', 0));
    if ($saved_id) {
      $saved_post = get_post($saved_id);
      if ($saved_post && $saved_post->post_type === 'cff_options') {
        return $saved_id;
      }
    }

    $existing = get_posts([
      'post_type' => 'cff_options',
      'post_status' => 'any',
      'numberposts' => 1,
      'no_found_rows' => true,
      'orderby' => 'ID',
      'order' => 'ASC',
    ]);
    if (!empty($existing[0]->ID)) {
      $post_id = (int) $existing[0]->ID;
      update_option('cffp_global_settings_post_id', $post_id);
      return $post_id;
    }

    $post_id = wp_insert_post([
      'post_type' => 'cff_options',
      'post_status' => 'publish',
      'post_title' => __('Global Settings', 'cff'),
    ]);

    if ($post_id && !is_wp_error($post_id)) {
      update_option('cffp_global_settings_post_id', (int) $post_id);
      return (int) $post_id;
    }

    return 0;
  }

  public function page_global_settings() {
    if (!current_user_can('manage_options')) return;

    $post_id = $this->ensure_global_settings_post();
    if (!$post_id) {
      echo '<div class="wrap"><h1>' . esc_html__('Global Settings', 'cff') . '</h1><p>' . esc_html__('Unable to initialize global settings page.', 'cff') . '</p></div>';
      return;
    }

    $url = add_query_arg([
      'post' => $post_id,
      'action' => 'edit',
    ], admin_url('post.php'));
    wp_safe_redirect($url);
    exit;
  }

  public function page_docs() {
    if (!current_user_can('manage_options')) return;

    $field_types = [
      ['text', 'Single line text', 'Text biasa seperti headline, slug, kode pendek.'],
      ['number', 'Numeric value', 'Harga, jumlah, urutan, rating.'],
      ['textarea', 'Multi-line text', 'Deskripsi, catatan, ringkasan.'],
      ['wysiwyg', 'Rich editor', 'Konten HTML/editor lengkap.'],
      ['color', 'Color picker', 'Kode warna brand, badge, background.'],
      ['url', 'URL string', 'Link biasa dalam format teks.'],
      ['link', 'Structured link', 'Menyimpan URL, title, dan target.'],
      ['embed', 'Embed content', 'YouTube, Vimeo, atau embed lain.'],
      ['choice', 'Select/radio/checkbox', 'Opsi tunggal atau multiple choice.'],
      ['relational', 'Relation data', 'Relasi ke post, page, taxonomy, atau user.'],
      ['date_picker', 'Date only', 'Tanggal publish, event date, deadline.'],
      ['datetime_picker', 'Date and time', 'Jadwal lengkap dengan jam.'],
      ['checkbox', 'Boolean', 'Ya/tidak, active/inactive, toggle sederhana.'],
      ['image', 'Media image', 'Thumbnail, hero, banner.'],
      ['file', 'File upload', 'PDF, DOC, file download.'],
      ['repeater', 'Repeatable rows', 'FAQ, features, steps, table-like content.'],
      ['group', 'Nested object', 'Field yang dikelompokkan dalam satu blok.'],
      ['flexible', 'Flexible layouts', 'Section builder dengan beberapa layout berbeda.'],
    ];

    $regular_snippet = <<<'PHP'
<?php
use function CFF\cff_get_text;
use function CFF\cff_get_image_url;
use function CFF\cff_get_repeater_rows;

$headline = cff_get_text('headline');
$hero_image = cff_get_image_url('hero_image');
$faq_items = cff_get_repeater_rows('faq_items');

if ($headline !== '') {
  echo '<h1>' . esc_html($headline) . '</h1>';
}

if ($hero_image !== '') {
  echo '<img src="' . esc_url($hero_image) . '" alt="">';
}

foreach ($faq_items as $row) {
  echo '<h3>' . esc_html($row['question'] ?? '') . '</h3>';
  echo wp_kses_post($row['answer'] ?? '');
}
PHP;

    $cross_page_snippet = <<<'PHP'
<?php
$source_page_id = 42;
$headline = \CFF\cff_get_text('headline', $source_page_id);

echo esc_html($headline);
PHP;

    $image_snippet = <<<'PHP'
<?php
$image = get_field('hero_image');

if (!empty($image['url'])) {
  echo '<img src="' . esc_url($image['url']) . '" alt="">';
}
PHP;

    $repeater_loop_snippet = <<<'PHP'
<?php
if (have_rows('faq_items')) {
  while (have_rows('faq_items')) {
    the_row();
    echo '<h3>' . esc_html(get_sub_field('question')) . '</h3>';
    echo wp_kses_post(get_sub_field('answer'));
  }
}
PHP;

    $reorder_snippet = <<<'PHP'
<?php
$post_id = get_the_ID();
$ordered = \CFF\cff_get_ordered_field_names($post_id, [
  'gallery_1',
  'huge_image',
  'gallery_2',
  'detail_2',
]);

foreach ($ordered as $field_name) {
  echo '<section class="section-' . esc_attr($field_name) . '">';
  // render sesuai mapping template
  echo '</section>';
}
PHP;

    $shortcode_single_snippet = <<<'TEXT'
[cff_value name="headline"]
[cff_item name="subtitle" default="Tidak ada subtitle"]
[cff_item name="headline" page_id="42" lang="en"]
TEXT;

    $shortcode_loop_snippet = <<<'TEXT'
[cff_items group_id="123"]
  <section class="section-[cff_item key='name']">
    <h2>[cff_item key='label']</h2>
    [cff_item]
  </section>
[/cff_items]
TEXT;

    $shortcode_candidates_snippet = <<<'TEXT'
[cff_items page_id="42" candidates="gallery_1,huge_image,gallery_2,detail_2" lang="current"]
  <section class="section-[cff_item key='name']">
    [cff_item]
  </section>
[/cff_items]
TEXT;

    $shortcode_php_snippet = <<<'PHP'
<?php
echo do_shortcode('[cff_value name="headline"]');

echo do_shortcode('[cff_item name="hero_media" class="hero-media" alt="Hero media"]');

echo do_shortcode('
  [cff_items group_id="123"]
    <section class="section-[cff_item key="name"]">
      <h2>[cff_item key="label"]</h2>
      [cff_item]
    </section>
  [/cff_items]
');
PHP;

    $shortcode_image_snippet = <<<'TEXT'
[cff_item name="hero_media" class="hero-media" alt="Hero media"]

[cff_items group_id="123" include_empty="id,url"]
  <figure class="section-[cff_item key='name']">
    [cff_item class="hero-media" alt="Hero media"]
  </figure>
[/cff_items]
TEXT;

    $shortcode_include_empty_snippet = <<<'TEXT'
[cff_items group_id="123" include_empty="id,url,link.title,image.url"]
  <div class="row row-[cff_item key='name']">
    <strong>[cff_item key='label']</strong>
    [cff_item default="-"]
  </div>
[/cff_items]
TEXT;

    $shortcode_debug_snippet = <<<'TEXT'
[cff_debug name="hero_image"]

[cff_items group_id="123"]
  <div class="debug-block">
    [cff_item key="label"]
    [cff_debug]
    [cff_debug target="item"]
  </div>
[/cff_items]
TEXT;

    $shortcode_video_snippet = <<<'TEXT'
[cff_item name="hero_media" class="hero-media" controls="1" muted="1" playsinline="1"]
[cff_item name="promo_media" class="hero-media" controls="1" muted="1" playsinline="1" default="<p>Media tidak tersedia</p>"]
TEXT;

    $shortcode_relational_snippet = <<<'TEXT'
[cff_item name="related_post" class="related-link"]
[cff_item name="contributors" class="related-user"]
TEXT;

    echo '<div class="wrap cff-admin">';
    echo '<h1>' . esc_html__('CFF Documentation', 'cff') . '</h1>';
    echo '<p class="description">' . esc_html__('Reference for managing Field Groups and rendering CFF values on the frontend. Choose the tab based on the rendering style you use in your project.', 'cff') . '</p>';

    echo '<style>
      .cff-doc-shell{max-width:1080px;margin-top:18px;background:#f6f7f7;}
      .cff-doc-tabs{display:flex;gap:0;margin:0;background:#f0f0f1;border-bottom:1px solid #dcdcde;overflow:auto}
      .cff-doc-tab{margin:0 0 -1px;padding:12px 18px;border:1px solid transparent;border-bottom:none;background:transparent;color:#50575e;cursor:pointer;font-weight:600;white-space:nowrap}
      .cff-doc-tab:hover{color:#2271b1}
      .cff-doc-tab.is-active{background:#fff;color:#2271b1;border-color:#dcdcde;border-top-left-radius:6px;border-top-right-radius:6px}
      .cff-doc-panel{display:none;padding:18px;background:#fff}
      .cff-doc-panel.is-active{display:block}
      .cff-doc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:16px 0}
      .cff-doc-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px}
      .cff-doc-card h2,.cff-doc-card h3{margin-top:0}
      .cff-doc-table{width:100%;border-collapse:collapse;background:#fff}
      .cff-doc-table th,.cff-doc-table td{border:1px solid #dcdcde;padding:10px 12px;text-align:left;vertical-align:top}
      .cff-doc-table th{background:#f6f7f7}
      .cff-doc-code{white-space:pre-wrap;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;border-radius:6px}
      .cff-doc-list{margin:0;padding-left:18px;line-height:1.8}
      .cff-doc-note{margin-top:12px;padding:12px 14px;background:#f6f7f7;border-left:4px solid #2271b1}
    </style>';

    echo '<div class="cff-doc-shell">';
    echo '<div class="cff-doc-tabs" role="tablist" aria-label="' . esc_attr__('CFF Documentation Tabs', 'cff') . '">';
    echo '<button type="button" class="cff-doc-tab is-active" data-target="cff-doc-regular">' . esc_html__('Regular', 'cff') . '</button>';
    echo '<button type="button" class="cff-doc-tab" data-target="cff-doc-shortcode">' . esc_html__('Shortcode', 'cff') . '</button>';
    echo '</div>';

    echo '<div id="cff-doc-regular" class="cff-doc-panel is-active">';
    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('How To Manage', 'cff') . '</h2><ol class="cff-doc-list">';
    echo '<li>' . esc_html__('Create or edit a Field Group from Custom Fields -> Field Groups.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Add fields and choose the correct field type for the content shape.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Set Location Rules so the group appears only on the intended post type, page, or options page.', 'cff') . '</li>';
    echo '<li>' . esc_html__('For global data, use the rule Options Page == Global Settings.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Save the group, then fill the values in the target editor screen.', 'cff') . '</li>';
    echo '</ol></div>';

    echo '<div class="cff-doc-card"><h2>' . esc_html__('When To Use', 'cff') . '</h2><ul class="cff-doc-list">';
    echo '<li><strong>' . esc_html__('Regular helper:', 'cff') . '</strong> ' . esc_html__('best for theme templates, PHP control, conditional rendering, and complex layouts.', 'cff') . '</li>';
    echo '<li><strong>' . esc_html__('Repeater / Group / Flexible:', 'cff') . '</strong> ' . esc_html__('use when the content is nested or repeatable.', 'cff') . '</li>';
    echo '<li><strong>' . esc_html__('Reorder:', 'cff') . '</strong> ' . esc_html__('use when the frontend section order needs to follow editor sorting.', 'cff') . '</li>';
    echo '<li><strong>' . esc_html__('Cross-page:', 'cff') . '</strong> ' . esc_html__('pass a post or page ID if the source content lives on another page.', 'cff') . '</li>';
    echo '</ul></div>';
    echo '</div>';

    echo '<div class="cff-doc-card" style="max-width:1080px;margin-bottom:16px;">';
    echo '<h2>' . esc_html__('Supported Field Types', 'cff') . '</h2>';
    echo '<table class="cff-doc-table"><thead><tr><th>' . esc_html__('Type', 'cff') . '</th><th>' . esc_html__('Output Shape', 'cff') . '</th><th>' . esc_html__('Common Usage', 'cff') . '</th></tr></thead><tbody>';
    foreach ($field_types as $row) {
      echo '<tr>';
      echo '<td><code>' . esc_html($row[0]) . '</code></td>';
      echo '<td>' . esc_html($row[1]) . '</td>';
      echo '<td>' . esc_html($row[2]) . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Basic Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($regular_snippet) . '</pre></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Cross Page Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($cross_page_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use the second parameter on helper functions when the field source is another page or post.', 'cff') . '</div></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Image Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($image_snippet) . '</pre></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Loop Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($repeater_loop_snippet) . '</pre></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Reorder Rendering', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($reorder_snippet) . '</pre></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Performance Notes', 'cff') . '</h2><ul class="cff-doc-list">';
    echo '<li>' . esc_html__('For single values, prefer direct helpers such as cff_get_text() or get_field().', 'cff') . '</li>';
    echo '<li>' . esc_html__('For reorder output, use a known group_id whenever possible.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Avoid scanning the same group repeatedly in one template. Resolve once, then render.', 'cff') . '</li>';
    echo '<li>' . esc_html__('CFF already caches key frontend lookups per request for post meta and field group settings.', 'cff') . '</li>';
    echo '</ul></div>';
    echo '</div>';

    echo '<div class="cff-doc-card" style="max-width:1080px;margin-bottom:16px;">';
    echo '<h2>' . esc_html__('Polylang', 'cff') . '</h2>';
    echo '<ul class="cff-doc-list">';
    echo '<li>' . esc_html__('If you render from the current post/page, helpers follow the current queried object.', 'cff') . '</li>';
    echo '<li>' . esc_html__('If you render from another page, pass the translated post/page ID in PHP.', 'cff') . '</li>';
    echo '<li>' . esc_html__('For Options Page / Global Settings, make sure the location rules are configured correctly per content source.', 'cff') . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';

    echo '<div id="cff-doc-shortcode" class="cff-doc-panel">';
    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Available Shortcodes', 'cff') . '</h2><ul class="cff-doc-list">';
    echo '<li><code>[cff_value]</code> ' . esc_html__('render one field value directly.', 'cff') . '</li>';
    echo '<li><code>[cff_field]</code> ' . esc_html__('render one field value or active loop field.', 'cff') . '</li>';
    echo '<li><code>[cff_item]</code> ' . esc_html__('alias of [cff_field].', 'cff') . '</li>';
    echo '<li><code>[cff_loop]</code> ' . esc_html__('loop ordered fields.', 'cff') . '</li>';
    echo '<li><code>[cff_items]</code> ' . esc_html__('alias of [cff_loop].', 'cff') . '</li>';
    echo '</ul></div>';

    echo '<div class="cff-doc-card"><h2>' . esc_html__('Shortcode Attributes', 'cff') . '</h2><ul class="cff-doc-list">';
    echo '<li><code>name</code> ' . esc_html__('field name for single render.', 'cff') . '</li>';
    echo '<li><code>post_id</code> / <code>page_id</code> ' . esc_html__('source content ID for cross-page rendering.', 'cff') . '</li>';
    echo '<li><code>group_id</code> ' . esc_html__('explicit field group for ordered loop rendering.', 'cff') . '</li>';
    echo '<li><code>candidates</code> ' . esc_html__('candidate field names for auto-detected reorder sequence.', 'cff') . '</li>';
    echo '<li><code>lang</code> ' . esc_html__('Polylang language slug such as id or en. Empty means current language.', 'cff') . '</li>';
    echo '<li><code>key</code> ' . esc_html__('inside a loop, use value, label, name, or type.', 'cff') . '</li>';
    echo '<li><code>class</code> ' . esc_html__('CSS class for text, image, video, file, or link output.', 'cff') . '</li>';
    echo '<li><code>img_class</code> / <code>video_class</code> / <code>link_class</code> ' . esc_html__('specific class override for media/link output.', 'cff') . '</li>';
    echo '<li><code>alt</code> ' . esc_html__('custom alt attribute for image output.', 'cff') . '</li>';
    echo '<li><code>text</code> ' . esc_html__('custom link/file label.', 'cff') . '</li>';
    echo '<li><code>controls</code>, <code>autoplay</code>, <code>muted</code>, <code>loop</code>, <code>playsinline</code> ' . esc_html__('video output attributes.', 'cff') . '</li>';
    echo '</ul></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Single Item Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_single_snippet) . '</pre></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Loop By Group Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_loop_snippet) . '</pre></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Use Shortcode In PHP', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_php_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use do_shortcode() when you need the same shortcode output inside theme files, template parts, or custom frontend PHP.', 'cff') . '</div></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Cross Page + Polylang', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_candidates_snippet) . '</pre></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Image / Video Auto Render', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_image_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use the same [cff_item] shortcode for image or video fields. CFF detects the media type automatically from the value.', 'cff') . '</div></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Video Options', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_video_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use controls, muted, autoplay, loop, and playsinline only when the field may contain video. Image fields ignore those attributes.', 'cff') . '</div></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('include_empty With Keys', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_include_empty_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use 1/true to include all empty items, or pass keys like id,url,title to keep items when one of those keys has a value.', 'cff') . '</div></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Relational Example', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_relational_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Relational values now render as linked post/title, term name, or user display name when possible.', 'cff') . '</div></div>';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Debug Value', 'cff') . '</h2><pre class="cff-doc-code">' . esc_html($shortcode_debug_snippet) . '</pre><div class="cff-doc-note">' . esc_html__('Use [cff_debug] to inspect the active value in JSON format. Use target="item" to inspect the whole loop item payload.', 'cff') . '</div></div>';
    echo '</div>';

    echo '<div class="cff-doc-grid">';
    echo '<div class="cff-doc-card"><h2>' . esc_html__('Shortcode Notes', 'cff') . '</h2><ul class="cff-doc-list">';
    echo '<li>' . esc_html__('Use shortcode when content needs to stay clean in page builder/editor.', 'cff') . '</li>';
    echo '<li>' . esc_html__('For one field only, use [cff_value] or [cff_item name="..."].', 'cff') . '</li>';
    echo '<li>' . esc_html__('Inside PHP templates, call the same shortcode with do_shortcode().', 'cff') . '</li>';
    echo '<li>' . esc_html__('Use the same [cff_item] shortcode for image and video. Output type is detected automatically.', 'cff') . '</li>';
    echo '<li>' . esc_html__('When the value is image, shortcode renders <img>. When the value is video, shortcode renders <video>.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Relational values render readable labels automatically for posts, terms, and users.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Use class for both image and video. Use img_class, video_class, or link_class only if you need a specific override.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Video-only attributes that admin can use: controls="1", muted="1", autoplay="1", loop="1", playsinline="1".', 'cff') . '</li>';
    echo '<li>' . esc_html__('Use [cff_debug] only while logged in as admin. It is hidden for non-admin users.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Use [cff_debug name="field_name"] to inspect a single field payload.', 'cff') . '</li>';
    echo '<li>' . esc_html__('Inside [cff_items], use [cff_debug] for the active value or [cff_debug target="item"] for the full item payload.', 'cff') . '</li>';
    echo '<li>' . esc_html__('For loop output, group_id is more efficient than auto-detect by candidates.', 'cff') . '</li>';
    echo '<li>' . esc_html__('include_empty accepts multiple keys separated by comma, including nested keys such as image.url or link.title.', 'cff') . '</li>';
    echo '<li>' . esc_html__('If Polylang translation for the given page/post exists, shortcode can resolve it via the lang attribute.', 'cff') . '</li>';
    echo '</ul></div>';
    echo '</div>';
    echo '</div>';

    echo '<script>
      (function(){
        var tabs = document.querySelectorAll(".cff-doc-tab");
        var panels = document.querySelectorAll(".cff-doc-panel");
        tabs.forEach(function(tab){
          tab.addEventListener("click", function(){
            var target = tab.getAttribute("data-target");
            tabs.forEach(function(btn){ btn.classList.remove("is-active"); });
            panels.forEach(function(panel){ panel.classList.remove("is-active"); });
            tab.classList.add("is-active");
            var panel = document.getElementById(target);
            if (panel) panel.classList.add("is-active");
          });
        });
      })();
    </script>';

    echo '<p style="margin-top:16px;">' . esc_html__('Open this page directly:', 'cff') . ' <code>admin.php?page=cff-docs</code></p>';
    echo '</div>';
  }

  public function register_rest_fields() {
    $this->rest_fields()->register();
  }
  public function rest_get_payload($post_id, $post_type) { return $this->rest_fields()->get_payload($post_id, $post_type); }
  public function rest_update_payload($post_id, $post_type, $value) { return $this->rest_fields()->update_payload($post_id, $post_type, $value); }
  public function rest_get_definitions_for_post_type($post_type) { return $this->rest_fields()->get_definitions_for_post_type($post_type); }
  public function rest_build_schema_properties_for_post_type($post_type) { return $this->rest_fields()->build_schema_properties_for_post_type($post_type); }
  public function rest_build_schema_for_field($field) { return $this->rest_fields()->build_schema_for_field($field); }
  public function rest_sanitize_field_value($field, $value) { return $this->rest_fields()->sanitize_field_value($field, $value); }

  public function page_post_types() {
    if (!current_user_can('manage_options')) return;

    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs)) $defs = [];

    if (!empty($_GET['updated'])) {
      add_settings_error('cffp_post_types', 'updated', __('Custom Post Type saved.', 'cff'), 'updated');
    }
    if (!empty($_GET['duplicated'])) {
      add_settings_error('cffp_post_types', 'duplicated', __('Custom Post Type duplicated.', 'cff'), 'updated');
    }
    if (!empty($_GET['deleted'])) {
      add_settings_error('cffp_post_types', 'deleted', __('Custom Post Type deleted.', 'cff'), 'updated');
    }

    $edit_key = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
    $editing  = ($edit_key && isset($defs[$edit_key]) && is_array($defs[$edit_key]));
    $edit_def = $editing ? (array) $defs[$edit_key] : [];

    // =========================
    // HANDLE SAVE / DELETE
    // =========================
    if (isset($_POST['cffp_cpt_nonce']) && wp_verify_nonce($_POST['cffp_cpt_nonce'], 'cffp_cpt_save')) {
      $action = sanitize_key($_POST['cffp_action'] ?? '');

    if (in_array($action, ['add','update'], true)) {
      $key = sanitize_key($_POST['cpt_key'] ?? '');
      if ($key) {
        $menu_icon_raw = isset($_POST['cpt_menu_icon']) ? trim(wp_unslash($_POST['cpt_menu_icon'])) : '';
        $menu_icon = '';

        if ($menu_icon_raw !== '') {
          if (strpos($menu_icon_raw, 'dashicons-') === 0) {
            $menu_icon = preg_replace('/[^a-z0-9\-_]/i', '', $menu_icon_raw);
          } else {
            $menu_icon = esc_url_raw($menu_icon_raw);
          }
        }

        $supports = isset($_POST['cpt_supports'])
          ? array_values(array_map('sanitize_key', (array) $_POST['cpt_supports']))
          : ['title','editor'];
        $list_thumbnail = !empty($_POST['cpt_list_thumbnail']);
        if ($list_thumbnail && !in_array('thumbnail', $supports, true)) {
          $supports[] = 'thumbnail';
        }
        $block_single = !empty($_POST['cpt_block_single']);
        $duplicate_status = sanitize_key($_POST['cpt_duplicate_status'] ?? 'active');
        if (!in_array($duplicate_status, ['active','inactive'], true)) {
          $duplicate_status = 'active';
        }

          $plural = sanitize_text_field($_POST['cpt_plural'] ?? '');
          $slug_input = isset($_POST['cpt_slug']) ? sanitize_title(wp_unslash($_POST['cpt_slug'])) : '';
          if ($slug_input === '') {
            $slug_input = sanitize_title($plural ?: $key);
          }

          $defs[$key] = [
            'singular' => sanitize_text_field($_POST['cpt_singular'] ?? ''),
          'plural'   => $plural,
          'slug'     => $slug_input,
            'public'   => !empty($_POST['cpt_public']),
            'has_archive' => !empty($_POST['cpt_archive']),
            'show_in_rest' => !empty($_POST['cpt_rest']),
          'supports' => $supports,
          'list_thumbnail' => $list_thumbnail,
          'block_single' => $block_single,
          'duplicate_status' => $duplicate_status,
          'taxonomies' => isset($_POST['cpt_taxonomies'])
            ? array_values(array_filter(array_map('sanitize_key',(array)$_POST['cpt_taxonomies'])))
            : [],
          'menu_icon' => $menu_icon,
          'menu_position' => isset($_POST['cpt_menu_position']) ? intval($_POST['cpt_menu_position']) : 25,
        ];
          if ($this->polylang_active()) {
            $singular_i18n = isset($_POST['cpt_singular_i18n']) && is_array($_POST['cpt_singular_i18n'])
              ? array_map('sanitize_text_field', (array) $_POST['cpt_singular_i18n'])
              : [];
            $plural_i18n = isset($_POST['cpt_plural_i18n']) && is_array($_POST['cpt_plural_i18n'])
              ? array_map('sanitize_text_field', (array) $_POST['cpt_plural_i18n'])
              : [];
            $slug_i18n = isset($_POST['cpt_slug_i18n']) && is_array($_POST['cpt_slug_i18n'])
              ? array_map('sanitize_title', (array) $_POST['cpt_slug_i18n'])
              : [];
            $defs[$key]['singular_i18n'] = array_filter($singular_i18n);
            $defs[$key]['plural_i18n'] = array_filter($plural_i18n);
            $defs[$key]['slug_i18n'] = array_filter($slug_i18n);
          }

          update_option('cffp_post_types', $defs);
          flush_rewrite_rules();

          // redirect aman tanpa header (biar sidebar icon ikut refresh)
          echo '<div class="notice notice-success"><p>Custom Post Type saved.</p></div>';
          echo '<script>window.location = ' . wp_json_encode( admin_url('admin.php?page=cff-post-types&updated=1') ) . ';</script>';
          exit;
        }
      }

      if ($action === 'duplicate') {
        $key = sanitize_key($_POST['cpt_key'] ?? '');
        if ($key && isset($defs[$key])) {
          $new_key = $key . '_copy';
          $i = 2;
          while (isset($defs[$new_key])) {
            $new_key = $key . '_copy' . $i;
            $i++;
          }
          $new_def = $defs[$key];
          $new_def['plural'] = ($new_def['plural'] ?? '') ? $new_def['plural'] . ' Copy' : 'Copy';
          $new_def['singular'] = ($new_def['singular'] ?? '') ? $new_def['singular'] . ' Copy' : 'Copy';
          $slug_candidate = sanitize_title($new_def['slug'] ?? $new_key);
          if (!$slug_candidate) {
            $slug_candidate = $new_key;
          }
          $new_def['slug'] = $slug_candidate;
          $status = $defs[$key]['duplicate_status'] ?? 'active';
          if (!in_array($status, ['active','inactive'], true)) {
            $status = 'active';
          }
          $new_def['public'] = ($status === 'active');
          $defs[$new_key] = $new_def;
          update_option('cffp_post_types', $defs);
          $url = admin_url('admin.php?page=cff-post-types&duplicated=1');
          echo '<div class="notice notice-success"><p>'.esc_html__('Custom Post Type duplicated.','cff').'</p></div>';
          echo '<script>window.location = ' . wp_json_encode($url) . ';</script>';
          exit;
        }
      }
      if ($action === 'delete') {
        $key = sanitize_key($_POST['cpt_key'] ?? '');
        if ($key && isset($defs[$key])) {
          unset($defs[$key]);
          update_option('cffp_post_types', $defs);
          flush_rewrite_rules();

          echo '<div class="notice notice-success"><p>Custom Post Type removed.</p></div>';
          echo '<script>window.location = ' . wp_json_encode( admin_url('admin.php?page=cff-post-types&deleted=1') ) . ';</script>';
          exit;
        }
      }
    }

    settings_errors('cffp_post_types');
    // =========================
    // UI LIST
    // =========================
    $supports_all = [
      'title' => 'Title',
      'editor' => 'Editor',
      'thumbnail' => 'Featured Image',
      'excerpt' => 'Excerpt',
      'revisions' => 'Revisions',
      'custom-fields' => 'Custom Fields',
    ];

    echo '<div class="wrap cff-admin"><h1>Post Types</h1>';
    echo '<h2>Existing</h2>';

    if (!$defs) {
      echo '<p class="cff-muted">No custom post types yet.</p>';
    } else {
      echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Label</th><th>Slug</th><th>Template Files</th><th>Icon</th><th>Thumbnail</th><th>Views</th><th>Public</th><th>Actions</th></tr></thead><tbody>';

      foreach ($defs as $key => $def) {
        $label = esc_html(($def['plural'] ?? $key));

        $icon = is_string($def['menu_icon'] ?? '') ? trim((string)$def['menu_icon']) : '';
        $icon_html = '—';
        if ($icon) {
          if (strpos($icon, 'dashicons-') === 0) {
            $icon_html = '<span class="dashicons '.esc_attr($icon).'" style="vertical-align:middle;"></span> <code>'.esc_html($icon).'</code>';
          } else {
            $icon_html = '<img src="'.esc_url($icon).'" style="width:18px;height:18px;vertical-align:middle;" /> <code>'.esc_html($icon).'</code>';
          }
        }

        $edit_url = add_query_arg(['page'=>'cff-post-types','edit'=>$key], admin_url('admin.php'));

        echo '<tr>';
        echo '<td><code>'.esc_html($key).'</code></td>';
        echo '<td>'.$label.'</td>';
        $slug_value = $def['slug'] ?? $key;
        $template_slug = sanitize_title($slug_value ?: $key);
        $archive_tpl = 'archive-' . ($template_slug ?: 'post-type') . '.php';
        $single_tpl = 'single-' . ($template_slug ?: 'post-type') . '.php';
        echo '<td><code>'.esc_html($slug_value).'</code></td>';
        $has_archive_view = !empty($def['has_archive']);
        $block_single = !empty($def['block_single']);
        $tpl_parts = [];
        if ($has_archive_view) {
          $tpl_parts[] = '<code>' . esc_html($archive_tpl) . '</code>';
        }
        if (!$block_single) {
          $tpl_parts[] = '<code>' . esc_html($single_tpl) . '</code>';
        }
        $tpl_cell = $tpl_parts ? implode('<br>', $tpl_parts) : '<span class="cff-muted">—</span>';
        echo '<td>' . $tpl_cell . '</td>';
        echo '<td>'.$icon_html.'</td>';
        $thumb_icon = !empty($def['list_thumbnail']) ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>';
        $archive_icon = !empty($def['has_archive']) ? 'dashicons-yes' : 'dashicons-no-alt';
        $block_icon = !empty($def['block_single']) ? 'dashicons-yes' : 'dashicons-no-alt';
        $views_html = '<div style="font-size:12px;line-height:1.4;">'
          . '<span class="dashicons ' . esc_attr($archive_icon) . '"></span> ' . esc_html__('Archive', 'cff') . '<br>'
          . '<span class="dashicons ' . esc_attr($block_icon) . '"></span> ' . esc_html__('No single', 'cff')
          . '</div>';
        echo '<td style="text-align:center;">'.$thumb_icon.'</td>';
        echo '<td>'.$views_html.'</td>';
        echo '<td>'.(!empty($def['public']) ? 'Yes' : 'No').'</td>';
        echo '<td>';
        echo '<div class="cff-cpt-actions" role="group" aria-label="'.esc_attr__('Post type actions','cff').'">';
        echo '<a class="cff-cpt-action cff-cpt-action-link" href="'.esc_url($edit_url).'" title="'.esc_attr__('Edit','cff').' '.esc_attr($label).'">';
        echo '<span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">'.esc_html__('Edit','cff').'</span>';
        echo '</a>';
        echo '<form method="post" class="cff-cpt-action-form">';
        wp_nonce_field('cffp_cpt_save','cffp_cpt_nonce');
        echo '<input type="hidden" name="cpt_key" value="'.esc_attr($key).'">';
        echo '<button class="cff-cpt-action" name="cffp_action" value="duplicate" title="'.esc_attr__('Duplicate','cff').' '.esc_attr($label).'">';
        echo '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="screen-reader-text">'.esc_html__('Duplicate','cff').'</span>';
        echo '</button>';
        echo '<button class="cff-cpt-action cff-cpt-action-destructive cff-requires-confirm" name="cffp_action" value="delete" data-confirm-title="'.esc_attr__('Delete Post Type', 'cff').'" data-confirm-message="'.esc_attr(sprintf(__('Delete the post type "%s"? This only removes the CFF definition and does not delete existing posts.', 'cff'), wp_strip_all_tags($label))).'" data-confirm-submit="1" title="'.esc_attr__('Delete','cff').' '.esc_attr($label).'">';
        echo '<span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">'.esc_html__('Delete','cff').'</span>';
        echo '</button>';
        echo '</form>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
      }

      echo '</tbody></table>';
    }

    // =========================
    // ADD / EDIT FORM
    // =========================
    echo '<hr><h2>'.($editing ? 'Edit Post Type' : 'Add New Post Type').'</h2>';

    $pub = $editing ? !empty($edit_def['public']) : true;
    $arc = $editing ? !empty($edit_def['has_archive']) : true;
    $rst = $editing ? !empty($edit_def['show_in_rest']) : true;
    $block_single = $editing ? !empty($edit_def['block_single']) : false;
    $dup_status = $editing ? ($edit_def['duplicate_status'] ?? 'active') : 'active';
    $current_key = $editing ? sanitize_key($edit_key) : '';
    $current_slug = $editing ? sanitize_title($edit_def['slug'] ?? $edit_key) : '';
    $menu_position_value = $editing ? intval($edit_def['menu_position'] ?? 25) : 25;

    $supports_selected = $editing && isset($edit_def['supports']) && is_array($edit_def['supports'])
      ? array_values(array_map('sanitize_key', $edit_def['supports']))
      : ['title','editor'];
    $list_thumb_selected = $editing ? !empty($edit_def['list_thumbnail']) : false;
    if ($list_thumb_selected && !in_array('thumbnail', $supports_selected, true)) {
      $supports_selected[] = 'thumbnail';
    }

    echo '<form method="post" class="cff-cpt-form">';
    wp_nonce_field('cffp_cpt_save','cffp_cpt_nonce');
    echo '<input type="hidden" name="cffp_action" value="'.($editing ? 'update' : 'add').'">';

    echo '<table class="form-table"><tbody>';

    echo '<tr class="cff-cpt-key-row"><th><label>Key</label></th><td>';
    echo '<input type="hidden" name="cpt_key" value="'.esc_attr($editing ? $edit_key : '').'">';
    echo $current_key ? '<code>'.esc_html($current_key).'</code>' : '<span class="cff-muted">Generated from Singular label</span>';
    echo '</td></tr>';
    $langs = $this->polylang_languages();
    echo '<tr><th><label>Singular</label></th><td><input name="cpt_singular" class="regular-text" placeholder="Custom Post" value="'.esc_attr($edit_def['singular'] ?? '').'"></td></tr>';
    echo '<tr><th><label>Plural</label></th><td><input name="cpt_plural" class="regular-text" placeholder="Custom Posts" value="'.esc_attr($edit_def['plural'] ?? '').'"></td></tr>';
    echo '<tr class="cff-cpt-slug-row"><th><label>Slug</label></th><td>';
    echo '<input type="text" name="cpt_slug" class="regular-text" placeholder="custom-post" value="'.esc_attr($current_slug).'">';
    echo '<p class="description">Enter the URL slug for this CPT (auto-generated from the plural label if left blank).</p>';
    echo '</td></tr>';
    if ($langs) {
      echo '<tr><th><label>Translations</label></th><td>';
      echo '<div class="cff-lang-tabs" data-default="'.esc_attr($langs[0]['slug']).'">';
      echo '<div class="cff-lang-tabbar">';
      foreach ($langs as $lang) {
        echo '<button type="button" class="cff-lang-tab" data-lang="'.esc_attr($lang['slug']).'">'.esc_html($lang['name'] ?: $lang['slug']).'</button>';
      }
      echo '</div>';
      foreach ($langs as $lang) {
        $slug = $lang['slug'];
        echo '<div class="cff-lang-panel" data-lang="'.esc_attr($slug).'">';
        echo '<p><label>Singular</label><br><input class="regular-text" placeholder="Custom Post" name="cpt_singular_i18n['.esc_attr($slug).']" value="'.esc_attr(($edit_def['singular_i18n'][$slug] ?? '')).'"></p>';
        echo '<p><label>Plural</label><br><input class="regular-text" placeholder="Custom Posts" name="cpt_plural_i18n['.esc_attr($slug).']" value="'.esc_attr(($edit_def['plural_i18n'][$slug] ?? '')).'"></p>';
        echo '<p><label>Slug</label><br><input class="regular-text" placeholder="custom-post" name="cpt_slug_i18n['.esc_attr($slug).']" value="'.esc_attr(($edit_def['slug_i18n'][$slug] ?? '')).'"></p>';
        echo '</div>';
      }
      echo '</div>';
      echo '</td></tr>';
    }

    echo '<tr><th><label>Options</label></th><td>';
    echo '<label><input type="checkbox" name="cpt_public" value="1" '.($pub?'checked':'').'> '.esc_html__('Public', 'cff').'</label> &nbsp; ';
    echo '<label><input type="checkbox" name="cpt_rest" value="1" '.($rst?'checked':'').'> '.esc_html__('REST API', 'cff').'</label>';
    echo '</td></tr>';
    echo '<tr><th><label>'.esc_html__('Views', 'cff').'</label></th><td>';
    echo '<label><input type="checkbox" name="cpt_archive" value="1" '.($arc?'checked':'').'> '.esc_html__('Archive view', 'cff').'</label> &nbsp; ';
    echo '<label><input type="checkbox" name="cpt_block_single" value="1" '.($block_single?'checked':'').'> '.esc_html__('No single view', 'cff').'</label>';
    echo '<p class="description">'.esc_html__('Archive view controls whether the CPT has an archive listing. No single view prevents individual permalinks from being publicly accessible.', 'cff').'</p>';
    echo '</td></tr>';

    echo '<tr><th><label>'.esc_html__('Duplicate status', 'cff').'</label></th><td>';
    echo '<label style="margin-right:12px;"><input type="radio" name="cpt_duplicate_status" value="active" '.($dup_status === 'active' ? 'checked' : '').'> '.esc_html__('Active', 'cff').'</label>';
    echo '<label style="margin-right:12px;"><input type="radio" name="cpt_duplicate_status" value="inactive" '.($dup_status === 'inactive' ? 'checked' : '').'> '.esc_html__('Non-active', 'cff').'</label>';
    echo '<p class="description">'.esc_html__('Choose the default publish status when duplicating this CPT.', 'cff').'</p>';
    echo '</td></tr>';

    // ✅ ini akan selalu muncul value saat edit
    echo '<tr><th><label>Menu Icon</label></th><td>';
    echo '<input name="cpt_menu_icon" class="regular-text" placeholder="dashicons-admin-post or https://example.com/icon.svg" value="'.esc_attr($edit_def['menu_icon'] ?? '').'">';
    echo '<p class="description">
      Use Dashicons class (e.g. <code>dashicons-admin-post</code>) or a full image URL.
      Reference: <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer">Dashicons Library</a>.
      Example image URL: <code>https://example.com/icon.svg</code>
    </p>';
    echo '</td></tr>';

    echo '<tr><th><label>Menu Position</label></th><td>';
    echo '<input type="number" min="0" name="cpt_menu_position" class="small-text" value="'.esc_attr($menu_position_value).'">';
    echo '<p class="description">Control where this CPT appears in the admin menu (same values as <code>register_post_type</code>). Use <code>0</code> to hand over placement to WordPress.</p>';
    echo '</td></tr>';

    echo '<tr><th><label>Supports</label></th><td>';
    foreach ($supports_all as $k => $lab) {
      $checked = in_array($k, $supports_selected, true) ? 'checked' : '';
      echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="cpt_supports[]" value="'.esc_attr($k).'" '.$checked.'> '.esc_html($lab).'</label>';
    }
    echo '</td></tr>';
    echo '<tr><th><label>Admin list</label></th><td>';
    echo '<label><input type="checkbox" name="cpt_list_thumbnail" value="1" '.($list_thumb_selected ? 'checked' : '').'> Show featured image column on the post listing screen.</label>';
    echo '<p class="description">Requires Featured Image support.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">Save CPT</button></p>';
    echo '</form>';

    echo '</div>';
  }

  public function page_taxonomies() {
    if (!current_user_can('manage_options')) return;

    $edit_key = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
    $defs_edit = get_option('cffp_taxonomies', []);
    $editing = ($edit_key && is_array($defs_edit) && isset($defs_edit[$edit_key]));
    $edit_def = $editing ? (array)$defs_edit[$edit_key] : [];

    if (!empty($_POST['cffp_tax_action']) && check_admin_referer('cffp_tax_nonce','cffp_tax_nonce')) {
      $defs = get_option('cffp_taxonomies', []);
      if (!is_array($defs)) $defs = [];

      $action = sanitize_key($_POST['cffp_tax_action']);

      if (in_array($action, ['add','update'], true)) {
        $key = sanitize_key($_POST['tax_key'] ?? '');
        if ($key) {
          if ($key === 'category') {
            $raw_pts = isset($_POST['post_types']) ? (array) $_POST['post_types'] : [];
            $pts = array_filter(array_map('sanitize_key', $raw_pts));
            $suffix = $pts ? implode('_', $pts) : 'post';
            $new_key = sanitize_key('category_' . $suffix);
            if (isset($defs[$key]) && !isset($defs[$new_key])) {
              $defs[$new_key] = $defs[$key];
              unset($defs[$key]);
            }
            $key = $new_key;
            add_settings_error(
              'cffp_taxonomies',
              'renamed',
              sprintf(__('Taxonomy key "category" reserved. Renamed to "%s".','cff'), $key),
              'warning'
            );
          }
          $defs[$key] = [
            'plural' => sanitize_text_field($_POST['plural'] ?? ''),
            'singular' => sanitize_text_field($_POST['singular'] ?? ''),
            'slug' => sanitize_title($_POST['slug'] ?? $key),
            'public' => !empty($_POST['public']),
            'hierarchical' => !empty($_POST['hierarchical']),
            'show_in_rest' => !empty($_POST['show_in_rest']),
            'post_types' => isset($_POST['post_types']) ? array_map('sanitize_key',(array)$_POST['post_types']) : [],
          ];
          if ($this->polylang_active()) {
            $singular_i18n = isset($_POST['singular_i18n']) && is_array($_POST['singular_i18n'])
              ? array_map('sanitize_text_field', (array) $_POST['singular_i18n'])
              : [];
            $plural_i18n = isset($_POST['plural_i18n']) && is_array($_POST['plural_i18n'])
              ? array_map('sanitize_text_field', (array) $_POST['plural_i18n'])
              : [];
            $slug_i18n = isset($_POST['slug_i18n']) && is_array($_POST['slug_i18n'])
              ? array_map('sanitize_title', (array) $_POST['slug_i18n'])
              : [];
            $defs[$key]['plural_i18n'] = array_filter($plural_i18n);
            $defs[$key]['singular_i18n'] = array_filter($singular_i18n);
            $defs[$key]['slug_i18n'] = array_filter($slug_i18n);
          }
          update_option('cffp_taxonomies', $defs);
          flush_rewrite_rules();
          add_settings_error('cffp_taxonomies','saved',__('Taxonomy saved','cff'),'updated');
        }
      }

      if ($action === 'duplicate') {
        $key = sanitize_key($_POST['tax_key'] ?? '');
        if ($key && isset($defs[$key])) {
          $new_key = $key.'_copy';
          $i=2; while(isset($defs[$new_key])){ $new_key = $key.'_copy'.$i; $i++; }
          $defs[$new_key] = $defs[$key];
          update_option('cffp_taxonomies',$defs);
          flush_rewrite_rules();
          add_settings_error('cffp_taxonomies','dup',__('Taxonomy duplicated','cff'),'updated');
        }
      }

      if ($action === 'delete') {
        $key = sanitize_key($_POST['tax_key'] ?? '');
        if ($key && isset($defs[$key])) {
          unset($defs[$key]);
          update_option('cffp_taxonomies', $defs);
          flush_rewrite_rules();
          add_settings_error('cffp_taxonomies','deleted',__('Taxonomy deleted','cff'),'updated');
        }
      }
    }

    settings_errors('cffp_taxonomies');

    $defs = get_option('cffp_taxonomies', []);
    if (!is_array($defs)) $defs = [];
    $post_types = get_post_types(['public'=>true], 'objects');
    $sel_pt = isset($edit_def['post_types']) && is_array($edit_def['post_types']) ? $edit_def['post_types'] : [];

    echo '<div class="wrap cff-admin"><h1>Taxonomies</h1>';
    echo '<h2>Existing</h2>';

    if (!$defs) {
      echo '<p>No custom taxonomies yet.</p>';
    } else {
      echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Label</th><th>Post Types</th><th>Public</th><th>Hierarchical</th><th>Actions</th></tr></thead><tbody>';
      foreach ($defs as $key=>$def) {
        $pts = isset($def['post_types']) ? implode(', ', array_map('esc_html',(array)$def['post_types'])) : '';
        echo '<tr>';
        echo '<td><code>'.esc_html($key).'</code></td>';
        echo '<td>'.esc_html($def['plural'] ?? '').'</td>';
        echo '<td>'.esc_html($pts).'</td>';
        echo '<td>'.(!empty($def['public'])?'Yes':'No').'</td>';
        echo '<td>'.(!empty($def['hierarchical'])?'Yes':'No').'</td>';
        echo '<td>';
        echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'cff-taxonomies','edit'=>$key], admin_url('admin.php'))).'">Edit</a> ';
        echo '<form method="post" style="display:inline">';
        echo wp_nonce_field('cffp_tax_nonce','cffp_tax_nonce',true,false);
        echo '<input type="hidden" name="tax_key" value="'.esc_attr($key).'">';
        echo '<button class="button" name="cffp_tax_action" value="duplicate">Duplicate</button> ';
        echo '<button class="button button-link-delete cff-requires-confirm" name="cffp_tax_action" value="delete" data-confirm-title="'.esc_attr__('Delete Taxonomy', 'cff').'" data-confirm-message="'.esc_attr(sprintf(__('Delete the taxonomy "%s"? This only removes the CFF definition and does not delete existing terms.', 'cff'), $def['plural'] ?? $key)).'" data-confirm-submit="1">Delete</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
    }

    echo '<hr><h2>'.($editing ? 'Edit Taxonomy' : 'Add New Taxonomy').'</h2>';
    echo '<form method="post" class="cff-tools-form cff-tax-form">';
    echo wp_nonce_field('cffp_tax_nonce','cffp_tax_nonce',true,false);
    echo '<input type="hidden" name="cffp_tax_action" value="'.($editing?'update':'add').'">';

    echo '<div class="cff-tools-card">';
    $langs = $this->polylang_languages();
    echo '<div class="cff-tools-field"><label>Plural Label <span class="required">*</span></label>';
    echo '<input class="regular-text" type="text" name="plural" placeholder="Genres" required value="' . esc_attr($edit_def['plural'] ?? '') . '">';
    echo '</div>';
    echo '<div class="cff-tools-field"><label>Singular Label <span class="required">*</span></label>';
    echo '<input class="regular-text" type="text" name="singular" placeholder="Genre" required value="' . esc_attr($edit_def['singular'] ?? '') . '">';
    echo '</div>';

    echo '<div class="cff-tools-field"><label>Taxonomy Key <span class="required">*</span></label>';
    echo '<input class="regular-text" type="text" name="tax_key" placeholder="genre" required value="' . esc_attr($editing ? $edit_key : '') . '" ' . ($editing ? 'readonly' : '') . '>';
    echo '<p class="description">Lower case letters, underscores and dashes only. Max 32 characters.</p>';
    echo '</div>';
    echo '<div class="cff-tools-field"><label>Slug</label>';
    echo '<input class="regular-text" type="text" name="slug" placeholder="genre" value="' . esc_attr($edit_def['slug'] ?? '') . '">';
    echo '</div>';

    if ($langs) {
      echo '<div class="cff-tools-field">';
      echo '<label>Translations</label>';
      echo '<div class="cff-lang-tabs" data-default="'.esc_attr($langs[0]['slug']).'">';
      echo '<div class="cff-lang-tabbar">';
      foreach ($langs as $lang) {
        echo '<button type="button" class="cff-lang-tab" data-lang="'.esc_attr($lang['slug']).'">'.esc_html($lang['name'] ?: $lang['slug']).'</button>';
      }
      echo '</div>';
      foreach ($langs as $lang) {
        $slug = $lang['slug'];
        echo '<div class="cff-lang-panel" data-lang="'.esc_attr($slug).'">';
        echo '<p><label>Plural</label><br><input class="regular-text" placeholder="Genres" name="plural_i18n['.esc_attr($slug).']" value="' . esc_attr($edit_def['plural_i18n'][$slug] ?? '') . '"></p>';
        echo '<p><label>Singular</label><br><input class="regular-text" placeholder="Genre" name="singular_i18n['.esc_attr($slug).']" value="' . esc_attr($edit_def['singular_i18n'][$slug] ?? '') . '"></p>';
        echo '<p><label>Slug</label><br><input class="regular-text" placeholder="genre" name="slug_i18n['.esc_attr($slug).']" value="' . esc_attr($edit_def['slug_i18n'][$slug] ?? '') . '"></p>';
        echo '</div>';
      }
      echo '</div>';
      echo '</div>';
    }

    echo '<div class="cff-tools-field"><label>Post Types</label>';
    echo '<select name="post_types[]" multiple class="cff-select2 regular-text">';
    foreach ($post_types as $pt) {
      echo '<option value="' . esc_attr($pt->name) . '" ' . (in_array($pt->name, $sel_pt, true) ? 'selected' : '') . '>' . esc_html($pt->labels->name) . ' (' . esc_html($pt->name) . ')</option>';
    }
    echo '</select><p class="description">One or many post types that can be classified with this taxonomy.</p></div>';

    $public_checked = $editing ? !empty($edit_def['public']) : true;
    $rest_checked = $editing ? !empty($edit_def['show_in_rest']) : true;

    echo '<div class="cff-tools-toggles">';
    echo '<label class="cff-switch"><input type="checkbox" name="public" '.($public_checked?'checked':'').'><span class="cff-slider"></span></label><div><strong>Public</strong><div class="description">Visible on frontend/admin.</div></div>';
    echo '</div>';

    echo '<div class="cff-tools-toggles">';
    echo '<label class="cff-switch"><input type="checkbox" name="hierarchical" '.(!empty($edit_def['hierarchical'])?'checked':'').'><span class="cff-slider"></span></label><div><strong>Hierarchical</strong><div class="description">Like categories.</div></div>';
    echo '</div>';

    echo '<div class="cff-tools-toggles">';
    echo '<label class="cff-switch"><input type="checkbox" name="show_in_rest" '.($rest_checked?'checked':'').'><span class="cff-slider"></span></label><div><strong>REST API</strong><div class="description">Expose in REST.</div></div>';
    echo '</div>';

    echo '<p><button class="button button-primary">Save Taxonomy</button></p>';
    echo '</div></form>';
    echo '</div>';
  }

  public function page_reorder() {
    if (!current_user_can('manage_options')) return;

    $post_types = get_post_types(['public'=>true], 'objects');
    $taxonomies = get_taxonomies(['public'=>true], 'objects');

    echo '<div class="wrap cff-admin"><h1>Reorder</h1>';
    echo '<div id="cff-reorder">';

    echo '<div class="cff-reorder-section">';
    echo '<h2>Posts / Pages / Custom Post Types</h2>';
    echo '<div class="cff-reorder-controls">';
    echo '<label for="cff-reorder-post-type">Post Type</label> ';
    echo '<select id="cff-reorder-post-type">';
    foreach ($post_types as $pt) {
      echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->labels->name).'</option>';
    }
    echo '</select> ';
    echo '<button type="button" class="button" id="cff-reorder-load-posts">Load</button>';
    echo '</div>';
    echo '<ul class="cff-reorder-list" data-kind="post"></ul>';
    echo '<p><button type="button" class="button button-primary" id="cff-reorder-save-posts">Save Order</button></p>';
    echo '</div>';

    echo '<hr>';

    echo '<div class="cff-reorder-section">';
    echo '<h2>Taxonomies / Categories</h2>';
    echo '<div class="cff-reorder-controls">';
    echo '<label for="cff-reorder-taxonomy">Taxonomy</label> ';
    echo '<select id="cff-reorder-taxonomy">';
    foreach ($taxonomies as $tax) {
      $pt_labels = [];
      foreach ((array) ($tax->object_type ?? []) as $pt_name) {
        $pt_obj = get_post_type_object($pt_name);
        if ($pt_obj) {
          $pt_labels[] = $pt_obj->labels->singular_name ?? $pt_obj->labels->name ?? $pt_name;
        } else {
          $pt_labels[] = $pt_name;
        }
      }
      $pt_labels = array_values(array_unique(array_filter($pt_labels)));
      $tax_label = $tax->labels->name ?? $tax->label ?? $tax->name;
      $display_label = $pt_labels ? (implode(', ', $pt_labels) . ' - ' . $tax_label) : $tax_label;
      echo '<option value="'.esc_attr($tax->name).'">'.esc_html($display_label).'</option>';
    }
    echo '</select> ';
    echo '<button type="button" class="button" id="cff-reorder-load-terms">Load</button>';
    echo '</div>';
    echo '<ul class="cff-reorder-list" data-kind="term"></ul>';
    echo '<p><button type="button" class="button button-primary" id="cff-reorder-save-terms">Save Order</button></p>';
    echo '</div>';

    echo '<hr>';

    echo '<div class="cff-reorder-section">';
    echo '<h2>Field Groups</h2>';
    echo '<p class="description" style="margin-bottom:8px;">' . esc_html__('Drag field groups to adjust the order they appear in the editor and frontend.', 'cff') . '</p>';
    echo '<div class="cff-reorder-controls">';
    echo '<button type="button" class="button" id="cff-reorder-load-groups">Load Field Groups</button>';
    echo '</div>';
    echo '<ul class="cff-reorder-list" data-kind="group"></ul>';
    echo '<p><button type="button" class="button button-primary" id="cff-reorder-save-groups">Save Order</button></p>';
    echo '</div>';

    echo '</div></div>';
  }

  public function page_reorder_post_type($post_type, $label = '') {
    if (!current_user_can('manage_options')) return;

    $post_type = sanitize_key($post_type);
    if (!$post_type) return;
    $obj = get_post_type_object($post_type);
    if (!$obj || empty($obj->show_ui)) return;

    if (!$label) {
      $label = $obj->labels->name ?? ucfirst($post_type);
    }

    echo '<div class="wrap cff-admin"><h1>' . esc_html__('Reorder', 'cff') . ' - ' . esc_html($label) . '</h1>';
    echo '<div id="cff-reorder">';
    echo '<div class="cff-reorder-section">';
    echo '<h2>' . esc_html($label) . '</h2>';
    echo '<div class="cff-reorder-controls">';
    echo '<label for="cff-reorder-post-type">' . esc_html__('Post Type', 'cff') . '</label> ';
    echo '<select id="cff-reorder-post-type">';
    echo '<option value="' . esc_attr($post_type) . '" selected>' . esc_html($label) . '</option>';
    echo '</select> ';
    echo '<button type="button" class="button" id="cff-reorder-load-posts">' . esc_html__('Load', 'cff') . '</button>';
    echo '</div>';
    echo '<ul class="cff-reorder-list" data-kind="post"></ul>';
    echo '<p><button type="button" class="button button-primary" id="cff-reorder-save-posts">' . esc_html__('Save Order', 'cff') . '</button></p>';
    echo '</div>';
    echo '</div>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var btn=document.getElementById("cff-reorder-load-posts");if(btn){btn.click();}});</script>';
    echo '</div>';
  }

  public function assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_group = $screen && $screen->post_type === 'cff_group';
    $is_group_editor = $is_group && in_array($screen->base, ['post', 'post-new'], true);
    $is_list_screen = $screen && $screen->base === 'edit';
    $is_post_edit = $screen && in_array($screen->base, ['post','post-new'], true);
    $is_term_edit = $screen && in_array($screen->base, ['edit-tags','term'], true);
    $is_plugin_screen = strpos((string) $hook, 'cff') !== false;

    if ($is_list_screen) {
      wp_enqueue_style('cff-list', CFFP_URL . 'assets/list.css', [], $this->asset_ver('assets/list.css'));
    }

    if ($is_group_editor || $is_plugin_screen) {
      wp_enqueue_style('cff-admin', CFFP_URL . 'assets/admin.css', [], $this->asset_ver('assets/admin.css'));

      wp_enqueue_style('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0-rc.0');
      wp_enqueue_script('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.js', ['jquery'], '4.1.0-rc.0', true);
      wp_enqueue_script('cff-select2-init', plugin_dir_url(__FILE__) . '../assets/js/select2-init.js', ['jquery','cff-select2'], $this->asset_ver('assets/js/select2-init.js'), true);

      wp_enqueue_script('cff-admin', CFFP_URL . 'assets/admin.js', ['jquery','jquery-ui-sortable','jquery-ui-droppable'], $this->asset_ver('assets/admin.js'), true);
      wp_localize_script('cff-admin', 'CFFP', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cffp'),
        'archives' => $this->get_archive_links(),
        'relational_post_types' => $this->get_relational_post_types(),
      ]);
    }

    if ($is_post_edit) {
      wp_enqueue_style('cff-admin', CFFP_URL . 'assets/admin.css', [], $this->asset_ver('assets/admin.css'));
      wp_enqueue_style('cff-post', CFFP_URL . 'assets/post.css', [], $this->asset_ver('assets/post.css'));

      wp_enqueue_media();

      wp_enqueue_editor();

      wp_enqueue_style('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0-rc.0');
      wp_enqueue_script('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.js', ['jquery'], '4.1.0-rc.0', true);

      wp_enqueue_script('cff-select2-init', plugin_dir_url(__FILE__) . '../assets/js/select2-init.js', ['jquery','cff-select2'], $this->asset_ver('assets/js/select2-init.js'), true);

      wp_enqueue_script('cff-admin', CFFP_URL . 'assets/admin.js', ['jquery','jquery-ui-sortable','jquery-ui-droppable'], $this->asset_ver('assets/admin.js'), true);
      wp_localize_script('cff-admin', 'CFFP', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cffp'),
        'archives' => $this->get_archive_links(),
        'relational_post_types' => $this->get_relational_post_types(),
      ]);

      wp_enqueue_script(
        'cff-post',
        CFFP_URL . 'assets/post.js',
        ['jquery', 'wp-editor', 'cff-select2'],
        $this->asset_ver('assets/post.js'),
        true
      );

      wp_localize_script('cff-post', 'CFFP', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cffp'),
      ]);
    }

    if ($is_term_edit) {
      wp_enqueue_media();
      wp_enqueue_style('cff-taxonomy', CFFP_URL . 'assets/taxonomy.css', [], $this->asset_ver('assets/taxonomy.css'));
      wp_enqueue_script('cff-taxonomy', CFFP_URL . 'assets/taxonomy.js', ['jquery'], $this->asset_ver('assets/taxonomy.js'), true);
    }
  }

  public function enqueue_block_editor_assets() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post') return;
    if (!method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) return;

    $enabled_default = !empty(get_option('cffp_block_sidebar_enabled', 0));
    $enabled = (bool) apply_filters('cff_block_sidebar_enabled', $enabled_default, $screen);
    if (!$enabled) return;

    wp_enqueue_style('cff-block-sidebar', CFFP_URL . 'assets/block-sidebar.css', [], $this->asset_ver('assets/block-sidebar.css'));
    wp_enqueue_script(
      'cff-block-sidebar',
      CFFP_URL . 'assets/block-sidebar.js',
      ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-dom-ready'],
      $this->asset_ver('assets/block-sidebar.js'),
      true
    );
    wp_localize_script('cff-block-sidebar', 'CFFBlockSidebar', [
      'enabled' => true,
      'title' => __('CFF Fields', 'cff'),
      'empty' => __('No CFF field groups are active for this post.', 'cff'),
    ]);
  }

  private function asset_ver($rel_path) {
    $path = CFFP_DIR . ltrim($rel_path, '/');
    return file_exists($path) ? (string) filemtime($path) : CFFP_VERSION;
  }

  private function get_archive_links() {
    $types = get_post_types(['public' => true], 'objects');
    if (empty($types)) return [];

    $links = [];
    foreach ($types as $slug => $obj) {
      if (empty($obj->has_archive)) {
        continue;
      }

      $url = get_post_type_archive_link($slug);
      if (!$url) continue;
      $label = $obj->labels->name ?? $obj->label ?? $slug;
      $links[] = [
        'label' => sanitize_text_field($label),
        'slug' => sanitize_key($slug),
        'url' => esc_url_raw($url),
      ];
    }

    return $links;
  }

  private function get_relational_post_types() {
    $types = get_post_types([
      'show_ui' => true,
    ], 'objects');

    $out = [];
    $seen = [];

    if (!empty($types)) {
      foreach ($types as $slug => $obj) {
        $slug = sanitize_key($slug);

        // skip internal post type plugin
        if ($slug === 'cff_group') continue;

        $label = $obj->labels->singular_name ?? $obj->label ?? $slug;
        $out[] = [
          'value' => $slug,
          'label' => sanitize_text_field($label),
        ];
        $seen[$slug] = true;
      }
    }

    // fallback dari option (biar aman)
    $defs = get_option('cffp_post_types', []);
    if (is_array($defs)) {
      foreach ($defs as $slug => $def) {
        $slug = sanitize_key($slug);
        if (!$slug || isset($seen[$slug])) continue;

        if ($slug === 'cff_group') continue;

        $label = sanitize_text_field($def['singular'] ?? $slug);
        $out[] = [
          'value' => $slug,
          'label' => $label,
        ];
        $seen[$slug] = true;
      }
    }

    return $out;
  }

  public function register_term_meta_fields() {
    $taxonomies = get_taxonomies(['public'=>true], 'names');
    foreach ($taxonomies as $tax) {
      add_action($tax . '_add_form_fields', [$this, 'render_term_fields_add'], 10, 1);
      add_action($tax . '_edit_form_fields', [$this, 'render_term_fields_edit'], 10, 2);
      add_action('created_' . $tax, [$this, 'save_term_fields'], 10, 2);
      add_action('edited_' . $tax, [$this, 'save_term_fields'], 10, 2);
    }
  }

  private function term_image_preview($image_id) {
    if ($image_id) {
      $img = wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'cff-term-thumb']);
      if ($img) return $img;
    }
    return '<span class="description">No image selected</span>';
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

  public function apply_reorder_post_types($q) {
    if (!($q instanceof \WP_Query)) return;
    if (!$q->is_main_query()) return;

    $types = get_option('cffp_reorder_post_types', []);
    if (!is_array($types) || !$types) return;

    $pt = $q->get('post_type');
    if (!$pt) $pt = 'post';
    $pt_list = is_array($pt) ? $pt : [$pt];

    $match = false;
    foreach ($pt_list as $t) {
      if (in_array($t, $types, true)) { $match = true; break; }
    }
    if (!$match) return;

    $orderby = $q->get('orderby');
    if ($orderby && $orderby !== 'date') return;
    $q->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
    if (!$q->get('order')) $q->set('order', 'ASC');
  }

  public function apply_reorder_terms_args($args, $taxonomies) {
    $enabled = get_option('cffp_reorder_taxonomies', []);
    if (!is_array($enabled) || !$enabled) return $args;

    $tax_list = (array) $taxonomies;
    $match = false;
    foreach ($tax_list as $tax) {
      if (in_array($tax, $enabled, true)) { $match = true; break; }
    }
    if (!$match) return $args;
    if (!empty($args['orderby']) && $args['orderby'] !== 'name') return $args;

    $args['meta_key'] = 'cffp_term_order';
    $args['orderby'] = 'meta_value_num';
    if (empty($args['order'])) $args['order'] = 'ASC';
    return $args;
  }

  public function category_rewrite_rules($rules) {
    $base = get_option('category_base');
    $base = is_string($base) ? trim($base) : '';
    if ($base !== 'category') return $rules;

    $terms = get_terms([
      'taxonomy' => 'category',
      'hide_empty' => false,
    ]);
    if (is_wp_error($terms) || empty($terms)) return $rules;

    $new = [];
    foreach ($terms as $term) {
      $slug = $term->slug;
      if (!$slug) continue;
      $new[$slug . '/?$'] = 'index.php?category_name=' . $slug;
      $new[$slug . '/page/([0-9]+)/?$'] = 'index.php?category_name=' . $slug . '&paged=$matches[1]';
    }
    return $new + $rules;
  }

  public function category_link_no_base($link, $term_id) {
    $base = get_option('category_base');
    $base = is_string($base) ? trim($base) : '';
    if ($base !== 'category') return $link;
    return str_replace('/' . trim($base, '/') . '/', '/', $link);
  }

  public function ajax_reorder_get_posts() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

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
    $is_hierarchical = $post_type_object && !empty($post_type_object->hierarchical);

    if ($is_hierarchical) {
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
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

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

    foreach ($order as $id) {
      $id = absint($id);
      if (!$id) continue;

      $post = $posts_by_id[$id] ?? null;
      if (!$post || $post->post_type !== $pt) continue;

      $parent_id = (int) $post->post_parent;
      $menu_order = $parent_counts[$parent_id] ?? 0;
      wp_update_post(['ID' => $id, 'menu_order' => $menu_order]);
      $parent_counts[$parent_id] = $menu_order + 1;
    }

    $enabled = get_option('cffp_reorder_post_types', []);
    if (!is_array($enabled)) $enabled = [];
    if (!in_array($pt, $enabled, true)) {
      $enabled[] = $pt;
      update_option('cffp_reorder_post_types', $enabled);
    }

    wp_send_json_success(['count' => count($order)]);
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

  public function ajax_reorder_get_terms() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

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
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

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
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

    $groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'any',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
      'no_found_rows' => true,
    ]);

    if (is_wp_error($groups)) wp_send_json_error(['message' => 'Failed to load field groups'], 500);

    usort($groups, function($a, $b){
      $sa = get_post_meta($a->ID, '_cff_settings', true);
      $sb = get_post_meta($b->ID, '_cff_settings', true);
      $oa = intval($sa['presentation']['order'] ?? 0);
      $ob = intval($sb['presentation']['order'] ?? 0);
      if ($oa === $ob) return strcasecmp($a->post_title ?? '', $b->post_title ?? '');
      return ($oa < $ob) ? -1 : 1;
    });

    $out = [];
    foreach ($groups as $g) {
      $settings = get_post_meta($g->ID, '_cff_settings', true);
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
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('absint', $_POST['order']) : [];
    if (!$order) wp_send_json_error(['message'=>'Invalid payload'], 400);

    foreach ($order as $i => $id) {
      if (!$id) continue;
      $group = get_post($id);
      if (!$group || $group->post_type !== 'cff_group') continue;
      $settings = get_post_meta($id, '_cff_settings', true);
      if (!is_array($settings)) $settings = [];
      $presentation = isset($settings['presentation']) && is_array($settings['presentation']) ? $settings['presentation'] : [];
      $presentation['order'] = $i;
      $settings['presentation'] = $this->sanitize_presentation($presentation);
      update_post_meta($id, '_cff_settings', $settings);
    }

    wp_send_json_success(['count' => count($order)]);
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

  private function polylang_languages() {
    if (!function_exists('pll_languages_list')) return [];
    $slugs = pll_languages_list(['fields' => 'slug']);
    $names = pll_languages_list(['fields' => 'name']);
    if (!is_array($slugs)) return [];
    $out = [];
    foreach ($slugs as $i => $slug) {
      $name = (is_array($names) && isset($names[$i])) ? $names[$i] : $slug;
      $out[] = ['slug' => $slug, 'name' => $name];
    }
    return $out;
  }

  private function i18n_value($def, $key, $fallback) {
    $lang = $this->current_lang();
    if (!$lang) return $fallback;
    $map = $def[$key] ?? [];
    if (is_array($map) && !empty($map[$lang])) {
      return sanitize_text_field($map[$lang]);
    }
    return $fallback;
  }

  private function i18n_slug($def, $key, $fallback) {
    $lang = $this->current_lang();
    if (!$lang) return $fallback;
    $map = $def[$key] ?? [];
    if (is_array($map) && !empty($map[$lang])) {
      return sanitize_title($map[$lang]);
    }
    return $fallback;
  }

  private function i18n_slug_for_lang($def, $key, $lang, $fallback) {
    if (!$lang) return $fallback;
    $map = $def[$key] ?? [];
    if (is_array($map) && !empty($map[$lang])) {
      return sanitize_title($map[$lang]);
    }
    return $fallback;
  }

  public function add_polylang_rewrite_rules() {
    if (!function_exists('pll_default_language') || !function_exists('pll_home_url')) return;

    $default_lang = pll_default_language('slug');
    if (!$default_lang) return;
    $hide_default = trailingslashit(pll_home_url($default_lang)) === trailingslashit(home_url('/'));

    $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : [];
    if (!is_array($langs) || !$langs) return;

    $defs = get_option('cffp_post_types', []);
    if (is_array($defs)) {
      foreach ($defs as $key => $def) {
        if (empty($def['has_archive'])) continue;
        $base_slug = sanitize_title($def['slug'] ?? $key);
        foreach ($langs as $lang) {
          $slug = $this->i18n_slug_for_lang($def, 'slug_i18n', $lang, $base_slug);
          if (!$slug) continue;
          $prefix = ($hide_default && $lang === $default_lang) ? '' : $lang . '/';

          add_rewrite_rule(
            '^' . $prefix . $slug . '/?$',
            'index.php?post_type=' . $key . '&lang=' . $lang,
            'top'
          );
          add_rewrite_rule(
            '^' . $prefix . $slug . '/page/([0-9]+)/?$',
            'index.php?post_type=' . $key . '&paged=$matches[1]&lang=' . $lang,
            'top'
          );
        }
      }
    }

    $tax_defs = get_option('cffp_taxonomies', []);
    if (is_array($tax_defs)) {
      foreach ($tax_defs as $tax => $def) {
        if (!taxonomy_exists($tax)) continue;
        $tax_obj = get_taxonomy($tax);
        if (!$tax_obj || empty($tax_obj->rewrite)) continue;

        $base_slug = sanitize_title($def['slug'] ?? $tax_obj->rewrite['slug'] ?? $tax);
        $query_var = $tax_obj->query_var ? $tax_obj->query_var : $tax;

        foreach ($langs as $lang) {
          $slug = $this->i18n_slug_for_lang($def, 'slug_i18n', $lang, $base_slug);
          if (!$slug) continue;
          $prefix = ($hide_default && $lang === $default_lang) ? '' : $lang . '/';

          add_rewrite_rule(
            '^' . $prefix . $slug . '/(.+?)/?$',
            'index.php?' . $query_var . '=$matches[1]&lang=' . $lang,
            'top'
          );
        }
      }
    }

    if (taxonomy_exists('category')) {
      $cat_base = get_option('category_base');
      $cat_base = is_string($cat_base) ? trim($cat_base) : '';
      $use_base = ($cat_base !== '' && $cat_base !== 'category');
      $terms = null;

      foreach ($langs as $lang) {
        $prefix = ($hide_default && $lang === $default_lang) ? '' : $lang . '/';

        if ($use_base) {
          add_rewrite_rule(
            '^' . $prefix . preg_quote($cat_base, '/') . '/(.+?)/?$',
            'index.php?category_name=$matches[1]&lang=' . $lang,
            'top'
          );
          add_rewrite_rule(
            '^' . $prefix . preg_quote($cat_base, '/') . '/(.+?)/page/([0-9]+)/?$',
            'index.php?category_name=$matches[1]&paged=$matches[2]&lang=' . $lang,
            'top'
          );
          continue;
        }

        if ($terms === null) {
          $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
          ]);
        }
        if (is_wp_error($terms) || empty($terms)) continue;
        $seen = [];
        foreach ($terms as $t) {
          $term_id = function_exists('pll_get_term') ? pll_get_term($t->term_id, $lang) : $t->term_id;
          if (!$term_id) continue;
          $term_obj = get_term($term_id, 'category');
          if (!$term_obj || is_wp_error($term_obj)) continue;
          $slug = $term_obj->slug;
          if (!$slug || isset($seen[$slug])) continue;
          $seen[$slug] = true;

          add_rewrite_rule(
            '^' . $prefix . preg_quote($slug, '/') . '/?$',
            'index.php?category_name=' . $slug . '&lang=' . $lang,
            'top'
          );
          add_rewrite_rule(
            '^' . $prefix . preg_quote($slug, '/') . '/page/([0-9]+)/?$',
            'index.php?category_name=' . $slug . '&paged=$matches[1]&lang=' . $lang,
            'top'
          );
        }
      }
    }
  }

  public function pll_translate_post_type_rewrite_slug($slug, $post_type, $lang) {
    if (!function_exists('pll_current_language')) return $slug;
    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs) || !isset($defs[$post_type])) return $slug;
    $def = $defs[$post_type];
    $map = isset($def['slug_i18n']) && is_array($def['slug_i18n']) ? $def['slug_i18n'] : [];
    if (!empty($map[$lang])) return sanitize_title($map[$lang]);
    return $slug;
  }

  public function pll_translate_taxonomy_rewrite_slug($slug, $taxonomy, $lang) {
    if (!function_exists('pll_current_language')) return $slug;
    $defs = get_option('cffp_taxonomies', []);
    if (!is_array($defs) || !isset($defs[$taxonomy])) return $slug;
    $def = $defs[$taxonomy];
    $map = isset($def['slug_i18n']) && is_array($def['slug_i18n']) ? $def['slug_i18n'] : [];
    if (!empty($map[$lang])) return sanitize_title($map[$lang]);
    return $slug;
  }

  public function pll_fix_archive_lang_link($url, $lang, $args) {
    if (!function_exists('pll_home_url')) return $url;
    if (!is_post_type_archive()) return $url;

    $pt = get_query_var('post_type');
    if (is_array($pt)) $pt = reset($pt);
    if (!$pt) return $url;

    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs) || !isset($defs[$pt])) return $url;
    $def = $defs[$pt];
    $slug = $this->i18n_slug_for_lang($def, 'slug_i18n', $lang, sanitize_title($def['slug'] ?? $pt));
    if (!$slug) return $url;

    $base = pll_home_url($lang);
    return trailingslashit($base . ltrim($slug, '/'));
  }

  public function pll_fix_taxonomy_lang_link($url, $lang, $args) {
    if (!function_exists('pll_home_url')) return $url;
    if (!is_tax() && !is_category() && !is_tag()) return $url;

    $term = get_queried_object();
    if (!$term || empty($term->taxonomy)) return $url;

    $tax = $term->taxonomy;
    $tax_obj = get_taxonomy($tax);
    if (!$tax_obj || empty($tax_obj->rewrite)) return $url;

    $defs = get_option('cffp_taxonomies', []);
    $def = (is_array($defs) && isset($defs[$tax])) ? $defs[$tax] : [];

    $base_slug = sanitize_title($def['slug'] ?? $tax_obj->rewrite['slug'] ?? $tax);
    $tax_slug = $this->i18n_slug_for_lang($def, 'slug_i18n', $lang, $base_slug);
    if ($tax === 'category') {
      $cat_base = get_option('category_base');
      $cat_base = is_string($cat_base) ? trim($cat_base) : '';
      if ($cat_base === 'category') $tax_slug = '';
    }

    $term_slug = $term->slug;
    if (function_exists('pll_get_term')) {
      $term_id = pll_get_term($term->term_id, $lang);
      if ($term_id) {
        $term_obj = get_term($term_id, $tax);
        if ($term_obj && !is_wp_error($term_obj)) {
          $term_slug = $term_obj->slug;
        }
      }
    }

    $base = pll_home_url($lang);
    $path = $tax_slug ? (ltrim($tax_slug, '/') . '/' . ltrim($term_slug, '/')) : ltrim($term_slug, '/');
    return trailingslashit($base . $path);
  }


  public function meta_boxes() {
    add_meta_box('cff_group_settings', __('Settings', 'cff'), [$this,'render_group_settings'], 'cff_group', 'normal', 'high');
    add_meta_box('cff_global_ui_settings', __('Editor UI Settings', 'cff'), [$this,'render_global_ui_settings'], 'cff_options', 'normal', 'high');
  }

  public function render_global_ui_settings($post) {
    if (!$post || $post->post_type !== 'cff_options') return;
    wp_nonce_field('cff_global_ui_settings_save', 'cff_global_ui_settings_nonce');

    $enabled = !empty(get_option('cffp_block_sidebar_enabled', 0));
    $keep_data = !empty(get_option('cffp_keep_data_on_uninstall', 1));

    echo '<div class="cff-global-settings-stack">';

    echo '<section class="cff-setting-card">';
    echo '<div class="cff-setting-copy">';
    echo '<h3>' . esc_html__('Gutenberg Sidebar Panels', 'cff') . '</h3>';
    echo '<p>' . esc_html__('Move matching CFF metabox groups into document sidebar panels when editing posts in the block editor.', 'cff') . '</p>';
    echo '<p class="description">' . esc_html__('You can still override this behavior in code via the `cff_block_sidebar_enabled` filter.', 'cff') . '</p>';
    echo '</div>';
    echo '<label class="cff-switch" aria-label="' . esc_attr__('Enable CFF panels in Gutenberg document sidebar.', 'cff') . '">';
    echo '<input type="checkbox" name="cffp_block_sidebar_enabled" value="1" ' . checked($enabled, true, false) . '>';
    echo '<span class="cff-slider"></span>';
    echo '</label>';
    echo '</section>';

    echo '<section class="cff-setting-card is-warning">';
    echo '<div class="cff-setting-copy">';
    echo '<h3>' . esc_html__('Uninstall Data Policy', 'cff') . '</h3>';
    echo '<p>' . esc_html__('Keep all CFF configuration and saved field values when the plugin is removed.', 'cff') . '</p>';
    echo '<p class="description">' . esc_html__('Recommended. Disable this only if uninstall should permanently delete field groups, settings, reorder data, and saved `_cff_*` values.', 'cff') . '</p>';
    echo '</div>';
    echo '<label class="cff-switch" aria-label="' . esc_attr__('Keep CFF data when the plugin is uninstalled.', 'cff') . '">';
    echo '<input type="checkbox" name="cffp_keep_data_on_uninstall" value="1" ' . checked($keep_data, true, false) . '>';
    echo '<span class="cff-slider"></span>';
    echo '</label>';
    echo '</section>';

    echo '</div>';
  }

  public function save_global_ui_settings($post_id, $post) {
    if (!$post || $post->post_type !== 'cff_options') return;
    if (!isset($_POST['cff_global_ui_settings_nonce']) || !wp_verify_nonce($_POST['cff_global_ui_settings_nonce'], 'cff_global_ui_settings_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $enabled = !empty($_POST['cffp_block_sidebar_enabled']) ? 1 : 0;
    $keep_data = !empty($_POST['cffp_keep_data_on_uninstall']) ? 1 : 0;

    update_option('cffp_block_sidebar_enabled', $enabled);
    update_option('cffp_keep_data_on_uninstall', $keep_data);
  }

  public function render_group_settings($post) {
    wp_nonce_field('cff_group_save', 'cff_group_nonce');

    $settings = get_post_meta($post->ID, '_cff_settings', true);
    if (!is_array($settings)) $settings = [];

    $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];
    $fields_json = wp_json_encode($fields);

    $location = isset($settings['location']) && is_array($settings['location']) ? $settings['location'] : [
      [ ['param'=>'post_type','operator'=>'==','value'=>'post'] ]
    ];
    $location_json = wp_json_encode($location);

    $presentation = isset($settings['presentation']) && is_array($settings['presentation']) ? $settings['presentation'] : [
      'style' => 'standard',
      'position' => 'normal',
      'label_placement' => 'top',
      'instruction_placement' => 'below_labels',
      'order' => 0,
      'hide_on_screen' => (object)[],
    ];
    $presentation_json = wp_json_encode($presentation);

    echo '<div id="message" class="notice notice-info inline">';
    echo '<p>Fields marked with <span class="cff-required-indicator" aria-hidden="true">*</span> are required.</p>';
    echo '</div>';

    echo '<div class="cff-tabs">';
    echo '<div class="cff-tabbar">';
    echo '<button type="button" class="cff-tab active" data-tab="fields">Fields</button>';
    echo '<button type="button" class="cff-tab" data-tab="location">Location Rules</button>';
    echo '<button type="button" class="cff-tab" data-tab="presentation">Presentation</button>';
    echo '</div>';

    echo '<div class="cff-tabpanel active" data-panel="fields">';
    echo '<input type="hidden" id="cff_fields_json" name="cff_fields_json" value="'.esc_attr($fields_json).'">';
    echo '<div id="cff-fields-builder"></div>';
    echo '</div>';

    echo '<div class="cff-tabpanel" data-panel="location">';
    echo '<input type="hidden" id="cff_location_json" name="cff_location_json" value="'.esc_attr($location_json).'">';
    echo '<div id="cff-location-builder"></div>';
    echo '</div>';

    echo '<div class="cff-tabpanel" data-panel="presentation">';
    echo '<input type="hidden" id="cff_presentation_json" name="cff_presentation_json" value="'.esc_attr($presentation_json).'">';

    echo '<div id="cff-presentation-builder" class="cff-presentation">';
    echo '  <div class="cff-presentation-grid">';

    echo '    <div class="cff-pres-left">';

    echo '      <div class="cff-pres-section">';
    echo '        <h3>Style</h3>';
    echo '        <div class="cff-btn-group" data-name="style">';
    echo '          <button type="button" data-value="standard">Standard (WP metabox)</button>';
    echo '          <button type="button" data-value="seamless">Seamless (no metabox)</button>';
    echo '        </div>';
    echo '      </div>';

    echo '      <div class="cff-pres-section">';
    echo '        <h3>Position</h3>';
    echo '        <div class="cff-btn-group" data-name="position">';
    echo '          <button type="button" data-value="high">High (after title)</button>';
    echo '          <button type="button" data-value="normal">Normal (after content)</button>';
    echo '          <button type="button" data-value="side">Side</button>';
    echo '        </div>';
    echo '      </div>';

    echo '      <div class="cff-pres-section">';
    echo '        <h3>Label Placement</h3>';
    echo '        <div class="cff-btn-group" data-name="label_placement">';
    echo '          <button type="button" data-value="top">Top aligned</button>';
    echo '          <button type="button" data-value="left">Left aligned</button>';
    echo '        </div>';
    echo '      </div>';

    echo '      <div class="cff-pres-section">';
    echo '        <h3>Instruction Placement</h3>';
    echo '        <div class="cff-btn-group" data-name="instruction_placement">';
    echo '          <button type="button" data-value="below_labels">Below labels</button>';
    echo '          <button type="button" data-value="below_fields">Below fields</button>';
    echo '        </div>';
    echo '      </div>';

    echo '      <div class="cff-pres-section">';
    echo '        <h3>Order No.</h3>';
    echo '        <input type="number" id="cff-order" class="cff-order-input" value="'.esc_attr(intval($presentation['order'] ?? 0)).'">';
    echo '        <p class="description">Field groups with a lower order will appear first</p>';
    echo '      </div>';

    echo '    </div>'; // left

    echo '    <div class="cff-pres-right">';
    echo '      <div class="cff-pres-section cff-hide-screen">';
    echo '        <div class="cff-pres-right-head">';
    echo '          <h3>Hide on screen</h3>';
    echo '          <span class="cff-pres-help" title="Hide default WordPress panels for matched screens.">?</span>';
    echo '        </div>';

    echo '        <label class="cff-check"><input type="checkbox" data-key="toggle_all"> <span>Toggle All</span></label>';

    $hide_keys = [
      'permalink' => 'Permalink',
      'editor' => 'Content Editor',
      'excerpt' => 'Excerpt',
      'discussion' => 'Discussion',
      'comments' => 'Comments',
      'revisions' => 'Revisions',
      'slug' => 'Slug',
      'author' => 'Author',
      'format' => 'Format',
      'page_attributes' => 'Page Attributes',
      'featured_image' => 'Featured Image',
      'categories' => 'Categories',
      'tags' => 'Tags',
      'trackbacks' => 'Send Trackbacks',
      'field_actions' => 'Field Actions',
      'copy_to_translations' => 'Save + Copy CFF to Translations Button',
    ];

    foreach ($hide_keys as $k => $label) {
      $checked = !empty($presentation['hide_on_screen'][$k]) ? 'checked' : '';
      echo '<label class="cff-check"><input type="checkbox" data-key="'.esc_attr($k).'" '.$checked.'> <span>'.esc_html($label).'</span></label>';
    }

    echo '      </div>';
    echo '    </div>'; // right

    echo '  </div>'; // grid
    echo '</div>'; // builder

    echo '</div>'; // panel

    $this->render_js_templates();
  }

  private function render_js_templates() {
    ?>
    <!-- Field Builder templates -->
    <script type="text/template" id="tmpl-cff-field">
      <div class="cff-field-row" data-i="{{i}}">
        <div class="cff-handle-wrap">
          <button type="button" class="cff-acc-toggle" aria-expanded="true"></button>
          <div class="cff-handle"></div>
        </div>
        <div class="cff-field-structure">
          <div class="cff-field-head">
            <div class="cff-col">
              <label>Label</label>
              <input type="text" class="cff-input cff-label" value="{{label}}">
            </div>
            <div class="cff-col">
              <label>Name</label>
              <input type="text" class="cff-input cff-name" value="{{name}}">
            </div>
            <div class="cff-col cff-row-type">
              <div class="cff-row-type-main">
                <label>Type</label>
                <select class="cff-input cff-type cff-select2">
                  <option value="text">Text</option>
                  <option value="number">Number</option>
                  <option value="textarea">Textarea</option>
                  <option value="wysiwyg">WYSIWYG</option>
                  <option value="color">Color</option>
                  <option value="url">URL (Simple)</option>
                  <option value="link">Link (URL + Label Button)</option>
                  <option value="embed">Embed</option>
                  <option value="choice">Choice</option>
                  <option value="relational">Relational</option>
                  <option value="date_picker">Date Picker</option>
                  <option value="datetime_picker">Date Time Picker</option>
                  <option value="checkbox">Checkbox</option>
                  <option value="image">Image</option>
                  <option value="gallery">Gallery</option>
                  <option value="file">File</option>
                  <option value="repeater">Repeater</option>
                  <option value="group">Group</option>
                  <option value="flexible">Flexible Content</option>
                </select>
              </div>
            </div>
            <div class="cff-col cff-actions">
              <button type="button" class="button cff-icon-button cff-duplicate" aria-label="Duplicate field">
                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
              </button>
              <button type="button" class="button cff-icon-button cff-remove" aria-label="Remove field">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="cff-field-meta-row">
            <div class="cff-row-placeholder">
              <label>Placeholder</label>
              <input type="text" class="cff-input cff-placeholder" placeholder="Placeholder" value="{{placeholder}}">
            </div>
            <div class="cff-row-repeater-options">
              <label>Repeater Layout</label>
              <select class="cff-input cff-repeater-layout cff-select2">
                <option value="default">Default (stacked rows)</option>
                <option value="simple">Simple (remove-only header)</option>
                <option value="grid">Grid (multi-column)</option>
                <option value="row">Row (single horizontal row)</option>
                <option value="gallery">Gallery Images</option>
                <option value="table">Table (fill values inline)</option>
              </select>
              <p class="description">Choose how each repeater row is presented while editing.</p>
              <div class="cff-row-repeater-advanced">
                <div class="cff-row-repeater-col">
                  <label>Min Rows</label>
                  <input type="number" class="cff-input cff-repeater-min" min="0" step="1" value="0">
                </div>
                <div class="cff-row-repeater-col">
                  <label>Max Rows (0 = unlimited)</label>
                  <input type="number" class="cff-input cff-repeater-max" min="0" step="1" value="0">
                </div>
              </div>
              <div class="cff-row-repeater-col">
                <label>Row Label Field (sub field name)</label>
                <input type="text" class="cff-input cff-repeater-row-label" placeholder="title">
              </div>
              <span class="cff-tools-toggles cff-row-repeater-collapse">
                <div><strong>Collapsed by default</strong></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-repeater-collapsed-toggle">
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-datetime-options">
              <span class="cff-tools-toggles">
                <div><strong>Use Time</strong><div class="description">Enable time selector for datetime picker.</div></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-datetime-use-time-toggle" checked>
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-media-options">
              <label>File Library</label>
              <select class="cff-input cff-file-library cff-select2">
                <option value="all">All files</option>
                <option value="pdf">PDF only</option>
                <option value="excel">Excel only</option>
                <option value="word">Word only</option>
                <option value="image">Images only</option>
                <option value="video">Video only</option>
                <option value="document">Document bundle</option>
              </select>
              <label>Max Upload Size (MB)</label>
              <input type="number" class="cff-input cff-max-upload-mb" min="1" step="1" value="2">
              <p class="description">Default 2 MB. Set a larger value for this image or file field only.</p>
            </div>
            <div class="cff-row-rules">
              <div class="cff-row-required">
                <span class="cff-tools-toggles">
                  <div>
                    <strong>Required</strong>
                  </div>
                  <label class="cff-switch">
                    <input type="checkbox" class="cff-required-toggle">
                    <span class="cff-slider"></span>
                  </label>
                </span>
              </div>
              <div class="cff-row-conditional">
                <span class="cff-tools-toggles">
                  <div><strong>Conditional Logic</strong></div>
                  <label class="cff-switch">
                    <input type="checkbox" class="cff-conditional-enabled">
                    <span class="cff-slider"></span>
                  </label>
                </span>
                <div class="cff-conditional-config" style="margin-top:8px;display:none;">
                  <p><label>Field Name</label>
                    <select class="cff-input cff-conditional-field cff-select2">
                      <option value="">Select field…</option>
                    </select>
                  </p>
                  <p><label>Operator</label>
                    <select class="cff-input cff-conditional-operator cff-select2">
                      <option value="==">is equal to</option>
                      <option value="!=">is not equal to</option>
                      <option value="contains">contains</option>
                      <option value="not_contains">does not contain</option>
                      <option value="empty">is empty</option>
                      <option value="not_empty">is not empty</option>
                    </select>
                  </p>
                  <p class="cff-conditional-value-row"><label>Value</label>
                  <input type="text" class="cff-input cff-conditional-value" placeholder="value"></p>
                </div>
              </div>
            </div>
          </div>
          <div class="cff-field-choice is-hidden">
            <div class="cff-subhead">
              <strong>Choices</strong>
              <button type="button" class="button cff-add-choice">Add Choice</button>
            </div>
            <div class="cff-row-choice-display">
              <label>Display</label>
              <select class="cff-input cff-choice-display cff-select2">
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio Button</option>
                <option value="button_group">Button Group</option>
                <option value="true_false">True / False</option>
              </select>
            </div>
            <div class="cff-row-choice-default">
              <label>Default Choice</label>
              <select class="cff-input cff-choice-default cff-select2">
                <option value="">None</option>
              </select>
            </div>
            <div class="cff-choices-list"></div>
          </div>
          <div class="cff-field-relational is-hidden">
            <div class="cff-subhead">
              <strong>Relational Settings</strong>
            </div>
            <div class="cff-row-relational-type">
              <label>Relation Type</label>
              <select class="cff-input cff-relational-type cff-select2">
                <option value="post">Post Only</option>
                <option value="page">Page Only</option>
                <option value="post_and_page">Post & Page</option>
                <option value="post_type">Custom Post Type</option>
                <option value="taxonomy">Taxonomy</option>
                <option value="user">User</option>
              </select>
            </div>
            <div class="cff-row-relational-subtype" style="display:none;">
              <label>Select Type</label>
              <select class="cff-input cff-relational-subtype cff-select2"></select>
            </div>
            <div class="cff-row-relational-display">
              <label>Display</label>
              <select class="cff-input cff-relational-display cff-select2">
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio Button</option>
              </select>
            </div>
            <div class="cff-row-relational-multiple">
              <span class="cff-tools-toggles">
                <div><strong>Multiple</strong></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-relational-multiple-toggle">
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-relational-archives">
              <strong>Archive Links</strong>
              <div class="cff-relational-archive-list"></div>
            </div>
          </div>
        </div>

        <div class="cff-advanced">
          <div class="cff-subbuilder" data-kind="repeater">
            <div class="cff-subhead">
              <strong>Sub Fields (Repeater)</strong>
              <button type="button" class="button cff-add-sub">Add Sub Field</button>
            </div>
            <div class="cff-subfields-table-head" aria-hidden="true">
              <div>Label</div>
              <div>Name</div>
              <div>Type</div>
              <div>Actions</div>
            </div>
            <div class="cff-subfields"></div>
          </div>

          <div class="cff-groupbuilder" data-kind="group">
            <div class="cff-subhead">
              <strong>Group Fields</strong>
              <button type="button" class="button cff-add-group-sub">Add Field</button>
            </div>
            <div class="cff-group-fields"></div>
          </div>

          <div class="cff-flexbuilder" data-kind="flexible">
            <div class="cff-subhead">
              <strong>Layouts (Flexible Content)</strong>
              <button type="button" class="button cff-add-layout">Add Layout</button>
            </div>
            <div class="cff-layouts"></div>
          </div>
        </div>
      </div>
  </script>

    <script type="text/template" id="tmpl-cff-subfield">
      <div class="cff-subfield" data-si="{{si}}">
        <div class="cff-handle-wrap">
          <button type="button" class="cff-sub-acc-toggle" aria-expanded="true"></button>
          <div class="cff-handle"></div>
        </div>
        <div class="cff-subfield-structure">
          <div class="cff-field-head">
            <div class="cff-col">
              <label>Label</label>
              <input type="text" class="cff-input cff-slabel" value="{{label}}">
            </div>
            <div class="cff-col">
              <label>Name</label>
              <input type="text" class="cff-input cff-sname" value="{{name}}">
            </div>
            <div class="cff-col cff-row-type">
              <div class="cff-row-type-main">
                <label>Type</label>
                <select class="cff-input cff-stype cff-select2">
                  <option value="text">Text</option>
                  <option value="number">Number</option>
                  <option value="textarea">Textarea</option>
                  <option value="wysiwyg">WYSIWYG</option>
                  <option value="color">Color</option>
                  <option value="url">URL (Simple)</option>
                  <option value="link">Link (URL + Label Button)</option>
                  <option value="embed">Embed</option>
                  <option value="choice">Choice</option>
                  <option value="relational">Relational</option>
                  <option value="date_picker">Date Picker</option>
                  <option value="datetime_picker">Date Time Picker</option>
                  <option value="checkbox">Checkbox</option>
                  <option value="image">Image</option>
                  <option value="gallery">Gallery</option>
                  <option value="file">File</option>
                  <option value="repeater">Repeater</option>
                  <option value="group">Group</option>
                </select>
              </div>
            </div>
            <div class="cff-col cff-actions">
              <button type="button" class="button cff-icon-button cff-duplicate-sub" aria-label="Duplicate sub field">
                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
              </button>
              <button type="button" class="button cff-icon-button cff-remove-sub" aria-label="Remove sub field">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="cff-field-meta-row">
            <div class="cff-row-placeholder">
              <label>Placeholder</label>
              <input type="text" class="cff-input cff-placeholder" placeholder="Placeholder" value="{{placeholder}}">
            </div>
            <div class="cff-row-repeater-options">
              <label>Repeater Layout</label>
              <select class="cff-input cff-repeater-layout cff-select2">
                <option value="default">Default (stacked rows)</option>
                <option value="simple">Simple (remove-only header)</option>
                <option value="grid">Grid (multi-column)</option>
                <option value="row">Row (single horizontal row)</option>
                <option value="gallery">Gallery Images</option>
                <option value="table">Table (fill values inline)</option>
              </select>
              <p class="description">Choose how each repeater row is presented while editing.</p>
              <div class="cff-row-repeater-advanced">
                <div class="cff-row-repeater-col">
                  <label>Min Rows</label>
                  <input type="number" class="cff-input cff-repeater-min" min="0" step="1" value="0">
                </div>
                <div class="cff-row-repeater-col">
                  <label>Max Rows (0 = unlimited)</label>
                  <input type="number" class="cff-input cff-repeater-max" min="0" step="1" value="0">
                </div>
              </div>
              <div class="cff-row-repeater-col">
                <label>Row Label Field (sub field name)</label>
                <input type="text" class="cff-input cff-repeater-row-label" placeholder="title">
              </div>
              <span class="cff-tools-toggles cff-row-repeater-collapse">
                <div><strong>Collapsed by default</strong></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-repeater-collapsed-toggle">
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-datetime-options">
              <span class="cff-tools-toggles">
                <div><strong>Use Time</strong><div class="description">Enable time selector for datetime picker.</div></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-datetime-use-time-toggle" checked>
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-media-options">
              <label>File Library</label>
              <select class="cff-input cff-file-library cff-select2">
                <option value="all">All files</option>
                <option value="pdf">PDF only</option>
                <option value="excel">Excel only</option>
                <option value="word">Word only</option>
                <option value="image">Images only</option>
                <option value="video">Video only</option>
                <option value="document">Document bundle</option>
              </select>
              <label>Max Upload Size (MB)</label>
              <input type="number" class="cff-input cff-max-upload-mb" min="1" step="1" value="2">
              <p class="description">Default 2 MB. Set a larger value for this image or file field only.</p>
            </div>
            <div class="cff-row-rules">
              <div class="cff-row-required">
                <span class="cff-tools-toggles">
                  <div>
                    <strong>Required</strong>
                  </div>
                  <label class="cff-switch">
                    <input type="checkbox" class="cff-required-toggle">
                    <span class="cff-slider"></span>
                  </label>
                </span>
              </div>
              <div class="cff-row-conditional">
                <span class="cff-tools-toggles">
                  <div><strong>Conditional Logic</strong></div>
                  <label class="cff-switch">
                    <input type="checkbox" class="cff-conditional-enabled">
                    <span class="cff-slider"></span>
                  </label>
                </span>
                <div class="cff-conditional-config" style="margin-top:8px;display:none;">
                  <p><label>Field Name</label>
                    <select class="cff-input cff-conditional-field cff-select2">
                      <option value="">Select field…</option>
                    </select>
                  </p>
                  <p><label>Operator</label>
                    <select class="cff-input cff-conditional-operator cff-select2">
                      <option value="==">is equal to</option>
                      <option value="!=">is not equal to</option>
                      <option value="contains">contains</option>
                      <option value="not_contains">does not contain</option>
                      <option value="empty">is empty</option>
                      <option value="not_empty">is not empty</option>
                    </select>
                  </p>
                  <p class="cff-conditional-value-row"><label>Value</label>
                  <input type="text" class="cff-input cff-conditional-value" placeholder="value"></p>
                </div>
              </div>
            </div>
          </div>
          <div class="cff-field-choice is-hidden">
            <div class="cff-subhead">
              <strong>Choices</strong>
              <button type="button" class="button cff-add-choice">Add Choice</button>
            </div>
            <div class="cff-row-choice-display">
              <label>Display</label>
              <select class="cff-input cff-choice-display cff-select2">
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio Button</option>
                <option value="button_group">Button Group</option>
                <option value="true_false">True / False</option>
              </select>
            </div>
            <div class="cff-row-choice-default">
              <label>Default Choice</label>
              <select class="cff-input cff-choice-default cff-select2">
                <option value="">None</option>
              </select>
            </div>
            <div class="cff-choices-list"></div>
          </div>
          <div class="cff-field-relational is-hidden">
            <div class="cff-subhead">
              <strong>Relational Settings</strong>
            </div>
            <div class="cff-row-relational-type">
              <label>Relation Type</label>
              <select class="cff-input cff-relational-type cff-select2">
                <option value="post">Post Only</option>
                <option value="page">Page Only</option>
                <option value="post_and_page">Post & Page</option>
                <option value="post_type">Custom Post Type</option>
                <option value="taxonomy">Taxonomy</option>
                <option value="user">User</option>
              </select>
            </div>
            <div class="cff-row-relational-subtype" style="display:none;">
              <label>Select Type</label>
              <select class="cff-input cff-relational-subtype cff-select2"></select>
            </div>
            <div class="cff-row-relational-display">
              <label>Display</label>
              <select class="cff-input cff-relational-display cff-select2">
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio Button</option>
              </select>
            </div>
            <div class="cff-row-relational-multiple">
              <span class="cff-tools-toggles">
                <div><strong>Multiple</strong></div>
                <label class="cff-switch">
                  <input type="checkbox" class="cff-relational-multiple-toggle">
                  <span class="cff-slider"></span>
                </label>
              </span>
            </div>
            <div class="cff-row-relational-archives">
              <strong>Archive Links</strong>
              <div class="cff-relational-archive-list"></div>
            </div>
          </div>
        </div>
        <div class="cff-subbuilder cff-subrepeater" data-kind="repeater">
          <div class="cff-subhead">
            <strong>Sub Fields (Repeater)</strong>
            <button type="button" class="button cff-add-sub">Add Sub Field</button>
          </div>
          <div class="cff-subfields-table-head" aria-hidden="true">
            <div>Label</div>
            <div>Name</div>
            <div>Type</div>
            <div>Actions</div>
          </div>
          <div class="cff-subfields"></div>
        </div>

        <div class="cff-groupbuilder cff-subgroupbuilder" data-kind="group">
          <div class="cff-subhead">
            <strong>Group Fields</strong>
            <button type="button" class="button cff-add-group-sub">Add Field</button>
          </div>
          <div class="cff-group-fields"></div>
        </div>
      </div>
    </script>

    <script type="text/template" id="tmpl-cff-layout">
      <div class="cff-layout" data-li="{{li}}">
        <div class="cff-layout-head">
          <div class="cff-handle"></div>
          <div class="cff-col">
            <label>Layout Label</label>
            <input type="text" class="cff-input cff-llabel" value="{{label}}">
          </div>
          <div class="cff-col">
            <label>Layout Name</label>
            <input type="text" class="cff-input cff-lname" value="{{name}}">
          </div>
          <div class="cff-col cff-actions">
            <button type="button" class="button cff-toggle-layout">Edit Fields</button>
            <button type="button" class="button cff-remove-layout">Remove</button>
          </div>
        </div>
        <div class="cff-layout-body">
          <div class="cff-subhead">
            <strong>Layout Fields</strong>
            <button type="button" class="button cff-add-layout-field">Add Field</button>
          </div>
          <div class="cff-layout-fields"></div>
        </div>
      </div>
    </script>

    <!-- Location Rules templates -->
    <script type="text/html" id="tmpl-cff-loc-group">
      <div class="cff-loc-group" data-gi="{{gi}}">
        <div class="cff-loc-head">
          <strong>Rule Group (OR)</strong>
          <button type="button" class="button cff-loc-remove-group">Remove group</button>
        </div>

        <div class="cff-loc-rules"></div>

        <p>
          <button type="button" class="button cff-loc-add-rule">Add rule (AND)</button>
        </p>
      </div>
    </script>

    <script type="text/html" id="tmpl-cff-loc-rule">
      <div class="cff-loc-rule" data-ri="{{ri}}">
        <select class="cff-input cff-loc-param">
          <option value="post_type">Post Type</option>
          <option value="page_template">Page Template</option>
          <option value="post">Specific Post</option>
          <option value="page">Specific Page</option>
          <option value="options_page">Options Page</option>
        </select>

        <select class="cff-input cff-loc-op">
          <option value="==">is equal to</option>
          <option value="!=">is not equal to</option>
        </select>

        <select class="cff-input cff-loc-value" style="min-width:420px"></select>

        <button type="button" class="button cff-loc-remove-rule">×</button>
      </div>
    </script>
    <?php
  }

  public function save_group($post_id, $post) {
    if (!isset($_POST['cff_group_nonce']) || !wp_verify_nonce($_POST['cff_group_nonce'], 'cff_group_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $settings = [];

    $fields_json = isset($_POST['cff_fields_json']) ? wp_unslash($_POST['cff_fields_json']) : '[]';
    $fields = json_decode($fields_json, true);
    if (!is_array($fields)) $fields = [];

    $location_json = isset($_POST['cff_location_json']) ? wp_unslash($_POST['cff_location_json']) : '[]';
    $location = json_decode($location_json, true);
    if (!is_array($location)) $location = [];

    $presentation_json = isset($_POST['cff_presentation_json']) ? wp_unslash($_POST['cff_presentation_json']) : '{}';
    $presentation = json_decode($presentation_json, true);
    $presentation = $this->sanitize_presentation(is_array($presentation) ? $presentation : []);

    // sanitize fields
    $clean = [];
    $seen = [];
    $seen_keys = [];
    foreach ($fields as $f) {
      $type = isset($f['type']) ? sanitize_key($f['type']) : 'text';
      $name = isset($f['name']) ? sanitize_key($f['name']) : '';
      if (!$name || isset($seen[$name])) continue;
      $seen[$name] = true;

      $field_key = $this->sanitize_field_key($f['key'] ?? '');
      while (isset($seen_keys[$field_key])) {
        $field_key = $this->sanitize_field_key('');
      }
      $seen_keys[$field_key] = true;

      $item = [
        'label' => $this->sanitize_string_value($f['label'] ?? $name),
        'name'  => $name,
        'type'  => $type,
        'key' => $field_key,
        'required' => !empty($f['required']),
        'placeholder' => $this->sanitize_string_value($f['placeholder'] ?? ''),
      ];
      if ($type === 'image' || $type === 'file') {
        $item['file_library'] = $this->sanitize_file_library($f['file_library'] ?? 'document');
        $item['max_upload_mb'] = $this->sanitize_media_max_upload_mb($f['max_upload_mb'] ?? 2);
      }
      $aliases = $this->sanitize_field_aliases($f['aliases'] ?? [], $name);
      if ($aliases) {
        $item['aliases'] = $aliases;
      }
      $conditional_logic = $this->sanitize_conditional_logic($f['conditional_logic'] ?? []);
      if ($conditional_logic) {
        $item['conditional_logic'] = $conditional_logic;
      }

      if ($type === 'repeater') {
        $sub_fields = $this->sanitize_subfields($f['sub_fields'] ?? []);
        $min = $this->sanitize_repeater_min($f['min'] ?? 0);
        $item['sub_fields'] = $sub_fields;
        $item['repeater_layout'] = $this->sanitize_repeater_layout($f['repeater_layout'] ?? 'default');
        $item['min'] = $min;
        $item['max'] = $this->sanitize_repeater_max($f['max'] ?? 0, $min);
        $item['repeater_row_label'] = $this->sanitize_repeater_row_label($f['repeater_row_label'] ?? '', $sub_fields);
        $item['repeater_collapsed'] = !empty($f['repeater_collapsed']);
      }
      if ($type === 'datetime_picker') {
        $item['datetime_use_time'] = !array_key_exists('datetime_use_time', $f) ? true : !empty($f['datetime_use_time']);
      }
      if ($type === 'flexible') {
        $item['layouts'] = $this->sanitize_layouts($f['layouts'] ?? []);
      }
      if ($type === 'group') {
        $item['sub_fields'] = $this->sanitize_subfields($f['sub_fields'] ?? []);
      }
      if ($type === 'choice') {
        $item['choices'] = $this->sanitize_choices($f['choices'] ?? []);
        $item['choice_display'] = $this->sanitize_choice_display($f['choice_display'] ?? '');
        $item['choice_default'] = $this->sanitize_choice_default($f['choice_default'] ?? '', $item['choices'], $item['choice_display']);
      }
      if ($type === 'relational') {
        $item['relational_type'] = $this->sanitize_relational_type($f['relational_type'] ?? 'post');
        $item['relational_subtype'] = $this->sanitize_string_value($f['relational_subtype'] ?? '');
        $item['relational_display'] = $this->sanitize_relational_display($f['relational_display'] ?? 'select');
        $item['relational_multiple'] = !empty($f['relational_multiple']);
      }

      $clean[] = $item;
    }
    $settings['fields'] = $clean;

    // sanitize location groups
    $loc_clean = [];
    foreach ($location as $group) {
      if (!is_array($group)) continue;
      $g = [];
      foreach ($group as $rule) {
        if (!is_array($rule)) continue;
        $param = sanitize_key($rule['param'] ?? 'post_type');
        $op = in_array(($rule['operator'] ?? '=='), ['==','!='], true) ? $rule['operator'] : '==';
        $val = sanitize_text_field($rule['value'] ?? '');
        if (!$val) continue;
        if ($param === 'options_page') {
          $val = sanitize_key(str_replace([' ', '-'], '_', (string) $val));
          if ($val === 'global_settings') $val = 'global';
          if ($val !== 'global') $val = 'global';
        }
        $g[] = ['param'=>$param,'operator'=>$op,'value'=>$val];
      }
      if ($g) $loc_clean[] = $g;
    }
    if (!$loc_clean) $loc_clean = [[['param'=>'post_type','operator'=>'==','value'=>'post']]];
    $settings['location'] = $loc_clean;

    // FIX: presentation ikut disimpan
    $settings['presentation'] = $presentation;

    update_post_meta($post_id, '_cff_settings', $settings);
  }

  private function sanitize_presentation($p) {
    $style = in_array(($p['style'] ?? 'standard'), ['standard','seamless'], true) ? $p['style'] : 'standard';
    $position = in_array(($p['position'] ?? 'normal'), ['high','normal','side'], true) ? $p['position'] : 'normal';
    $label = in_array(($p['label_placement'] ?? 'top'), ['top','left'], true) ? $p['label_placement'] : 'top';
    $instr = in_array(($p['instruction_placement'] ?? 'below_labels'), ['below_labels','below_fields'], true) ? $p['instruction_placement'] : 'below_labels';
    $order = intval($p['order'] ?? 0);

    $allowed_hide = [
      'permalink','editor','excerpt','discussion','comments','revisions','slug',
      'author','format','page_attributes','featured_image','categories','tags','trackbacks',
      'field_actions','copy_to_translations'
    ];

    $hide = [];
    $raw_hide = (isset($p['hide_on_screen']) && is_array($p['hide_on_screen'])) ? $p['hide_on_screen'] : [];
    foreach ($allowed_hide as $k) {
      if (!empty($raw_hide[$k])) $hide[$k] = true;
    }

    return [
      'style' => $style,
      'position' => $position,
      'label_placement' => $label,
      'instruction_placement' => $instr,
      'order' => $order,
      'hide_on_screen' => $hide,
    ];
  }

  private function sanitize_string_value($value) {
    if (is_array($value) || is_object($value)) {
      return '';
    }
    return sanitize_text_field($value ?? '');
  }

  private function sanitize_repeater_layout($value) {
    $allowed = ['default','simple','grid','row','gallery','table'];
    $layout = sanitize_key($value ?? '');
    if (!in_array($layout, $allowed, true)) {
      return 'default';
    }
    return $layout;
  }

  private function sanitize_repeater_min($value) {
    return max(0, intval($value));
  }

  private function sanitize_repeater_max($value, $min = 0) {
    $max = max(0, intval($value));
    $min = max(0, intval($min));
    if ($max > 0 && $max < $min) {
      $max = $min;
    }
    return $max;
  }

  private function sanitize_media_max_upload_mb($value) {
    $size = absint($value);
    return $size > 0 ? $size : 2;
  }

  private function sanitize_file_library($value) {
    $allowed = ['document', 'all', 'pdf', 'excel', 'word', 'image', 'video'];
    $library = sanitize_key($value ?? '');
    return in_array($library, $allowed, true) ? $library : 'document';
  }

  private function sanitize_repeater_row_label($value, $sub_fields = []) {
    $label = sanitize_key($value ?? '');
    if (!$label) return '';
    if (!is_array($sub_fields) || !$sub_fields) return $label;

    foreach ($sub_fields as $sub_field) {
      if (sanitize_key($sub_field['name'] ?? '') === $label) {
        return $label;
      }
    }
    return '';
  }

  private function sanitize_field_key($value = '') {
    $key = sanitize_key($value ?? '');
    if ($key) return $key;
    return 'fld_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12);
  }

  private function sanitize_field_aliases($aliases, $current_name) {
    $current_name = sanitize_key($current_name);
    $out = [];
    foreach ((array) $aliases as $alias) {
      $alias = sanitize_key($alias);
      if (!$alias || $alias === $current_name || isset($out[$alias])) continue;
      $out[$alias] = true;
    }
    return array_keys($out);
  }

  private function sanitize_subfields($subs) {
    $out = [];
    if (!is_array($subs)) return $out;
    $seen = [];
    $seen_keys = [];
    foreach ($subs as $s) {
      $name = sanitize_key($s['name'] ?? '');
      if (!$name) continue;
      if (isset($seen[$name])) continue;
      $seen[$name] = true;
      $type = sanitize_key($s['type'] ?? 'text');
      $field_key = $this->sanitize_field_key($s['key'] ?? '');
      while (isset($seen_keys[$field_key])) {
        $field_key = $this->sanitize_field_key('');
      }
      $seen_keys[$field_key] = true;
      $item = [
        'label' => $this->sanitize_string_value($s['label'] ?? $name),
        'name'  => $name,
        'type'  => $type,
        'key' => $field_key,
        'required' => !empty($s['required']),
        'placeholder' => $this->sanitize_string_value($s['placeholder'] ?? ''),
      ];
      if ($type === 'image' || $type === 'file') {
        $item['file_library'] = $this->sanitize_file_library($s['file_library'] ?? 'document');
        $item['max_upload_mb'] = $this->sanitize_media_max_upload_mb($s['max_upload_mb'] ?? 2);
      }
      $aliases = $this->sanitize_field_aliases($s['aliases'] ?? [], $name);
      if ($aliases) {
        $item['aliases'] = $aliases;
      }
      $conditional_logic = $this->sanitize_conditional_logic($s['conditional_logic'] ?? []);
      if ($conditional_logic) {
        $item['conditional_logic'] = $conditional_logic;
      }
      if ($type === 'relational') {
        $item['relational_type'] = $this->sanitize_relational_type($s['relational_type'] ?? 'post');
        $item['relational_subtype'] = $this->sanitize_string_value($s['relational_subtype'] ?? '');
        $item['relational_display'] = $this->sanitize_relational_display($s['relational_display'] ?? 'select');
        $item['relational_multiple'] = !empty($s['relational_multiple']);
      }
      if ($type === 'datetime_picker') {
        $item['datetime_use_time'] = !array_key_exists('datetime_use_time', $s) ? true : !empty($s['datetime_use_time']);
      }
      if ($type === 'group' || $type === 'repeater') {
        $item['sub_fields'] = $this->sanitize_subfields($s['sub_fields'] ?? []);
        if ($type === 'repeater') {
          $min = $this->sanitize_repeater_min($s['min'] ?? 0);
          $item['repeater_layout'] = $this->sanitize_repeater_layout($s['repeater_layout'] ?? 'default');
          $item['min'] = $min;
          $item['max'] = $this->sanitize_repeater_max($s['max'] ?? 0, $min);
          $item['repeater_row_label'] = $this->sanitize_repeater_row_label($s['repeater_row_label'] ?? '', $item['sub_fields']);
          $item['repeater_collapsed'] = !empty($s['repeater_collapsed']);
        }
      }
      if ($type === 'choice') {
        $item['choices'] = $this->sanitize_choices($s['choices'] ?? []);
        $item['choice_display'] = $this->sanitize_choice_display($s['choice_display'] ?? '');
        $item['choice_default'] = $this->sanitize_choice_default($s['choice_default'] ?? '', $item['choices'], $item['choice_display']);
      }
      $out[] = $item;
    }
    return $out;
  }

  private function sanitize_layouts($layouts) {
    $out = [];
    if (!is_array($layouts)) return $out;
    foreach ($layouts as $l) {
      $lname = sanitize_key($l['name'] ?? '');
      if (!$lname) continue;
      $out[] = [
        'label' => $this->sanitize_string_value($l['label'] ?? $lname),
        'name'  => $lname,
        'sub_fields' => $this->sanitize_subfields($l['sub_fields'] ?? []),
      ];
    }
    return $out;
  }

  private function sanitize_conditional_logic($logic) {
    if (!is_array($logic) || empty($logic['enabled'])) {
      return [];
    }

    $key = sanitize_key($logic['key'] ?? '');
    $field = sanitize_key($logic['field'] ?? '');
    if (!$field && !$key) return [];

    $allowed = ['==', '!=', 'contains', 'not_contains', 'empty', 'not_empty'];
    $operator = in_array(($logic['operator'] ?? '=='), $allowed, true) ? $logic['operator'] : '==';
    $value = sanitize_text_field($logic['value'] ?? '');

    if (in_array($operator, ['empty', 'not_empty'], true)) {
      $value = '';
    }

    $out = [
      'enabled' => true,
      'operator' => $operator,
      'value' => $value,
    ];

    if ($field) {
      $out['field'] = $field;
    }

    if ($key) {
      $out['key'] = $key;
    }

    return $out;
  }

  private function output_hide_on_screen_css($hide) {
    if (!is_array($hide) || !$hide) return;

    $map = [
      'permalink' => '#edit-slug-box, #slugdiv',
      'editor' => '#postdivrich, #wp-content-editor-tools',
      'excerpt' => '#postexcerpt',
      'discussion' => '#commentstatusdiv',
      'comments' => '#commentsdiv',
      'revisions' => '#revisionsdiv',
      'slug' => '#slugdiv',
      'author' => '#authordiv',
      'format' => '#formatdiv',
      'page_attributes' => '#pageparentdiv',
      'featured_image' => '#postimagediv',
      'categories' => '#categorydiv',
      'tags' => '#tagsdiv-post_tag',
      'trackbacks' => '#trackbacksdiv',
      'field_actions' => '.cff-field-actions',
      'copy_to_translations' => '.cff-copy-all-action',
    ];

    $selectors = [];
    foreach ($hide as $k => $on) {
      if ($on && isset($map[$k])) $selectors[] = $map[$k];
    }
    if (!$selectors) return;

    echo '<style>' . implode(',', $selectors) . '{display:none !important;}</style>';
  }

  public function content_meta_boxes($post_type, $post) {
    if (!is_admin()) return;

    // jangan apply untuk editor field group
    if ($post_type === 'cff_group') return;

    if (!$post || empty($post->ID)) return;

    $groups = $this->get_groups_for_context($post);

    if ($post_type === 'cff_options' && empty($groups)) {
      add_meta_box(
        'cff_global_settings_hint',
        esc_html__('Global Settings is empty', 'cff'),
        function() {
          $create_group_url = add_query_arg(
            [
              'post_type' => 'cff_group',
            ],
            admin_url('post-new.php')
          );

          echo '<p>' . esc_html__('No Field Group is assigned to this page yet.', 'cff') . '</p>';
          echo '<p>' . esc_html__('To show fields here, create/edit a Field Group and set Location Rules:', 'cff') . ' <strong>' . esc_html__('Options Page', 'cff') . ' == ' . esc_html__('Global Settings', 'cff') . '</strong>.</p>';
          echo '<p><a class="button button-primary" href="' . esc_url($create_group_url) . '">' . esc_html__('Create Field Group', 'cff') . '</a></p>';
        },
        'cff_options',
        'normal',
        'high'
      );
    }

    // urutkan by order
    usort($groups, function($a, $b){
      $sa = get_post_meta($a->ID, '_cff_settings', true);
      $sb = get_post_meta($b->ID, '_cff_settings', true);
      $oa = intval($sa['presentation']['order'] ?? 0);
      $ob = intval($sb['presentation']['order'] ?? 0);
      return $oa <=> $ob;
    });

    // merge hide_on_screen hanya dari group yang match
    $hide = [];
    foreach ($groups as $g) {
      $s = get_post_meta($g->ID, '_cff_settings', true);
      $h = $s['presentation']['hide_on_screen'] ?? [];
      if (is_array($h)) $hide = array_merge($hide, $h);
    }

    if ($hide) {
      add_action('admin_head', function() use ($hide) {
        $this->output_hide_on_screen_css($hide);
      });
    }

    foreach ($groups as $g) {
      $s = get_post_meta($g->ID, '_cff_settings', true);
      $pres = $s['presentation'] ?? [];

      $context = 'normal';
      $priority = 'high';
      if (($pres['position'] ?? '') === 'side') { $context = 'side'; $priority = 'default'; }
      if (($pres['position'] ?? '') === 'high') { $context = 'advanced'; $priority = 'high'; }

      $box_id = 'cff_group_'.$g->ID;

      add_meta_box(
        $box_id,
        esc_html($g->post_title),
        function($post) use ($g) {
          $settings = get_post_meta($g->ID, '_cff_settings', true);
          $fields = isset($settings['fields']) ? $settings['fields'] : [];
          $fields = $this->get_ordered_group_fields_for_post($post->ID, $g->ID, $fields);
          $field_order = [];
          foreach ($fields as $field_item) {
            $field_name = sanitize_key($field_item['name'] ?? '');
            if ($field_name) {
              $field_order[] = $field_name;
            }
          }
          wp_nonce_field('cff_content_save', 'cff_content_nonce');
          echo '<div class="cff-metabox">';
          echo '<div class="cff-field-view-controls cff-metabox-view">';
          echo '<div class="cff-field-view-copy">';
          echo '<label for="cff-field-view-mode-' . intval($g->ID) . '">' . esc_html__('Field View', 'cff') . '</label>';
          echo '<p>' . esc_html__('Switch between the field editor and manual ordering for this group.', 'cff') . '</p>';
          echo '</div>';
          echo '<div class="cff-field-view-actions">';
          echo '<select id="cff-field-view-mode-' . intval($g->ID) . '" class="cff-field-view-mode cff-field-view-mode--metabox">';
          echo '<option value="builder">' . esc_html__('Builder', 'cff') . '</option>';
          echo '<option value="reorder">' . esc_html__('Reorder', 'cff') . '</option>';
          echo '</select>';
          if ($this->polylang_active()) {
            echo '<button type="submit" class="button cff-copy-all-action" name="cff_copy_all_to_translations_trigger" value="1">' . esc_html__('Save + Copy All CFF to Translations', 'cff') . '</button>';
          }
          echo '</div>';
          echo '</div>';
          echo '<div class="cff-metabox-reorder" aria-hidden="true">';
          echo '<p class="cff-metabox-reorder-label">' . esc_html__('Drag the fields below to update their order.', 'cff') . '</p>';
          echo '<p class="cff-metabox-reorder-note">' . esc_html__('This only changes the sequence inside this field group for the current post.', 'cff') . '</p>';
          echo '<ul class="cff-metabox-reorder-list"></ul>';
          echo '<input type="hidden" class="cff-metabox-order-input" name="cff_group_field_order[' . intval($g->ID) . ']" value="' . esc_attr(implode(',', $field_order)) . '">';
          echo '</div>';
          echo '<div class="cff-metabox-fields">';
          foreach ($fields as $f) $this->render_field($post, $f);
          echo '</div>';
          echo '</div>';
        },
        $post_type,
        $context,
        $priority
      );

      if (($pres['style'] ?? '') === 'seamless') {
        add_filter('postbox_classes_'.$post_type.'_'.$box_id, function($classes) {
          $classes[] = 'cff-seamless';
          return $classes;
        });
      }
    }
  }

  private function get_ordered_group_fields_for_post($post_id, $group_id, $fields) {
    if (!is_array($fields) || !$fields) return [];

    $meta_key = '_cff_group_field_order_' . intval($group_id);
    $saved = get_post_meta($post_id, $meta_key, true);
    if (is_string($saved)) {
      $saved = array_filter(array_map('sanitize_key', explode(',', $saved)));
    }
    if (!is_array($saved) || !$saved) {
      return $fields;
    }

    $rank = [];
    foreach ($saved as $idx => $name) {
      if (!isset($rank[$name])) {
        $rank[$name] = $idx;
      }
    }

    usort($fields, function($a, $b) use ($rank) {
      $name_a = sanitize_key($a['name'] ?? '');
      $name_b = sanitize_key($b['name'] ?? '');
      $has_a = array_key_exists($name_a, $rank);
      $has_b = array_key_exists($name_b, $rank);

      if ($has_a && $has_b) return $rank[$name_a] <=> $rank[$name_b];
      if ($has_a) return -1;
      if ($has_b) return 1;
      return 0;
    });

    return $fields;
  }

  private function get_groups_for_context($post) {
    $groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'publish',
      'numberposts' => -1,
      'no_found_rows' => true,
    ]);
    $out = [];
    foreach ($groups as $g) {
      $settings = get_post_meta($g->ID, '_cff_settings', true);
      $location = $settings['location'] ?? [];
      if ($this->match_location($post, $location)) $out[] = $g;
    }
    return $out;
  }

  public function get_field_definitions_for_post($post) {
    $definitions = [];
    $groups = $this->get_groups_for_context($post);
    foreach ($groups as $g) {
      $settings = get_post_meta($g->ID, '_cff_settings', true);
      $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];
      foreach ($fields as $field) {
        $name = sanitize_key($field['name'] ?? '');
        if (!$name) continue;
        $definitions[$name] = $field;
      }
    }
    return $definitions;
  }

  private function match_location($post, $groups) {
    if (!is_array($groups) || !$groups) return false;
    foreach ($groups as $rules) {
      if (!is_array($rules) || !$rules) continue;
      $by_key = [];
      foreach ($rules as $r) {
        $param = $r['param'] ?? 'post_type';
        $op = $r['operator'] ?? '==';
        $by_key[$param . '|' . $op][] = $r;
      }

      $all = true;
      foreach ($by_key as $key => $set) {
        list($param, $op) = explode('|', $key, 2);

        if ($op === '==') {
          $any = false;
          foreach ($set as $r) {
            $val = $r['value'] ?? '';
            if ($this->match_rule($post, $param, $val)) {
              $any = true;
              break;
            }
          }
          if (!$any) { $all = false; break; }
        } else {
          $ok_all = true;
          foreach ($set as $r) {
            $val = $r['value'] ?? '';
            if ($this->match_rule($post, $param, $val)) {
              $ok_all = false;
              break;
            }
          }
          if (!$ok_all) { $all = false; break; }
        }
      }

      if ($all) return true;
    }
    return false;
  }

  private function match_rule($post, $param, $val) {
    if ($param === 'options_page') {
      if (empty($post->post_type) || $post->post_type !== 'cff_options') return false;
      $normalized = sanitize_key(str_replace([' ', '-'], '_', (string) $val));
      return in_array($normalized, ['global', 'global_settings'], true);
    }
    if ($param === 'post_type') return $post->post_type === $val;
    if ($param === 'page_template') {
      $tpl = get_page_template_slug($post->ID);
      if (!$tpl) $tpl = 'default';
      return $tpl === $val;
    }
    if ($param === 'post') return intval($post->ID) === intval($val);
    if ($param === 'page') return intval($post->ID) === intval($val);
    return false;
  }

  public function meta_key($name) { return '_cff_' . sanitize_key($name); }
  public function render_field($post, $f) { require_once __DIR__ . '/render.php'; \CFF\render_field_impl($this, $post, $f); }
  public function save_content_fields($post_id, $post) { require_once __DIR__ . '/render.php'; \CFF\save_content_fields_impl($this, $post_id, $post); }

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

  public function ajax_get_templates() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $t = wp_get_theme();
    $templates = $t->get_page_templates();

    $out = [['id'=>'default','text'=>'Default Template','meta'=>'']];

    foreach ($templates as $file => $name) {
      $out[] = [
        'id'   => $file,
        'text' => $name,
        'meta' => $file
      ];
    }

    wp_send_json_success($out);
  }

  public function ajax_get_post_types() {
    check_ajax_referer('cffp', 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(['message' => __('Forbidden', 'cff')], 403);
    }

    $pts = get_post_types(['show_ui' => true], 'objects');

    $out = [];
    foreach ($pts as $pt) {
      if ($pt->name === 'cff_group') continue;
      $out[] = ['id'=>$pt->name, 'text'=>$pt->labels->singular_name, 'meta'=>$pt->name];
    }
    wp_send_json_success($out);
  }

  /* =========================
   * Tools: Export / Import / Migration
   * ========================= */
  public function page_tools() {
    return $this->tools_page()->page_tools();
  }

  public function handle_export_group() {
    return $this->tools_page()->handle_export_group();
  }

  public function handle_export_tools() {
    return $this->tools_page()->handle_export_tools();
  }

  public function handle_export_acf_data() {
    return $this->tools_page()->handle_export_acf_data();
  }

  public function tools_export_field_groups($group_id = 0) { return $this->export_field_groups($group_id); }
  public function tools_sanitize_import_post_types($defs) { return $this->sanitize_import_post_types($defs); }
  public function tools_sanitize_import_taxonomies($defs) { return $this->sanitize_import_taxonomies($defs); }
  public function tools_import_field_groups($groups) { $this->import_field_groups($groups); }
  public function tools_looks_like_acf_json($data) { return $this->looks_like_acf_json($data); }
  public function tools_import_acf_json($groups) { $this->import_acf_json($groups); }
  public function tools_migrate_from_acf() { return $this->migrate_from_acf(); }
  public function tools_build_acf_data_export_sql() { return $this->build_acf_data_export_sql(); }
  public function rest_match_location($post, $groups) { return $this->match_location($post, $groups); }

  private function export_field_groups($group_id = 0) {
    $args = [
      'post_type'=>'cff_group',
      'post_status'=>'any',
      'numberposts'=>-1,
      'no_found_rows'=>true,
    ];
    if (is_array($group_id)) {
      $ids = array_filter(array_map('absint', $group_id));
      if (!$ids) return [];
      $args['post__in'] = $ids;
      $args['orderby'] = 'post__in';
    } elseif ($group_id) {
      $args['p'] = $group_id;
    }
    $posts = get_posts($args);
    $out = [];
    foreach ($posts as $p) {
      $out[] = [
        'post' => [
          'post_title' => $p->post_title,
          'post_name'  => $p->post_name,
          'post_status'=> $p->post_status,
        ],
        'settings' => get_post_meta($p->ID, '_cff_settings', true),
      ];
    }
    return $out;
  }

  private function sanitize_import_post_types($defs) {
    $out = [];
    foreach ((array) $defs as $key => $def) {
      $key = sanitize_key($key);
      if (!$key || !is_array($def)) continue;

      $singular = sanitize_text_field($def['singular'] ?? ucfirst($key));
      $plural = sanitize_text_field($def['plural'] ?? ($singular . 's'));
      $slug = sanitize_title($def['slug'] ?? $key);
      $public = !empty($def['public']);
      $has_archive = !empty($def['has_archive']);
      $show_in_rest = !empty($def['show_in_rest']);
      $supports = (isset($def['supports']) && is_array($def['supports']))
        ? array_values(array_map('sanitize_key', $def['supports']))
        : ['title', 'editor'];
      $taxonomies = (isset($def['taxonomies']) && is_array($def['taxonomies']))
        ? array_values(array_filter(array_map('sanitize_key', $def['taxonomies'])))
        : [];

      $menu_icon_raw = isset($def['menu_icon']) ? trim((string) $def['menu_icon']) : '';
      $menu_icon = '';
      if ($menu_icon_raw !== '') {
        if (strpos($menu_icon_raw, 'dashicons-') === 0) {
          $menu_icon = preg_replace('/[^a-z0-9\-_]/i', '', $menu_icon_raw);
        } else {
          $menu_icon = esc_url_raw($menu_icon_raw);
        }
      }

      $out[$key] = [
        'singular' => $singular,
        'plural' => $plural,
        'slug' => $slug,
        'public' => $public,
        'has_archive' => $has_archive,
        'show_in_rest' => $show_in_rest,
        'supports' => $supports,
        'list_thumbnail' => !empty($def['list_thumbnail']),
        'block_single' => !empty($def['block_single']),
        'taxonomies' => $taxonomies,
        'menu_icon' => $menu_icon,
      ];

      if (isset($def['singular_i18n']) && is_array($def['singular_i18n'])) {
        $out[$key]['singular_i18n'] = array_filter(array_map('sanitize_text_field', $def['singular_i18n']));
      }
      if (isset($def['plural_i18n']) && is_array($def['plural_i18n'])) {
        $out[$key]['plural_i18n'] = array_filter(array_map('sanitize_text_field', $def['plural_i18n']));
      }
      if (isset($def['slug_i18n']) && is_array($def['slug_i18n'])) {
        $out[$key]['slug_i18n'] = array_filter(array_map('sanitize_title', $def['slug_i18n']));
      }
    }
    return $out;
  }

  private function sanitize_choices($choices) {
    $out = [];
    if (!is_array($choices)) return $out;
    foreach ($choices as $c) {
      $label = sanitize_text_field($c['label'] ?? '');
      $value = sanitize_text_field($c['value'] ?? '');
      if ($label === '' && $value === '') continue;
      $out[] = ['label' => $label, 'value' => $value];
    }
    return $out;
  }

  private function sanitize_choice_display($display) {
    $allowed = ['select','checkbox','radio','button_group','true_false'];
    $key = sanitize_key($display ?? '');
    return in_array($key, $allowed, true) ? $key : 'select';
  }

  private function sanitize_choice_default($value, $choices, $display) {
    $display = $this->sanitize_choice_display($display);
    $value = sanitize_text_field($value ?? '');

    if ($display === 'true_false') {
      return ($value === '1') ? '1' : '';
    }

    $allowed = [];
    foreach ((array) $choices as $choice) {
      $choice_value = sanitize_text_field($choice['value'] ?? ($choice['label'] ?? ''));
      if ($choice_value !== '') {
        $allowed[$choice_value] = true;
      }
    }

    return isset($allowed[$value]) ? $value : '';
  }

  private function sanitize_relational_type($type) {
    $allowed = ['post','page','post_and_page','post_type','taxonomy','user'];
    $key = sanitize_key($type ?? '');
    return in_array($key, $allowed, true) ? $key : 'post';
  }

  private function sanitize_relational_display($display) {
    $allowed = ['select','checkbox','radio'];
    $key = sanitize_key($display ?? '');
    return in_array($key, $allowed, true) ? $key : 'select';
  }

  private function sanitize_import_taxonomies($defs) {
    $out = [];
    foreach ((array) $defs as $key => $def) {
      $key = sanitize_key($key);
      if (!$key || !is_array($def)) continue;

      $post_types = (isset($def['post_types']) && is_array($def['post_types']))
        ? array_values(array_filter(array_map('sanitize_key', $def['post_types'])))
        : [];
      if ($key === 'category') {
        $suffix = $post_types ? implode('_', $post_types) : 'post';
        $key = sanitize_key('category_' . $suffix);
      }

      $plural = sanitize_text_field($def['plural'] ?? '');
      $singular = sanitize_text_field($def['singular'] ?? '');
      $slug = sanitize_title($def['slug'] ?? $key);
      $public = !empty($def['public']);
      $hierarchical = !empty($def['hierarchical']);
      $show_in_rest = !empty($def['show_in_rest']);

      $out[$key] = [
        'plural' => $plural,
        'singular' => $singular,
        'slug' => $slug,
        'public' => $public,
        'hierarchical' => $hierarchical,
        'show_in_rest' => $show_in_rest,
        'post_types' => $post_types,
      ];

      if (isset($def['plural_i18n']) && is_array($def['plural_i18n'])) {
        $out[$key]['plural_i18n'] = array_filter(array_map('sanitize_text_field', $def['plural_i18n']));
      }
      if (isset($def['singular_i18n']) && is_array($def['singular_i18n'])) {
        $out[$key]['singular_i18n'] = array_filter(array_map('sanitize_text_field', $def['singular_i18n']));
      }
      if (isset($def['slug_i18n']) && is_array($def['slug_i18n'])) {
        $out[$key]['slug_i18n'] = array_filter(array_map('sanitize_title', $def['slug_i18n']));
      }
    }
    return $out;
  }

  private function import_field_groups($groups) {
    foreach ($groups as $g) {
      $title = sanitize_text_field($g['post']['post_title'] ?? 'Imported Group');
      $existing_id = $this->find_group_by_title($title);

      $post_id = $existing_id ? $existing_id : wp_insert_post([
        'post_type'=>'cff_group',
        'post_status'=>'publish',
        'post_title'=>$title,
      ]);

      if ($post_id && !is_wp_error($post_id)) {
        $settings = isset($g['settings']) && is_array($g['settings']) ? $g['settings'] : [];
        update_post_meta($post_id, '_cff_settings', $this->sanitize_group_settings($settings));
      }
    }
  }

  private function looks_like_acf_json($data) {
    if (!is_array($data) || empty($data)) return false;
    $first = reset($data);
    return is_array($first) && isset($first['key'], $first['title'], $first['fields']) && is_array($first['fields']);
  }

  private function import_acf_json($groups) {
    foreach ($groups as $acf_group) {
      if (!is_array($acf_group)) continue;

      $title = sanitize_text_field($acf_group['title'] ?? 'ACF Group');
      $existing_id = $this->find_group_by_title($title);

      $post_id = $existing_id ? $existing_id : wp_insert_post([
        'post_type'=>'cff_group',
        'post_status'=>'publish',
        'post_title'=>$title,
      ]);

      if (!$post_id || is_wp_error($post_id)) continue;

      $loc = $this->convert_acf_location($acf_group['location'] ?? []);
      $mapped_fields = $this->map_acf_fields($acf_group['fields'] ?? []);

      $settings = [
        'fields' => $mapped_fields,
        'location' => $loc ?: [[['param'=>'post_type','operator'=>'==','value'=>'post']]],
        'presentation' => [
          'style'=>'standard',
          'position'=>'normal',
          'label_placement'=>'top',
          'instruction_placement'=>'below_labels',
          'order'=>intval($acf_group['menu_order'] ?? 0),
          'hide_on_screen'=>$this->map_acf_hide_on_screen($acf_group['hide_on_screen'] ?? []),
        ],
      ];

      update_post_meta($post_id, '_cff_settings', $settings);
    }
  }

  private function find_group_by_title($title) {
    $q = new \WP_Query([
      'post_type' => 'cff_group',
      'post_status' => 'any',
      'title' => $title,
      'posts_per_page' => 1,
      'no_found_rows' => true,
      'fields' => 'ids',
    ]);
    return !empty($q->posts) ? (int) $q->posts[0] : 0;
  }

  private function convert_acf_location($loc) {
    $out = [];
    if (!is_array($loc)) return $out;
    foreach ($loc as $or_group) {
      $and_rules = [];
      foreach ((array)$or_group as $rule) {
        $param = $rule['param'] ?? '';
        $op = ($rule['operator'] ?? '==') === '!=' ? '!=' : '==';
        $value = $rule['value'] ?? '';
        if ($param === 'post_type') {
          $and_rules[] = ['param'=>'post_type','operator'=>$op,'value'=>sanitize_key($value)];
        } elseif ($param === 'page_template') {
          $and_rules[] = ['param'=>'page_template','operator'=>$op,'value'=>sanitize_text_field($value)];
        } elseif ($param === 'post') {
          $and_rules[] = ['param'=>'post','operator'=>$op,'value'=>intval($value)];
        } elseif ($param === 'page') {
          $and_rules[] = ['param'=>'page','operator'=>$op,'value'=>intval($value)];
        }
      }
      if ($and_rules) $out[] = $and_rules;
    }
    return $out;
  }

  private function map_acf_hide_on_screen($hide) {
    $allowed = [
      'permalink' => 'permalink',
      'the_content' => 'editor',
      'editor' => 'editor',
      'excerpt' => 'excerpt',
      'discussion' => 'discussion',
      'comments' => 'comments',
      'revisions' => 'revisions',
      'slug' => 'slug',
      'author' => 'author',
      'format' => 'format',
      'page_attributes' => 'page_attributes',
      'featured_image' => 'featured_image',
      'categories' => 'categories',
      'tags' => 'tags',
      'trackbacks' => 'trackbacks',
    ];

    $out = [];
    if (is_array($hide)) {
      foreach ($hide as $k) {
        if (isset($allowed[$k])) $out[$allowed[$k]] = true;
      }
    }
    return $out;
  }

  private function migrate_from_acf() {
    if (!function_exists('acf_get_field_groups')) return 0;

    $groups = acf_get_field_groups();
    $count = 0;

    foreach ($groups as $acf_group) {
      $title = $acf_group['title'] ?? 'ACF Group';

      $post_id = wp_insert_post([
        'post_type'=>'cff_group',
        'post_status'=>'publish',
        'post_title'=>$title
      ]);

      if (!$post_id || is_wp_error($post_id)) continue;

      // Location map (ACF -> CFF location)
      $loc = [];
      if (!empty($acf_group['location']) && is_array($acf_group['location'])) {
        foreach ($acf_group['location'] as $or_group) {
          $and_rules = [];
          foreach ($or_group as $rule) {
            if (($rule['param'] ?? '') === 'post_type') {
              $and_rules[] = ['param'=>'post_type','operator'=>'==','value'=>sanitize_key($rule['value'] ?? 'post')];
            }
            if (($rule['param'] ?? '') === 'page_template') {
              $and_rules[] = ['param'=>'page_template','operator'=>'==','value'=>sanitize_text_field($rule['value'] ?? '')];
            }
          }
          if ($and_rules) $loc[] = $and_rules;
        }
      }

      // Fields
      $fields = function_exists('acf_get_fields') ? acf_get_fields($acf_group) : [];
      $mapped_fields = $this->map_acf_fields($fields);

      $settings = [
        'fields' => $mapped_fields,
        'location' => $loc ?: [[['param'=>'post_type','operator'=>'==','value'=>'post']]],
        'presentation' => [
          'style'=>'standard',
          'position'=>'normal',
          'label_placement'=>'top',
          'instruction_placement'=>'below_labels',
          'order'=>0,
          'hide_on_screen'=>[],
        ],
      ];

      update_post_meta($post_id, '_cff_settings', $settings);
      $this->migrate_acf_content($acf_group, $mapped_fields);
      $count++;
    }

    return $count;
  }

  private function build_acf_data_export_sql() {
    if (!function_exists('acf_get_field_groups')) return '';
    global $wpdb;
    $groups = acf_get_field_groups();
    if (empty($groups)) return '';

    $table = str_replace('`', '', $wpdb->postmeta);
    $lines = [];
    $written = [];

    foreach ($groups as $acf_group) {
      $fields = function_exists('acf_get_fields') ? acf_get_fields($acf_group) : [];
      $mapped_fields = $this->map_acf_fields($fields);
      if (empty($mapped_fields)) continue;

      $posts = $this->get_posts_for_acf_group($acf_group);
      foreach ($posts as $post) {
        $post_id = intval($post->ID);
        if (!$post_id) continue;

        foreach ($mapped_fields as $field) {
          $name = $field['name'] ?? '';
          if (!$name) continue;

          $meta_key = $this->meta_key($name);
          if ($this->is_non_empty(get_post_meta($post_id, $meta_key, true))) continue;
          if (isset($written[$post_id][$meta_key])) continue;

          $raw = get_field($name, $post_id);
          if ($raw === null || $raw === false || $raw === '') continue;

          $value = $this->acf_value_to_cff($field, $raw);
          if (!$this->is_non_empty($value)) continue;

          $serialized = maybe_serialize($value);
          $value_sql = esc_sql($serialized);
          $meta_key_sql = esc_sql($meta_key);

          $lines[] = "DELETE FROM `{$table}` WHERE `post_id` = {$post_id} AND `meta_key` = '{$meta_key_sql}';";
          $lines[] = "INSERT INTO `{$table}` (`post_id`, `meta_key`, `meta_value`) VALUES ({$post_id}, '{$meta_key_sql}', '{$value_sql}');";

          $written[$post_id][$meta_key] = true;
        }
      }
    }

    if (empty($lines)) return '';

    $header = [
      '-- Custom Fields Framework Pro - ACF to CFF export',
      '-- Generated at ' . gmdate('c'),
      '-- Run these statements after importing the matching CFF field groups.',
      "-- Table used: `{$table}`",
    ];

    return implode("\n", array_merge($header, $lines)) . "\n";
  }

  private function map_acf_fields($acf_fields) {
    $out = [];
    if (!is_array($acf_fields)) return $out;

    foreach ($acf_fields as $f) {
      $type = $f['type'] ?? 'text';
      $name = sanitize_key($f['name'] ?? '');
      $label= sanitize_text_field($f['label'] ?? $name);

      $mapped_type = $type;
      if ($type === 'true_false') $mapped_type = 'checkbox';
      if ($type === 'color_picker') $mapped_type = 'color';
      if ($type === 'flexible_content') $mapped_type = 'flexible';

      $base = [
        'label'=>$label ?: $name,
        'name'=>$name ?: sanitize_key($label),
        'type'=>$mapped_type,
      ];

      if ($type === 'repeater') {
        $base['type'] = 'repeater';
        $base['sub_fields'] = $this->map_acf_fields($f['sub_fields'] ?? []);
      }

      if ($type === 'flexible_content') {
        $layouts = [];
        foreach (($f['layouts'] ?? []) as $lay) {
          $layouts[] = [
            'name' => sanitize_key($lay['name'] ?? ''),
            'label'=> sanitize_text_field($lay['label'] ?? ''),
            'sub_fields' => $this->map_acf_fields($lay['sub_fields'] ?? []),
          ];
        }
        $base['layouts'] = $layouts;
      }

      if ($type === 'group') {
        $base['sub_fields'] = $this->map_acf_fields($f['sub_fields'] ?? []);
      }

      $out[] = $base;
    }

    return $out;
  }

  private function sanitize_group_settings($settings) {
    $settings = is_array($settings) ? $settings : [];
    $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];
    $location = isset($settings['location']) && is_array($settings['location']) ? $settings['location'] : [];
    $presentation = isset($settings['presentation']) && is_array($settings['presentation']) ? $settings['presentation'] : [];

    $clean_fields = $this->sanitize_fields($fields);

    $loc_clean = [];
    foreach ($location as $group) {
      if (!is_array($group)) continue;
      $g = [];
      foreach ($group as $rule) {
        if (!is_array($rule)) continue;
        $param = sanitize_key($rule['param'] ?? 'post_type');
        $op = in_array(($rule['operator'] ?? '=='), ['==', '!='], true) ? $rule['operator'] : '==';
        $val = sanitize_text_field($rule['value'] ?? '');
        if (!$val) continue;
        $g[] = ['param' => $param, 'operator' => $op, 'value' => $val];
      }
      if ($g) $loc_clean[] = $g;
    }
    if (!$loc_clean) {
      $loc_clean = [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]];
    }

    return [
      'fields' => $clean_fields,
      'location' => $loc_clean,
      'presentation' => $this->sanitize_presentation($presentation),
    ];
  }

  private function sanitize_fields($fields) {
    $out = [];
    $seen_keys = [];
    foreach ((array) $fields as $f) {
      if (!is_array($f)) continue;
      $type = sanitize_key($f['type'] ?? 'text');
      $name = sanitize_key($f['name'] ?? '');
      if (!$name) continue;
      $field_key = $this->sanitize_field_key($f['key'] ?? '');
      while (isset($seen_keys[$field_key])) {
        $field_key = $this->sanitize_field_key('');
      }
      $seen_keys[$field_key] = true;

      $item = [
        'label' => sanitize_text_field($f['label'] ?? $name),
        'name' => $name,
        'type' => $type,
        'key' => $field_key,
      ];
      $aliases = $this->sanitize_field_aliases($f['aliases'] ?? [], $name);
      if ($aliases) {
        $item['aliases'] = $aliases;
      }
      $conditional_logic = $this->sanitize_conditional_logic($f['conditional_logic'] ?? []);
      if ($conditional_logic) {
        $item['conditional_logic'] = $conditional_logic;
      }

      if ($type === 'repeater' || $type === 'group') {
        $item['sub_fields'] = $this->sanitize_subfields($f['sub_fields'] ?? []);
        if ($type === 'repeater') {
          $min = $this->sanitize_repeater_min($f['min'] ?? 0);
          $item['repeater_layout'] = $this->sanitize_repeater_layout($f['repeater_layout'] ?? 'default');
          $item['min'] = $min;
          $item['max'] = $this->sanitize_repeater_max($f['max'] ?? 0, $min);
          $item['repeater_row_label'] = $this->sanitize_repeater_row_label($f['repeater_row_label'] ?? '', $item['sub_fields']);
          $item['repeater_collapsed'] = !empty($f['repeater_collapsed']);
        }
      }
      if ($type === 'flexible') {
        $item['layouts'] = $this->sanitize_layouts($f['layouts'] ?? []);
      }
      if ($type === 'datetime_picker') {
        $item['datetime_use_time'] = !array_key_exists('datetime_use_time', $f) ? true : !empty($f['datetime_use_time']);
      }

      $out[] = $item;
    }
    return $out;
  }

  /**
   * Migrasi data meta dari ACF ke CFF untuk field group tertentu.
   * Hanya menulis jika meta CFF belum ada, supaya tidak menimpa data yang sudah disimpan.
   */
  private function migrate_acf_content($acf_group, $mapped_fields) {
    if (!function_exists('get_field')) return;
    if (empty($mapped_fields)) return;

    $posts = $this->get_posts_for_acf_group($acf_group);
    foreach ($posts as $post) {
      foreach ($mapped_fields as $field) {
        $name = $field['name'] ?? '';
        if (!$name) continue;

        $meta_key = $this->meta_key($name);
        $existing = get_post_meta($post->ID, $meta_key, true);
        if ($this->is_non_empty($existing)) continue; // jangan timpa data yang sudah ada

        $raw = get_field($name, $post->ID);
        if ($raw === null || $raw === false || $raw === '') continue;

        $value = $this->acf_value_to_cff($field, $raw);
        if ($this->is_non_empty($value)) {
          update_post_meta($post->ID, $meta_key, $value);
        }
      }
    }
  }

  /**
   * Ambil daftar post yang masuk dalam location rules ACF, lalu filter lagi memakai match_location().
   */
  private function get_posts_for_acf_group($acf_group) {
    $loc = $acf_group['location'] ?? [];

    // konversi rules ACF ke format CFF untuk match_location
    $converted_rules = [];
    $broad_post_types = [];
    $specific_post_ids = [];
    $needs_post_meta = false;
    foreach ((array)$loc as $or_group) {
      $and_rules = [];
      $group_post_types = [];
      $group_specific_ids = [];
      foreach ((array)$or_group as $rule) {
        $param = $rule['param'] ?? '';
        $value = $rule['value'] ?? '';
        if ($param === 'post_type') {
          $post_type = sanitize_key($value);
          if ($post_type !== '') {
            $and_rules[] = ['param'=>'post_type','operator'=>'==','value'=>$post_type];
            $group_post_types[] = $post_type;
          }
        } elseif ($param === 'page_template') {
          $needs_post_meta = true;
          $and_rules[] = ['param'=>'page_template','operator'=>'==','value'=>sanitize_text_field($value)];
          $group_post_types[] = 'page';
        } elseif ($param === 'post') {
          $post_id = intval($value);
          if ($post_id > 0) {
            $and_rules[] = ['param'=>'post','operator'=>'==','value'=>$post_id];
            $group_specific_ids[] = $post_id;
          }
        } elseif ($param === 'page') {
          $page_id = intval($value);
          if ($page_id > 0) {
            $and_rules[] = ['param'=>'page','operator'=>'==','value'=>$page_id];
            $group_specific_ids[] = $page_id;
            $group_post_types[] = 'page';
          }
        }
      }
      if (!$and_rules) continue;

      $converted_rules[] = $and_rules;

      if ($group_specific_ids) {
        $specific_post_ids = array_merge($specific_post_ids, $group_specific_ids);
        continue;
      }

      $broad_post_types = array_merge($broad_post_types, $group_post_types);
    }

    $specific_post_ids = array_values(array_unique(array_filter(array_map('absint', $specific_post_ids))));
    $broad_post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $broad_post_types))));
    if (!$broad_post_types && !$specific_post_ids) {
      $broad_post_types = ['post', 'page'];
    }

    $posts = [];
    $seen_posts = [];

    if ($specific_post_ids) {
      $specific_posts = get_posts([
        'post_type' => 'any',
        'post_status' => 'any',
        'post__in' => $specific_post_ids,
        'numberposts' => count($specific_post_ids),
        'orderby' => 'post__in',
        'no_found_rows' => true,
        'update_post_meta_cache' => $needs_post_meta,
        'update_post_term_cache' => false,
      ]);

      foreach ($specific_posts as $post) {
        $post_id = (int) $post->ID;
        if (!$post_id || isset($seen_posts[$post_id])) continue;
        $seen_posts[$post_id] = true;
        $posts[] = $post;
      }
    }

    if ($broad_post_types) {
      $queried_posts = get_posts([
        'post_type' => $broad_post_types,
        'post_status' => 'any',
        'numberposts' => -1,
        'no_found_rows' => true,
        'update_post_meta_cache' => $needs_post_meta,
        'update_post_term_cache' => false,
      ]);

      foreach ($queried_posts as $post) {
        $post_id = (int) $post->ID;
        if (!$post_id || isset($seen_posts[$post_id])) continue;
        $seen_posts[$post_id] = true;
        $posts[] = $post;
      }
    }

    $out = [];
    $has_rules = !empty($converted_rules);
    foreach ($posts as $p) {
      if (!$has_rules || $this->match_location($p, $converted_rules)) $out[] = $p;
    }
    return $out;
  }

  private function acf_value_to_cff($field, $value) {
    $type = $field['type'] ?? 'text';

    if ($type === 'repeater') {
      $out = [];
      $subs = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
      if (is_array($value)) {
        foreach ($value as $row) {
          if (!is_array($row)) continue;
          $row_out = [];
          foreach ($subs as $sf) {
            $sname = $sf['name'] ?? '';
            if (!$sname) continue;
            $row_out[$sname] = $this->acf_value_to_cff($sf, $row[$sname] ?? '');
          }
          if ($this->is_non_empty($row_out)) $out[] = $row_out;
        }
      }
      return $out;
    }

    if ($type === 'group') {
      $out = [];
      $subs = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
      if (is_array($value)) {
        foreach ($subs as $sf) {
          $sname = $sf['name'] ?? '';
          if (!$sname) continue;
          $out[$sname] = $this->acf_value_to_cff($sf, $value[$sname] ?? '');
        }
      }
      return $out;
    }

    if ($type === 'flexible') {
      $out = [];
      $layouts = isset($field['layouts']) && is_array($field['layouts']) ? $field['layouts'] : [];
      $layout_map = [];
      foreach ($layouts as $l) {
        if (!empty($l['name'])) $layout_map[$l['name']] = $l;
      }

      if (is_array($value)) {
        foreach ($value as $row) {
          if (!is_array($row) || empty($row['acf_fc_layout'])) continue;
          $layout = sanitize_key($row['acf_fc_layout']);
          if (!isset($layout_map[$layout])) continue;
          $l = $layout_map[$layout];
          $fields = [];
          foreach (($l['sub_fields'] ?? []) as $sf) {
            $sname = $sf['name'] ?? '';
            if (!$sname) continue;
            $fields[$sname] = $this->acf_value_to_cff($sf, $row[$sname] ?? '');
          }
          $out[] = [
            'layout' => $layout,
            'fields' => $fields,
          ];
        }
      }
      return $out;
    }

    if ($type === 'image' || $type === 'file') {
      if (is_array($value) && isset($value['ID'])) return intval($value['ID']);
      if (is_numeric($value)) return intval($value);
      return 0;
    }
    if ($type === 'gallery') {
      $out = [];
      if (is_array($value)) {
        foreach ($value as $item) {
          if (is_array($item) && isset($item['ID'])) {
            $id = intval($item['ID']);
          } else {
            $id = is_numeric($item) ? intval($item) : 0;
          }
          if ($id) $out[] = $id;
        }
      }
      return array_values(array_unique($out));
    }

    if ($type === 'link') {
      if (is_array($value)) {
        return [
          'url' => isset($value['url']) ? (string) $value['url'] : '',
          'title' => isset($value['title']) ? (string) $value['title'] : '',
          'target' => isset($value['target']) ? (string) $value['target'] : '',
        ];
      }
      if (is_string($value) && $value !== '') {
        return ['url' => $value, 'title' => '', 'target' => ''];
      }
      return [];
    }

    if ($type === 'url' || $type === 'color') {
      if (is_array($value)) return '';
      return wp_kses_post((string) $value);
    }

    // default text/scalar
    if (is_array($value)) return '';
    return wp_kses_post((string) $value);
  }

  private function is_non_empty($v) {
    if (is_array($v)) return !empty($v);
    return $v !== null && $v !== false && $v !== '';
  }

  public function filter_duplicate_row_action($actions, $post) {
    if (!current_user_can('edit_post', $post->ID)) return $actions;
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
    $redirect_to = '';
    if ($request_uri) {
      $redirect_to = wp_validate_redirect(home_url($request_uri), '');
    }
    if (!$redirect_to) {
      $redirect_to = admin_url($post->post_type === 'post' ? 'edit.php' : 'edit.php?post_type=' . $post->post_type);
    }
    $url = wp_nonce_url(
      add_query_arg([
        'action' => 'cff_duplicate_post',
        'post' => $post->ID,
        'redirect_to' => $redirect_to,
      ], admin_url('admin-post.php')),
      'cff-duplicate-post_' . $post->ID
    );
    $actions['cff_duplicate'] = '<a href="'.esc_url($url).'">' . esc_html__('Duplicate', 'cff') . '</a>';
    return $actions;
  }

  public function disable_quick_edit_for_groups($enabled, $post_type) {
    if ($post_type === 'cff_group') {
      return false;
    }
    return $enabled;
  }

  public function filter_group_bulk_actions($actions) {
    if (isset($actions['edit'])) {
      unset($actions['edit']);
    }
    return $actions;
  }

  public function ensure_quick_edit_row_action($actions, $post) {
    if (!$post || !($post instanceof \WP_Post)) {
      return $actions;
    }

    if ($post->post_type === 'cff_group') {
      return $actions;
    }

    if (isset($actions['inline hide-if-no-js'])) {
      return $actions;
    }

    if (!current_user_can('edit_post', $post->ID)) {
      return $actions;
    }

    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || empty($post_type_object->show_ui)) {
      return $actions;
    }

    $quick_edit_enabled = apply_filters('quick_edit_enabled_for_post_type', true, $post->post_type);
    if (!$quick_edit_enabled) {
      return $actions;
    }

    $title = _draft_or_post_title($post->ID);
    $quick_edit = sprintf(
      '<button type="button" class="button-link editinline" aria-label="%1$s" aria-expanded="false">%2$s</button>',
      esc_attr(sprintf(__('Quick edit &#8220;%s&#8221; inline'), $title)),
      __('Quick&nbsp;Edit')
    );

    $updated = [];
    $inserted = false;
    foreach ((array) $actions as $key => $markup) {
      $updated[$key] = $markup;
      if (!$inserted && in_array((string) $key, ['edit', 'trash'], true)) {
        $updated['inline hide-if-no-js'] = $quick_edit;
        $inserted = true;
      }
    }
    if (!$inserted) {
      $updated['inline hide-if-no-js'] = $quick_edit;
    }

    return $updated;
  }

  public function admin_post_duplicate_post() {
    $post_id = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
    if (!$post_id) wp_die(__('Invalid post ID.', 'cff'));
    check_admin_referer('cff-duplicate-post_' . $post_id);
    if (!current_user_can('edit_post', $post_id)) wp_die(__('You do not have permission to duplicate this post.', 'cff'));
    $post = get_post($post_id);
    if (!$post) wp_die(__('Post not found.', 'cff'));

    $new_post = [
      'post_content' => $post->post_content,
      'post_excerpt' => $post->post_excerpt,
      'post_status' => 'draft',
      'post_title' => $post->post_title ? $post->post_title . ' (Copy)' : __('Draft Copy', 'cff'),
      'post_type' => $post->post_type,
      'post_author' => get_current_user_id(),
      'post_name' => '',
      'post_parent' => $post->post_parent,
      'comment_status' => $post->comment_status,
      'ping_status' => $post->ping_status,
      'menu_order' => $post->menu_order,
      'post_password' => '',
    ];
    $new_post_id = wp_insert_post($new_post);
    if (!$new_post_id) wp_die(__('Unable to duplicate post.', 'cff'));

    $meta = get_post_meta($post_id);
    foreach ($meta as $key => $values) {
      if ($key === '_edit_lock') continue;
      foreach ((array)$values as $value) {
        add_post_meta($new_post_id, $key, maybe_unserialize($value));
      }
    }

    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
      $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
      if (!is_wp_error($terms)) {
        wp_set_object_terms($new_post_id, $terms, $taxonomy);
      }
    }

    $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash((string) $_REQUEST['redirect_to']) : '';
    $redirect = wp_validate_redirect($redirect, '');
    if (!$redirect) {
      $redirect = wp_get_referer() ?: admin_url($post->post_type === 'post' ? 'edit.php' : 'edit.php?post_type=' . $post->post_type);
    }
    $redirect = add_query_arg(['duplicated' => $new_post_id], $redirect);
    wp_safe_redirect($redirect);
    exit;
  }

  public function filter_slug_based_single_template($template) {
    if (!is_singular()) return $template;
    global $post;
    if (!$post) return $template;
    $definition = $this->get_post_type_definition($post->post_type);
    if (!$definition) return $template;
    if (!empty($definition['block_single'])) return $template;
    $slug = $this->get_post_type_slug_from_definition($definition);
    if (!$slug || $slug === $post->post_type) return $template;
    $file = 'single-' . sanitize_file_name($slug) . '.php';
    $located = locate_template($file);
    if ($located) return $located;
    return $template;
  }

  public function filter_slug_based_archive_template($template) {
    if (!is_post_type_archive()) return $template;
    $post_type = get_query_var('post_type');
    if (!$post_type) return $template;
    $definition = $this->get_post_type_definition($post_type);
    if (!$definition) return $template;
    $slug = $this->get_post_type_slug_from_definition($definition);
    if (!$slug || $slug === $post_type) return $template;
    $file = 'archive-' . sanitize_file_name($slug) . '.php';
    $located = locate_template($file);
    if ($located) return $located;
    return $template;
  }

  public function filter_nav_menu_css_class($classes, $item, $args = null, $depth = 0) {
    $classes = is_array($classes) ? $classes : [];

    if ($this->menu_item_has_current_class($classes) || $this->menu_item_matches_current_slug($item)) {
      $classes[] = 'current';
      $classes[] = 'current-menu-item';
      $classes[] = 'current_page_item';
    }

    return array_values(array_unique(array_filter($classes)));
  }

  public function add_copy_notice_flag_to_redirect($location, $post_id) {
    if (empty($_POST['cff_copy_all_to_translations_trigger']) && empty($_POST['cff_copy_field_to_translations'])) {
      return $location;
    }

    $status = !empty($_POST['cff_copy_field_to_translations']) ? 'copied_field' : 'copied_all';
    if (is_user_logged_in()) {
      $transient_key = 'cff_copy_to_translations_result_' . get_current_user_id();
      $stored_status = get_transient($transient_key);
      if (is_string($stored_status) && $stored_status !== '') {
        $status = sanitize_key($stored_status);
      }
      delete_transient($transient_key);
    }

    return add_query_arg('cff_copied_translations', $status, $location);
  }

  private function get_missing_translation_create_links($post_id) {
    $post_id = absint($post_id);
    if (!$post_id || !function_exists('pll_languages_list') || !function_exists('pll_get_post_translations')) {
      return [];
    }

    $post = get_post($post_id);
    if (!$post || empty($post->post_type) || !post_type_exists($post->post_type) || !current_user_can('edit_post', $post_id)) {
      return [];
    }

    $languages = pll_languages_list(['fields' => 'slug']);
    $translations = pll_get_post_translations($post_id);
    if (!is_array($languages) || empty($languages) || !is_array($translations)) {
      return [];
    }

    $missing = array_values(array_diff($languages, array_keys($translations)));
    if (empty($missing)) {
      return [];
    }

    $links = [];
    foreach ($missing as $lang) {
      $lang = sanitize_key((string) $lang);
      if ($lang === '') {
        continue;
      }
      $links[] = [
        'lang' => $lang,
        'url' => add_query_arg([
          'post_type' => $post->post_type,
          'lang' => $lang,
          'from_post' => $post_id,
        ], admin_url('post-new.php')),
      ];
    }

    return $links;
  }

  public function maybe_render_copy_to_translations_notice() {
    if (empty($_GET['cff_copied_translations'])) {
      return;
    }
    if (!current_user_can('edit_posts')) {
      return;
    }

    $status = sanitize_key((string) $_GET['cff_copied_translations']);
    if ($status === 'missing_translation_page') {
      $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
      $links = $this->get_missing_translation_create_links($post_id);
      $message = esc_html__('Content was saved, but no translation posts/pages exist yet. Create the translation first, then use Save + Copy CFF to Translations again.', 'cff');
      if (!empty($links)) {
        $items = [];
        foreach ($links as $link) {
          $items[] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url($link['url']),
            esc_html(sprintf(__('Create %s translation', 'cff'), strtoupper($link['lang'])))
          );
        }
        $message .= ' ' . implode(' | ', $items);
      }
      echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
      return;
    }

    if ($status === 'missing_polylang') {
      echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Content was saved, but Polylang is not available, so translations could not be updated.', 'cff') . '</p></div>';
      return;
    }

    if ($status === 'copied_field') {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('The selected CFF field was copied to available translations.', 'cff') . '</p></div>';
      return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All CFF values were copied to available translations.', 'cff') . '</p></div>';
  }

  private function get_post_type_definition($post_type) {
    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs) || empty($defs[$post_type])) return [];
    return (array) $defs[$post_type];
  }

  private function get_taxonomy_definition($taxonomy) {
    $defs = get_option('cffp_taxonomies', []);
    if (!is_array($defs) || empty($defs[$taxonomy])) return [];
    return (array) $defs[$taxonomy];
  }

  private function get_post_type_slug_from_definition($definition) {
    $slug = trim($definition['slug'] ?? '');
    if ($slug === '') {
      $slug = trim($definition['plural'] ?? '');
    }
    return sanitize_title($slug ?: '');
  }

  private function get_post_type_menu_slug($post_type) {
    $post_type = sanitize_key($post_type);
    if ($post_type === '') return '';

    $definition = $this->get_post_type_definition($post_type);
    $slug = $this->get_post_type_slug_from_definition($definition);

    if ($slug === '') {
      $obj = get_post_type_object($post_type);
      if ($obj && !empty($obj->rewrite['slug'])) {
        $slug = (string) $obj->rewrite['slug'];
      }
    }

    return $this->translate_registered_slug($slug, $definition, 'slug_i18n');
  }

  private function get_taxonomy_menu_slug($taxonomy) {
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '') return '';

    $definition = $this->get_taxonomy_definition($taxonomy);
    $tax_obj = get_taxonomy($taxonomy);

    $slug = trim((string) ($definition['slug'] ?? ''));
    if ($slug === '' && $tax_obj && !empty($tax_obj->rewrite['slug'])) {
      $slug = (string) $tax_obj->rewrite['slug'];
    }
    if ($slug === '') {
      $slug = $taxonomy;
    }

    return $this->translate_registered_slug($slug, $definition, 'slug_i18n');
  }

  private function translate_registered_slug($slug, $definition, $key) {
    $slug = sanitize_title($slug);
    if ($slug === '') return '';

    $lang = $this->current_lang();
    if ($lang !== '') {
      $slug = $this->i18n_slug_for_lang($definition, $key, $lang, $slug);
    }

    return sanitize_title($slug);
  }

  private function menu_item_has_current_class($classes) {
    foreach ((array) $classes as $class) {
      $class = (string) $class;
      if ($class === 'current' || strpos($class, 'current-') === 0 || strpos($class, 'current_') === 0) {
        return true;
      }
    }

    return false;
  }

  private function menu_item_matches_current_slug($item) {
    if (!is_object($item)) return false;

    $item_slug = $this->get_menu_item_slug($item);
    if ($item_slug === '') return false;

    return in_array($item_slug, $this->get_current_menu_match_slugs(), true);
  }

  private function get_menu_item_slug($item) {
    if (!is_object($item)) return '';

    if (($item->object ?? '') === 'page') {
      $page_id = absint($item->object_id ?? 0);
      if ($page_id) {
        $page = get_post($page_id);
        if ($page && $page->post_type === 'page') {
          return sanitize_title($page->post_name);
        }
      }
    }

    $url = isset($item->url) ? (string) $item->url : '';
    if ($url === '') return '';

    $path = wp_parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') return '';

    return sanitize_title(basename(trim($path, '/')));
  }

  private function get_current_menu_match_slugs() {
    $slugs = [];

    if (is_page()) {
      $page = get_queried_object();
      if ($page instanceof \WP_Post && $page->post_type === 'page') {
        $slugs[] = sanitize_title($page->post_name);
      }
    }

    if (is_post_type_archive()) {
      $post_type = get_query_var('post_type');
      if (is_array($post_type)) {
        $post_type = reset($post_type);
      }
      if (is_string($post_type) && $post_type !== '') {
        $slugs[] = $this->get_post_type_menu_slug($post_type);
      }
    }

    if (is_singular() && !is_page()) {
      $post = get_queried_object();
      if ($post instanceof \WP_Post && !empty($post->post_type)) {
        $slugs[] = $this->get_post_type_menu_slug($post->post_type);
      }
    }

    if (is_tax() || is_category() || is_tag()) {
      $term = get_queried_object();
      if ($term instanceof \WP_Term && !empty($term->taxonomy)) {
        $slugs[] = $this->get_taxonomy_menu_slug($term->taxonomy);
      }
    }

    return array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
  }
}
