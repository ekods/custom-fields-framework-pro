<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Plugin {
  private static $instance = null;

  public static function instance() {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
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
        'rewrite' => ['slug'=>$slug],
        'show_in_rest' => $show_in_rest,
        'show_ui' => array_key_exists('show_ui',$def) ? (bool)$def['show_ui'] : $public,
        'query_var' => array_key_exists('query_var',$def) ? (bool)$def['query_var'] : true,
        'show_admin_column' => array_key_exists('show_admin_column',$def) ? (bool)$def['show_admin_column'] : true,
        'supports' => $supports,
        'taxonomies' => (isset($def['taxonomies']) && is_array($def['taxonomies']))
          ? array_map('sanitize_key',$def['taxonomies'])
          : [],
        'menu_position' => 25,
        'menu_icon' => $menu_icon ?: 'dashicons-admin-post',
      ]);
    }
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
    add_action('init', [$this, 'register_dynamic_cpts'], 20);
    add_action('init', [$this, 'register_dynamic_taxonomies'], 25);
    add_action('init', [$this, 'register_term_meta_fields'], 30);
    add_action('init', [$this, 'add_polylang_rewrite_rules'], 100);

    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('add_meta_boxes', [$this, 'meta_boxes']);
    add_action('save_post_cff_group', [$this, 'save_group'], 10, 2);

    add_action('add_meta_boxes', [$this, 'content_meta_boxes'], 20, 2);
    add_action('save_post', [$this, 'save_content_fields'], 10, 2);

    add_action('admin_enqueue_scripts', [$this, 'assets']);
    add_action('admin_init', [$this, 'handle_export_tools']);
    add_action('admin_init', [$this, 'handle_export_group']);
    add_action('admin_init', [$this, 'handle_export_acf_data']);
    add_action('pre_get_posts', [$this, 'apply_reorder_post_types']);
    add_filter('get_terms_args', [$this, 'apply_reorder_terms_args'], 10, 2);
    add_filter('category_rewrite_rules', [$this, 'category_rewrite_rules']);
    add_filter('category_link', [$this, 'category_link_no_base'], 10, 2);
    add_filter('pll_translate_post_type_rewrite_slug', [$this, 'pll_translate_post_type_rewrite_slug'], 10, 3);
    add_filter('pll_translate_taxonomy_rewrite_slug', [$this, 'pll_translate_taxonomy_rewrite_slug'], 10, 3);
    add_filter('pll_the_language_link', [$this, 'pll_fix_archive_lang_link'], 10, 3);
    add_filter('pll_the_language_link', [$this, 'pll_fix_taxonomy_lang_link'], 10, 3);

    add_action('wp_ajax_cff_search_posts', [$this, 'ajax_search_posts']);
    add_action('wp_ajax_cff_get_templates', [$this, 'ajax_get_templates']);
    add_action('wp_ajax_cff_get_post_types', [$this, 'ajax_get_post_types']);
    add_action('wp_ajax_cff_reorder_get_posts', [$this, 'ajax_reorder_get_posts']);
    add_action('wp_ajax_cff_reorder_save_posts', [$this, 'ajax_reorder_save_posts']);
    add_action('wp_ajax_cff_reorder_get_terms', [$this, 'ajax_reorder_get_terms']);
    add_action('wp_ajax_cff_reorder_save_terms', [$this, 'ajax_reorder_save_terms']);
  }

  public function admin_menu() {
    $cap = 'manage_options';
    add_menu_page(__('Custom Fields', 'cff'), __('Custom Fields', 'cff'), $cap, 'cff', [$this,'page_dashboard'], 'dashicons-feedback', 58);
    add_submenu_page('cff', __('Field Groups','cff'), __('Field Groups','cff'), $cap, 'edit.php?post_type=cff_group');
    add_submenu_page('cff', __('Post Types','cff'), __('Post Types','cff'), $cap, 'cff-post-types', [$this,'page_post_types']);
    add_submenu_page('cff', __('Taxonomies','cff'), __('Taxonomies','cff'), $cap, 'cff-taxonomies', [$this,'page_taxonomies']);
    add_submenu_page('cff', __('Reorder','cff'), __('Reorder','cff'), $cap, 'cff-reorder', [$this,'page_reorder']);
    add_submenu_page('cff', __('Tools','cff'), __('Tools','cff'), $cap, 'cff-tools', [$this,'page_tools']);
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

  public function page_post_types() {
    if (!current_user_can('manage_options')) return;

    $defs = get_option('cffp_post_types', []);
    if (!is_array($defs)) $defs = [];

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

          $defs[$key] = [
            'singular' => sanitize_text_field($_POST['cpt_singular'] ?? ''),
            'plural'   => sanitize_text_field($_POST['cpt_plural'] ?? ''),
            'slug'     => sanitize_title($_POST['cpt_slug'] ?? $key),
            'public'   => !empty($_POST['cpt_public']),
            'has_archive' => !empty($_POST['cpt_archive']),
            'show_in_rest' => !empty($_POST['cpt_rest']),
            'supports' => isset($_POST['cpt_supports'])
              ? array_values(array_map('sanitize_key', (array) $_POST['cpt_supports']))
              : ['title','editor'],
            'taxonomies' => isset($_POST['cpt_taxonomies'])
              ? array_values(array_filter(array_map('sanitize_key',(array)$_POST['cpt_taxonomies'])))
              : [],
            'menu_icon' => $menu_icon,
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
      echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Label</th><th>Slug</th><th>Icon</th><th>Public</th><th>Actions</th></tr></thead><tbody>';

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
        echo '<td><code>'.esc_html($def['slug'] ?? $key).'</code></td>';
        echo '<td>'.$icon_html.'</td>';
        echo '<td>'.(!empty($def['public']) ? 'Yes' : 'No').'</td>';
        echo '<td>';

        echo '<a class="button button-small" href="'.esc_url($edit_url).'">Edit</a> ';

        echo '<form method="post" style="display:inline">';
        wp_nonce_field('cffp_cpt_save','cffp_cpt_nonce');
        echo '<input type="hidden" name="cffp_action" value="delete">';
        echo '<input type="hidden" name="cpt_key" value="'.esc_attr($key).'">';
        echo '<button class="button button-link-delete button-small" type="submit" onclick="return confirm(\'Delete CPT?\')">Delete</button>';
        echo '</form>';

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

    $supports_selected = $editing && isset($edit_def['supports']) && is_array($edit_def['supports'])
      ? array_values(array_map('sanitize_key', $edit_def['supports']))
      : ['title','editor'];

    echo '<form method="post" class="cff-cpt-form">';
    wp_nonce_field('cffp_cpt_save','cffp_cpt_nonce');
    echo '<input type="hidden" name="cffp_action" value="'.($editing ? 'update' : 'add').'">';

    echo '<table class="form-table"><tbody>';

    echo '<tr><th><label>Key</label></th><td><input name="cpt_key" class="regular-text" placeholder="custom_post" required value="'.esc_attr($editing ? $edit_key : '').'" '.($editing?'readonly':'').'></td></tr>';
    $langs = $this->polylang_languages();
    echo '<tr><th><label>Singular</label></th><td><input name="cpt_singular" class="regular-text" placeholder="Custom Post" value="'.esc_attr($edit_def['singular'] ?? '').'"></td></tr>';
    echo '<tr><th><label>Plural</label></th><td><input name="cpt_plural" class="regular-text" placeholder="Custom Posts" value="'.esc_attr($edit_def['plural'] ?? '').'"></td></tr>';
    echo '<tr><th><label>Slug</label></th><td><input name="cpt_slug" class="regular-text" placeholder="custom-post" value="'.esc_attr($edit_def['slug'] ?? '').'"></td></tr>';
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
    echo '<label><input type="checkbox" name="cpt_public" value="1" '.($pub?'checked':'').'> Public</label> &nbsp; ';
    echo '<label><input type="checkbox" name="cpt_archive" value="1" '.($arc?'checked':'').'> Has Archive</label> &nbsp; ';
    echo '<label><input type="checkbox" name="cpt_rest" value="1" '.($rst?'checked':'').'> REST API</label>';
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

    echo '<tr><th><label>Supports</label></th><td>';
    foreach ($supports_all as $k => $lab) {
      $checked = in_array($k, $supports_selected, true) ? 'checked' : '';
      echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="cpt_supports[]" value="'.esc_attr($k).'" '.$checked.'> '.esc_html($lab).'</label>';
    }
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
          add_settings_error('cffp_taxonomies','dup',__('Taxonomy duplicated','cff'),'updated');
        }
      }

      if ($action === 'delete') {
        $key = sanitize_key($_POST['tax_key'] ?? '');
        if ($key && isset($defs[$key])) {
          unset($defs[$key]);
          update_option('cffp_taxonomies', $defs);
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
        echo '<button class="button button-link-delete" name="cffp_tax_action" value="delete" onclick="return confirm(\'Delete taxonomy?\')">Delete</button>';
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
      echo '<option value="'.esc_attr($tax->name).'">'.esc_html($tax->labels->name).'</option>';
    }
    echo '</select> ';
    echo '<button type="button" class="button" id="cff-reorder-load-terms">Load</button>';
    echo '</div>';
    echo '<ul class="cff-reorder-list" data-kind="term"></ul>';
    echo '<p><button type="button" class="button button-primary" id="cff-reorder-save-terms">Save Order</button></p>';
    echo '</div>';

    echo '</div></div>';
  }

  public function assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_group = $screen && $screen->post_type === 'cff_group';
    $is_post_edit = $screen && in_array($screen->base, ['post','post-new'], true);
    $is_term_edit = $screen && in_array($screen->base, ['edit-tags','term'], true);

    if ($is_group || strpos($hook, 'cff') !== false) {
      wp_enqueue_style('cff-admin', CFFP_URL . 'assets/admin.css', [], $this->asset_ver('assets/admin.css'));

      wp_enqueue_style('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0-rc.0');
      wp_enqueue_script('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.js', ['jquery'], '4.1.0-rc.0', true);
      wp_enqueue_script('cff-select2-init', plugin_dir_url(__FILE__) . '../assets/js/select2-init.js', ['jquery','cff-select2'], $this->asset_ver('assets/js/select2-init.js'), true);

      wp_enqueue_script('cff-admin', CFFP_URL . 'assets/admin.js', ['jquery','jquery-ui-sortable'], $this->asset_ver('assets/admin.js'), true);
      wp_localize_script('cff-admin', 'CFFP', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cffp'),
      ]);
    }

    if ($is_post_edit) {
      wp_enqueue_style('cff-post', CFFP_URL . 'assets/post.css', [], $this->asset_ver('assets/post.css'));

      wp_enqueue_media();

      wp_enqueue_editor();

      wp_enqueue_style('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0-rc.0');
      wp_enqueue_script('cff-select2', CFFP_URL . 'assets/vendor/select2/select2.min.js', ['jquery'], '4.1.0-rc.0', true);

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

  private function asset_ver($rel_path) {
    $path = CFFP_DIR . ltrim($rel_path, '/');
    return file_exists($path) ? (string) filemtime($path) : CFFP_VERSION;
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
    ]);

    $out = [];
    foreach ($posts as $p) {
      $out[] = [
        'id' => $p->ID,
        'title' => $p->post_title ?: '(no title)',
        'status' => $p->post_status,
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

    foreach ($order as $i => $id) {
      if ($id) wp_update_post(['ID' => $id, 'menu_order' => $i]);
    }

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
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);

    $tax = sanitize_key($_POST['taxonomy'] ?? '');
    if (!$tax || !taxonomy_exists($tax)) wp_send_json_error(['message'=>'Invalid taxonomy'], 400);

    $terms = get_terms([
      'taxonomy' => $tax,
      'hide_empty' => false,
    ]);
    if (is_wp_error($terms)) wp_send_json_error(['message'=>'Failed to load terms'], 500);

    usort($terms, function($a, $b){
      $oa = (int) get_term_meta($a->term_id, 'cffp_term_order', true);
      $ob = (int) get_term_meta($b->term_id, 'cffp_term_order', true);
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
      $terms = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => false,
      ]);

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
        <div class="cff-col">
          <label>Label</label>
          <input type="text" class="cff-input cff-label" value="{{label}}">
        </div>
        <div class="cff-col">
          <label>Name</label>
          <input type="text" class="cff-input cff-name" value="{{name}}">
        </div>
        <div class="cff-col">
          <label>Type</label>
          <select class="cff-input cff-type">
            <option value="text">Text</option>
            <option value="textarea">Textarea</option>
            <option value="wysiwyg">WYSIWYG</option>
            <option value="color">Color</option>
            <option value="url">URL</option>
            <option value="link">Link</option>
            <option value="checkbox">Checkbox</option>
            <option value="image">Image</option>
            <option value="file">File</option>
            <option value="group">Group</option>
            <option value="repeater">Repeater</option>
            <option value="flexible">Flexible Content</option>
          </select>
        </div>
        <div class="cff-col cff-actions">
          <button type="button" class="button cff-duplicate">Duplicate</button>
          <button type="button" class="button cff-remove">Remove</button>
        </div>

        <div class="cff-advanced">
          <div class="cff-subbuilder" data-kind="repeater">
            <div class="cff-subhead">
              <strong>Sub Fields (Repeater)</strong>
              <button type="button" class="button cff-add-sub">Add Sub Field</button>
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
        <div class="cff-col">
          <label>Label</label>
          <input type="text" class="cff-input cff-slabel" value="{{label}}">
        </div>
        <div class="cff-col">
          <label>Name</label>
          <input type="text" class="cff-input cff-sname" value="{{name}}">
        </div>
        <div class="cff-col">
          <label>Type</label>
          <select class="cff-input cff-stype">
            <option value="text">Text</option>
            <option value="textarea">Textarea</option>
            <option value="wysiwyg">WYSIWYG</option>
            <option value="color">Color</option>
            <option value="url">URL</option>
            <option value="link">Link</option>
            <option value="checkbox">Checkbox</option>
            <option value="image">Image</option>
            <option value="file">File</option>
            <option value="group">Group</option>
          </select>
        </div>
        <div class="cff-col cff-actions">
          <button type="button" class="button cff-remove-sub">Remove</button>
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
    foreach ($fields as $f) {
      $type = isset($f['type']) ? sanitize_key($f['type']) : 'text';
      $name = isset($f['name']) ? sanitize_key($f['name']) : '';
      if (!$name) continue;

      $item = [
        'label' => sanitize_text_field($f['label'] ?? $name),
        'name'  => $name,
        'type'  => $type,
      ];

      if ($type === 'repeater') {
        $item['sub_fields'] = $this->sanitize_subfields($f['sub_fields'] ?? []);
      }
      if ($type === 'flexible') {
        $item['layouts'] = $this->sanitize_layouts($f['layouts'] ?? []);
      }
      if ($type === 'group') {
        $item['sub_fields'] = $this->sanitize_subfields($f['sub_fields'] ?? []);
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
      'author','format','page_attributes','featured_image','categories','tags','trackbacks'
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

  private function sanitize_subfields($subs) {
    $out = [];
    if (!is_array($subs)) return $out;
    foreach ($subs as $s) {
      $name = sanitize_key($s['name'] ?? '');
      if (!$name) continue;
      $type = sanitize_key($s['type'] ?? 'text');
      $item = [
        'label' => sanitize_text_field($s['label'] ?? $name),
        'name'  => $name,
        'type'  => $type,
      ];
      if ($type === 'group') {
        $item['sub_fields'] = $this->sanitize_subfields($s['sub_fields'] ?? []);
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
        'label' => sanitize_text_field($l['label'] ?? $lname),
        'name'  => $lname,
        'sub_fields' => $this->sanitize_subfields($l['sub_fields'] ?? []),
      ];
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
          wp_nonce_field('cff_content_save', 'cff_content_nonce');
          echo '<div class="cff-metabox">';
          foreach ($fields as $f) $this->render_field($post, $f);
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
      $date = get_the_date('', $p->ID);
      $label = trim($pt_label . ' - (' . $date . ') - ' . $p->post_title);
      $out[] = [
        'id' => $p->ID,
        'text' => $label,
        'meta' => '',
        'url' => get_permalink($p->ID),
      ];
    }
    wp_send_json_success($out);
  }

  public function ajax_get_templates() {
    check_ajax_referer('cffp', 'nonce');

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
    $pts = get_post_types(['public'=>true], 'objects');
    $out = [];
    foreach ($pts as $pt) {
      $out[] = ['id'=>$pt->name, 'text'=>$pt->labels->singular_name, 'meta'=>$pt->name];
    }
    wp_send_json_success($out);
  }

  /* =========================
   * Tools: Export / Import / Migration
   * ========================= */
  public function page_tools() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['cff_tools_action']) && check_admin_referer('cff_tools_nonce','cff_tools_nonce')) {
      if ($_POST['cff_tools_action'] === 'import' && !empty($_FILES['cff_json']['tmp_name'])) {
        $raw = file_get_contents($_FILES['cff_json']['tmp_name']);
        $data = json_decode($raw, true);

        if (is_array($data)) {
          if (isset($data['post_types'])) {
            update_option('cffp_post_types', $this->sanitize_import_post_types((array)$data['post_types']));
          }
          if (isset($data['taxonomies'])) {
            update_option('cffp_taxonomies', $this->sanitize_import_taxonomies((array)$data['taxonomies']));
          }
          if (isset($data['field_groups'])) {
            $this->import_field_groups((array)$data['field_groups']);
          } elseif ($this->looks_like_acf_json($data)) {
            $this->import_acf_json($data);
          }
          add_settings_error('cff_tools','imported',__('Import completed. If you changed CPT/Taxonomies, re-save permalinks.','cff'),'updated');
        } else {
          add_settings_error('cff_tools','badjson',__('Invalid JSON file.','cff'),'error');
        }
      }

      if ($_POST['cff_tools_action'] === 'migrate_acf') {
        $migrated = $this->migrate_from_acf();
        add_settings_error('cff_tools','migrated',sprintf(__('ACF migration finished. Imported %d field groups.','cff'), intval($migrated)),'updated');
      }
    }

    settings_errors('cff_tools');

    $export_url = admin_url('admin.php?page=cff-tools&cff_export=1');
    $export_groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'any',
      'numberposts' => -1,
      'no_found_rows' => true,
    ]);

    echo '<div class="wrap cff-admin"><h1>Tools</h1><div class="cff-tools-grid">';

    echo '<div class="cff-tools-card">';
    echo '<h2>Export</h2><p class="description">Download JSON export of Field Groups, Post Types, and Taxonomies.</p>';
    echo '<form method="post" action="'.esc_url($export_url).'">';
    echo wp_nonce_field('cff_export_nonce','cff_export_nonce',true,false);
    echo '<input type="hidden" name="cff_export" value="1">';
    echo '<p><label><input type="checkbox" name="cff_export_post_types" checked> Post Types</label></p>';
    echo '<p><label><input type="checkbox" name="cff_export_taxonomies" checked> Taxonomies</label></p>';
    echo '<div class="cff-tools-field"><label>Field Groups</label>';
    if ($export_groups) {
      echo '<div class="cff-export-checklist">';
      foreach ($export_groups as $group) {
        echo '<label><input type="checkbox" name="cff_export_groups[]" value="' . esc_attr($group->ID) . '" checked> ' . esc_html($group->post_title) . '</label>';
      }
      echo '</div>';
      echo '<p class="description">Uncheck to exclude specific groups.</p>';
    } else {
      echo '<p class="description">No field groups found.</p>';
    }
    echo '</div>';
    echo '<p><button class="button button-primary">Download Export JSON</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="cff-tools-card">';
    echo '<h2>' . esc_html__('Export ACF Values', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Generate SQL that copies ACF post meta into the `_cff_` meta keys so it can be imported by CFF without losing existing content.', 'cff') . '</p>';
    echo '<form method="post">';
    echo wp_nonce_field('cff_export_acf_data', 'cff_export_acf_nonce', true, false);
    echo '<input type="hidden" name="cff_export_acf_data" value="1">';
    echo '<p><button class="button button-primary">' . esc_html__('Download ACF to CFF SQL', 'cff') . '</button></p>';
    echo '<p class="description">' . esc_html__('ACF must be active so the exporter can read the existing values.', 'cff') . '</p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="cff-tools-card">';
    echo '<h2>Import</h2><p class="description">Import JSON exported from this plugin.</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo wp_nonce_field('cff_tools_nonce','cff_tools_nonce',true,false);
    echo '<input type="hidden" name="cff_tools_action" value="import">';
    echo '<input type="file" name="cff_json" accept="application/json" required>';
    echo '<p><button class="button button-primary">Import JSON</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="cff-tools-card">';
    echo '<h2>Migrate from ACF</h2><p class="description">If ACF is installed, import its field groups into CFF (basic mapping).</p>';
    echo '<form method="post">';
    echo wp_nonce_field('cff_tools_nonce','cff_tools_nonce',true,false);
    echo '<input type="hidden" name="cff_tools_action" value="migrate_acf">';
    echo '<p><button class="button">Run ACF Migration</button></p>';
    echo '</form>';
    echo '</div>';

    echo '</div></div>';
  }

  public function handle_export_group() {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['cff_export_group'])) return;

    $group_id = absint($_GET['cff_export_group']);
    if (!$group_id) return;

    if (!check_admin_referer('cff_export_group_' . $group_id)) return;

    $group = get_post($group_id);
    if (!$group || $group->post_type !== 'cff_group') return;

    $payload = [
      'version' => '0.13.0',
      'exported_at' => gmdate('c'),
      'post_types' => [],
      'taxonomies' => [],
      'field_groups' => $this->export_field_groups($group_id),
    ];

    $json = wp_json_encode($payload, JSON_PRETTY_PRINT);
    $slug = $group->post_name ?: ('group-' . $group_id);
    $filename = 'cff-group-' . sanitize_file_name($slug) . '-' . date('Ymd-His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $json;
    exit;
  }

  public function handle_export_tools() {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['cff_export']) && empty($_POST['cff_export'])) return;

    if (!check_admin_referer('cff_export_nonce', 'cff_export_nonce')) return;

    $include_post_types = !empty($_REQUEST['cff_export_post_types']);
    $include_taxonomies = !empty($_REQUEST['cff_export_taxonomies']);
    $has_group_filter = isset($_REQUEST['cff_export_groups']);
    $group_ids = $has_group_filter ? array_filter(array_map('absint', (array) $_REQUEST['cff_export_groups'])) : [];

    $payload = [
      'version' => '0.13.0',
      'exported_at' => gmdate('c'),
      'post_types' => $include_post_types ? get_option('cffp_post_types', []) : [],
      'taxonomies' => $include_taxonomies ? get_option('cffp_taxonomies', []) : [],
      'field_groups' => $has_group_filter ? $this->export_field_groups($group_ids) : $this->export_field_groups(),
    ];

    $json = wp_json_encode($payload, JSON_PRETTY_PRINT);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cff-export-' . date('Ymd-His') . '.json"');
    echo $json;
    exit;
  }

  public function handle_export_acf_data() {
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['cff_export_acf_data'])) return;
    if (!check_admin_referer('cff_export_acf_data','cff_export_acf_nonce')) return;

    if (!function_exists('acf_get_field_groups')) {
      add_settings_error('cff_tools','acf_export_no_acf',__('ACF is not active or available for export.','cff'),'error');
      return;
    }

    $sql = $this->build_acf_data_export_sql();
    if (!$sql) {
      add_settings_error('cff_tools','acf_export_empty',__('No eligible ACF values were found for export.','cff'),'error');
      return;
    }

    $filename = 'cff-acf-data-' . gmdate('Ymd-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql;
    exit;
  }

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
    foreach ((array) $fields as $f) {
      if (!is_array($f)) continue;
      $type = sanitize_key($f['type'] ?? 'text');
      $name = sanitize_key($f['name'] ?? '');
      if (!$name) continue;

      $item = [
        'label' => sanitize_text_field($f['label'] ?? $name),
        'name' => $name,
        'type' => $type,
      ];

      if ($type === 'repeater' || $type === 'group') {
        $item['sub_fields'] = $this->sanitize_subfields($f['sub_fields'] ?? []);
      }
      if ($type === 'flexible') {
        $item['layouts'] = $this->sanitize_layouts($f['layouts'] ?? []);
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

    $post_types = [];
    if (is_array($loc)) {
      foreach ($loc as $or_group) {
        foreach ((array)$or_group as $rule) {
          if (($rule['param'] ?? '') === 'post_type' && !empty($rule['value'])) {
            $post_types[] = sanitize_key($rule['value']);
          }
        }
      }
    }

    $post_types = array_values(array_unique(array_filter($post_types)));
    if (!$post_types) $post_types = ['post','page'];

    $posts = get_posts([
      'post_type' => $post_types,
      'post_status' => 'any',
      'numberposts' => -1,
      'no_found_rows' => true,
    ]);

    // konversi rules ACF ke format CFF untuk match_location
    $converted_rules = [];
    foreach ((array)$loc as $or_group) {
      $and_rules = [];
      foreach ((array)$or_group as $rule) {
        $param = $rule['param'] ?? '';
        $value = $rule['value'] ?? '';
        if ($param === 'post_type') {
          $and_rules[] = ['param'=>'post_type','operator'=>'==','value'=>sanitize_key($value)];
        } elseif ($param === 'page_template') {
          $and_rules[] = ['param'=>'page_template','operator'=>'==','value'=>sanitize_text_field($value)];
        } elseif ($param === 'post') {
          $and_rules[] = ['param'=>'post','operator'=>'==','value'=>intval($value)];
        } elseif ($param === 'page') {
          $and_rules[] = ['param'=>'page','operator'=>'==','value'=>intval($value)];
        }
      }
      if ($and_rules) $converted_rules[] = $and_rules;
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
}
