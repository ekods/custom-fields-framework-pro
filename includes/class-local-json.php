<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/**
 * Local JSON: field groups are mirrored to {theme}/cff-json/{group_key}.json whenever they change, so they can
 * be versioned and deployed. Files that are newer than the database (or missing from it) can be synced back
 * from the Field Groups screen. Enabled only when the folder exists and is writable.
 */
class Local_JSON {
  private $plugin;
  private $syncing = false;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function register() {
    add_action('added_post_meta', [$this, 'on_settings_meta'], 10, 3);
    add_action('updated_post_meta', [$this, 'on_settings_meta'], 10, 3);
    add_action('save_post_cff_group', [$this, 'on_save_group'], 20, 2);
    add_action('untrashed_post', [$this, 'write_group']);
    add_action('wp_trash_post', [$this, 'delete_file']);
    add_action('before_delete_post', [$this, 'delete_file']);
    add_action('admin_notices', [$this, 'render_sync_notice']);
    add_action('admin_post_cff_local_json_sync', [$this, 'handle_sync']);
  }

  public function save_path() {
    $path = apply_filters('cff/settings/save_json', trailingslashit(get_stylesheet_directory()) . 'cff-json');
    return untrailingslashit((string) $path);
  }

  public function load_paths() {
    $paths = apply_filters('cff/settings/load_json', [$this->save_path()]);
    return array_values(array_unique(array_filter(array_map('untrailingslashit', array_map('strval', (array) $paths)))));
  }

  public function is_enabled() {
    $path = $this->save_path();
    return $path !== '' && is_dir($path) && wp_is_writable($path);
  }

  /** Stable key used as the file name and to match groups across sites. */
  public function group_key($post_id) {
    $key = sanitize_key((string) get_post_meta($post_id, 'cff_group_key', true));
    if (!$key) {
      $key = 'group_' . substr(md5(uniqid((string) $post_id, true)), 0, 13);
      update_post_meta($post_id, 'cff_group_key', $key);
    }
    return $key;
  }

  public function on_settings_meta($meta_id, $post_id, $meta_key) {
    if ($meta_key !== '_cff_settings' || get_post_type($post_id) !== 'cff_group') return;
    $this->write_group($post_id);
  }

