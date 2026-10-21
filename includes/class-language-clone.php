<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/** One-time language copies, with optional filling of empty existing data. */
class Language_Clone {
  private $plugin;
  private $running = false;
  const BATCH_SIZE = 5;
  const JOB_TTL = 86400;

  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  public function register() {
    add_action('admin_menu', [$this, 'admin_menu'], 20);
    add_action('admin_enqueue_scripts', [$this, 'assets']);
    add_action('wp_ajax_cff_language_clone_prepare', [$this, 'ajax_prepare']);
    add_action('wp_ajax_cff_language_clone_batch', [$this, 'ajax_batch']);
    // Do not let Polylang's optional continuous sync change the source during a copy.
    add_filter('pll_copy_post_metas', [$this, 'prevent_sync'], PHP_INT_MAX, 2);
    add_filter('pll_copy_taxonomies', [$this, 'prevent_sync'], PHP_INT_MAX, 2);
  }

  public function prevent_sync($keys, $sync) {
    return $this->running && $sync ? [] : $keys;
  }

  public function available() {
    foreach (['pll_languages_list', 'pll_is_translated_post_type', 'pll_get_post_language',
      'pll_get_post', 'pll_get_post_translations', 'pll_set_post_language', 'pll_save_post_translations',
      'pll_is_translated_taxonomy', 'pll_get_term', 'pll_get_term_language', 'pll_set_term_language',
      'pll_get_term_translations', 'pll_save_term_translations'] as $function) {
      if (!function_exists($function)) return false;
    }
    return true;
  }

  public function admin_menu() {
    if (!$this->available()) return;
    $languages = pll_languages_list(['hide_empty' => false, 'fields' => 'slug']);
    if (!is_array($languages) || count($languages) < 2) return;
    add_submenu_page('cff', __('Language Clone', 'cff'), __('Language Clone', 'cff'),
      Plugin::admin_capability(), 'cff-language-clone', [$this, 'page']);
  }

  public function assets($hook) {
    if (strpos((string) $hook, 'cff-language-clone') === false) return;
    wp_enqueue_style('cff-language-clone', CFFP_URL . 'assets/language-clone.css', [], CFFP_VERSION);
    wp_enqueue_script('cff-language-clone', CFFP_URL . 'assets/js/language-clone.js', [], CFFP_VERSION, true);
    wp_localize_script('cff-language-clone', 'CFFLanguageClone', [
      'ajax' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('cff_language_clone'),
      'preparing' => __('Preparing preview…', 'cff'),
      'ready' => __('Ready to clone. Review the summary, then click Start clone.', 'cff'),
      'working' => __('Cloning content… Keep this page open.', 'cff'),
      'finalizing' => __('Resolving hierarchy and CFF references…', 'cff'),
      'done' => __('Clone completed. New translations keep the source status and dates; existing translations keep their status.', 'cff'),
      'doneWithErrors' => __('Clone finished with errors. Review the failed items below. Existing translations keep their status; new translations keep the source status and dates.', 'cff'),
      'failed' => __('The request failed. Retry to continue this job.', 'cff'),
      'empty' => __('No source content matches this selection.', 'cff'),
      'retry' => __('Retry / Continue', 'cff'),
      'toFill' => __('Existing translations to fill', 'cff'),
      'filled' => __('Filled existing translations', 'cff'),
    ]);
  }

  public function options() {
    return [
      'title' => __('Title', 'cff'),
      'content' => __('Main content / block editor content', 'cff'),
      'excerpt' => __('Excerpt', 'cff'),
      'cff' => __('All CFF fields, including groups, repeaters, flexible layouts and section settings', 'cff'),
      'meta' => __('Other custom meta, including page template and SEO fields', 'cff'),
      'thumbnail' => __('Featured image', 'cff'),
      'terms' => __('Categories, tags and custom taxonomies, including term fields', 'cff'),
      'hierarchy' => __('Parent / child hierarchy and menu order', 'cff'),
    ];
  }

  private function post_types() {
    $types = [];
    if (!$this->available()) return $types;
    foreach (get_post_types(['show_ui' => true], 'objects') as $name => $type) {
      if (in_array($name, ['attachment', 'cff_group', 'cff_options', 'nav_menu_item'], true)) continue;
      if (pll_is_translated_post_type($name) && current_user_can($type->cap->create_posts)) {
        $types[$name] = $type;
      }
    }
    return $types;
  }

