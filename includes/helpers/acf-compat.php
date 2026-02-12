<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

/**
 * ACF-compatible helpers (minimal but real for repeater + flexible).
 * Data is stored in post meta keys: _cff_{field_name}
 */
$GLOBALS['cff_row_stack'] = [];

function cff_meta_key($name) { return '_cff_' . sanitize_key($name); }
function cff_format_value($val, $format_value = true) {
  if (!$format_value) return $val;
  if (is_array($val)) {
    $out = [];
    foreach ($val as $k => $v) {
      if (is_array($v)) {
        $out[$k] = cff_format_value($v, true);
        continue;
      }

      if (is_numeric($v) && is_string($k)) {
        $id = (int) $v;
        if ($id) {
          $url_key = $k . '_url';
          $url = (isset($val[$url_key]) && is_string($val[$url_key])) ? $val[$url_key] : '';
          if (!$url) $url = wp_get_attachment_url($id);
          if ($url) {
            $out[$k] = [
              'id' => $id,
              'url' => $url,
            ];
            continue;
          }
        }
      }

      $out[$k] = $v;
    }
    return $out;
  }

  if (is_numeric($val)) {
    $id = (int) $val;
    if ($id) {
      $url = wp_get_attachment_url($id);
      if ($url) {
        return [
          'id' => $id,
          'url' => $url,
        ];
      }
    }
  }
  return $val;
}

if (!function_exists('get_field')) {
  function get_field($selector, $post_id = false, $format_value = true) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!$post_id) return null;
    $val = get_post_meta($post_id, cff_meta_key($selector), true);
    return cff_format_value($val, $format_value);
  }
}

if (!function_exists('the_field')) {
  function the_field($selector, $post_id = false) {
    $v = get_field($selector, $post_id, true);
    if (is_scalar($v)) echo wp_kses_post(wpautop($v));
  }
}

if (!function_exists('have_rows')) {
  function have_rows($selector, $post_id = false) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $data = get_field($selector, $post_id, true);
    if (!is_array($data) || !count($data)) return false;

    $key = $post_id . ':' . sanitize_key($selector);
    if (!isset($GLOBALS['cff_row_stack'][$key])) {
      $GLOBALS['cff_row_stack'][$key] = [
        'rows' => array_values($data),
        'i' => -1,
        'current' => null,
      ];
    }
    $st = &$GLOBALS['cff_row_stack'][$key];
    return ($st['i'] + 1) < count($st['rows']);
  }
}

if (!function_exists('the_row')) {
  function the_row() {
    // Find the most recent stack entry
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return false;

    $st = &$GLOBALS['cff_row_stack'][$key];
    $st['i']++;
    $st['current'] = $st['rows'][$st['i']] ?? null;
    return $st['current'] !== null;
  }
}

if (!function_exists('get_row_layout')) {
  function get_row_layout() {
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return null;
    $cur = $GLOBALS['cff_row_stack'][$key]['current'];
    if (is_array($cur) && isset($cur['layout'])) return $cur['layout'];
    return null;
  }
}

if (!function_exists('get_sub_field')) {
  function get_sub_field($selector, $format_value = true) {
    end($GLOBALS['cff_row_stack']);
    $key = key($GLOBALS['cff_row_stack']);
    if ($key === null) return null;
    $cur = $GLOBALS['cff_row_stack'][$key]['current'];
    if (!is_array($cur)) return null;

    // Flexible stores subfields under ['fields']
    if (isset($cur['fields']) && is_array($cur['fields'])) {
      $val = $cur['fields'][sanitize_key($selector)] ?? null;
      return cff_format_value($val, $format_value);
    }
    // Repeater rows store direct subkeys
    $val = $cur[sanitize_key($selector)] ?? null;
    return cff_format_value($val, $format_value);
  }
}

