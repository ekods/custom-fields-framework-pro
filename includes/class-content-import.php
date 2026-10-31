<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/** JSON content import. Preview is read-only; batches revalidate permissions and schema. */
class Content_Import {
  const MAX_BYTES = 5242880;
  const MAX_ITEMS = 500;
  const BATCH_SIZE = 5;
  const TTL = 86400;
  private $plugin;
  private $running = false;
  private $context_id = 0;

  public function __construct(Plugin $plugin) { $this->plugin = $plugin; }

  public function register() {
    add_action('admin_menu', [$this, 'menu'], 20);
    add_action('admin_enqueue_scripts', [$this, 'assets']);
    add_action('add_meta_boxes', [$this, 'editor_box'], 20, 2);
    add_action('wp_ajax_cff_content_import', [$this, 'ajax']);
    add_action('admin_post_cff_content_import_sample', [$this, 'sample_download']);
    add_action('cff_content_import_expire', [$this, 'expire'], 10, 3);
    add_filter('pll_copy_post_metas', [$this, 'prevent_sync'], PHP_INT_MAX, 2);
    add_filter('pll_copy_taxonomies', [$this, 'prevent_sync'], PHP_INT_MAX, 2);
  }

  public function prevent_sync($keys, $sync) { return $this->running && $sync ? [] : $keys; }

  public function menu() {
    add_submenu_page('cff', __('Import Content', 'cff'), __('Import Content', 'cff'),
      Plugin::admin_capability(), 'cff-import-content', [$this, 'page']);
  }

  public function assets($hook) {
    if (strpos((string) $hook, 'cff-import-content') === false) return;
    wp_enqueue_style('cff-content-import', CFFP_URL . 'assets/content-import.css', [], CFFP_VERSION);
    wp_enqueue_script('cff-content-import', CFFP_URL . 'assets/js/content-import.js', [], CFFP_VERSION, true);
    wp_localize_script('cff-content-import', 'CFFContentImport', [
      'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('cff_content_import'),
      'postId' => isset($_GET['post_id']) ? absint($_GET['post_id']) : 0,
    ]);
  }

  public function destination($id) {
    $post = get_post($this->id($id, 'Destination', false));
    if (!$post || !isset($this->post_types()[$post->post_type]) || !current_user_can('edit_post', $post->ID)
      || in_array($post->post_status, ['trash', 'auto-draft'], true)) throw new \RuntimeException('Save and select an accessible destination page first.');
    return $post;
  }

  public function editor_box($type, $post) {
    if (!$post || !current_user_can(Plugin::admin_capability()) || !isset($this->post_types()[$type])) return;
    if (!$this->plugin->get_field_definitions_for_import($type, $post->ID, get_page_template_slug($post->ID) ?: 'default')) return;
    add_meta_box('cff-import-content', __('Import Content', 'cff'), [$this, 'editor_box_content'], $type, 'side');
  }