  public function page() {
    if (!current_user_can(Plugin::admin_capability())) return;
    echo '<div class="wrap tk-wrap cff-admin cff-tools-page cff-language-clone-page">';
    cff_render_header_branding();
    cff_render_page_hero(__('Language Clone', 'cff'),
      __('Copy content from a source language into a new target language with Polylang.', 'cff'), 'dashicons-translation');
    if (!$this->available()) {
      echo '<div class="notice notice-warning inline"><p>' . esc_html__('Activate Polylang to use Language Clone.', 'cff') . '</p></div></div>';
      return;
    }
    $languages = pll_languages_list(['hide_empty' => false, 'fields' => 'slug']);
    $names = pll_languages_list(['hide_empty' => false, 'fields' => 'name']);
    if (count($languages) < 2) {
      echo '<div class="notice notice-warning inline"><p>' . esc_html__('Add a target language in Languages before cloning content.', 'cff') . '</p></div></div>';
      return;
    }
    echo '<div class="tk-card"><form id="cff-language-clone-form">';
    echo '<p>' . esc_html__('New translations keep the source publication status and dates, including scheduled publication. Published source content creates published translations. Text is copied as-is so you can translate it afterward. Media files are reused.', 'cff') . '</p>';
    foreach (['source' => __('Source Language', 'cff'), 'target' => __('Target Language', 'cff')] as $key => $label) {
      echo '<div class="cff-tools-field"><label for="cff-clone-' . esc_attr($key) . '">' . esc_html($label) . '</label>';
      echo '<select required id="cff-clone-' . esc_attr($key) . '" name="' . esc_attr($key) . '"><option value="">' . esc_html__('Select language', 'cff') . '</option>';
      foreach ($languages as $index => $slug) {
        echo '<option value="' . esc_attr($slug) . '">' . esc_html(($names[$index] ?? $slug) . ' (' . $slug . ')') . '</option>';
      }
      echo '</select></div>';
    }
    echo '<fieldset class="cff-tools-field"><legend><strong>' . esc_html__('Post types', 'cff') . '</strong></legend>';
    foreach ($this->post_types() as $name => $type) {
      echo '<p><label><input type="checkbox" name="post_types[]" value="' . esc_attr($name) . '" checked> ' . esc_html($type->labels->name) . '</label></p>';
    }
    echo '<p class="description">' . esc_html__('All matching published, draft, pending, private and scheduled content is included. Only post types enabled in Polylang are listed.', 'cff') . '</p></fieldset>';
    echo '<fieldset class="cff-tools-field"><legend><strong>' . esc_html__('What to clone', 'cff') . '</strong></legend>';
    foreach ($this->options() as $key => $label) {
      echo '<p><label><input type="checkbox" name="parts[]" value="' . esc_attr($key) . '" checked> ' . esc_html($label) . '</label></p>';
    }
    echo '</fieldset><p><label><input type="checkbox" name="fill_existing" value="1" checked> ' . esc_html__('Fill empty selected data in existing translations', 'cff') . '</label></p>';
    echo '<p class="description">' . esc_html__('Existing translations keep their ID, URL, status and filled values. Only empty selected fields are filled; populated groups and repeater/flexible layouts are preserved as a whole. Uncheck this option to skip all existing translations.', 'cff') . '</p>';
    echo '<p class="description">' . esc_html__('Missing translated taxonomy terms are created when taxonomy copying is selected. Shared CFF field definitions and Global Settings remain shared. Navigation menus, comments and media library duplication are not included.', 'cff') . '</p>';
    echo '<p><button class="button button-primary" type="submit" id="cff-clone-preview">' . esc_html__('Preview clone', 'cff') . '</button> ';
    echo '<button class="button button-primary" type="button" id="cff-clone-start" hidden>' . esc_html__('Start clone', 'cff') . '</button></p></form></div>';
    echo '<div class="tk-card" id="cff-clone-result" hidden><p id="cff-clone-message" role="status" aria-live="polite"></p>';
    echo '<dl id="cff-clone-summary">';
    foreach (['total' => __('Source content', 'cff'), 'created' => __('Created translations', 'cff'), 'updated' => __('Filled existing translations', 'cff'), 'skipped' => __('Unchanged / skipped', 'cff'), 'failed' => __('Failed items', 'cff')] as $key => $label) {
      echo '<dt id="cff-clone-' . esc_attr($key) . '-label">' . esc_html($label) . '</dt><dd id="cff-clone-' . esc_attr($key) . '">0</dd>';
    }
    echo '</dl><progress id="cff-clone-progress" max="100" value="0" style="width:100%"></progress>';
    echo '<ul id="cff-clone-errors"></ul><p id="cff-clone-links"></p></div></div>';
  }

