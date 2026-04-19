<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

class Tools_Page {
  private $plugin;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function page_tools() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['cff_tools_action']) && check_admin_referer('cff_tools_nonce', 'cff_tools_nonce')) {
      $this->handle_tools_post_action();
    }

    settings_errors('cff_tools');

    $export_url = admin_url('admin.php?page=cff-tools&cff_export=1');
    $export_groups = get_posts([
      'post_type' => 'cff_group',
      'post_status' => 'any',
      'numberposts' => -1,
      'no_found_rows' => true,
    ]);
    $export_post_types = get_option('cffp_post_types', []);
    if (!is_array($export_post_types)) $export_post_types = [];
    $export_taxonomies = get_option('cffp_taxonomies', []);
    if (!is_array($export_taxonomies)) $export_taxonomies = [];

    echo '<div class="wrap cff-admin cff-tools-page">';
    echo '<div class="cff-page-header">';
    echo '<h1>' . esc_html__('Export / Import', 'cff') . '</h1>';
    echo '<p>' . esc_html__('Move CFF structures between sites, migrate ACF data, and keep content models versionable.', 'cff') . '</p>';
    echo '</div>';

    echo '<div class="cff-tools-grid">';

    echo '<section class="cff-tools-card cff-tools-card--feature">';
    echo '<h2>' . esc_html__('Export Configuration', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Download a JSON package containing field groups, post types, and taxonomies.', 'cff') . '</p>';
    echo '<form method="post" action="' . esc_url($export_url) . '">';
    echo wp_nonce_field('cff_export_nonce', 'cff_export_nonce', true, false);
    echo '<input type="hidden" name="cff_export" value="1">';

    $this->render_export_toggle(
      'cff_export_post_types',
      __('Post Types', 'cff'),
      '.cff-export-post-types-list',
      $export_post_types,
      'cff_export_post_types_keys[]'
    );
    echo '<div class="cff-tools-field cff-export-post-types-list">';
    echo '<input type="hidden" name="cff_export_post_types_filter" value="1">';
    $this->render_export_checklist($export_post_types, 'cff_export_post_types_keys[]');
    echo '</div>';

    $this->render_export_toggle(
      'cff_export_taxonomies',
      __('Taxonomies', 'cff'),
      '.cff-export-taxonomies-list',
      $export_taxonomies,
      'cff_export_taxonomies_keys[]'
    );
    echo '<div class="cff-tools-field cff-export-taxonomies-list">';
    echo '<input type="hidden" name="cff_export_taxonomies_filter" value="1">';
    $this->render_export_checklist($export_taxonomies, 'cff_export_taxonomies_keys[]');
    echo '</div>';

    $this->render_export_toggle(
      'cff_export_field_groups',
      __('Field Groups', 'cff'),
      '.cff-export-field-groups-list',
      $export_groups,
      'cff_export_groups[]'
    );
    echo '<div class="cff-tools-field cff-export-field-groups-list">';
    echo '<input type="hidden" name="cff_export_field_groups_filter" value="1">';
    $this->render_group_checklist($export_groups);
    echo '</div>';

    echo '<div class="cff-tools-actions"><button class="button button-primary">' . esc_html__('Download Export JSON', 'cff') . '</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="cff-tools-card">';
    echo '<h2>' . esc_html__('Export ACF Values', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Generate SQL that copies ACF post meta into the `_cff_` keys used by CFF.', 'cff') . '</p>';
    echo '<form method="post">';
    echo wp_nonce_field('cff_export_acf_data', 'cff_export_acf_nonce', true, false);
    echo '<input type="hidden" name="cff_export_acf_data" value="1">';
    echo '<div class="cff-tools-actions"><button class="button button-primary">' . esc_html__('Download ACF to CFF SQL', 'cff') . '</button></div>';
    echo '<p class="cff-tools-note">' . esc_html__('ACF must be active so the exporter can read the existing values.', 'cff') . '</p>';
    echo '</form>';
    echo '</section>';

    echo '<section class="cff-tools-card">';
    echo '<h2>' . esc_html__('Import Configuration', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Import a JSON package exported from this plugin or basic ACF JSON group definitions.', 'cff') . '</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo wp_nonce_field('cff_tools_nonce', 'cff_tools_nonce', true, false);
    echo '<input type="hidden" name="cff_tools_action" value="import">';
    echo '<input class="cff-file-input" type="file" name="cff_json" accept="application/json" required>';
    echo '<div class="cff-tools-actions"><button class="button button-primary">' . esc_html__('Import JSON', 'cff') . '</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="cff-tools-card">';
    echo '<h2>' . esc_html__('Migrate from ACF', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Import ACF field groups into CFF using the current basic field mapping.', 'cff') . '</p>';
    echo '<form method="post">';
    echo wp_nonce_field('cff_tools_nonce', 'cff_tools_nonce', true, false);
    echo '<input type="hidden" name="cff_tools_action" value="migrate_acf">';
    echo '<div class="cff-tools-actions"><button class="button">' . esc_html__('Run ACF Migration', 'cff') . '</button></div>';
    echo '<p class="cff-tools-note">' . esc_html__('Review imported groups before using them in production templates.', 'cff') . '</p>';
    echo '</form>';
    echo '</section>';

    echo '</div>';
    $this->render_tools_script();
    echo '</div>';
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
      'version' => defined('CFFP_VERSION') ? CFFP_VERSION : '0.0.0',
      'exported_at' => gmdate('c'),
      'post_types' => [],
      'taxonomies' => [],
      'field_groups' => $this->plugin->tools_export_field_groups($group_id),
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
    $include_field_groups = !empty($_REQUEST['cff_export_field_groups']);

    $all_post_types = get_option('cffp_post_types', []);
    if (!is_array($all_post_types)) $all_post_types = [];
    $post_types_payload = [];
    if ($include_post_types) {
      $has_pt_filter = !empty($_REQUEST['cff_export_post_types_filter']);
      if ($has_pt_filter) {
        $pt_keys = isset($_REQUEST['cff_export_post_types_keys']) ? array_map('sanitize_key', (array) $_REQUEST['cff_export_post_types_keys']) : [];
        foreach ($pt_keys as $pt_key) {
          if (isset($all_post_types[$pt_key])) {
            $post_types_payload[$pt_key] = $all_post_types[$pt_key];
          }
        }
      } else {
        $post_types_payload = $all_post_types;
      }
    }

    $all_taxonomies = get_option('cffp_taxonomies', []);
    if (!is_array($all_taxonomies)) $all_taxonomies = [];
    $taxonomies_payload = [];
    if ($include_taxonomies) {
      $has_tax_filter = !empty($_REQUEST['cff_export_taxonomies_filter']);
      if ($has_tax_filter) {
        $tax_keys = isset($_REQUEST['cff_export_taxonomies_keys']) ? array_map('sanitize_key', (array) $_REQUEST['cff_export_taxonomies_keys']) : [];
        foreach ($tax_keys as $tax_key) {
          if (isset($all_taxonomies[$tax_key])) {
            $taxonomies_payload[$tax_key] = $all_taxonomies[$tax_key];
          }
        }
      } else {
        $taxonomies_payload = $all_taxonomies;
      }
    }

    $field_groups_payload = [];
    if ($include_field_groups) {
      $has_group_filter = !empty($_REQUEST['cff_export_field_groups_filter']);
      if ($has_group_filter) {
        $group_ids = isset($_REQUEST['cff_export_groups']) ? array_filter(array_map('absint', (array) $_REQUEST['cff_export_groups'])) : [];
        $field_groups_payload = $group_ids ? $this->plugin->tools_export_field_groups($group_ids) : [];
      } else {
        $field_groups_payload = $this->plugin->tools_export_field_groups();
      }
    }

    $payload = [
      'version' => defined('CFFP_VERSION') ? CFFP_VERSION : '0.0.0',
      'exported_at' => gmdate('c'),
      'post_types' => $post_types_payload,
      'taxonomies' => $taxonomies_payload,
      'field_groups' => $field_groups_payload,
    ];

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cff-export-' . date('Ymd-His') . '.json"');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT);
    exit;
  }

  public function handle_export_acf_data() {
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['cff_export_acf_data'])) return;
    if (!check_admin_referer('cff_export_acf_data', 'cff_export_acf_nonce')) return;

    if (!function_exists('acf_get_field_groups')) {
      add_settings_error('cff_tools', 'acf_export_no_acf', __('ACF is not active or available for export.', 'cff'), 'error');
      return;
    }

    $sql = $this->plugin->tools_build_acf_data_export_sql();
    if (!$sql) {
      add_settings_error('cff_tools', 'acf_export_empty', __('No eligible ACF values were found for export.', 'cff'), 'error');
      return;
    }

    $filename = 'cff-acf-data-' . gmdate('Ymd-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql;
    exit;
  }

  private function handle_tools_post_action() {
    $action = sanitize_key($_POST['cff_tools_action'] ?? '');

    if ($action === 'import' && !empty($_FILES['cff_json']['tmp_name'])) {
      $raw = file_get_contents($_FILES['cff_json']['tmp_name']);
      $data = json_decode($raw, true);

      if (!is_array($data)) {
        add_settings_error('cff_tools', 'badjson', __('Invalid JSON file.', 'cff'), 'error');
        return;
      }

      if (isset($data['post_types'])) {
        $import_post_types = $this->plugin->tools_sanitize_import_post_types((array) $data['post_types']);
        $existing_post_types = get_option('cffp_post_types', []);
        if (!is_array($existing_post_types)) $existing_post_types = [];
        foreach ($import_post_types as $key => $definition) {
          $existing_post_types[$key] = $definition;
        }
        update_option('cffp_post_types', $existing_post_types);
      }

      if (isset($data['taxonomies'])) {
        $import_taxonomies = $this->plugin->tools_sanitize_import_taxonomies((array) $data['taxonomies']);
        $existing_taxonomies = get_option('cffp_taxonomies', []);
        if (!is_array($existing_taxonomies)) $existing_taxonomies = [];
        foreach ($import_taxonomies as $key => $definition) {
          $existing_taxonomies[$key] = $definition;
        }
        update_option('cffp_taxonomies', $existing_taxonomies);
      }

      if (isset($data['field_groups'])) {
        $this->plugin->tools_import_field_groups((array) $data['field_groups']);
      } elseif ($this->plugin->tools_looks_like_acf_json($data)) {
        $this->plugin->tools_import_acf_json($data);
      }

      add_settings_error('cff_tools', 'imported', __('Import completed. If you changed CPT/Taxonomies, re-save permalinks.', 'cff'), 'updated');
      return;
    }

    if ($action === 'migrate_acf') {
      $migrated = $this->plugin->tools_migrate_from_acf();
      add_settings_error('cff_tools', 'migrated', sprintf(__('ACF migration finished. Imported %d field groups.', 'cff'), intval($migrated)), 'updated');
    }
  }

  private function render_export_toggle($name, $label, $target, $items, $input_name) {
    $has_items = !empty($items);
    echo '<label class="cff-check-row">';
    echo '<input type="checkbox" class="cff-export-toggle" data-target="' . esc_attr($target) . '" name="' . esc_attr($name) . '" value="1" ' . checked($has_items, true, false) . '>';
    echo '<span><strong>' . esc_html($label) . '</strong></span>';
    echo '</label>';
  }

  private function render_export_checklist($items, $input_name) {
    if (empty($items)) {
      echo '<p class="cff-tools-note">' . esc_html__('Nothing to export in this section yet.', 'cff') . '</p>';
      return;
    }

    echo '<div class="cff-export-checklist">';
    foreach ($items as $key => $def) {
      $label = trim((string) ($def['plural'] ?? $def['singular'] ?? $key));
      if ($label === '') $label = $key;
      echo '<label><input type="checkbox" name="' . esc_attr($input_name) . '" value="' . esc_attr($key) . '" checked> ' . esc_html($label) . ' <code>' . esc_html($key) . '</code></label>';
    }
    echo '</div>';
    echo '<p class="cff-tools-note">' . esc_html__('Uncheck any item you do not want to include.', 'cff') . '</p>';
  }

  private function render_group_checklist($groups) {
    if (empty($groups)) {
      echo '<p class="cff-tools-note">' . esc_html__('No field groups found.', 'cff') . '</p>';
      return;
    }

    echo '<div class="cff-export-checklist">';
    foreach ($groups as $group) {
      echo '<label><input type="checkbox" name="cff_export_groups[]" value="' . esc_attr($group->ID) . '" checked> ' . esc_html($group->post_title) . '</label>';
    }
    echo '</div>';
    echo '<p class="cff-tools-note">' . esc_html__('Uncheck any field group you want to skip.', 'cff') . '</p>';
  }

  private function render_tools_script() {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
      var toggles = document.querySelectorAll(".cff-export-toggle");
      function syncToggle(toggle) {
        var target = toggle.getAttribute("data-target");
        if (!target) return;
        var panel = document.querySelector(target);
        if (!panel) return;
        var show = !!toggle.checked;
        panel.style.display = show ? "" : "none";
        var inputs = panel.querySelectorAll("input, select, textarea, button");
        inputs.forEach(function (el) { el.disabled = !show; });
      }
      toggles.forEach(function (toggle) {
        syncToggle(toggle);
        toggle.addEventListener("change", function () { syncToggle(toggle); });
      });
    });
    </script>';
  }
}
