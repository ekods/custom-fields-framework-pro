<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

$GLOBALS['cff_shortcode_loop_stack'] = [];

if (!function_exists(__NAMESPACE__ . '\cff_register_frontend_shortcodes')) {
  function cff_register_frontend_shortcodes() {
    add_shortcode('cff_loop', __NAMESPACE__ . '\cff_shortcode_loop');
    add_shortcode('cff_items', __NAMESPACE__ . '\cff_shortcode_loop');
    add_shortcode('cff_field', __NAMESPACE__ . '\cff_shortcode_field');
    add_shortcode('cff_item', __NAMESPACE__ . '\cff_shortcode_field');
    add_shortcode('cff_value', __NAMESPACE__ . '\cff_shortcode_value');
    add_shortcode('cff_debug', __NAMESPACE__ . '\cff_shortcode_debug');
  }
  add_action('init', __NAMESPACE__ . '\cff_register_frontend_shortcodes', 40);
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_resolve_lang')) {
  function cff_shortcode_resolve_lang($lang = '') {
    $lang = is_scalar($lang) ? sanitize_key((string) $lang) : '';
    if ($lang && $lang !== 'current') {
      return $lang;
    }

    if (function_exists('pll_current_language')) {
      $current = pll_current_language('slug');
      if (is_string($current) && $current !== '') {
        return sanitize_key($current);
      }
    }

    return '';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_translate_post_id')) {
  function cff_shortcode_translate_post_id($post_id, $lang = '') {
    $post_id = absint($post_id);
    if (!$post_id) return 0;

    $lang = cff_shortcode_resolve_lang($lang);
    if ($lang !== '' && function_exists('pll_get_post')) {
      $translated_id = pll_get_post($post_id, $lang);
      if ($translated_id) {
        return absint($translated_id);
      }
    }

    return $post_id;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_resolve_post_id')) {
  function cff_shortcode_resolve_post_id($post_id = 0, $page_id = 0, $lang = '') {
    $resolved_id = absint($post_id);
    if (!$resolved_id) {
      $resolved_id = absint($page_id);
    }
    if (!$resolved_id) {
      $resolved_id = cff_resolve_post_id(0);
    }

    return cff_shortcode_translate_post_id($resolved_id, $lang);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_normalize_candidates')) {
  function cff_shortcode_normalize_candidates($raw) {
    if (is_array($raw)) {
      $values = $raw;
    } else {
      $values = preg_split('/[\s,|]+/', (string) $raw);
    }

    return array_values(array_filter(array_map('sanitize_key', (array) $values)));
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_truthy')) {
  function cff_shortcode_truthy($value) {
    if (is_bool($value)) return $value;
    $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
    return in_array($value, ['1', 'true', 'yes', 'on', 'all'], true);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_normalize_keys')) {
  function cff_shortcode_normalize_keys($raw) {
    if (is_array($raw)) {
      $values = $raw;
    } else {
      $values = preg_split('/[\s,|]+/', (string) $raw);
    }

    return array_values(array_filter(array_map('sanitize_key', (array) $values)));
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_value_has_any_key')) {
  function cff_shortcode_value_has_any_key($value, $keys) {
    $keys = cff_shortcode_normalize_keys($keys);
    if (!$keys) return false;

    foreach ($keys as $key) {
      $candidate = cff_shortcode_get_nested_value($value, $key, '__cff_missing__');
      if ($candidate === '__cff_missing__') continue;
      if (is_array($candidate)) {
        if (!empty($candidate)) return true;
      } elseif ($candidate !== null && $candidate !== '') {
        return true;
      }
    }

    return false;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_get_nested_value')) {
  function cff_shortcode_get_nested_value($value, $path, $default = null) {
    $path = is_scalar($path) ? trim((string) $path) : '';
    if ($path === '') return $default;

    $segments = array_values(array_filter(array_map('sanitize_key', explode('.', $path))));
    if (!$segments) return $default;

    $current = $value;
    foreach ($segments as $segment) {
      if (is_object($current)) {
        $current = (array) $current;
      }
      if (!is_array($current) || !array_key_exists($segment, $current)) {
        return $default;
      }
      $current = $current[$segment];
    }

    return $current;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_should_include_item')) {
  function cff_shortcode_should_include_item($item, $include_empty) {
    $value = $item['value'] ?? null;

    if (is_array($value)) {
      if (!empty($value)) return true;
    } elseif ($value !== null && $value !== '') {
      return true;
    }

    if (cff_shortcode_truthy($include_empty)) {
      return true;
    }

    return cff_shortcode_value_has_any_key($value, $include_empty) || cff_shortcode_value_has_any_key($item, $include_empty);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_is_video_url')) {
  function cff_shortcode_is_video_url($url) {
    $url = is_scalar($url) ? strtolower((string) $url) : '';
    if ($url === '') return false;
    return (bool) preg_match('/\.(mp4|webm|ogg|ogv|mov|m4v)(\?.*)?$/', $url);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_detect_value_type')) {
  function cff_shortcode_detect_value_type($value, $field_type = '', $atts = []) {
    $explicit = sanitize_key($atts['render'] ?? '');
    if ($explicit !== '' && $explicit !== 'auto') {
      return $explicit;
    }

    $field_type = sanitize_key($field_type);
    if (in_array($field_type, ['image', 'file', 'link', 'embed'], true)) {
      if ($field_type === 'file' && is_array($value)) {
        $attachment_id = absint($value['id'] ?? 0);
        $url = is_string($value['url'] ?? null) ? (string) $value['url'] : '';
        if (($attachment_id && wp_attachment_is('video', $attachment_id)) || cff_shortcode_is_video_url($url)) {
          return 'video';
        }
      }
      return $field_type;
    }

    if (is_array($value)) {
      $attachment_id = absint($value['id'] ?? 0);
      $url = is_string($value['url'] ?? null) ? (string) $value['url'] : '';
      if (isset($value['target']) || isset($value['title'])) {
        return 'link';
      }
      if (($attachment_id && wp_attachment_is('image', $attachment_id)) || (!empty($value['url']) && !cff_shortcode_is_video_url($url))) {
        return 'image';
      }
      if (($attachment_id && wp_attachment_is('video', $attachment_id)) || cff_shortcode_is_video_url($url)) {
        return 'video';
      }
      if ($url !== '') {
        return 'file';
      }
    }

    return '';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_merge_class')) {
  function cff_shortcode_merge_class($base_class, $extra_class = '') {
    $classes = array_filter(array_map('sanitize_html_class', preg_split('/\s+/', trim($base_class . ' ' . $extra_class))));
    return implode(' ', array_unique($classes));
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_image')) {
  function cff_shortcode_render_image($value, $atts = []) {
    if (is_object($value)) $value = (array) $value;
    if (!is_array($value)) return '';

    $url = is_string($value['url'] ?? null) ? $value['url'] : '';
    if ($url === '') return '';

    $class = cff_shortcode_merge_class('cff-shortcode-image', (string) ($atts['img_class'] ?? $atts['class'] ?? ''));
    $alt = is_scalar($atts['alt'] ?? null) ? (string) $atts['alt'] : '';
    if ($alt === '') {
      $alt = is_string($value['alt'] ?? null) ? $value['alt'] : '';
    }

    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" class="' . esc_attr($class) . '">';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_video')) {
  function cff_shortcode_render_video($value, $atts = []) {
    if (is_object($value)) $value = (array) $value;
    if (!is_array($value)) return '';

    $url = is_string($value['url'] ?? null) ? $value['url'] : '';
    if ($url === '') return '';

    $attachment_id = absint($value['id'] ?? 0);
    $mime = $attachment_id ? (string) get_post_mime_type($attachment_id) : '';
    $class = cff_shortcode_merge_class('cff-shortcode-video', (string) ($atts['video_class'] ?? $atts['class'] ?? ''));
    $controls = array_key_exists('controls', $atts) ? cff_shortcode_truthy($atts['controls']) : true;
    $autoplay = cff_shortcode_truthy($atts['autoplay'] ?? false);
    $muted = cff_shortcode_truthy($atts['muted'] ?? false);
    $loop = cff_shortcode_truthy($atts['loop'] ?? false);
    $playsinline = array_key_exists('playsinline', $atts) ? cff_shortcode_truthy($atts['playsinline']) : true;

    $attrs = ' class="' . esc_attr($class) . '" preload="metadata"';
    if ($controls) $attrs .= ' controls';
    if ($autoplay) $attrs .= ' autoplay';
    if ($muted) $attrs .= ' muted';
    if ($loop) $attrs .= ' loop';
    if ($playsinline) $attrs .= ' playsinline';

    $source_type = $mime !== '' ? ' type="' . esc_attr($mime) . '"' : '';
    return '<video' . $attrs . '><source src="' . esc_url($url) . '"' . $source_type . '></video>';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_file')) {
  function cff_shortcode_render_file($value, $atts = []) {
    if (is_object($value)) $value = (array) $value;
    if (!is_array($value)) return '';

    $url = is_string($value['url'] ?? null) ? $value['url'] : '';
    if ($url === '') return '';

    $label = is_scalar($atts['text'] ?? null) ? (string) $atts['text'] : '';
    if ($label === '') {
      $label = is_string($value['title'] ?? null) ? $value['title'] : basename((string) wp_parse_url($url, PHP_URL_PATH));
    }

    $class = cff_shortcode_merge_class('cff-shortcode-file', (string) ($atts['link_class'] ?? $atts['class'] ?? ''));
    return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_link')) {
  function cff_shortcode_render_link($value, $atts = []) {
    if (is_object($value)) $value = (array) $value;
    if (!is_array($value)) return '';

    $url = is_string($value['url'] ?? null) ? $value['url'] : '';
    if ($url === '') return '';

    $label = is_scalar($atts['text'] ?? null) ? (string) $atts['text'] : '';
    if ($label === '') {
      $label = is_string($value['title'] ?? null) && $value['title'] !== '' ? $value['title'] : $url;
    }

    $target = is_scalar($atts['target'] ?? null) ? (string) $atts['target'] : '';
    if ($target === '') {
      $target = is_string($value['target'] ?? null) ? $value['target'] : '';
    }

    $class = cff_shortcode_merge_class('cff-shortcode-link', (string) ($atts['link_class'] ?? $atts['class'] ?? ''));
    $target_attr = $target !== '' ? ' target="' . esc_attr($target) . '"' : '';
    $rel_attr = $target === '_blank' ? ' rel="noopener noreferrer"' : '';

    return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '"' . $target_attr . $rel_attr . '>' . esc_html($label) . '</a>';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_relational_single')) {
  function cff_shortcode_render_relational_single($id, $atts = []) {
    $id = absint($id);
    if (!$id) return '';

    $class = cff_shortcode_merge_class('cff-shortcode-relational', (string) ($atts['link_class'] ?? $atts['class'] ?? ''));

    $post = get_post($id);
    if ($post instanceof \WP_Post) {
      $label = $post->post_title !== '' ? $post->post_title : ('#' . $post->ID);
      $url = get_permalink($post);
      if ($url) {
        return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
      }
      return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    $term = get_term($id);
    if ($term instanceof \WP_Term && !is_wp_error($term)) {
      $url = get_term_link($term);
      if (!is_wp_error($url) && $url) {
        return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($term->name) . '</a>';
      }
      return '<span class="' . esc_attr($class) . '">' . esc_html($term->name) . '</span>';
    }

    $user = get_userdata($id);
    if ($user instanceof \WP_User) {
      $label = $user->display_name !== '' ? $user->display_name : $user->user_login;
      return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    return '<span class="' . esc_attr($class) . '">' . esc_html((string) $id) . '</span>';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_render_relational')) {
  function cff_shortcode_render_relational($value, $atts = []) {
    if (is_object($value)) $value = (array) $value;

    if (is_numeric($value)) {
      return cff_shortcode_render_relational_single($value, $atts);
    }

    if (is_array($value)) {
      $parts = [];
      foreach ($value as $item) {
        if (!is_numeric($item)) continue;
        $html = cff_shortcode_render_relational_single($item, $atts);
        if ($html !== '') $parts[] = $html;
      }
      if ($parts) {
        return implode(', ', $parts);
      }
    }

    return '';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_format_value')) {
  function cff_shortcode_format_value($value, $fallback = '', $field_type = '', $atts = []) {
    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
      return (string) $fallback;
    }

    $render_type = cff_shortcode_detect_value_type($value, $field_type, $atts);
    if ($render_type === 'image') {
      $html = cff_shortcode_render_image($value, $atts);
      return $html !== '' ? $html : (string) $fallback;
    }
    if ($render_type === 'video') {
      $html = cff_shortcode_render_video($value, $atts);
      return $html !== '' ? $html : (string) $fallback;
    }
    if ($render_type === 'file') {
      $html = cff_shortcode_render_file($value, $atts);
      return $html !== '' ? $html : (string) $fallback;
    }
    if ($render_type === 'link') {
      $html = cff_shortcode_render_link($value, $atts);
      return $html !== '' ? $html : (string) $fallback;
    }
    if ($field_type === 'relational') {
      $html = cff_shortcode_render_relational($value, $atts);
      return $html !== '' ? $html : (string) $fallback;
    }

    if (is_scalar($value)) {
      $text = wp_kses_post((string) $value);
      $class = trim((string) ($atts['class'] ?? ''));
      if ($class !== '') {
        return '<span class="' . esc_attr(cff_shortcode_merge_class('cff-shortcode-text', $class)) . '">' . $text . '</span>';
      }
      return $text;
    }

    if (is_object($value)) {
      $value = (array) $value;
    }

    if (is_array($value)) {
      return '<pre>' . esc_html(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    return (string) $fallback;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_get_field_definitions')) {
  function cff_shortcode_get_field_definitions($post_id) {
    $post_id = absint($post_id);
    if (!$post_id) return [];

    static $cache = [];
    if (isset($cache[$post_id])) {
      return $cache[$post_id];
    }

    $post = get_post($post_id);
    if (!$post) {
      $cache[$post_id] = [];
      return $cache[$post_id];
    }

    $plugin = Plugin::instance();
    $cache[$post_id] = method_exists($plugin, 'get_field_definitions_for_post')
      ? (array) $plugin->get_field_definitions_for_post($post)
      : [];

    return $cache[$post_id];
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_get_loop_items')) {
  function cff_shortcode_get_loop_items($atts) {
    $post_id = cff_shortcode_resolve_post_id($atts['post_id'] ?? 0, $atts['page_id'] ?? 0, $atts['lang'] ?? '');
    if (!$post_id) return [];

    $group_id = absint($atts['group_id'] ?? 0);
    $format_value = !isset($atts['format']) || filter_var($atts['format'], FILTER_VALIDATE_BOOLEAN);
    $include_empty = $atts['include_empty'] ?? '0';
    $items = [];

    if ($group_id) {
      $items = cff_get_ordered_fields($post_id, $group_id, true, $format_value);
    } else {
      $candidates = cff_shortcode_normalize_candidates($atts['candidates'] ?? '');
      if ($candidates) {
        $definitions = cff_shortcode_get_field_definitions($post_id);
        $ordered_names = cff_get_ordered_field_names($post_id, $candidates, 0);

        foreach ($ordered_names as $name) {
          $name = sanitize_key($name);
          if (!$name) continue;

          $definition = isset($definitions[$name]) && is_array($definitions[$name]) ? $definitions[$name] : [];
          $items[] = array_merge($definition, [
            'name' => $name,
            'label' => sanitize_text_field($definition['label'] ?? $name),
            'type' => sanitize_key($definition['type'] ?? 'text'),
            'value' => get_field($name, $post_id, $format_value),
          ]);
        }
      }
    }

    return array_values(array_filter((array) $items, function($item) use ($include_empty) {
      return cff_shortcode_should_include_item($item, $include_empty);
    }));
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_loop')) {
  function cff_shortcode_loop($atts, $content = null) {
    $atts = shortcode_atts([
      'post_id' => 0,
      'page_id' => 0,
      'group_id' => 0,
      'candidates' => '',
      'lang' => '',
      'format' => '1',
      'include_empty' => '0',
      'empty' => '',
      'class' => '',
      'img_class' => '',
      'video_class' => '',
      'link_class' => '',
      'alt' => '',
      'text' => '',
      'render' => 'auto',
      'controls' => '1',
      'autoplay' => '0',
      'muted' => '0',
      'loop' => '0',
      'playsinline' => '1',
    ], (array) $atts, 'cff_loop');

    $items = cff_shortcode_get_loop_items($atts);
    if (!$items) {
      return (string) $atts['empty'];
    }

    $output = '';
    foreach ($items as $item) {
      $GLOBALS['cff_shortcode_loop_stack'][] = [
        'post_id' => cff_shortcode_resolve_post_id($atts['post_id'], $atts['page_id'], $atts['lang']),
        'item' => $item,
      ];

      if ($content !== null && $content !== '') {
        $output .= do_shortcode($content);
      } else {
        $output .= cff_shortcode_format_value($item['value'] ?? '', '', $item['type'] ?? '', $atts);
      }

      array_pop($GLOBALS['cff_shortcode_loop_stack']);
    }

    return $output;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_field')) {
  function cff_shortcode_field($atts) {
    $atts = shortcode_atts([
      'name' => '',
      'post_id' => 0,
      'page_id' => 0,
      'key' => 'value',
      'lang' => '',
      'format' => '1',
      'default' => '',
      'class' => '',
      'img_class' => '',
      'video_class' => '',
      'link_class' => '',
      'alt' => '',
      'text' => '',
      'render' => 'auto',
      'controls' => '1',
      'autoplay' => '0',
      'muted' => '0',
      'loop' => '0',
      'playsinline' => '1',
      'target' => '',
    ], (array) $atts, 'cff_field');

    $field_name = sanitize_key($atts['name']);
    $post_id = cff_shortcode_resolve_post_id($atts['post_id'], $atts['page_id'], $atts['lang']);
    $format_value = !isset($atts['format']) || filter_var($atts['format'], FILTER_VALIDATE_BOOLEAN);

    if ($field_name) {
      $definitions = cff_shortcode_get_field_definitions($post_id);
      $field_type = sanitize_key($definitions[$field_name]['type'] ?? '');
      $value = get_field($field_name, $post_id, $format_value);
      return cff_shortcode_format_value($value, $atts['default'], $field_type, $atts);
    }

    $current = end($GLOBALS['cff_shortcode_loop_stack']);
    if (!$current || empty($current['item']) || !is_array($current['item'])) {
      return (string) $atts['default'];
    }

    $key = sanitize_key($atts['key']);
    if ($key === '') {
      $key = 'value';
    }

    $item = $current['item'];
    $value = $item[$key] ?? null;
    if (($key === 'label' || $key === 'name' || $key === 'type') && is_scalar($value)) {
      return esc_html((string) $value);
    }

    return cff_shortcode_format_value($value, $atts['default'], $item['type'] ?? '', $atts);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_value')) {
  function cff_shortcode_value($atts) {
    return cff_shortcode_field($atts);
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_shortcode_debug')) {
  function cff_shortcode_debug($atts) {
    if (!current_user_can('manage_options')) {
      return '';
    }

    $atts = shortcode_atts([
      'name' => '',
      'post_id' => 0,
      'page_id' => 0,
      'lang' => '',
      'target' => 'value',
      'format' => '1',
    ], (array) $atts, 'cff_debug');

    $target = sanitize_key($atts['target']);
    if ($target === '') {
      $target = 'value';
    }

    $current = end($GLOBALS['cff_shortcode_loop_stack']);
    $post_id = cff_shortcode_resolve_post_id($atts['post_id'], $atts['page_id'], $atts['lang']);
    $format_value = !isset($atts['format']) || filter_var($atts['format'], FILTER_VALIDATE_BOOLEAN);
    $field_name = sanitize_key($atts['name']);

    if ($field_name !== '') {
      $value = get_field($field_name, $post_id, $format_value);
      return '<pre class="cff-shortcode-debug">' . esc_html(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    if (!$current || empty($current['item']) || !is_array($current['item'])) {
      return '';
    }

    $payload = ($target === 'item') ? $current['item'] : ($current['item']['value'] ?? null);

    return '<pre class="cff-shortcode-debug">' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
  }
}