  private function authorize() {
    check_ajax_referer('cff_language_clone', 'nonce');
    if (!current_user_can(Plugin::admin_capability())) {
      wp_send_json_error(['message' => __('You cannot clone languages.', 'cff')], 403);
    }
    if (!$this->available()) {
      wp_send_json_error(['message' => __('Polylang is not available.', 'cff')], 400);
    }
  }

  public function validate_selection($input) {
    $source = sanitize_key(is_string($input['source'] ?? null) ? $input['source'] : '');
    $target = sanitize_key(is_string($input['target'] ?? null) ? $input['target'] : '');
    $languages = pll_languages_list(['hide_empty' => false, 'fields' => 'slug']);
    if (!$source || $source === $target || !in_array($source, $languages, true) || !in_array($target, $languages, true)) {
      return new \WP_Error('language', __('Select two different, configured Polylang languages.', 'cff'));
    }
    $types = array_values(array_intersect(array_keys($this->post_types()), array_filter((array) ($input['post_types'] ?? []), 'is_string')));
    $parts = array_values(array_intersect(array_keys($this->options()), array_filter((array) ($input['parts'] ?? []), 'is_string')));
    if (!$types || !$parts) return new \WP_Error('selection', __('Select at least one post type and one item to clone.', 'cff'));
    $fill_existing = isset($input['fill_existing']) && in_array($input['fill_existing'], ['1', 1, true], true);
    return compact('source', 'target', 'types', 'parts', 'fill_existing');
  }

  public function ajax_prepare() {
    $this->authorize();
    $selection = $this->validate_selection(wp_unslash($_POST));
    if (is_wp_error($selection)) wp_send_json_error(['message' => $selection->get_error_message()], 400);
    // Snapshot IDs so new translations and language filter changes cannot shift pagination.
    $ids = get_posts([
      'post_type' => $selection['types'], 'lang' => $selection['source'],
      'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
      'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
      'suppress_filters' => false,
    ]);
    $job = $selection + [
      'ids' => array_map('absint', $ids), 'cursor' => 0, 'phase' => 'clone',
      'created' => [], 'updated' => [], 'updated_details' => [], 'skipped' => 0, 'failed' => 0, 'errors' => [],
    ];
    $token = wp_generate_uuid4();
    set_transient($this->job_key($token), $job, self::JOB_TTL);
    $result = $this->summary($job);
    $result['job'] = $token;
    $result['existing'] = 0;
    $result['fillable'] = 0;
    foreach ($ids as $id) {
      $target_id = pll_get_post($id, $selection['target']);
      if (!$target_id) continue;
      if ($selection['fill_existing'] && $this->existing_plan(get_post($id), get_post($target_id), $selection)) {
        $result['fillable']++;
      } else {
        $result['existing']++;
      }
    }
    wp_send_json_success($result);
  }

  private function job_key($token) {
    return 'cff_language_clone_' . get_current_user_id() . '_' . $token;
  }

  public function ajax_batch() {
    $this->authorize();
    $token = sanitize_key(is_string($_POST['job'] ?? null) ? $_POST['job'] : '');
    $job = get_transient($this->job_key($token));
    if (!$token || !is_array($job)) wp_send_json_error(['message' => __('This clone job has expired. Prepare a new preview.', 'cff')], 404);
    $valid = $this->validate_selection(['source' => $job['source'], 'target' => $job['target'], 'post_types' => $job['types'], 'parts' => $job['parts'], 'fill_existing' => $job['fill_existing'] ?? false]);
    if (is_wp_error($valid)) wp_send_json_error(['message' => $valid->get_error_message()], 400);
    // Serialize all clone jobs for a language pair, including jobs opened in other tabs/users.
    $lock = 'cff_clone_lock_' . md5($job['source'] . ':' . $job['target']);
    $expires = (int) get_option($lock, 0);
    if ($expires && $expires < time()) delete_option($lock);
    if (!add_option($lock, time() + 300, '', false)) {
      wp_send_json_error(['message' => __('Another clone request is running for these languages. Retry shortly.', 'cff')], 409);
    }
    $this->running = true;
    try {
      // Reload after acquiring the lock, so repeated requests use the latest cursor.
      $job = get_transient($this->job_key($token));
      $this->process_batch($job, function($state) use ($token) {
        set_transient($this->job_key($token), $state, self::JOB_TTL);
      });
      set_transient($this->job_key($token), $job, self::JOB_TTL);
      $result = $this->summary($job);
    } finally {
      $this->running = false;
      delete_option($lock);
    }
    wp_send_json_success($result);
  }

