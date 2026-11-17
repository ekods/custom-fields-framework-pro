<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/** Content history belongs to its post ID, independently of core title/body revisions. */
class Content_Revisions {
  const META_KEY = '_cffp_content_revision';
  const LIMIT = 20;
  private $plugin;
  private $pending = [];
  private $restoring = false;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function register() {
    // Observe the shared storage boundary: classic, REST, imports and translation copies.
    // Capture before the first write, then save only the completed state at shutdown.
    add_action('add_post_meta', [$this, 'before_add'], 10, 2);
    add_action('update_post_meta', [$this, 'before_change'], 10, 3);
    add_action('delete_post_meta', [$this, 'before_change'], 10, 3);
    add_action('shutdown', [$this, 'flush'], PHP_INT_MAX);
    add_action('add_meta_boxes', [$this, 'meta_box'], 30, 2);
    add_action('admin_menu', [$this, 'register_page']);
    add_action('admin_enqueue_scripts', [$this, 'assets']);
    add_action('wp_ajax_cff_content_revisions', [$this, 'ajax']);
    add_filter('pll_copy_post_metas', [$this, 'translation_meta_keys'], PHP_INT_MAX, 2);
  }

  private function content_post($post_id) {
    $post = get_post($post_id);
    if (!$post || in_array($post->post_type, ['cff_group', 'cff_options', 'revision', 'attachment', 'nav_menu_item'], true)) return null;
    $type = get_post_type_object($post->post_type);
    return $type && !empty($type->show_ui) ? $post : null;
  }

  private function is_field_key($key) {
    return is_string($key) && strpos($key, '_cff_') === 0 && $key !== '_cff_settings';
  }