if (!function_exists('the_sub_field')) {
  function the_sub_field($selector) {
    $v = get_sub_field($selector);
    if (is_scalar($v)) echo wp_kses_post(wpautop($v));
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_ordered_fields')) {
  /**
   * Return ordered field definitions for a group on a specific post.
   *
   * @param int  $post_id Post ID where order is saved.
   * @param int  $group_id Field Group post ID.
   * @param bool $include_values Include field values in each item.
   * @param bool $format_value Format values using get_field formatter.
   * @return array
   */
  function cff_get_ordered_fields($post_id, $group_id, $include_values = false, $format_value = true) {
    $post_id = absint($post_id);
    $group_id = absint($group_id);
    if (!$post_id || !$group_id) return [];

    $settings = get_post_meta($group_id, '_cff_settings', true);
    $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];
    if (!$fields) return [];

    $saved = get_post_meta($post_id, '_cff_group_field_order_' . $group_id, true);
    if (is_string($saved)) {
      $saved = array_filter(array_map('sanitize_key', explode(',', $saved)));
    }
    if (!is_array($saved)) {
      $saved = [];
    }

    if ($saved) {
      $rank = [];
      foreach ($saved as $idx => $name) {
        if (!isset($rank[$name])) $rank[$name] = $idx;
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
    }

    if (!$include_values) {
      return $fields;
    }

    $out = [];
    foreach ($fields as $field) {
      $name = sanitize_key($field['name'] ?? '');
      if (!$name) continue;
      $field['value'] = get_field($name, $post_id, $format_value);
      $out[] = $field;
    }
    return $out;
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_render_ordered_fields')) {
  /**
   * Render ordered field list with basic HTML structure.
   *
   * @param int $post_id Post ID.
   * @param int $group_id Field Group post ID.
   * @return void
   */
  function cff_render_ordered_fields($post_id, $group_id) {
    $items = cff_get_ordered_fields($post_id, $group_id, true, true);
    if (!$items) return;

    echo '<div class="cff-frontend-fields">';
    foreach ($items as $item) {
      $name = sanitize_key($item['name'] ?? '');
      if (!$name) continue;

      $label = sanitize_text_field($item['label'] ?? $name);
      $value = $item['value'] ?? null;

      echo '<div class="cff-frontend-field cff-frontend-field-' . esc_attr($name) . '">';
      echo '<h4 class="cff-frontend-field-label">' . esc_html($label) . '</h4>';
      echo '<div class="cff-frontend-field-value">';

      if (is_scalar($value)) {
        echo wp_kses_post((string) $value);
      } elseif (is_array($value) || is_object($value)) {
        echo '<pre>' . esc_html(wp_json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
      } else {
        echo '';
      }

      echo '</div>';
      echo '</div>';
    }
    echo '</div>';
  }
}

if (!function_exists(__NAMESPACE__ . '\cff_get_ordered_field_names')) {
  /**
   * Resolve ordered field names from a matching field group.
   *
   * @param int   $post_id Post ID.
   * @param array $candidate_names Candidate field names used by template.
   * @param int   $group_id Optional explicit group ID (0 = auto detect by best match).
   * @return array
   */
  function cff_get_ordered_field_names($post_id, $candidate_names = [], $group_id = 0) {
    $post_id = absint($post_id);
    if (!$post_id) {
      $post_id = absint(get_queried_object_id());
    }
    if (!$post_id) return [];

    $candidate_names = array_values(array_filter(array_map('sanitize_key', (array) $candidate_names)));
    if (!$candidate_names) return [];

    $normalize = static function($name) {
      return preg_replace('/[^a-z0-9]/', '', sanitize_key($name));
    };
    $candidate_norm = [];
    foreach ($candidate_names as $candidate_name) {
      $candidate_norm[$candidate_name] = $normalize($candidate_name);
    }
    $match_candidate = static function($name) use ($candidate_names, $candidate_norm, $normalize) {
      $name = sanitize_key($name);
      if (!$name) return '';
      if (in_array($name, $candidate_names, true)) return $name;
      $name_norm = $normalize($name);
      foreach ($candidate_norm as $candidate_name => $candidate_name_norm) {
        if (!$candidate_name_norm) continue;
        if ($name_norm === $candidate_name_norm) return $candidate_name;
        if (strpos($name_norm, $candidate_name_norm) !== false) return $candidate_name;
        if (strpos($candidate_name_norm, $name_norm) !== false) return $candidate_name;
      }
      return '';
    };

    $group_id = absint($group_id);
    if (!$group_id) {
      $groups = get_posts([
        'post_type' => 'cff_group',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
      ]);

      // Fast path: pick ONE best saved reorder source (not merged from many groups).
      $best_saved_order = [];
      $best_saved_score = 0;
      $best_field_score_for_saved = -1;
      $best_group_by_fields = 0;
      $best_field_score = 0;

      foreach ((array) $groups as $group_post) {
        $group_id_current = (int) $group_post->ID;
        $settings = get_post_meta($group_id_current, '_cff_settings', true);
        $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];

        $field_names = [];
        foreach ($fields as $field) {
          $name = sanitize_key($field['name'] ?? '');
          if ($name) $field_names[] = $name;
        }
        $field_score = count(array_intersect($candidate_names, array_unique($field_names)));
        if ($field_score > $best_field_score) {
          $best_field_score = $field_score;
          $best_group_by_fields = $group_id_current;
        }

        $saved = get_post_meta($post_id, '_cff_group_field_order_' . $group_id_current, true);
        if (is_string($saved)) {
          $saved = array_filter(array_map('sanitize_key', explode(',', $saved)));
        } elseif (is_array($saved)) {
          $saved = array_filter(array_map('sanitize_key', $saved));
        } else {
          $saved = [];
        }

        $saved_filtered = [];
        foreach ($saved as $name) {
          $matched = $match_candidate($name);
          if ($matched && !in_array($matched, $saved_filtered, true)) {
            $saved_filtered[] = $matched;
          }
        }
        $saved_score = count($saved_filtered);
        if ($saved_score > $best_saved_score || ($saved_score === $best_saved_score && $field_score > $best_field_score_for_saved)) {
          $best_saved_score = $saved_score;
          $best_field_score_for_saved = $field_score;
          $best_saved_order = $saved_filtered;
        }
      }

      if ($best_saved_score > 0 && $best_saved_order) {
        return $best_saved_order;
      }

      $group_id = $best_group_by_fields;
    }

    if (!$group_id) return [];

    $defs = cff_get_ordered_fields($post_id, $group_id, false);
    $ordered = [];
    foreach ((array) $defs as $def) {
      $name = sanitize_key($def['name'] ?? '');
      $matched = $match_candidate($name);
      if ($matched && !in_array($matched, $ordered, true)) {
        $ordered[] = $matched;
      }
    }
    return $ordered;
  }
}