  public function process_batch(&$job, $checkpoint = null) {
    if ($job['phase'] === 'done') return;
    $updated = $job['updated'] ?? [];
    $ids = $job['phase'] === 'clone' ? $job['ids'] : array_keys($job['created'] + $updated);
    foreach (array_slice($ids, $job['cursor'], self::BATCH_SIZE) as $source_id) {
      try {
        if ($job['phase'] === 'clone') {
          $result = $this->clone_post($source_id, $job);
          if (is_wp_error($result)) {
            $job['failed']++;
            $this->record_error($job, $source_id, $result->get_error_message());
          } elseif (is_array($result)) {
            $job['updated'][$source_id] = $result['id'];
            $job['updated_details'][$source_id] = $result;
          } elseif ($result) {
            $job['created'][$source_id] = $result;
          } else {
            $job['skipped']++;
          }
        } else {
          $this->finalize_post($source_id, ($job['created'] + $updated)[$source_id], $job);
        }
      } catch (\Throwable $error) {
        $job['failed']++;
        $this->record_error($job, $source_id, $error->getMessage());
      }
      $job['cursor']++;
      if ($checkpoint) call_user_func($checkpoint, $job);
    }
    if ($job['cursor'] >= count($ids)) {
      $job['phase'] = $job['phase'] === 'clone' && ($job['created'] || !empty($job['updated'])) ? 'finalize' : 'done';
      $job['cursor'] = 0;
    }
  }

  private function record_error(&$job, $id, $message) {
    if (count($job['errors']) < 50) $job['errors'][] = '#' . absint($id) . ': ' . sanitize_text_field($message);
  }

  private function summary($job) {
    $total = count($job['ids']);
    $updated = $job['updated'] ?? [];
    $affected = $job['created'] + $updated;
    $created = count($affected);
    $progress = $job['phase'] === 'clone' ? ($total ? 80 * $job['cursor'] / $total : 0)
      : ($job['phase'] === 'done' ? 100 : 80 + ($created ? 20 * $job['cursor'] / $created : 20));
    $links = [];
    foreach (array_slice($affected, 0, 5, true) as $target_id) {
      $links[] = ['title' => get_the_title($target_id), 'url' => get_edit_post_link($target_id, 'raw')];
    }
    return ['total' => $total, 'created' => count($job['created']), 'updated' => count($updated), 'skipped' => $job['skipped'],
      'failed' => $job['failed'], 'errors' => $job['errors'], 'phase' => $job['phase'],
      'progress' => round($progress), 'links' => $links];
  }

  private function selected($job, $part) {
    return in_array($part, $job['parts'], true);
  }