  public function before_add($post_id, $key) {
    if ($this->restoring || !$this->is_field_key($key) || isset($this->pending[$post_id])) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !$this->content_post($post_id)) return;
    $this->pending[$post_id] = ['before' => $this->snapshot($post_id), 'user' => get_current_user_id()];
  }

  public function before_change($meta_ids, $post_id, $key) {
    $this->before_add($post_id, $key);
  }

  public function snapshot($post_id) {
    $snapshot = [];
    foreach (array_keys((array) get_post_meta($post_id)) as $key) {
      // Read by key: WordPress's all-meta API returns serialized nested values.
      if ($this->is_field_key($key)) $snapshot[$key] = get_post_meta($post_id, $key, false);
    }
    ksort($snapshot);
    return $snapshot;
  }

  public function fingerprint($snapshot) {
    return hash('sha256', serialize($snapshot));
  }

  public function history($post_id) {
    $history = [];
    foreach (get_post_meta($post_id, self::META_KEY, false) as $entry) {
      // Even an accidental third-party metadata copy cannot expose another ID's history.
      if (!is_array($entry) || (int) ($entry['post_id'] ?? 0) !== (int) $post_id) continue;
      if (empty($entry['id']) || !is_array($entry['snapshot'] ?? null)) continue;
      $history[] = $entry;
    }
    return $history;
  }

  private function append($post_id, $snapshot, $user, $kind, $restored_fields = []) {
    $history = $this->history($post_id);
    $latest = $history ? end($history) : null;
    if ($latest && $latest['snapshot'] === $snapshot) return true;
    $entry = [
      'id' => wp_generate_uuid4(), 'post_id' => (int) $post_id,
      'created_gmt' => gmdate('Y-m-d H:i:s'), 'user' => (int) $user,
      'kind' => $kind, 'snapshot' => $snapshot,
    ];
    $author = get_userdata($user);
    $entry['user_name'] = $author ? $author->display_name : '';
    if ($restored_fields) $entry['restored_fields'] = array_values($restored_fields);
    if (!add_post_meta($post_id, self::META_KEY, wp_slash($entry))) {
      return new \WP_Error('cff_revision_write', __('Unable to save CFF revision history.', 'cff'), ['status' => 500]);
    }
    $history = $this->history($post_id);
    foreach (array_slice($history, 0, max(0, count($history) - self::LIMIT)) as $old) {
      delete_post_meta($post_id, self::META_KEY, wp_slash($old));
    }
    return true;
  }

  public function flush() {
    $pending = $this->pending;
    $this->pending = [];
    foreach ($pending as $post_id => $change) {
      if (!$this->content_post($post_id)) continue;
      $after = $this->snapshot($post_id);
      // Failed writes, no-op saves and rolled-back imports produce no new revision.
      if ($after === $change['before']) continue;
      $saved = $this->append($post_id, $change['before'], $change['user'], 'before');
      if (!is_wp_error($saved)) $this->append($post_id, $after, $change['user'], 'save');
    }
  }

  public function translation_meta_keys($keys, $sync) {
    return array_values(array_filter((array) $keys, function($key) {
      return $key !== self::META_KEY && !($this->restoring && $this->is_field_key($key));
    }));
  }

  private function find($post_id, $revision_id) {
    foreach ($this->history($post_id) as $entry) {
      if ($entry['id'] === $revision_id) return $entry;
    }
    return null;
  }

  private function authorize($post_id) {
    if (!$this->content_post($post_id) || !current_user_can('edit_post', $post_id)) {
      return new \WP_Error('cff_revision_forbidden', __('You cannot access revisions for this content.', 'cff'), ['status' => 403]);
    }
    return true;
  }

  /** Replace the complete CFF state, including fields absent from the selected version. */
  private function write_snapshot($post_id, $target) {
    foreach (array_unique(array_merge(array_keys($this->snapshot($post_id)), array_keys($target))) as $key) {
      if (!$this->is_field_key($key)) continue;
      $values = $target[$key] ?? [];
      if (get_post_meta($post_id, $key, false) === $values) continue;
      delete_post_meta($post_id, $key);
      if (metadata_exists('post', $post_id, $key)) return false;
      foreach ($values as $value) {
        if (!add_post_meta($post_id, $key, wp_slash($value))) return false;
      }
    }
    return $this->snapshot($post_id) === $target;
  }

  public function restore($post_id, $revision_id, $expected_hash, $fields = null) {
    $allowed = $this->authorize($post_id);
    if (is_wp_error($allowed)) return $allowed;
    $entry = $this->find($post_id, $revision_id);
    if (!$entry) return new \WP_Error('cff_revision_missing', __('Revision not found for this content.', 'cff'), ['status' => 404]);
    if (function_exists('wp_check_post_lock') && wp_check_post_lock($post_id)) {
      return new \WP_Error('cff_revision_locked', __('Another user is editing this content. Try again after they finish.', 'cff'), ['status' => 409]);
    }
    $current = $this->snapshot($post_id);
    if (!is_string($expected_hash) || !hash_equals($this->fingerprint($current), $expected_hash)) {
      return new \WP_Error('cff_revision_stale', __('Saved values have changed. Compare the revision again before restoring.', 'cff'), ['status' => 409]);
    }
    $target = $entry['snapshot'];
    if ($fields !== null) {
      $definitions = $this->plugin->get_field_definitions_for_post(get_post($post_id));
      $units = $this->restore_units($entry['snapshot'], $current, $definitions);
      if (!is_array($fields) || !$fields || count($fields) > count($units)) return $this->invalid_fields();
      foreach ($fields as $id) {
        if (!is_string($id) || !isset($units[$id])) return $this->invalid_fields();
      }
      $fields = array_values(array_unique($fields));
      $target = $this->partial_snapshot($current, $entry['snapshot'], $units, $fields);
    }
    if ($current === $target) return true;
    // Persist a recovery point before changing any fields.
    $saved = $this->append($post_id, $current, get_current_user_id(), 'before');
    if (is_wp_error($saved)) return $saved;
    $this->restoring = true;
    try {
      if (!$this->write_snapshot($post_id, $target)) {
        $rolled_back = $this->write_snapshot($post_id, $current);
        return new \WP_Error('cff_revision_restore', $rolled_back
          ? __('Restore failed. The previous CFF values have been preserved.', 'cff')
          : __('Restore failed. Use the saved recovery revision to recover the CFF values.', 'cff'), ['status' => 500]);
      }
      $saved = $this->append($post_id, $target, get_current_user_id(), 'restore', $fields ?: []);
      if (is_wp_error($saved)) {
        $this->write_snapshot($post_id, $current);
        return $saved;
      }
      unset($this->pending[$post_id]);
      return true;
    } finally {
      $this->restoring = false;
    }
  }

  private function invalid_fields() {
    return new \WP_Error('cff_revision_fields', __('Choose valid fields or sections from this content revision.', 'cff'), ['status' => 400]);
  }

  private function restore_id($name) {
    if (in_array($name, ['hidden_sections', 'hidden_sections_local'], true)) return 'meta:visibility';
    if (strpos($name, 'group_field_order') === 0) return 'meta:order';
    return 'field:' . $name;
  }

  /** Each root field is a unit: nested row/layout structures must travel together. */
  private function restore_units($saved, $current, $definitions) {
    $units = [];
    foreach (array_unique(array_merge(array_keys($saved), array_keys($current))) as $key) {
      if (!$this->is_field_key($key)) continue;
      $name = substr($key, 5);
      $id = $this->restore_id($name);
      if (!isset($units[$id])) {
        $label = $id === 'meta:visibility' ? __('Section visibility', 'cff') : ($id === 'meta:order' ? __('Field order', 'cff')
          : ($definitions[$name]['label'] ?? ucwords(str_replace('_', ' ', $name))));
        $units[$id] = ['id' => $id, 'label' => $label, 'keys' => [], 'name' => $name];
      }
      $units[$id]['keys'][] = $key;
    }
    // Legacy media URL companions belong to the selected media field, not a separate section.
    foreach ($definitions as $name => $definition) {
      if (!in_array($definition['type'] ?? '', ['image', 'file'], true) || isset($definitions[$name . '_url'])) continue;
      $id = 'field:' . $name;
      $url_id = $id . '_url';
      if (isset($units[$url_id])) {
        if (!isset($units[$id])) $units[$id] = ['id' => $id, 'label' => $definition['label'] ?? $name, 'keys' => ['_cff_' . $name], 'name' => $name];
        $units[$id]['keys'] = array_values(array_unique(array_merge($units[$id]['keys'], $units[$url_id]['keys'])));
        unset($units[$url_id]);
      }
    }
    return $units;
  }

  private function partial_snapshot($current, $saved, $units, $fields) {
    $target = $current;
    foreach ($fields as $id) {
      foreach ($units[$id]['keys'] as $key) {
        if (array_key_exists($key, $saved)) $target[$key] = $saved[$key];
        else unset($target[$key]);
      }
    }
    // A section's own hide switch follows it. Other sections and ordering remain intact.
    $section_fields = array_filter($fields, function($id) { return strpos($id, 'field:') === 0; });
    if ($section_fields && !in_array('meta:visibility', $fields, true)) {
      $hidden = $current['_cff_hidden_sections'][0] ?? [];
      $old_hidden = $saved['_cff_hidden_sections'][0] ?? [];
      $hidden = is_array($hidden) ? $hidden : [];
      $old_hidden = is_array($old_hidden) ? $old_hidden : [];
      foreach ($fields as $id) {
        if (strpos($id, 'field:') !== 0) continue;
        $name = $units[$id]['name'];
        if (array_key_exists($name, $old_hidden)) $hidden[$name] = $old_hidden[$name];
        else unset($hidden[$name]);
      }
      if ($hidden) {
        $target['_cff_hidden_sections'] = [$hidden];
        if (!isset($target['_cff_hidden_sections_local']) && isset($saved['_cff_hidden_sections_local'])) {
          $target['_cff_hidden_sections_local'] = $saved['_cff_hidden_sections_local'];
        }
      } else {
        unset($target['_cff_hidden_sections'], $target['_cff_hidden_sections_local']);
      }
    }
    ksort($target);
    return $target;
  }

  public function compare($post_id, $revision_id, $compare_id = '') {
    $entry = $this->find($post_id, $revision_id);
    $other = $compare_id !== '' ? $this->find($post_id, $compare_id) : null;
    if (!$entry || ($compare_id !== '' && !$other)) {
      return new \WP_Error('cff_revision_missing', __('Revision not found for this content.', 'cff'), ['status' => 404]);
    }
    $current = $this->snapshot($post_id);
    $right = $other ? $other['snapshot'] : $current;
    $changes = [];
    foreach (array_unique(array_merge(array_keys($entry['snapshot']), array_keys($right))) as $key) {
      $left_values = $entry['snapshot'][$key] ?? [];
      $right_values = $right[$key] ?? [];
      $this->diff(substr($key, 5), count($left_values) === 1 ? $left_values[0] : $left_values,
        count($right_values) === 1 ? $right_values[0] : $right_values, (bool) $left_values, (bool) $right_values, $changes);
    }
    $definitions = $this->plugin->get_field_definitions_for_post(get_post($post_id));
    foreach ($changes as &$change) {
      preg_match('/^([^.[\]]+)/', $change['field'], $root);
      $name = $root[1] ?? $change['field'];
      $change['restore_id'] = $this->restore_id($name);
      if ($name === 'hidden_sections' || $name === 'hidden_sections_local') {
        $change['label'] = __('Section visibility', 'cff');
      } elseif (strpos($name, 'group_field_order') === 0) {
        $change['label'] = __('Field order', 'cff');
      } else {
        $change['label'] = $definitions[$name]['label'] ?? ucwords(str_replace('_', ' ', $name));
      }
      $change['diff_html'] = $this->render_diff($change);
    }
    unset($change);
    $choices = [];
    if ($other) {
      $units = $this->restore_units($other['snapshot'], $current, $definitions);
      foreach ($units as $id => $unit) {
        if ($this->partial_snapshot($current, $other['snapshot'], $units, [$id]) !== $current) {
          $choices[] = ['id' => $id, 'label' => $unit['label']];
        }
      }
    }
    return ['changes' => $changes, 'current_hash' => $this->fingerprint($current), 'can_restore' => $current !== $entry['snapshot'],
      'target_can_restore' => $other && $current !== $other['snapshot'], 'restore_fields' => $choices];
  }

  private function display_value($value, $exists) {
    if (!$exists) return __('(not saved)', 'cff');
    return is_string($value) ? ($value === '' ? '""' : $value) : wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /** Reuse WordPress's line/word diff renderer; field HTML remains escaped text. */
  private function render_diff($change) {
    $left = $this->display_value($change['revision'], $change['revision_exists']);
    $right = $this->display_value($change['comparison'], $change['comparison_exists']);
    if (!class_exists('WP_Text_Diff_Renderer_Table', false)) require_once ABSPATH . WPINC . '/wp-diff.php';
    $body = '';
    // Large JSON/WYSIWYG values still display completely without quadratic word matching.
    if (strlen($left) + strlen($right) < 100000 && substr_count($left . $right, "\n") < 400) {
      $diff = new \Text_Diff('auto', [explode("\n", $left), explode("\n", $right)]);
      $renderer = new \WP_Text_Diff_Renderer_Table(['show_split_view' => true]);
      $body = $renderer->render($diff);
    }
    if ($body === '') {
      // Distinguish typed changes (e.g. 0 vs "0") even when their displayed text matches.
      if ($left === $right) {
        $left = gettype($change['revision']) . ': ' . $left;
        $right = gettype($change['comparison']) . ': ' . $right;
      }
      $body = '<tr><td class="diff-deletedline"><span class="cff-diff-marker" aria-hidden="true">−</span>' . esc_html($left) . '</td>'
        . '<td class="diff-addedline"><span class="cff-diff-marker" aria-hidden="true">+</span>' . esc_html($right) . '</td></tr>';
    }
    return '<table class="diff is-split-view"><caption class="screen-reader-text">' . esc_html($change['field']) . '</caption>'
      . '<thead><tr><th scope="col">' . esc_html__('Removed', 'cff') . '</th><th scope="col">' . esc_html__('Added', 'cff') . '</th></tr></thead>'
      . '<tbody>' . $body . '</tbody></table>';
  }

  private function summary($entry, $current = false) {
    $user = get_userdata($entry['user']);
    $name = $user ? $user->display_name : ($entry['user_name'] ?? '');
    $timestamp = strtotime($entry['created_gmt'] . ' UTC');
    return [
      'id' => $entry['id'], 'user_id' => (int) $entry['user'],
      'user' => $name !== '' ? $name : __('Unknown user', 'cff'),
      'author_known' => $entry['kind'] !== 'before' && $entry['user'] > 0,
      'avatar' => get_avatar_url($entry['user'], ['size' => 48, 'default' => 'mystery', 'force_default' => $entry['kind'] === 'before']),
      'date' => get_date_from_gmt($entry['created_gmt'], 'j M Y @ H:i:s T'),
      'date_long' => get_date_from_gmt($entry['created_gmt'], 'F j, Y @ H:i:s'),
      'local_date' => get_date_from_gmt($entry['created_gmt'], 'Y-m-d'),
      'datetime' => gmdate('Y-m-d\TH:i:s\Z', $timestamp),
      'relative' => sprintf(__('%s ago', 'cff'), human_time_diff($timestamp, time())),
      'kind' => $entry['kind'], 'current' => $current,
      'restored_fields' => $entry['restored_fields'] ?? [],
    ];
  }

  private function diff($path, $left, $right, $left_exists, $right_exists, &$changes, $depth = 0) {
    if ($left_exists === $right_exists && $left === $right) return;
    if ($left_exists && $right_exists && is_array($left) && is_array($right) && $depth < 12 && ($left || $right)) {
      foreach (array_unique(array_merge(array_keys($left), array_keys($right))) as $key) {
        if ($key === '__cff_row_id') continue;
        $child = $path . (is_int($key) ? '[' . $key . ']' : '.' . $key);
        $this->diff($child, $left[$key] ?? null, $right[$key] ?? null, array_key_exists($key, $left), array_key_exists($key, $right), $changes, $depth + 1);
      }
      return;
    }
    $changes[] = ['field' => $path, 'revision' => $left, 'comparison' => $right, 'revision_exists' => $left_exists, 'comparison_exists' => $right_exists];
  }

  public function meta_box($post_type, $post) {
    if (!$post || !$this->content_post($post->ID) || !current_user_can('edit_post', $post->ID)) return;
    if (!$this->plugin->get_field_definitions_for_post($post) && !$this->history($post->ID)) return;
    add_meta_box('cff-content-revisions', __('CFF Revision History', 'cff'), [$this, 'render_meta_box'], $post_type, 'side', 'default', ['__block_editor_compatible_meta_box' => true]);
  }

  public function render_meta_box($post) {
    echo '<div class="cff-revisions" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr(wp_create_nonce('cff_content_revisions_' . $post->ID)) . '">';
    echo '<p>' . esc_html(sprintf(__('History for this content only (ID %d). Keeps the latest 20 versions of CFF values, field order and section visibility.', 'cff'), $post->ID)) . '</p>';
    echo '<a class="button cff-revisions-open" href="' . esc_url($this->page_url($post->ID)) . '">' . esc_html__('View revisions', 'cff') . '</a>';
    echo '<p class="cff-revisions-error" role="alert"></p></div>';
  }

  public function page_url($post_id) {
    return add_query_arg(['page' => 'cff-content-revisions', 'post_id' => (int) $post_id], admin_url('admin.php'));
  }

  public function register_page() {
    // An editor-only destination with no central history menu. Authorization is per post below.
    add_submenu_page('', __('CFF Revisions', 'cff'), __('CFF Revisions', 'cff'), 'read', 'cff-content-revisions', [$this, 'page']);
  }

  public function page() {
    $post_id = isset($_GET['post_id']) && is_scalar($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    $allowed = $this->authorize($post_id);
    if (is_wp_error($allowed)) wp_die(esc_html($allowed->get_error_message()), '', ['response' => 403]);
    $post = get_post($post_id);
    $edit_url = get_edit_post_link($post_id, 'raw');
    include __DIR__ . '/admin/views/content-revisions.php';
  }

  public function assets($hook) {
    $is_page = isset($_GET['page']) && $_GET['page'] === 'cff-content-revisions';
    if (!$is_page && !in_array($hook, ['post.php', 'post-new.php'], true)) return;
    wp_enqueue_style('cff-content-revisions', CFFP_URL . 'assets/content-revisions.css', [], CFFP_VERSION);
    wp_enqueue_script('cff-content-revisions', CFFP_URL . 'assets/js/content-revisions.js', ['jquery', 'wp-i18n'], CFFP_VERSION, true);
    wp_localize_script('cff-content-revisions', 'cffContentRevisions', ['ajaxUrl' => admin_url('admin-ajax.php')]);
  }

  public function ajax() {
    $post_id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
    if (!$post_id || !wp_verify_nonce($nonce, 'cff_content_revisions_' . $post_id)) {
      wp_send_json_error(['message' => __('Invalid revision request. Reload the editor and try again.', 'cff')], 403);
    }
    $allowed = $this->authorize($post_id);
    if (is_wp_error($allowed)) $this->send_error($allowed);
    $mode = isset($_POST['mode']) && is_string($_POST['mode']) ? sanitize_key($_POST['mode']) : '';
    $revision_id = isset($_POST['revision_id']) && is_string($_POST['revision_id']) ? wp_unslash($_POST['revision_id']) : '';
    if ($mode === 'history') {
      $items = [];
      $history = $this->history($post_id);
      $latest = $history ? end($history) : null;
      $current = $latest && $latest['snapshot'] === $this->snapshot($post_id);
      foreach (array_reverse($history) as $entry) {
        $items[] = $this->summary($entry, $current && $entry['id'] === $latest['id']);
      }
      wp_send_json_success(['items' => $items, 'current_is_recorded' => (bool) $current]);
    }
    if ($mode === 'compare') {
      $compare_id = isset($_POST['compare_id']) && is_string($_POST['compare_id']) ? wp_unslash($_POST['compare_id']) : '';
      $result = $this->compare($post_id, $revision_id, $compare_id);
    } elseif ($mode === 'restore') {
      $hash = isset($_POST['current_hash']) && is_string($_POST['current_hash']) ? wp_unslash($_POST['current_hash']) : '';
      // A supplied empty/malformed selection must never become a full restore.
      $fields = array_key_exists('fields', $_POST) ? wp_unslash($_POST['fields']) : null;
      if (array_key_exists('fields', $_POST) && $fields === null) $this->send_error($this->invalid_fields());
      $result = $this->restore($post_id, $revision_id, $hash, $fields);
    } else {
      $result = new \WP_Error('cff_revision_mode', __('Unknown revision action.', 'cff'), ['status' => 400]);
    }
    if (is_wp_error($result)) $this->send_error($result);
    wp_send_json_success($result);
  }

  private function send_error($error) {
    $data = $error->get_error_data();
    wp_send_json_error(['message' => $error->get_error_message()], $data['status'] ?? 500);
  }
}