  public function editor_box_content($post) {
    if ($post->post_status === 'auto-draft') {
      echo '<p>' . esc_html__('Save this page as a draft before importing content.', 'cff') . '</p>'; return;
    }
    echo '<p>' . esc_html__('Import one JSON file into this page. Save any editor changes before opening the importer.', 'cff') . '</p>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=cff-import-content&post_id=' . (int) $post->ID)) . '">' . esc_html__('Import JSON into this page', 'cff') . '</a>';
  }

  public function page() {
    if (!current_user_can(Plugin::admin_capability())) return;
    $types = $this->post_types();
    $destination = null;
    if (!empty($_GET['post_id'])) {
      try { $destination = $this->destination(absint($_GET['post_id'])); }
      catch (\RuntimeException $error) { wp_die(esc_html($error->getMessage()), '', ['response' => 403]); return; }
    } elseif (empty($_GET['bulk'])) {
      $type = is_string($_GET['post_type'] ?? null) ? sanitize_key($_GET['post_type']) : 'page';
      if (!isset($types[$type])) $type = key($types);
      $search = is_string($_GET['content_search'] ?? null) ? sanitize_text_field(wp_unslash($_GET['content_search'])) : '';
      $candidates = $type ? get_posts(['post_type' => $type, 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
        's' => $search, 'numberposts' => 30, 'suppress_filters' => true, 'orderby' => 'modified', 'order' => 'DESC']) : [];
      $candidates = array_filter($candidates, function($post) { return current_user_can('edit_post', $post->ID); });
      require __DIR__ . '/admin/views/import-content-select.php'; return;
    }
    $sample_url = wp_nonce_url(admin_url('admin-post.php?action=cff_content_import_sample'), 'cff_content_import_sample');
    if ($destination) $sample_url .= '&post_id=' . (int) $destination->ID;
    require __DIR__ . '/admin/views/import-content.php';
  }

  public function post_types() {
    $out = [];
    foreach (get_post_types(['show_ui' => true], 'objects') as $name => $type) {
      if (in_array($name, ['attachment', 'cff_group', 'cff_options', 'revision', 'nav_menu_item'], true)) continue;
      if (current_user_can($type->cap->create_posts) || current_user_can($type->cap->edit_posts)) $out[$name] = $type;
    }
    return $out;
  }

  public static function sample() {
    return ['format' => 'cff-content', 'version' => 1, 'items' => [[
      'post_type' => 'page', 'post_title' => 'Example page', 'post_name' => 'example-import-page',
      'post_content' => '<p>Page content goes here.</p>', 'post_excerpt' => 'Short summary.',
      'post_status' => 'draft', 'cff' => new \stdClass(),
    ]]];
  }

  public function sample_download() {
    if (!current_user_can(Plugin::admin_capability())) wp_die('Forbidden', '', ['response' => 403]);
    check_admin_referer('cff_content_import_sample');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cff-content-example.json"');
    $sample = self::sample();
    if (!empty($_GET['post_id'])) {
      try {
        $post = $this->destination(absint($_GET['post_id']));
        $definitions = $this->plugin->get_field_definitions_for_import($post->post_type, $post->ID, get_page_template_slug($post->ID) ?: 'default');
        $sample = [];
        foreach ($definitions as $name => $field) $sample[$name] = $this->example_value($field);
        $sample = (object) $sample;
      } catch (\RuntimeException $error) { wp_die(esc_html($error->getMessage()), '', ['response' => 403]); return; }
    }
    echo wp_json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function example_value($field) {
    $type = $field['type'] ?? 'text';
    if ($type === 'group') {
      $value = [];
      foreach ($field['sub_fields'] ?? [] as $child) $value[$child['name']] = $this->example_value($child);
      return (object) $value;
    }
    if (in_array($type, ['repeater', 'gallery', 'flexible'], true) || ($type === 'relational' && !empty($field['relational_multiple']))) return [];
    if (in_array($type, ['number', 'image', 'file', 'relational'], true)) return 0;
    if ($type === 'checkbox') return false;
    if ($type === 'link') return ['mode' => 'custom', 'url' => '', 'title' => ''];
    return '';
  }

  public function parse($raw) {
    if (!is_string($raw) || strlen($raw) > self::MAX_BYTES) throw new \RuntimeException('JSON files must be no larger than 5 MB.');
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true, 32);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) throw new \RuntimeException('Invalid JSON: upload an array of content items or an object with an items array.');
    if (isset($data['field_groups']) || isset($data['post_types']) || isset($data['taxonomies'])) {
      // A single content item may assign taxonomies; configuration packages have no content title/ID/items.
      if (isset($data['field_groups']) || isset($data['post_types']) || (!isset($data['items']) && !isset($data['post_title']) && !isset($data['ID']))) {
        throw new \RuntimeException('This is a configuration package. Use Export / Import for field groups, post types and taxonomies.');
      }
    }
    if (isset($data['format']) && $data['format'] !== 'cff-content') throw new \RuntimeException('Unsupported JSON format. Expected cff-content.');
    if (isset($data['version']) && $data['version'] !== 1) throw new \RuntimeException('Unsupported content JSON version. Expected version 1.');
    if (array_key_exists('items', $data)) $items = $data['items'];
    elseif (isset($data['post_title']) || isset($data['ID'])) $items = [$data];
    else $items = $data;
    if (!is_array($items) || !$items || array_keys($items) !== range(0, count($items) - 1)) throw new \RuntimeException('Content items must be a non-empty JSON array.');
    if (count($items) > self::MAX_ITEMS) throw new \RuntimeException('Import at most 500 items per file. Split larger files into smaller batches.');
    foreach ($items as $item) if (!is_array($item) || !$item) throw new \RuntimeException('Each content item must be a JSON object.');
    return $items;
  }

  private function id($value, $label, $allow_zero = true) {
    if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/D', $value))) throw new \RuntimeException($label . ' must be an integer ID.');
    $digits = ltrim((string) $value, '0') ?: '0';
    if (strlen($digits) > strlen((string) PHP_INT_MAX) || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) throw new \RuntimeException($label . ' is outside the supported ID range.');
    if ((int) $value < ($allow_zero ? 0 : 1)) throw new \RuntimeException($label . ' must be a valid ID.');
    return (int) $value;
  }

  private function text($value, $label) {
    if (!is_scalar($value) && $value !== null) throw new \RuntimeException($label . ' must be text.');
    return (string) $value;
  }

  public function validate_options($input) {
    if (($input['scope'] ?? '') === 'page') {
      $post = $this->destination($input['post_id'] ?? 0);
      $policy = $input['policy'] ?? 'fill_empty';
      if (!in_array($policy, ['replace', 'fill_empty'], true)) throw new \RuntimeException('Choose Replace supplied fields or Fill empty fields only.');
      return ['scope' => 'page', 'post_id' => (int) $post->ID, 'post_type' => $post->post_type, 'mode' => 'update', 'allow_publish' => false, 'policy' => $policy];
    }
    $type = is_string($input['post_type'] ?? null) ? sanitize_key($input['post_type']) : '';
    $mode = is_string($input['mode'] ?? null) ? $input['mode'] : 'create';
    if (!isset($this->post_types()[$type])) throw new \RuntimeException('Select an accessible target post type.');
    if (!in_array($mode, ['create', 'update', 'upsert'], true)) throw new \RuntimeException('Invalid import mode.');
    return ['post_type' => $type, 'mode' => $mode, 'allow_publish' => !empty($input['allow_publish'])];
  }

  /** A page file is a field object, never a list of destinations or core post properties. */
  public function parse_page($raw, $options) {
    if (!is_string($raw) || strlen($raw) > self::MAX_BYTES) throw new \RuntimeException('JSON files must be no larger than 5 MB.');
    $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw), true, 32);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !$data || array_keys($data) === range(0, count($data) - 1)) throw new \RuntimeException('Upload one JSON object containing this page\'s CFF fields or sections.');
    if (isset($data['field_groups']) || isset($data['post_types']) || isset($data['format'])) throw new \RuntimeException('Use a page content object, not a configuration or bulk import package.');
    if (count($data) === 1 && isset($data['cff'])) $data = $data['cff'];
    if (!is_array($data) || !$data) throw new \RuntimeException('The JSON has no page field values.');
    return [['ID' => $options['post_id'], 'cff' => $data]];
  }

  private function prepare_item($row, $options) {
    return ($options['scope'] ?? '') === 'page' ? $this->prepare_page_row($row, $options) : $this->prepare_row($row, $options);
  }

  public function prepare_page_row($row, $options) {
    $post = $this->destination($options['post_id']);
    if ((int) ($row['ID'] ?? 0) !== (int) $post->ID) throw new \RuntimeException('The JSON destination does not match the current page.');
    $definitions = $this->plugin->get_field_definitions_for_import($post->post_type, $post->ID, get_page_template_slug($post->ID) ?: 'default');
    $values = []; $mapping = []; $warnings = [];
    foreach ($row['cff'] as $source => $value) {
      if (!is_string($source) || sanitize_key($source) !== $source) throw new \RuntimeException('JSON section names must match CFF field names.');
      $candidates = isset($definitions[$source]) ? [$source] : array_keys(array_filter($definitions, function($field, $name) use ($source) {
        return substr($name, -strlen('_' . $source)) === '_' . $source;
      }, ARRAY_FILTER_USE_BOTH));
      if (count($candidates) !== 1) throw new \RuntimeException(count($candidates) ? 'Ambiguous section ' . $source . '. Use its full CFF field name.' : 'No matching CFF field for section ' . $source . ' on this page/template.');
      $name = $candidates[0];
      if (isset($mapping[$name])) throw new \RuntimeException('Multiple JSON sections map to ' . $name . '. Use one key per field.');
      $mapping[$name] = $source;
      $field = $definitions[$name];
      $key = $this->plugin->meta_key($name);
      $exists = metadata_exists('post', $post->ID, $key);
      $existing = get_post_meta($post->ID, $key, true);
      if ($options['policy'] === 'fill_empty') {
        list($include, $value) = $this->empty_patch($field, $value, $existing, $exists, $name);
        if (!$include) continue;
      }
      list($include, $value) = $this->page_media($field, $value, $existing, $exists, $name, $warnings);
      if ($include) $values[$name] = $value;
    }
    $plan = $this->prepare_row(['ID' => (int) $post->ID, 'cff' => $values], $options);
    $plan['policy'] = $options['policy'];
    $plan['mapping'] = [];
    foreach ($mapping as $name => $source) $plan['mapping'][] = $source . ' → ' . $name;
    $plan['warnings'] = $warnings;
    if (!$values) {
      $plan['action'] = 'skip';
      $plan['message'] = $options['policy'] === 'fill_empty' ? 'No empty fields need changing. Existing values were kept.' : 'No importable values remain. Review the media notes; existing content was kept.';
    } else $plan['message'] = count($values) . ' CFF field(s) to update. ' . ($options['policy'] === 'fill_empty' ? 'Only empty values will be filled.' : 'Supplied values will replace existing values.');
    return $plan;
  }

  private function blank_field($field, $value, $exists) {
    if (!$exists || $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '')) return true;
    $type = $field['type'] ?? 'text';
    if (in_array($type, ['image', 'file', 'relational'], true) && empty($field['relational_multiple'])) return $value === 0 || $value === '0';
    if ($type === 'link' && is_array($value)) return empty($value['url']) && empty($value['internal_id']);
    // Zero numbers and explicit false checkbox values are intentional content.
    return false;
  }

  private function empty_patch($field, $incoming, $existing, $exists, $path) {
    if (($field['type'] ?? '') === 'group' && is_array($incoming) && $incoming) {
      $children = array_column($field['sub_fields'] ?? [], null, 'name'); $patch = [];
      foreach ($incoming as $name => $value) {
        if (!isset($children[$name])) throw new \RuntimeException($path . ': unknown subfield ' . $name . '.');
        $present = is_array($existing) && array_key_exists($name, $existing);
        list($include, $value) = $this->empty_patch($children[$name], $value, $present ? $existing[$name] : null, $present, $path . '.' . $name);
        if ($include) $patch[$name] = $value;
      }
      return [!empty($patch), $patch];
    }
    return [$this->blank_field($field, $existing, $exists), $incoming];
  }

  /** Resolve references without downloading a file or modifying attachment metadata. */
  private function existing_media($value) {
    if (!is_string($value) || preg_match('/^\d+$/D', $value)) return null;
    $url = $value;
    if (function_exists('attachment_url_to_postid') && preg_match('~^https?://~i', $url)) {
      $id = attachment_url_to_postid($url);
      if ($id && current_user_can('read_post', $id)) return (int) $id;
    }
    $path = parse_url($value, PHP_URL_PATH);
    $filename = is_string($path) ? basename(rawurldecode($path)) : '';
    if (!$filename || strpos($filename, '.') === false) return null;
    $matches = [];
    foreach (get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 101, 'suppress_filters' => true,
      'meta_query' => [['key' => '_wp_attached_file', 'value' => $filename, 'compare' => 'LIKE']]]) as $post) {
      if (basename((string) get_post_meta($post->ID, '_wp_attached_file', true)) === $filename && current_user_can('read_post', $post->ID)) $matches[] = (int) $post->ID;
    }
    return count($matches) === 1 ? $matches[0] : null;
  }

  private function page_media($field, $value, $existing, $exists, $path, &$warnings) {
    $type = $field['type'] ?? 'text';
    if (in_array($type, ['image', 'file'], true)) {
      $reference = is_array($value) && empty($value['id']) ? ($value['url'] ?? null) : $value;
      if (is_string($reference) && $reference !== '' && !preg_match('/^\d+$/D', $reference)) {
        $id = $this->existing_media($reference);
        if ($id) return [true, $id];
        $warnings[] = $path . ': no unique accessible media match for ' . $this->short($reference, 120) . '. Existing media kept; no file downloaded.';
        return [$exists, $existing];
      }
      return [true, $value];
    }
    if ($type === 'group' && is_array($value)) {
      $had_values = !empty($value);
      $children = array_column($field['sub_fields'] ?? [], null, 'name');
      foreach ($value as $name => &$child) if (isset($children[$name])) {
        $present = is_array($existing) && array_key_exists($name, $existing);
        list($include, $child) = $this->page_media($children[$name], $child, $present ? $existing[$name] : null, $present, $path . '.' . $name, $warnings);
        if (!$include) unset($value[$name]);
      } unset($child);
      // A group emptied by unresolved media is not an explicit request to clear it.
      if ($had_values && !$value) return [false, []];
    } elseif (in_array($type, ['repeater', 'flexible'], true) && is_array($value)) {
      $layouts = array_column($field['layouts'] ?? [], null, 'name');
      foreach ($value as $index => &$row) {
        if (!is_array($row)) continue;
        $old = is_array($existing[$index] ?? null) ? $existing[$index] : [];
        $nested = ['type' => 'group', 'sub_fields' => $type === 'flexible' ? ($layouts[$row['layout'] ?? '']['sub_fields'] ?? []) : ($field['sub_fields'] ?? [])];
        $incoming = $type === 'flexible' ? ($row['fields'] ?? null) : $row;
        $previous = $type === 'flexible' && ($old['layout'] ?? '') === ($row['layout'] ?? '') ? ($old['fields'] ?? []) : ($type === 'repeater' ? $old : []);
        list($include, $clean) = $this->page_media($nested, $incoming, $previous, !empty($previous), $path . '[' . ($index + 1) . ']', $warnings);
        if ($type === 'flexible') $row['fields'] = $clean; else $row = $clean;
      } unset($row);
    }
    return [true, $value];
  }

  private function resolve_target($row, $type, $language) {
    if (isset($row['ID'])) {
      $post = get_post($this->id($row['ID'], 'ID', false));
      if (!$post || $post->post_type !== $type) throw new \RuntimeException('The destination ID does not exist in the selected post type.');
      return $post;
    }
    if (empty($row['post_name'])) return null;
    $slug = sanitize_title($this->text($row['post_name'], 'post_name'));
    if (!$slug) throw new \RuntimeException('post_name must contain a valid slug.');
    $matches = get_posts(['post_type' => $type, 'name' => $slug, 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
      'numberposts' => 101, 'suppress_filters' => true]);
    if ($language && function_exists('pll_get_post_language')) {
      $matches = array_values(array_filter($matches, function($post) use ($language) { return pll_get_post_language($post->ID, 'slug') === $language; }));
    }
    if (count($matches) > 1) throw new \RuntimeException('This slug matches multiple posts. Supply the destination ID.');
    return $matches ? $matches[0] : null;
  }

  /** Shared by preview and write: field definitions, object IDs and capabilities are never trusted from JSON. */
  public function prepare_row($row, $options) {
    $allowed = ['ID', 'post_type', 'post_title', 'post_name', 'post_content', 'post_excerpt', 'post_status', 'post_parent', 'menu_order',
      'page_template', 'language', 'featured_image', 'cff', 'taxonomies'];
    if (array_diff(array_keys($row), $allowed)) throw new \RuntimeException('Unknown item keys: ' . implode(', ', array_diff(array_keys($row), $allowed)) . '.');
    $type = $options['post_type'];
    if (isset($row['post_type']) && $row['post_type'] !== $type) throw new \RuntimeException('Item post_type does not match the selected target: ' . $type . '.');
    $object = get_post_type_object($type);
    if (!$object || !isset($this->post_types()[$type])) throw new \RuntimeException('Target post type is no longer available.');
    $language = '';
    if (isset($row['language'])) {
      $language = sanitize_key($this->text($row['language'], 'language'));
      if (!function_exists('pll_languages_list') || !function_exists('pll_set_post_language') || !pll_is_translated_post_type($type)
        || !in_array($language, pll_languages_list(['hide_empty' => false, 'fields' => 'slug']), true)) throw new \RuntimeException('Choose an existing Polylang language enabled for this post type.');
    }
    if (!isset($row['ID']) && empty($row['post_name']) && isset($row['post_title'])) $row['post_name'] = sanitize_title($this->text($row['post_title'], 'post_title'));
    $post = $this->resolve_target($row, $type, $language);
    if ($post && in_array($post->post_status, ['trash', 'auto-draft'], true)) throw new \RuntimeException('Trashed or auto-draft content cannot be updated.');
    if ($post && !current_user_can('edit_post', $post->ID)) throw new \RuntimeException('You cannot edit the destination post.');
    if ($post && $language && pll_get_post_language($post->ID, 'slug') !== $language) throw new \RuntimeException('Assign the requested language to the destination post first. Import does not move existing translations.');
    if ($post && $options['mode'] === 'create') return ['action' => 'skip', 'target_id' => (int) $post->ID, 'title' => $post->post_title, 'slug' => $post->post_name, 'message' => 'Already exists; create-only mode skips this item.'];
    if (!$post && $options['mode'] === 'update') throw new \RuntimeException('No destination found. Supply an existing ID or post_name.');
    if (!$post && !current_user_can($object->cap->create_posts)) throw new \RuntimeException('You cannot create this post type.');
    $core = ['post_type' => $type];
    foreach (['post_title', 'post_name', 'post_content', 'post_excerpt'] as $key) {
      if (!array_key_exists($key, $row)) continue;
      $value = $this->text($row[$key], $key);
      $core[$key] = $key === 'post_name' ? sanitize_title($value) : (in_array($key, ['post_content', 'post_excerpt'], true) ? wp_kses_post($value) : sanitize_text_field($value));
      if ($key === 'post_name' && !$core[$key]) throw new \RuntimeException('post_name must contain a valid slug.');
    }
    if (!$post && empty($core['post_title'])) throw new \RuntimeException('New content requires a non-empty post_title.');
    if (!$post && empty($core['post_name'])) $core['post_name'] = sanitize_title($core['post_title']);
    if (!$post && !$core['post_name']) throw new \RuntimeException('Supply a non-empty post_name.');
    $status = $post ? $post->post_status : 'draft';
    if (isset($row['post_status'])) {
      $status = $this->text($row['post_status'], 'post_status');
      if (!in_array($status, ['draft', 'pending', 'private', 'publish'], true)) throw new \RuntimeException('Supported statuses: draft, pending, private, publish.');
      if (in_array($status, ['publish', 'private'], true) && (!$options['allow_publish'] || !current_user_can($object->cap->publish_posts))) throw new \RuntimeException('Publishing/private content requires Allow publishing from JSON and publishing permission.');
      $core['post_status'] = $status;
    } elseif (!$post) $core['post_status'] = 'draft';
    if (isset($row['menu_order'])) {
      if (!is_int($row['menu_order'])) throw new \RuntimeException('menu_order must be an integer.');
      $core['menu_order'] = $row['menu_order'];
    }
    if (isset($row['post_parent'])) {
      $parent_id = $this->id($row['post_parent'], 'post_parent');
      $parent = $parent_id ? get_post($parent_id) : null;
      if ($parent_id && (!$parent || $parent->post_type !== $type || !current_user_can('read_post', $parent_id)
        || ($post && ($parent_id === (int) $post->ID || in_array((int) $post->ID, array_map('intval', get_post_ancestors($parent_id)), true))))) throw new \RuntimeException('post_parent is invalid or would create a circular hierarchy.');
      $core['post_parent'] = $parent_id;
    }
    $template = $post ? (get_page_template_slug($post->ID) ?: 'default') : 'default';
    $meta = [];
    if (array_key_exists('page_template', $row)) {
      $template = $this->text($row['page_template'], 'page_template') ?: 'default';
      $templates = wp_get_theme()->get_page_templates(null, $type);
      if ($template !== 'default' && !array_key_exists($template, $templates)) throw new \RuntimeException('page_template is not registered by the active theme for this post type.');
      $meta['_wp_page_template'] = $template;
    }
    $definitions = $this->plugin->get_field_definitions_for_import($type, $post ? $post->ID : 0, $template);
    $values = $row['cff'] ?? [];
    if (!is_array($values)) throw new \RuntimeException('cff must be an object keyed by field name.');
    foreach ($values as $name => $value) {
      if (!is_string($name) || !isset($definitions[$name])) throw new \RuntimeException('CFF field is not defined for this post type/template: ' . $name . '.');
      $field = $definitions[$name];
      $this->validate_value($field, $value, $name, !$post);
      $clean = $this->plugin->field_sanitizer()->sanitize($field, $value);
      if ($value !== null && $value !== '' && in_array($field['type'] ?? '', ['date_picker', 'datetime_picker', 'color'], true) && $clean === '') throw new \RuntimeException('Invalid value for ' . $name . '.');
      if (($field['type'] ?? '') === 'group' && $post && is_array($clean)) {
        $existing = get_post_meta($post->ID, $this->plugin->meta_key($name), true);
        if (is_array($existing) && $clean !== []) {
          require_once __DIR__ . '/render.php';
          $clean = deep_merge_assoc($existing, $clean);
        }
      }
      $meta[$this->plugin->meta_key($name)] = $clean;
    }
    if (!$post) foreach ($definitions as $name => $field) {
      if (!empty($field['required']) && !array_key_exists($name, $values)) throw new \RuntimeException('Required CFF field missing: ' . $name . '.');
    }
    if (array_key_exists('featured_image', $row)) {
      $id = $this->id($row['featured_image'], 'featured_image');
      if ($id) $this->validate_media($id, true, 'featured_image');
      $meta['_thumbnail_id'] = $id;
    }
    $terms = $row['taxonomies'] ?? [];
    if (!is_array($terms)) throw new \RuntimeException('taxonomies must be an object keyed by taxonomy name.');
    foreach ($terms as $taxonomy => &$ids) {
      $tax = is_string($taxonomy) ? get_taxonomy($taxonomy) : null;
      if (!$tax || in_array($taxonomy, ['language', 'post_translations', 'term_language', 'term_translations'], true)
        || !in_array($taxonomy, get_object_taxonomies($type), true) || !current_user_can($tax->cap->assign_terms)) throw new \RuntimeException('Taxonomy is not assignable for this post type: ' . $taxonomy . '.');
      if (!is_array($ids)) throw new \RuntimeException('Taxonomy values must be arrays of existing term IDs.');
      foreach ($ids as &$id) {
        $id = $this->id($id, $taxonomy, false);
        $term = get_term($id, $taxonomy);
        if (!$term || is_wp_error($term)) throw new \RuntimeException('Term ID does not belong to ' . $taxonomy . ': ' . $id . '.');
      } unset($id);
      $ids = array_values(array_unique($ids));
    } unset($ids);
    return ['action' => $post ? 'update' : 'create', 'target_id' => $post ? (int) $post->ID : 0,
      'title' => $core['post_title'] ?? $post->post_title, 'slug' => $core['post_name'] ?? $post->post_name,
      'core' => $core, 'meta' => $meta, 'terms' => $terms, 'language' => $language, 'status' => $status,
      'schema_hash' => hash('sha256', serialize($definitions)), 'message' => ''];
  }

  private function validate_media($id, $image, $path) {
    $post = get_post($id);
    if (!$post || $post->post_type !== 'attachment' || !current_user_can('read_post', $id)
      || ($image && !wp_attachment_is('image', $id) && !wp_attachment_is('video', $id))) throw new \RuntimeException($path . ' must reference an accessible existing media ID.');
  }

  public function validate_value($field, $value, $path, $new_group = true) {
    if ($value === null || $value === '' || $value === []) {
      if (!empty($field['required'])) throw new \RuntimeException('Required field is empty: ' . $path . '.');
      return;
    }
    $type = $field['type'] ?? 'text';
    if ($type === 'number' && !is_numeric($value)) throw new \RuntimeException($path . ' must be numeric.');
    if (in_array($type, ['date_picker', 'datetime_picker', 'color'], true) && $this->plugin->field_sanitizer()->sanitize($field, $value) === '') throw new \RuntimeException('Invalid value for ' . $path . '.');
    if (in_array($type, ['image', 'file'], true)) {
      $id = $this->id(is_array($value) ? ($value['id'] ?? 0) : $value, $path);
      if (is_array($value) && !empty($value['url'])) throw new \RuntimeException($path . ': use a media ID from this site; remote URL imports are not supported.');
      if ($id) $this->validate_media($id, $type === 'image', $path);
      return;
    }
    if ($type === 'gallery') {
      if (!is_array($value)) throw new \RuntimeException($path . ' must be an array of media IDs.');
      foreach ($value as $id) $this->validate_media($this->id($id, $path, false), false, $path);
      return;
    }
    if ($type === 'relational') {
      if (!empty($field['relational_multiple']) && !is_array($value)) throw new \RuntimeException($path . ' must be an array of IDs.');
      foreach (!empty($field['relational_multiple']) ? $value : [$value] as $id) {
        $id = $this->id($id, $path);
        if (!$id) continue;
        $kind = $field['relational_type'] ?? 'post';
        if ($kind === 'taxonomy') {
          $term = get_term($id, $field['relational_subtype'] ?? 'category');
          if (!$term || is_wp_error($term)) throw new \RuntimeException($path . ': term ID is not in the configured taxonomy.');
        } elseif ($kind === 'user') {
          if (!current_user_can('list_users') || !get_userdata($id)) throw new \RuntimeException($path . ': user ID is unavailable.');
        } else {
          $types = $kind === 'post_and_page' ? ['post', 'page'] : [$kind === 'post_type' ? ($field['relational_subtype'] ?? '') : $kind];
          $target = get_post($id);
          if (!$target || !in_array($target->post_type, $types, true) || in_array($target->post_status, ['trash', 'auto-draft'], true) || !current_user_can('read_post', $id)) throw new \RuntimeException($path . ': post ID is not in the configured relationship source.');
        }
      }
      return;
    }
    if (in_array($type, ['group', 'repeater', 'flexible'], true)) {
      if (!is_array($value)) throw new \RuntimeException($path . ' must contain structured JSON.');
      if ($type !== 'group' && array_keys($value) !== range(0, count($value) - 1)) throw new \RuntimeException($path . ' must be an array of row objects.');
      if ($type === 'repeater' && !empty($field['max']) && count($value) > (int) $field['max']) throw new \RuntimeException($path . ' exceeds the maximum row count.');
      $rows = $type === 'group' ? [$value] : $value;
      foreach ($rows as $index => $row) {
        if (!is_array($row)) throw new \RuntimeException($path . ': each row must be an object.');
        $fields = $field['sub_fields'] ?? [];
        if ($type === 'flexible') {
          $layouts = array_column($field['layouts'] ?? [], null, 'name');
          if (!isset($layouts[$row['layout'] ?? '']) || !is_array($row['fields'] ?? null)) throw new \RuntimeException($path . ': unknown layout or missing fields object.');
          $fields = $layouts[$row['layout']]['sub_fields'] ?? [];
          $row = $row['fields'];
        }
        $indexed = array_column($fields, null, 'name');
        if ($type !== 'group' || $new_group) foreach ($indexed as $name => $subfield) {
          if (!empty($subfield['required']) && !array_key_exists($name, $row)) throw new \RuntimeException('Required field missing: ' . $path . '.' . $name . '.');
        }
        foreach ($row as $name => $nested) {
          if ($name === '__cff_row_id') continue;
          if (!isset($indexed[$name])) throw new \RuntimeException($path . ': unknown subfield ' . $name . '.');
          $this->validate_value($indexed[$name], $nested, $path . '.' . $name, $type === 'group' ? $new_group : true);
        }
      }
      return;
    }
    if ($type === 'choice') {
      $allowed = array_map('strval', array_column($field['choices'] ?? [], 'value'));
      $items = ($field['choice_display'] ?? '') === 'checkbox' ? $value : [$value];
      if (!is_array($items)) throw new \RuntimeException($path . ' must contain an array of choice values.');
      foreach ($items as $item) if (!is_scalar($item) || ($allowed && !in_array((string) $item, $allowed, true))) throw new \RuntimeException($path . ': unknown choice value.');
      return;
    }
    if ($type === 'link') {
      if (!is_array($value)) throw new \RuntimeException($path . ' must be a link object.');
      if (($value['mode'] ?? '') === 'internal') {
        $target = get_post($this->id($value['internal_id'] ?? 0, $path, false));
        if (!$target || !current_user_can('read_post', $target->ID)) throw new \RuntimeException($path . ': internal post is unavailable.');
      }
      return;
    }
    if (!is_scalar($value)) throw new \RuntimeException($path . ' must contain a scalar value.');
  }

  private function key($kind, $owner = null, $context = null) {
    $context = $context ?? $this->context_id;
    return 'cff_content_import_' . $kind . '_' . (int) ($owner ?? get_current_user_id()) . ($context ? '_page_' . (int) $context : '');
  }

  private function lock() {
    global $wpdb;
    $key = $this->key('lock');
    $lease = ['token' => wp_generate_uuid4(), 'expires' => time() + 300];
    if (add_option($key, $lease, '', false)) return $lease;
    $old = get_option($key);
    if (is_array($old) && (int) ($old['expires'] ?? PHP_INT_MAX) < time()) {
      $this->unlock($old);
      if (add_option($key, $lease, '', false)) return $lease;
    }
    throw new \RuntimeException('Another import request is still running. Wait for it to finish before continuing.');
  }

  private function unlock($lease) {
    global $wpdb;
    $key = $this->key('lock');
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize($lease)));
    wp_cache_delete($key, 'options');
  }

  private function save_job($job) {
    update_option($this->key('job'), $job, false);
    // WordPress returns false for unchanged writes; verify actual state instead.
    if (get_option($this->key('job')) !== $job) throw new \RuntimeException('Import progress could not be saved. Stop and review the latest content before continuing.');
  }

  private function load_job($token = null) {
    $job = get_option($this->key('job'), []);
    if ($job && (int) $job['expires'] <= time()) {
      $this->expire(get_current_user_id(), $job['token'], $this->context_id);
      $job = [];
    }
    if ($token !== null && (!$job || !is_string($token) || !hash_equals($job['token'], $token))) throw new \RuntimeException('This import job is expired or belongs to another session. Prepare a new preview.');
    return $job;
  }

  public function expire($owner, $token, $context = 0) {
    $job = get_option($this->key('job', $owner, $context), []);
    if ($job && $job['token'] === $token && (int) $job['expires'] <= time()) {
      delete_option($this->key('payload', $owner, $context));
      delete_option($this->key('job', $owner, $context));
    }
  }

  public function upload($raw, $input) {
    $existing = $this->load_job();
    if ($existing && in_array($existing['phase'], ['preview', 'ready', 'running', 'paused'], true)) throw new \RuntimeException('Cancel or finish the current import before uploading another file.');
    $options = $this->validate_options($input);
    $items = ($options['scope'] ?? '') === 'page' ? $this->parse_page($raw, $options) : $this->parse($raw);
    $job = ['token' => wp_generate_uuid4(), 'owner' => get_current_user_id(), 'options' => $options,
      'phase' => 'preview', 'expires' => time() + self::TTL, 'total' => count($items),
      'preview_cursor' => 0, 'cursor' => 0, 'rows' => [], 'claimed' => []];
    update_option($this->key('payload'), $items, false);
    if (get_option($this->key('payload')) !== $items) throw new \RuntimeException('The JSON payload could not be saved.');
    $this->save_job($job);
    wp_schedule_single_event($job['expires'], 'cff_content_import_expire', [$job['owner'], $job['token'], $this->context_id]);
    return $job;
  }

  private function short($text, $length = 300) {
    return function_exists('mb_strcut') ? mb_strcut((string) $text, 0, $length, 'UTF-8') : wp_check_invalid_utf8(substr((string) $text, 0, $length), true);
  }

  private function snapshot($id, $plan) {
    $post = get_post($id);
    if (!$post) return [];
    $core = [];
    foreach (array_unique(array_merge(array_keys($plan['core']), ['post_status', 'post_modified_gmt'])) as $key) $core[$key] = $post->$key ?? '';
    $meta = [];
    foreach ($plan['meta'] as $key => $value) $meta[$key] = get_post_meta($id, $key, false);
    $terms = [];
    foreach ($plan['terms'] as $taxonomy => $ids) {
      $terms[$taxonomy] = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
      if (is_wp_error($terms[$taxonomy])) throw new \RuntimeException('Could not read existing taxonomy assignments.');
      sort($terms[$taxonomy]);
    }
    return ['core' => $core, 'meta' => $meta, 'terms' => $terms];
  }

  private function values_hash($plan) {
    $without_row_ids = function($value) use (&$without_row_ids) {
      if (!is_array($value)) return $value;
      unset($value['__cff_row_id']);
      return array_map($without_row_ids, $value);
    };
    return hash('sha256', serialize($without_row_ids(array_intersect_key($plan, array_flip(['core', 'meta', 'terms', 'language'])))));
  }

  public function preview_batch($job) {
    if ($job['phase'] !== 'preview') return $job;
    $items = get_option($this->key('payload'), []);
    if (count($items) !== $job['total']) throw new \RuntimeException('The uploaded JSON has expired. Prepare a new import.');
    $deadline = microtime(true) + 3;
    for ($count = 0; $job['preview_cursor'] < $job['total'] && $count < self::BATCH_SIZE; $count++) {
      $index = $job['preview_cursor'];
      $row = ['index' => $index + 1, 'title' => '', 'action' => '', 'target_id' => 0, 'state' => 'invalid', 'message' => ''];
      try {
        $plan = $this->prepare_item($items[$index], $job['options']);
        $row = array_merge($row, array_intersect_key($plan, array_flip(['action', 'target_id', 'slug', 'title', 'status', 'message', 'schema_hash', 'mapping', 'warnings', 'policy'])));
        $row['title'] = $this->short($row['title'], 160);
        if ($plan['action'] === 'skip') $row['state'] = 'skip';
        else {
          $identity = $plan['target_id'] ? 'id:' . $plan['target_id'] : $job['options']['post_type'] . ':' . $plan['slug'] . ':' . $plan['language'];
          if (isset($job['claimed'][$identity])) throw new \RuntimeException('This destination is already used by item ' . $job['claimed'][$identity] . '.');
          $job['claimed'][$identity] = $index + 1;
          $row['state'] = 'valid';
          $row['fields'] = array_keys($plan['meta']);
          if (($job['options']['scope'] ?? '') === 'page') $row['values_hash'] = $this->values_hash($plan);
          $row['snapshot_hash'] = $plan['target_id'] ? hash('sha256', serialize($this->snapshot($plan['target_id'], $plan))) : '';
        }
      } catch (\Throwable $error) { $row['state'] = 'invalid'; $row['message'] = $this->short($error->getMessage()); }
      $job['rows'][$index] = $row;
      $job['preview_cursor']++;
      if (microtime(true) >= $deadline) break;
    }
    if ($job['preview_cursor'] >= $job['total']) $job['phase'] = 'ready';
    $this->save_job($job);
    return $job;
  }

  private function same_value($a, $b) { return is_array($a) || is_array($b) ? $a === $b : (string) $a === (string) $b; }

  private function write_meta($id, $key, $value) {
    if ($value === null || $value === '' || $value === []) {
      delete_post_meta($id, $key);
      if (metadata_exists('post', $id, $key)) throw new \RuntimeException('Unable to clear field ' . $key . '.');
    } else {
      update_post_meta($id, $key, wp_slash($value));
      if (!$this->same_value(get_post_meta($id, $key, true), $value)) throw new \RuntimeException('Unable to save field ' . $key . '.');
    }
  }

  private function write_post($data, $insert = false) {
    // Polylang core synchronization is separate from its meta/taxonomy copy filters.
    $removed = [];
    $pll = function_exists('PLL') ? PLL() : null;
    if (is_object($pll) && isset($pll->sync) && is_object($pll->sync)) {
      foreach ([['pll_save_post', 'pll_save_post', 3], ['wp_insert_post_data', 'can_sync_post_data', 2], ['wp_insert_post_parent', 'can_sync_post_parent', 3]] as $hook) {
        $callback = [$pll->sync, $hook[1]];
        $priority = has_filter($hook[0], $callback);
        if ($priority !== false) {
          remove_filter($hook[0], $callback, $priority);
          $removed[] = [$hook[0], $callback, $priority, $hook[2]];
        }
      }
    }
    try { return $insert ? wp_insert_post(wp_slash($data), true) : wp_update_post(wp_slash($data), true); }
    finally { foreach ($removed as $hook) add_filter($hook[0], $hook[1], $hook[2], $hook[3]); }
  }

  public function apply_row($plan, &$job, $index) {
    $id = $plan['target_id'];
    $created = !$id;
    $backup = $id ? $this->snapshot($id, $plan) : [];
    $core_written = false;
    $this->running = true;
    try {
      if ($created) {
        // Finish metadata/term work before a new post becomes public.
        $id = $this->write_post(array_merge($plan['core'], ['post_status' => 'draft']), true);
        if (is_wp_error($id) || !$id) throw new \RuntimeException(is_wp_error($id) ? $id->get_error_message() : 'Could not create content.');
        $job['rows'][$index]['target_id'] = (int) $id;
        $this->save_job($job);
      }
      foreach ($plan['meta'] as $key => $value) $this->write_meta($id, $key, $value);
      foreach ($plan['terms'] as $taxonomy => $ids) {
        $result = wp_set_object_terms($id, $ids, $taxonomy, false);
        if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
        $actual = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
        $expected = $ids; sort($expected);
        if (is_wp_error($actual)) throw new \RuntimeException('Could not verify taxonomy assignments.');
        $actual = array_map('intval', $actual); sort($actual);
        if ($actual !== $expected) throw new \RuntimeException('Could not save taxonomy assignments.');
      }
      if ($created && $plan['language']) {
        pll_set_post_language($id, $plan['language']);
        if (pll_get_post_language($id, 'slug') !== $plan['language']) throw new \RuntimeException('Could not assign the content language.');
      }
      $core_written = true;
      $result = $this->write_post(['ID' => $id] + $plan['core']);
      if (is_wp_error($result) || !$result) throw new \RuntimeException(is_wp_error($result) ? $result->get_error_message() : 'Could not update content.');
      if (isset($plan['core']['post_status']) && get_post($id)->post_status !== $plan['core']['post_status']) throw new \RuntimeException('WordPress did not accept the requested publication status.');
      return ['state' => $created ? 'created' : 'updated', 'target_id' => (int) $id, 'message' => ''];
    } catch (\Throwable $error) {
      $restored = true;
      try {
        if ($created && is_numeric($id) && $id > 0) {
          wp_delete_post($id, true);
          $restored = !get_post($id);
        } elseif (!$created) {
          foreach ($backup['meta'] as $key => $values) {
            delete_post_meta($id, $key);
            foreach ($values as $value) add_post_meta($id, $key, wp_slash($value));
          }
          foreach ($backup['terms'] as $taxonomy => $ids) wp_set_object_terms($id, array_map('intval', $ids), $taxonomy, false);
          if ($core_written) $this->write_post(['ID' => $id] + array_diff_key($backup['core'], ['post_modified_gmt' => true]));
          $after = $this->snapshot($id, $plan);
          $before = $backup;
          unset($after['core']['post_modified_gmt'], $before['core']['post_modified_gmt']);
          $restored = $before === $after;
        }
      } catch (\Throwable $rollback_error) { $restored = false; }
      return ['state' => 'failed', 'target_id' => is_numeric($id) ? (int) $id : 0, 'needs_review' => !$restored,
        'message' => $this->short($error->getMessage()) . ($restored ? ' Changes for this item were rolled back.' : ' Rollback was incomplete; review the destination before continuing.')];
    } finally { $this->running = false; }
  }

  public function import_batch($job) {
    if ($job['phase'] !== 'running') return $job;
    $items = get_option($this->key('payload'), []);
    if (count($items) !== $job['total']) throw new \RuntimeException('Import payload is unavailable. Stop and review completed items.');
    $deadline = microtime(true) + 3;
    for ($count = 0; $job['cursor'] < $job['total'] && $count < self::BATCH_SIZE; $count++) {
      $index = $job['cursor'];
      $row = $job['rows'][$index];
      if ($row['state'] === 'writing') {
        // Do not automatically repeat an uncertain write after a fatal error or lost process.
        $job['rows'][$index]['state'] = 'failed';
        $job['rows'][$index]['message'] = 'The previous request stopped during this item. Review its destination before importing it again.';
        $job['rows'][$index]['needs_review'] = true;
        $job['cursor']++; $job['phase'] = 'paused'; $this->save_job($job); break;
      }
      if ($row['state'] === 'valid') {
        try {
          $plan = $this->prepare_item($items[$index], $job['options']);
          if ($plan['action'] !== $row['action'] || $plan['target_id'] !== $row['target_id']) throw new \RuntimeException('The destination changed since preview. Prepare a new preview for this item.');
          if ($plan['schema_hash'] !== $row['schema_hash']) throw new \RuntimeException('CFF definitions changed since preview. Prepare a new preview.');
          if (isset($row['values_hash']) && $this->values_hash($plan) !== $row['values_hash']) throw new \RuntimeException('Mapped field values or media matches changed since preview. Prepare a new preview.');
          if ($plan['target_id'] && hash('sha256', serialize($this->snapshot($plan['target_id'], $plan))) !== $row['snapshot_hash']) throw new \RuntimeException('Destination content changed since preview. No changes were applied.');
          $job['rows'][$index]['state'] = 'writing';
          $this->save_job($job);
          $job['rows'][$index] = array_merge($job['rows'][$index], $this->apply_row($plan, $job, $index));
        } catch (\Throwable $error) {
          $job['rows'][$index]['state'] = 'failed'; $job['rows'][$index]['message'] = $this->short($error->getMessage());
        }
      }
      $job['cursor']++;
      if (!empty($job['rows'][$index]['needs_review'])) $job['phase'] = 'paused';
      $this->save_job($job);
      if ($job['phase'] === 'paused' || microtime(true) >= $deadline) break;
    }
    if ($job['cursor'] >= $job['total']) {
      $job['phase'] = 'completed';
      delete_option($this->key('payload'));
      $this->save_job($job);
    }
    return $job;
  }

  public function command($command, $input = [], $raw = null) {
    if (!in_array($command, ['upload', 'preview', 'start', 'batch', 'pause', 'resume', 'cancel', 'status'], true)) throw new \RuntimeException('Unknown import command.');
    $this->context_id = ($input['scope'] ?? '') === 'page' ? (int) $this->destination($input['post_id'] ?? 0)->ID : 0;
    $lease = $this->lock();
    try {
      if ($command === 'upload') return $this->summary($this->upload($raw, $input));
      $job = $this->load_job($command === 'status' ? null : ($input['job'] ?? ''));
      if (!$job) return [];
      if ($command === 'preview') $job = $this->preview_batch($job);
      elseif ($command === 'start') {
        if ($job['phase'] !== 'ready') throw new \RuntimeException('Finish the preview before starting an import.');
        if (!$this->summary($job)['valid']) throw new \RuntimeException('There are no valid items to import.');
        $job['phase'] = 'running'; $this->save_job($job);
      } elseif ($command === 'batch') $job = $this->import_batch($job);
      elseif ($command === 'pause' && $job['phase'] === 'running') { $job['phase'] = 'paused'; $this->save_job($job); }
      elseif ($command === 'resume' && $job['phase'] === 'paused') { $job['phase'] = 'running'; $this->save_job($job); }
      elseif ($command === 'cancel' && !in_array($job['phase'], ['completed', 'cancelled'], true)) {
        $job['phase'] = 'cancelled'; delete_option($this->key('payload')); $this->save_job($job);
      }
      return $this->summary($job);
    } finally { $this->unlock($lease); }
  }

  public function summary($job) {
    if (!$job) return [];
    $result = array_intersect_key($job, array_flip(['token', 'phase', 'total', 'preview_cursor', 'cursor', 'expires']));
    $result += array_fill_keys(['valid', 'invalid', 'created', 'updated', 'skip', 'failed', 'needs_review'], 0);
    $result['rows'] = [];
    foreach ($job['rows'] as $row) {
      if (isset($result[$row['state']])) $result[$row['state']]++;
      if (!empty($row['needs_review'])) $result['needs_review']++;
      $entry = array_intersect_key($row, array_flip(['index', 'title', 'action', 'target_id', 'status', 'state', 'message', 'fields', 'needs_review', 'mapping', 'warnings', 'policy']));
      if ($row['target_id'] && current_user_can('edit_post', $row['target_id'])) $entry['url'] = admin_url('post.php?post=' . (int) $row['target_id'] . '&action=edit');
      $result['rows'][] = $entry;
    }
    return $result;
  }

  public function ajax() {
    try {
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can(Plugin::admin_capability())) throw new \RuntimeException('Forbidden.');
      $nonce = is_string($_POST['nonce'] ?? null) ? wp_unslash($_POST['nonce']) : '';
      if (!wp_verify_nonce($nonce, 'cff_content_import')) throw new \RuntimeException('Security check failed. Reload the Import Content page.');
      $command = is_string($_POST['command'] ?? null) ? sanitize_key($_POST['command']) : '';
      $raw = null;
      if ($command === 'upload') {
        $file = $_FILES['content_json'] ?? [];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Select a JSON file that can be uploaded by this server.');
        if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'json') throw new \RuntimeException('Only .json files are accepted.');
        if (filesize($file['tmp_name']) > self::MAX_BYTES) throw new \RuntimeException('The JSON file exceeds the 5 MB limit.');
        $raw = file_get_contents($file['tmp_name']);
      }
      $result = $this->command($command, wp_unslash($_POST), $raw);
    } catch (\RuntimeException $error) { wp_send_json_error(['message' => $error->getMessage()], 400); return; }
    wp_send_json_success($result);
  }
}