  public function clone_post($source_id, $job) {
    $source = get_post($source_id);
    if (!$source || !in_array($source->post_type, $job['types'], true)
      || !pll_is_translated_post_type($source->post_type)
      || !in_array($source->post_status, ['publish', 'draft', 'pending', 'private', 'future'], true)
      || pll_get_post_language($source_id, 'slug') !== $job['source']) {
      return new \WP_Error('source', __('Source content no longer matches the selection.', 'cff'));
    }
    $type = get_post_type_object($source->post_type);
    if (!current_user_can('edit_post', $source_id) || !$type || !current_user_can($type->cap->create_posts)) {
      return new \WP_Error('permission', __('You cannot clone this content.', 'cff'));
    }
    $existing_id = pll_get_post($source_id, $job['target']);
    if ($existing_id) {
      return !empty($job['fill_existing']) ? $this->fill_existing($source, $existing_id, $job) : 0;
    }
    if (in_array($source->post_status, ['publish', 'private', 'future'], true)
      && !current_user_can($type->cap->publish_posts)) {
      return new \WP_Error('permission', __('You cannot publish content of this post type with the source status.', 'cff'));
    }
    $target_id = wp_insert_post(wp_slash([
      'post_type' => $source->post_type, 'post_status' => $source->post_status,
      'post_date' => $source->post_date, 'post_date_gmt' => $source->post_date_gmt,
      'post_title' => $this->selected($job, 'title') ? $source->post_title : sprintf(__('Translation of #%d', 'cff'), $source_id),
      'post_content' => $this->selected($job, 'content') ? $source->post_content : '',
      'post_content_filtered' => $this->selected($job, 'content') ? $source->post_content_filtered : '',
      'post_excerpt' => $this->selected($job, 'excerpt') ? $source->post_excerpt : '',
      'post_name' => $source->post_name, 'post_author' => get_current_user_id(),
      'post_password' => $source->post_password, 'post_parent' => 0,
      'comment_status' => $source->comment_status, 'ping_status' => $source->ping_status,
      'menu_order' => $this->selected($job, 'hierarchy') ? $source->menu_order : 0,
    ]), true);
    if (is_wp_error($target_id)) return $target_id;
    try {
      pll_set_post_language($target_id, $job['target']);
      if (pll_get_post_language($target_id, 'slug') !== $job['target']) throw new \RuntimeException(__('Unable to set target language.', 'cff'));
      foreach ((array) get_post_meta($source_id) as $key => $values) {
        if (!$this->copy_meta_key($key, $job)) continue;
        delete_post_meta($target_id, $key);
        foreach ($values as $value) {
          if (!add_post_meta($target_id, $key, wp_slash(maybe_unserialize($value)))) {
            throw new \RuntimeException(sprintf(__('Unable to copy meta %s.', 'cff'), $key));
          }
        }
      }
      if ($this->selected($job, 'cff') && get_post_meta($source_id, '_cff_hidden_sections', true)) {
        update_post_meta($target_id, '_cff_hidden_sections_local', $job['target']);
      }
      if ($this->selected($job, 'terms')) $this->copy_post_terms($source, $target_id, $job);
      // Read the group again to preserve translations created in other languages meanwhile.
      $translations = pll_get_post_translations($source_id);
      $translations[$job['source']] = $source_id;
      $translations[$job['target']] = $target_id;
      pll_save_post_translations($translations);
      if ((int) pll_get_post($source_id, $job['target']) !== (int) $target_id) throw new \RuntimeException(__('Unable to link the translation.', 'cff'));
      return $target_id;
    } catch (\Throwable $error) {
      wp_delete_post($target_id, true);
      return new \WP_Error('clone', $error->getMessage());
    }
  }

  private function has_data($value) {
    if (is_array($value)) {
      foreach ($value as $item) {
        if ($this->has_data($item)) return true;
      }
      return false;
    }
    // Zero and false are intentional field values, not empty fields.
    return $value !== null && (!is_string($value) || trim($value) !== '');
  }

  private function field_has_data($field, $value) {
    $type = $field['type'] ?? '';
    if (in_array($type, ['image', 'file'], true)) {
      if (is_array($value)) return !empty($value['id']) || $this->has_data($value['url'] ?? '');
      return is_numeric($value) ? absint($value) > 0 : $this->has_data($value);
    }
    if ($type === 'relational' || $type === 'gallery') {
      return (bool) array_filter((array) $value, function($id) { return is_scalar($id) && absint($id) > 0; });
    }
    if ($type === 'link' && is_array($value)) return !empty($value['internal_id']) || $this->has_data($value['url'] ?? '');
    if ($type === 'group' && is_array($value)) return $this->fields_have_data($field['sub_fields'] ?? [], $value);
    if ($type === 'repeater' && is_array($value)) {
      foreach ($value as $row) {
        if (is_array($row) ? $this->fields_have_data($field['sub_fields'] ?? [], $row) : $this->has_data($row)) return true;
      }
      return false;
    }
    if ($type === 'flexible' && is_array($value)) {
      foreach ($value as $row) {
        if (!is_array($row)) {
          if ($this->has_data($row)) return true;
          continue;
        }
        $fields = [];
        foreach ((array) ($field['layouts'] ?? []) as $layout) {
          if (($layout['name'] ?? '') === ($row['layout'] ?? '')) $fields = $layout['sub_fields'] ?? [];
        }
        if ($this->fields_have_data($fields, (array) ($row['fields'] ?? []))) return true;
      }
      return false;
    }
    return $this->has_data($value);
  }