  /** Title/description edits do not touch _cff_settings, so also write after the group is saved. */
  public function on_save_group($post_id, $post) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    $this->write_group($post_id);
  }

  public function write_group($post_id) {
    if ($this->syncing || !$this->is_enabled()) return false;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'cff_group' || in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], true)) return false;
    $settings = get_post_meta($post_id, '_cff_settings', true);
    if (!is_array($settings)) return false;

    $modified = time();
    $data = [
      'key' => $this->group_key($post_id),
      'title' => $post->post_title,
      'description' => $post->post_excerpt,
      'status' => $post->post_status,
      'modified' => $modified,
      'settings' => $settings,
    ];
    $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!$json || file_put_contents($this->save_path() . '/' . $data['key'] . '.json', $json . "\n") === false) return false;
    update_post_meta($post_id, '_cff_json_modified', $modified);
    return true;
  }

  public function delete_file($post_id) {
    if (get_post_type($post_id) !== 'cff_group' || !$this->is_enabled()) return;
    $key = sanitize_key((string) get_post_meta($post_id, 'cff_group_key', true));
    $file = $this->save_path() . '/' . $key . '.json';
    if ($key && is_file($file)) wp_delete_file($file);
    delete_post_meta($post_id, '_cff_json_modified');
  }

  /** Valid JSON files from all load paths, keyed by group key (first path wins). */
  public function files() {
    $out = [];
    foreach ($this->load_paths() as $dir) {
      if (!is_dir($dir)) continue;
      foreach ((array) glob($dir . '/*.json') as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || empty($data['key']) || !is_array($data['settings'] ?? null)) continue;
        $key = sanitize_key($data['key']);
        if ($key && !isset($out[$key])) $out[$key] = $data + ['file' => $file];
      }
    }
    return $out;
  }

  /** Files that are new to this site or newer than the group's last write/sync. */
  public function pending() {
    $pending = [];
    foreach ($this->files() as $key => $data) {
      $post_id = $this->find_group($key, (string) ($data['title'] ?? ''));
      if ($post_id && get_post_status($post_id) === 'trash') continue;
      $local = $post_id ? (int) get_post_meta($post_id, '_cff_json_modified', true) : 0;
      if (!$post_id || (int) ($data['modified'] ?? 0) > $local) {
        $pending[$key] = $data + ['post_id' => $post_id];
      }
    }
    return $pending;
  }

  /** Match by key; a same-titled group without a key is adopted so first syncs do not duplicate it. */
  private function find_group($key, $title) {
    $ids = get_posts(['post_type' => 'cff_group', 'post_status' => 'any', 'meta_key' => 'cff_group_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1, 'no_found_rows' => true]);
    if ($ids) return (int) $ids[0];
    if ($title === '') return 0;
    $candidates = get_posts(['post_type' => 'cff_group', 'post_status' => ['publish', 'draft', 'private'], 'title' => $title, 'fields' => 'ids', 'numberposts' => -1, 'no_found_rows' => true]);
    foreach ($candidates as $id) {
      if (!get_post_meta($id, 'cff_group_key', true)) return (int) $id;
    }
    return 0;
  }

  private function import(array $data) {
    $this->syncing = true;
    $status = $data['status'] ?? 'publish';
    if (!in_array($status, ['publish', 'draft', 'private'], true)) $status = 'publish';
    $postarr = [
      'post_type' => 'cff_group',
      'post_title' => sanitize_text_field($data['title'] ?? $data['key']),
      'post_excerpt' => sanitize_textarea_field($data['description'] ?? ''),
      'post_status' => $status,
    ];
    if (!empty($data['post_id'])) {
      $postarr['ID'] = (int) $data['post_id'];
      $post_id = wp_update_post(wp_slash($postarr), true);
    } else {
      $post_id = wp_insert_post(wp_slash($postarr), true);
    }
    if ($post_id && !is_wp_error($post_id)) {
      $settings = $this->plugin->local_json_sanitize_settings($data['settings']);
      update_post_meta($post_id, 'cff_group_key', sanitize_key($data['key']));
      update_post_meta($post_id, '_cff_settings', wp_slash($settings));
      update_post_meta($post_id, 'cff_fields_json', wp_slash(wp_json_encode($settings['fields'] ?? [])));
      update_post_meta($post_id, '_cff_json_modified', (int) ($data['modified'] ?? time()));
    }
    $this->syncing = false;
    return $post_id && !is_wp_error($post_id);
  }

  public function handle_sync() {
    if (!current_user_can(Plugin::admin_capability())) wp_die(esc_html__('Forbidden', 'cff'), 403);
    check_admin_referer('cff_local_json_sync');
    $pending = $this->pending();
    $keys = isset($_POST['sync_all']) ? array_keys($pending) : array_map('sanitize_key', (array) wp_unslash($_POST['keys'] ?? []));
    $done = 0;
    foreach ($keys as $key) {
      if (isset($pending[$key]) && $this->import($pending[$key])) $done++;
    }
    wp_safe_redirect(add_query_arg('cff_json_synced', $done, admin_url('edit.php?post_type=cff_group')));
    exit;
  }

  public function render_sync_notice() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-cff_group' || !current_user_can(Plugin::admin_capability())) return;

    if (isset($_GET['cff_json_synced'])) {
      $count = absint($_GET['cff_json_synced']);
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d field group synced from Local JSON.', '%d field groups synced from Local JSON.', $count, 'cff'), $count)) . '</p></div>';
    }

    $pending = $this->pending();
    if (!$pending) return;
    echo '<div class="notice notice-warning cff-json-sync-notice">';
    echo '<p><strong>' . esc_html__('Local JSON', 'cff') . '</strong> ' . esc_html(sprintf(_n('%d field group has changes in JSON that are not in the database.', '%d field groups have changes in JSON that are not in the database.', count($pending), 'cff'), count($pending))) . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('cff_local_json_sync');
    echo '<input type="hidden" name="action" value="cff_local_json_sync">';
    echo '<ul class="cff-json-sync-list">';
    foreach ($pending as $key => $data) {
      $state = empty($data['post_id']) ? __('New', 'cff') : __('Update available', 'cff');
      $when = !empty($data['modified']) ? sprintf(__('%s ago', 'cff'), human_time_diff((int) $data['modified'])) : '';
      echo '<li><span class="cff-json-sync-title">' . esc_html($data['title'] ?? $key) . '</span> <code>' . esc_html($key) . '</code> ';
      echo '<span class="cff-json-sync-state">' . esc_html($state . ($when ? ' · ' . $when : '')) . '</span> ';
      echo '<button type="submit" class="button button-small" name="keys[]" value="' . esc_attr($key) . '">' . esc_html__('Sync', 'cff') . '</button></li>';
    }
    echo '</ul>';
    if (count($pending) > 1) {
      echo '<p><button type="submit" class="button button-primary" name="sync_all" value="1">' . esc_html__('Sync all', 'cff') . '</button></p>';
    }
    echo '</form></div>';
  }

  /** Status card for the Export / Import page. */
  public function render_status_card() {
    $path = $this->save_path();
    echo '<div class="tk-card cff-local-json-card">';
    echo '<h2>' . esc_html__('Local JSON', 'cff') . '</h2>';
    echo '<p class="description">' . esc_html__('Field groups are saved as JSON files in your theme so they can be versioned with git and synced between environments.', 'cff') . '</p>';
    if ($this->is_enabled()) {
      $count = count($this->files());
      echo '<p><span class="cff-status cff-status--public">' . esc_html__('Enabled', 'cff') . '</span> ' . esc_html(sprintf(_n('%d file', '%d files', $count, 'cff'), $count)) . '</p>';
    } elseif (is_dir($path)) {
      echo '<p><span class="cff-status cff-status--muted">' . esc_html__('Not writable', 'cff') . '</span> ' . esc_html__('Make the folder writable by the web server to enable saving.', 'cff') . '</p>';
    } else {
      echo '<p><span class="cff-status cff-status--muted">' . esc_html__('Off', 'cff') . '</span> ' . esc_html__('Create this folder to turn it on:', 'cff') . '</p>';
    }
    echo '<p><code>' . esc_html($path) . '</code></p>';
    echo '</div>';
  }
}