  private function fields_have_data($fields, $values) {
    $definitions = [];
    foreach ((array) $fields as $field) $definitions[$field['name'] ?? ''] = $field;
    foreach ($values as $name => $value) {
      if (in_array($name, ['__cff_row_id', '__cff_present'], true)) continue;
      if ($this->field_has_data($definitions[$name] ?? [], $value)) return true;
    }
    return false;
  }

  private function meta_has_data($key, $values, $definitions) {
    $field = strpos($key, '_cff_') === 0 ? ($definitions[substr($key, 5)] ?? []) : [];
    foreach ($values as $value) {
      if ($key === '_thumbnail_id' ? absint($value) > 0 : $this->field_has_data($field, $value)) return true;
    }
    return false;
  }

  private function existing_plan($source, $target, $job) {
    if (!$source || !$target || $source->post_type !== $target->post_type
      || !in_array($target->post_status, ['publish', 'draft', 'pending', 'private', 'future'], true)
      || pll_get_post_language($target->ID, 'slug') !== $job['target']) return [];
    $plan = ['core' => [], 'meta' => [], 'terms' => [], 'hierarchy' => false];
    foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $part => $field) {
      if ($this->selected($job, $part) && !$this->has_data($target->$field) && $this->has_data($source->$field)) {
        $plan['core'][$field] = $source->$field;
      }
    }
    if (isset($plan['core']['post_content']) && !$this->has_data($target->post_content_filtered)) {
      $plan['core']['post_content_filtered'] = $source->post_content_filtered;
    }
    $definitions = $this->selected($job, 'cff') ? $this->plugin->get_field_definitions_for_post($source) : [];
    foreach ((array) get_post_meta($source->ID) as $key => $values) {
      if (!$this->copy_meta_key($key, $job) || $this->meta_has_data($key, get_post_meta($target->ID, $key, false), $definitions)) continue;
      $values = array_map('maybe_unserialize', (array) $values);
      if ($this->meta_has_data($key, $values, $definitions)) $plan['meta'][$key] = $values;
    }
    if ($this->selected($job, 'hierarchy')) {
      $plan['hierarchy'] = !$target->post_parent && $source->post_parent;
      if (!$target->menu_order && $source->menu_order) $plan['core']['menu_order'] = $source->menu_order;
    }
    if ($this->selected($job, 'terms')) {
      foreach (get_object_taxonomies($source->post_type) as $taxonomy) {
        if (in_array($taxonomy, ['language', 'post_translations', 'term_language', 'term_translations'], true)) continue;
        $target_terms = wp_get_object_terms($target->ID, $taxonomy, ['fields' => 'ids']);
        $source_terms = wp_get_object_terms($source->ID, $taxonomy, ['fields' => 'ids']);
        if (!is_wp_error($target_terms) && !is_wp_error($source_terms) && !$target_terms && $source_terms) $plan['terms'][] = $taxonomy;
      }
    }
    return $plan['core'] || $plan['meta'] || $plan['terms'] || $plan['hierarchy'] ? $plan : [];
  }

  private function fill_existing($source, $target_id, $job) {
    if (!current_user_can('edit_post', $target_id)) {
      return new \WP_Error('permission', __('You cannot edit this existing translation.', 'cff'));
    }
    $plan = $this->existing_plan($source, get_post($target_id), $job);
    if (!$plan) return 0;
    $backup = [];
    try {
      foreach ($plan['meta'] as $key => $values) {
        $backup[$key] = get_post_meta($target_id, $key, false);
        delete_post_meta($target_id, $key);
        foreach ($values as $value) {
          if (!add_post_meta($target_id, $key, wp_slash($value))) throw new \RuntimeException(sprintf(__('Unable to copy meta %s.', 'cff'), $key));
        }
      }
      if (isset($plan['meta']['_cff_hidden_sections'])) {
        $backup['_cff_hidden_sections_local'] = get_post_meta($target_id, '_cff_hidden_sections_local', false);
        update_post_meta($target_id, '_cff_hidden_sections_local', $job['target']);
      }
      if ($plan['terms']) $this->copy_post_terms($source, $target_id, $job, $plan['terms']);
      if ($plan['core']) {
        $result = $this->update_post_without_sync(['ID' => $target_id] + $plan['core']);
        if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
      }
      return ['id' => (int) $target_id, 'meta_keys' => array_keys($plan['meta']), 'hierarchy' => $plan['hierarchy']];
    } catch (\Throwable $error) {
      // An existing translation must never be deleted when filling fails.
      foreach ($backup as $key => $values) {
        delete_post_meta($target_id, $key);
        foreach ($values as $value) add_post_meta($target_id, $key, wp_slash($value));
      }
      foreach ($plan['terms'] as $taxonomy) wp_set_object_terms($target_id, [], $taxonomy);
      return new \WP_Error('fill', $error->getMessage());
    }
  }

  public function copy_meta_key($key, $job) {
    if (in_array($key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_cff_hidden_sections_local',
      'language_id', '_language_id', '_translations', '_post_translations'], true)
      || strpos($key, '_pll') === 0 || strpos($key, 'pll_') === 0 || strpos($key, '_wp_trash_meta_') === 0) return false;
    if ($key === '_thumbnail_id') return $this->selected($job, 'thumbnail');
    return $this->selected($job, strpos($key, '_cff_') === 0 ? 'cff' : 'meta');
  }

  private function copy_post_terms($source, $target_id, $job, $only_taxonomies = null) {
    foreach (get_object_taxonomies($source->post_type) as $taxonomy) {
      if (in_array($taxonomy, ['language', 'post_translations', 'term_language', 'term_translations'], true)) continue;
      if (is_array($only_taxonomies) && !in_array($taxonomy, $only_taxonomies, true)) continue;
      $object = get_taxonomy($taxonomy);
      if (!$object || !current_user_can($object->cap->assign_terms)) throw new \RuntimeException(__('You cannot assign taxonomy terms.', 'cff'));
      $terms = wp_get_object_terms($source->ID, $taxonomy, ['fields' => 'ids']);
      if (is_wp_error($terms)) throw new \RuntimeException($terms->get_error_message());
      $targets = [];
      foreach ($terms as $term_id) $targets[] = $this->clone_term($term_id, $job['target']);
      $result = wp_set_object_terms($target_id, array_map('intval', $targets), $taxonomy);
      if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
    }
  }

  private function clone_term($id, $language, $ancestors = []) {
    $term = get_term($id);
    if (!$term || is_wp_error($term)) throw new \RuntimeException(__('Source term not found.', 'cff'));
    if (!pll_is_translated_taxonomy($term->taxonomy) || pll_get_term_language($id, 'slug') === $language) return (int) $id;
    $existing = pll_get_term($id, $language);
    if ($existing) return (int) $existing;
    $taxonomy = get_taxonomy($term->taxonomy);
    if (!$taxonomy || !current_user_can($taxonomy->cap->manage_terms)) throw new \RuntimeException(__('You cannot create translated taxonomy terms.', 'cff'));
    if (in_array((int) $id, $ancestors, true)) throw new \RuntimeException(__('Circular taxonomy hierarchy.', 'cff'));
    $ancestors[] = (int) $id;
    $parent = $term->parent ? $this->clone_term($term->parent, $language, $ancestors) : 0;
    $inserted = wp_insert_term($term->name, $term->taxonomy, [
      'slug' => $term->slug . '-' . $language, 'description' => $term->description, 'parent' => $parent,
    ]);
    if (is_wp_error($inserted)) throw new \RuntimeException($inserted->get_error_message());
    $target_id = (int) $inserted['term_id'];
    try {
      pll_set_term_language($target_id, $language);
      foreach ((array) get_term_meta($id) as $key => $values) {
        if (!$this->copy_meta_key($key, ['parts' => ['cff', 'meta', 'thumbnail']])) continue;
        foreach ($values as $value) {
          if (!add_term_meta($target_id, $key, wp_slash(maybe_unserialize($value)))) throw new \RuntimeException(__('Unable to copy term fields.', 'cff'));
        }
      }
      $translations = pll_get_term_translations($id);
      $translations[$language] = $target_id;
      pll_save_term_translations($translations);
      if ((int) pll_get_term($id, $language) !== $target_id) throw new \RuntimeException(__('Unable to link term translation.', 'cff'));
    } catch (\Throwable $error) {
      wp_delete_term($target_id, $term->taxonomy);
      throw $error;
    }
    return $target_id;
  }

  private function finalize_post($source_id, $target_id, $job) {
    $source = get_post($source_id);
    if (!$source || !get_post($target_id) || !current_user_can('edit_post', $target_id)
      || (int) pll_get_post($source_id, $job['target']) !== (int) $target_id) {
      throw new \RuntimeException(__('Cannot resolve references for this translation.', 'cff'));
    }
    $existing = $job['updated_details'][$source_id] ?? null;
    if ($this->selected($job, 'hierarchy') && $source->post_parent
      && (!$existing || (!empty($existing['hierarchy']) && !get_post($target_id)->post_parent))) {
      $parent = (int) pll_get_post($source->post_parent, $job['target']);
      $result = $this->update_post_without_sync(['ID' => $target_id, 'post_parent' => $parent]);
      if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
    }
    if (!$this->selected($job, 'cff')) return;
    foreach ($this->plugin->get_field_definitions_for_post($source) as $name => $field) {
      $key = $this->plugin->meta_key($name);
      if ($existing && !in_array($key, $existing['meta_keys'], true)) continue;
      // Use the target copy: do not pull subsequent source edits into an in-progress job.
      $values = get_post_meta($target_id, $key, false);
      $mapped = [];
      foreach ($values as $value) $mapped[] = $this->map_value($field, $value, $job['target']);
      if ($mapped === $values) continue;
      delete_post_meta($target_id, $key);
      foreach ($mapped as $value) {
        if (!add_post_meta($target_id, $key, wp_slash($value))) throw new \RuntimeException(__('Unable to save translated CFF references.', 'cff'));
      }
    }
  }

  private function update_post_without_sync($changes) {
    // Polylang syncs core fields (including dates) separately from meta. Suspend
    // its callbacks only for this copy update, then restore their priorities.
    // Otherwise saving a new translation could change fields on existing translations.
    $removed = [];
    $polylang = function_exists('PLL') ? PLL() : null;
    if (is_object($polylang) && isset($polylang->sync) && is_object($polylang->sync)) {
      foreach ([['pll_save_post', 'pll_save_post', 3],
        ['wp_insert_post_data', 'can_sync_post_data', 2],
        ['wp_insert_post_parent', 'can_sync_post_parent', 3]] as $hook) {
        $callback = [$polylang->sync, $hook[1]];
        $priority = has_filter($hook[0], $callback);
        if ($priority !== false) {
          remove_filter($hook[0], $callback, $priority);
          $removed[] = [$hook[0], $callback, $priority, $hook[2]];
        }
      }
    }
    try {
      return wp_update_post(wp_slash($changes), true);
    } finally {
      foreach ($removed as $hook) add_filter($hook[0], $hook[1], $hook[2], $hook[3]);
    }
  }

  private function map_fields($fields, $values, $language) {
    if (!is_array($values)) return $values;
    foreach ((array) $fields as $field) {
      $name = $field['name'] ?? '';
      if ($name && array_key_exists($name, $values)) $values[$name] = $this->map_value($field, $values[$name], $language);
    }
    return $values;
  }

  private function map_value($field, $value, $language) {
    $type = $field['type'] ?? '';
    if ($type === 'group') return $this->map_fields($field['sub_fields'] ?? [], $value, $language);
    if ($type === 'repeater' && is_array($value)) {
      foreach ($value as $index => $row) $value[$index] = $this->map_fields($field['sub_fields'] ?? [], $row, $language);
    } elseif ($type === 'flexible' && is_array($value)) {
      foreach ($value as $index => $row) {
        foreach ((array) ($field['layouts'] ?? []) as $layout) {
          if (($layout['name'] ?? '') === ($row['layout'] ?? '')) {
            $value[$index]['fields'] = $this->map_fields($layout['sub_fields'] ?? [], $row['fields'] ?? [], $language);
          }
        }
      }
    } elseif ($type === 'relational' && ($field['relational_type'] ?? 'post') !== 'user') {
      $map = function($id) use ($field, $language) {
        $mapped = ($field['relational_type'] ?? 'post') === 'taxonomy' ? pll_get_term(absint($id), $language) : pll_get_post(absint($id), $language);
        return $mapped ? (int) $mapped : $id;
      };
      $value = is_array($value) ? array_map($map, $value) : $map($value);
    } elseif ($type === 'link' && is_array($value) && ($value['mode'] ?? '') === 'internal' && !empty($value['internal_id'])) {
      $mapped = pll_get_post(absint($value['internal_id']), $language);
      if ($mapped) {
        $value['internal_id'] = (int) $mapped;
        $value = $this->plugin->field_sanitizer()->sanitize($field, $value);
      }
    }
    return $value;
  }
}
