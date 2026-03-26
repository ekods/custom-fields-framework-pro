<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

if (!function_exists(__NAMESPACE__ . '\render_field_impl')) {
  function cff_conditional_attrs($field) {
    $logic = (isset($field['conditional_logic']) && is_array($field['conditional_logic'])) ? $field['conditional_logic'] : [];
    if (empty($logic['enabled'])) return '';

    $source_key = sanitize_key($logic['key'] ?? '');
    $source_field = sanitize_key($logic['field'] ?? '');
    if (!$source_key && !$source_field) return '';

    $allowed_ops = ['==', '!=', 'contains', 'not_contains', 'empty', 'not_empty'];
    $operator = in_array(($logic['operator'] ?? '=='), $allowed_ops, true) ? $logic['operator'] : '==';
    $value = is_scalar($logic['value'] ?? '') ? (string) $logic['value'] : '';

    return ' data-cff-conditional-enabled="1"'
      . ' data-cff-conditional-key="' . esc_attr($source_key) . '"'
      . ' data-cff-conditional-field="' . esc_attr($source_field) . '"'
      . ' data-cff-conditional-operator="' . esc_attr($operator) . '"'
      . ' data-cff-conditional-value="' . esc_attr($value) . '"';
  }

  function cff_media_preview_html($type, $id) {
    $id = intval($id);
    if (!$id) return '<span class="cff-muted">No file selected</span>';

    if (wp_attachment_is('video', $id)) {
      $url = wp_get_attachment_url($id);
      if ($url) {
        $mime = get_post_mime_type($id);
        $type_attr = $mime ? ' type="' . esc_attr($mime) . '"' : '';
        return '<video class="cff-media-video" controls preload="metadata">'
          . '<source src="' . esc_url($url) . '"' . $type_attr . '>'
          . '</video>';
      }
    }

    if ($type === 'image') {
      // thumbnail (lebih enak daripada link)
      $img = wp_get_attachment_image(
        $id,
        'medium',
        false,
        [
          'class' => 'cff-media-thumb',
          'style' => 'max-width:max-content;height:auto;display:block;object-fit:contain;width:100%;aspect-ratio:initial;'
        ]
      );
      if ($img) return $img;
    }

    // fallback file/link
    $url = wp_get_attachment_url($id);
    if (!$url) return '<span class="cff-muted">File not found</span>';

    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
      . esc_html(basename($url)) .
    '</a>';
  }

  function cff_has_meaningful_value($value) {
    if (is_array($value)) return !empty($value);
    return !($value === null || $value === '');
  }

  function cff_generate_row_id($value = '') {
    $value = sanitize_key($value);
    if ($value) return $value;
    return 'row_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12);
  }

  function cff_encode_copy_field_request($request) {
    if (!is_array($request)) {
      return '';
    }

    $root = sanitize_key($request['root'] ?? '');
    $field = sanitize_key($request['field'] ?? '');
    $key = sanitize_key($request['key'] ?? '');
    $path = [];

    foreach ((array) ($request['path'] ?? []) as $segment) {
      if (!is_array($segment)) continue;
      $name = sanitize_key($segment['name'] ?? '');
      $type = sanitize_key($segment['type'] ?? '');
      if (!$name || !$type) continue;
      $path[] = [
        'name' => $name,
        'type' => $type,
      ];
    }

    if (!$root && !$field) {
      return '';
    }

    return base64_encode(wp_json_encode([
      'root' => $root ?: $field,
      'field' => $field ?: $root,
      'key' => $key,
      'path' => $path,
    ]));
  }

  function cff_decode_copy_field_request($raw) {
    $raw = is_scalar($raw) ? trim((string) $raw) : '';
    if ($raw === '') return [];

    $decoded = base64_decode($raw, true);
    if ($decoded !== false) {
      $request = json_decode($decoded, true);
      if (is_array($request)) {
        $root = sanitize_key($request['root'] ?? '');
        $field = sanitize_key($request['field'] ?? '');
        $key = sanitize_key($request['key'] ?? '');
        $path = [];
        foreach ((array) ($request['path'] ?? []) as $segment) {
          if (!is_array($segment)) continue;
          $name = sanitize_key($segment['name'] ?? '');
          $type = sanitize_key($segment['type'] ?? '');
          if (!$name || !$type) continue;
          $path[] = ['name' => $name, 'type' => $type];
        }
        if ($root || $field) {
          return [
            'root' => $root ?: $field,
            'field' => $field ?: $root,
            'key' => $key,
            'path' => $path,
          ];
        }
      }
    }

    $field_name = sanitize_key($raw);
    if (!$field_name) return [];

    return [
      'root' => $field_name,
      'field' => $field_name,
      'key' => '',
      'path' => [],
    ];
  }

  function cff_render_copy_to_translations_field_action($request = []) {
    if (!function_exists('pll_current_language')) {
      return;
    }

    $payload = is_array($request) ? cff_encode_copy_field_request($request) : '';
    if (!$payload) {
      return;
    }

    $segments = [];
    $root = sanitize_key($request['root'] ?? '');
    $field = sanitize_key($request['field'] ?? '');
    if ($root) {
      $segments[] = $root;
    }
    foreach ((array) ($request['path'] ?? []) as $segment) {
      if (!is_array($segment)) continue;
      $name = sanitize_key($segment['name'] ?? '');
      if ($name && ($root === '' || $name !== $root)) {
        $segments[] = $name;
      }
    }
    if ($field && ($root === '' || $field !== $root)) {
      $segments[] = $field;
    }
    $segments = array_values(array_unique(array_filter($segments)));
    $copy_indicator = implode(' -> ', $segments);
    if ($copy_indicator === '') {
      $copy_indicator = $field ?: $root;
    }

    echo '<div class="cff-field-actions">';
    if ($copy_indicator !== '') {
      echo '<div class="cff-copy-field-indicator">';
      echo '<span class="cff-copy-field-indicator-label">' . esc_html__('Copy:', 'cff') . '</span> ';
      echo '<code>' . esc_html($copy_indicator) . '</code>';
      echo '</div>';
    }
    echo '<button type="submit" class="button cff-copy-field-action" name="cff_copy_field_to_translations" value="' . esc_attr($payload) . '" title="' . esc_attr__('Save + Copy This Field to Translations', 'cff') . '" aria-label="' . esc_attr__('Save + Copy This Field to Translations', 'cff') . '">';
    echo '<span class="dashicons dashicons-translation" aria-hidden="true"></span>';
    echo '</button>';
    echo '</div>';
  }

  function cff_render_media_action_buttons() {
    echo '<p class="cff-media-actions">';
    echo '<button type="button" class="button cff-media-select" title="' . esc_attr__('Select media', 'cff') . '" aria-label="' . esc_attr__('Select media', 'cff') . '"><span class="dashicons dashicons-insert" aria-hidden="true"></span></button> ';
    echo '<button type="button" class="button cff-media-clear" title="' . esc_attr__('Clear media', 'cff') . '" aria-label="' . esc_attr__('Clear media', 'cff') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
    echo '</p>';
  }

  function cff_render_gallery_field($name_attr, $value) {
    $items = [];
    foreach ((array) $value as $item) {
      $id = absint(is_array($item) ? ($item['id'] ?? 0) : $item);
      if ($id) $items[] = $id;
    }
    $items = array_values(array_unique($items));

    echo '<div class="cff-gallery" data-type="gallery" data-name="' . esc_attr($name_attr) . '">';
    echo '<input type="hidden" class="cff-gallery-present" name="' . esc_attr($name_attr) . '[__cff_present]" value="1">';
    echo '<div class="cff-gallery-items">';
    foreach ($items as $id) {
      echo '<div class="cff-gallery-item" data-id="' . esc_attr($id) . '">';
      echo '<input type="hidden" name="' . esc_attr($name_attr) . '[]" value="' . esc_attr($id) . '">';
      echo '<button type="button" class="button-link cff-gallery-remove" aria-label="' . esc_attr__('Remove image', 'cff') . '" title="' . esc_attr__('Remove image', 'cff') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
      echo '<div class="cff-gallery-item-preview">' . cff_media_preview_html('image', $id) . '</div>';
      echo '</div>';
    }
    echo '</div>';
    echo '<p class="cff-media-actions">';
    echo '<button type="button" class="button cff-gallery-select" title="' . esc_attr__('Select gallery images', 'cff') . '" aria-label="' . esc_attr__('Select gallery images', 'cff') . '"><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span></button> ';
    echo '<button type="button" class="button cff-gallery-clear" title="' . esc_attr__('Clear gallery', 'cff') . '" aria-label="' . esc_attr__('Clear gallery', 'cff') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
    echo '</p>';
    echo '</div>';
  }

  function cff_get_value_with_aliases($plugin, $post_id, $name, $field) {
    $name = sanitize_key($name);
    if (!$name) return '';

    $value = get_post_meta($post_id, $plugin->meta_key($name), true);
    if (cff_has_meaningful_value($value)) {
      return $value;
    }

    $aliases = isset($field['aliases']) && is_array($field['aliases']) ? $field['aliases'] : [];
    foreach ($aliases as $alias) {
      $alias = sanitize_key($alias);
      if (!$alias || $alias === $name) continue;
      $alias_value = get_post_meta($post_id, $plugin->meta_key($alias), true);
      if (cff_has_meaningful_value($alias_value)) {
        return $alias_value;
      }
    }

    return $value;
  }


  function render_field_impl($plugin, $post, $f) {
    $type = $f['type'];
    $name = $f['name'];
    $label = $f['label'] ?? $name;
    $val = cff_get_value_with_aliases($plugin, $post->ID, $name, $f);

    $required = !empty($f['required']);
    $required_attr = $required ? ' required aria-required="true"' : '';
    $label_text = esc_html($label);
    if ($required) {
      $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
    }
    $placeholder = $f['placeholder'] ?? '';
    $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';

    $is_accordion = in_array($type, ['repeater', 'group'], true);
    $is_initially_closed = $is_accordion;
    $field_classes = 'cff-field cff-field-' . $type . ($is_accordion ? ' cff-postbox' : '');
    if ($is_initially_closed) {
      $field_classes .= ' closed';
    }
    $field_key = $name ? sanitize_key($name) : sanitize_key($label);
    if (!$field_key) {
      $field_key = 'cff-field-' . wp_rand(1000, 9999);
    }
    $field_attr_name = sanitize_key($name);
    if (!$field_attr_name) {
      $field_attr_name = $field_key;
    }
    echo '<div class="' . esc_attr($field_classes) . '" data-field-name="' . esc_attr($field_attr_name) . '" data-field-key="' . esc_attr($field_key) . '"' . cff_conditional_attrs($f) . '>';
    $type_label = ucfirst(str_replace('_', ' ', $type));
    if ($is_accordion) {
      echo '<div class="postbox-header">';
      echo '<div class="cff-hndle" role="heading" aria-level="2">';
      echo '<div class="cff-hndle-label">'.$label_text.'</div>';
      echo '<div class="cff-hndle-meta">';
      echo '<div class="description cff-meta-type">Type <b>'.esc_html($type_label).'</b></div>';
      echo '<div class="description cff-meta-name">'.esc_html($name).'</div>';
      echo '</div>';
      echo '</div>';
      echo '<div class="handle-actions hide-if-no-js">';
      echo '<button type="button" class="cff-handlediv cff-acc-toggle" aria-expanded="'.($is_initially_closed ? 'false' : 'true').'">';
      echo '<span class="screen-reader-text">'.esc_html__('Toggle panel', 'cff').'</span>';
      // echo '<span class="toggle-indicator" aria-hidden="true"></span>';
      echo '</button>';
      echo '</div>';
      echo '</div>';
      echo '<div class="inside cff-input">';
    } else {
      echo '<div class="cff-label">';
      echo '<div class="cff-label-head">';
      echo '<label>'.$label_text.'</label>';
      echo '</div>';
      echo '<div class="description cff-meta-type">Type <b>'.esc_html($type_label).'</b></div>';
      echo '<div class="description cff-meta-name">'.esc_html($name).'</div>';
      echo '</div>';
      echo '<div class="cff-input">';
    }

    if ($type === 'text' || $type === 'number') {
      $input_type = $type === 'number' ? 'number' : 'text';
      echo '<input class="widefat" type="'.esc_attr($input_type).'" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
    } elseif ($type === 'textarea') {
      echo '<textarea class="widefat" rows="5" name="cff_values['.esc_attr($name).']"'.$placeholder_attr.$required_attr.'>'.esc_textarea($val).'</textarea>';
    } elseif ($type === 'color') {
      echo '<div class="cff-color">';
      echo '<input class="cff-color-picker" type="color" value="'.esc_attr($val ?: '#ffffff').'">';
      echo '<input class="widefat cff-color-value" type="text" placeholder="#ffffff" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$required_attr.'>';
      echo '</div>';
    } elseif ($type === 'checkbox') {
      $checked = !empty($val) ? 'checked' : '';
      echo '<input type="hidden" name="cff_values['.esc_attr($name).']" value="0">';
      echo '<label><input type="checkbox" name="cff_values['.esc_attr($name).']" value="1" '.$checked.$required_attr.'> ' . esc_html($label) . '</label>';
    } elseif ($type === 'url') {
      render_url_input('cff_values[' . $name . ']', $val, $placeholder_attr, $required_attr);
    } elseif ($type === 'relational') {
      $rel_type = $f['relational_type'] ?? 'post';
      $rel_subtype = $f['relational_subtype'] ?? '';
      $rel_display = $f['relational_display'] ?? 'select';
      $rel_multiple = !empty($f['relational_multiple']);

      render_relational_input('cff_values['.esc_attr($name).']', $rel_type, $rel_subtype, $rel_display, $val, $rel_multiple, $required_attr);
    } elseif ($type === 'choice') {
      render_choice_input('cff_values['.esc_attr($name).']', $f['choices'] ?? [], $f['choice_display'] ?? '', $val, $required_attr, $f['choice_default'] ?? '');
    } elseif ($type === 'date_picker' || $type === 'datetime_picker') {
      $name_attr = 'cff_values[' . sanitize_key($name) . ']';
      $use_time = ($type === 'datetime_picker') ? !array_key_exists('datetime_use_time', $f) || !empty($f['datetime_use_time']) : true;
      render_picker_input($name_attr, $val, $placeholder_attr, $required_attr, $type, $use_time);
    } elseif ($type === 'link') {
      render_link_field('cff_values[' . $name . ']', $val);
    } elseif ($type === 'wysiwyg') {
      $editor_id = 'cff_wys_' . $name . '_' . $post->ID;
      ob_start();
      wp_editor((string)$val, $editor_id, [
        'textarea_name' => 'cff_values['.esc_attr($name).']',
        'media_buttons' => true,
        'teeny' => false,
        'textarea_rows' => 8,
      ]);
      $editor_html = ob_get_clean();
      if ($required) {
        $replacement = '$1' . $required_attr . '$2';
        $editor_html = preg_replace('#(<textarea[^>]*)(>)#', $replacement, $editor_html, 1);
      }
      echo $editor_html;
    } elseif ($type === 'image' || $type === 'file') {
      $id = intval($val);
      $url = $id ? wp_get_attachment_url($id) : '';
      echo '<div class="cff-media" data-type="'.esc_attr($type).'">';
      echo '<input type="hidden" class="cff-media-id" name="cff_values['.esc_attr($name).']" value="'.esc_attr($id).'">';
      echo '<input type="hidden" class="cff-media-url" name="cff_values['.esc_attr($name).'_url]" value="'.esc_attr($url).'">';
      echo '<div class="cff-media-preview">' . cff_media_preview_html($type, $id) . '</div>';
      cff_render_media_action_buttons();
      echo '</div>';
    } elseif ($type === 'gallery') {
      cff_render_gallery_field('cff_values[' . $name . ']', $val);
    } elseif ($type === 'repeater') {
      $rows = is_array($val) ? $val : [];
      $subs = isset($f['sub_fields']) ? $f['sub_fields'] : [];

      $min = isset($f['min']) ? (int) $f['min'] : 1;
      $max = isset($f['max']) ? (int) $f['max'] : 0;
      if ($max > 0 && count($rows) > $max) {
        $rows = array_slice($rows, 0, $max);
      }
      $layout = isset($f['repeater_layout']) ? $f['repeater_layout'] : 'default';
      $layout = in_array($layout, ['simple','grid','row','gallery','default'], true) ? $layout : 'default';
      $row_label_field = sanitize_key($f['repeater_row_label'] ?? '');
      $collapsed_default = !empty($f['repeater_collapsed']);

      $rep_field_key = sanitize_key($f['key'] ?? '');
      if (!$rep_field_key) $rep_field_key = sanitize_key($name);
      echo '<div class="cff-repeater"
        data-field="'.esc_attr($rep_field_key).'"
        data-min="'.esc_attr($min).'"
        data-max="'.esc_attr($max).'"
        data-layout="'.esc_attr($layout).'"
        data-row-label="'.esc_attr($row_label_field).'"
        data-collapsed-default="'.($collapsed_default ? '1' : '0').'"
      >';

      // ✅ FLAG: supaya key repeater tetap ada di $_POST walau 0 row
      echo '<input type="hidden" class="cff-rep-present" name="cff_values['.esc_attr($name).'][__cff_present]" value="1">';

      echo '<div class="cff-rep-rows">';
      foreach ($rows as $i => $row) {
        render_repeater_row($name, $subs, $row, $i, $post->ID, null, $layout, $row_label_field, $collapsed_default, $name, []);
      }
      echo '</div>';

      echo '<p><button type="button" class="button cff-rep-add">Add Row</button></p>';

      echo '<template class="cff-rep-template">';
      render_repeater_row($name, $subs, [], '__INDEX__', $post->ID, null, $layout, $row_label_field, $collapsed_default, $name, []);
      echo '</template>';

      echo '</div>';

      do_action('cff_after_repeater_render', $plugin, $post, $f);
    } elseif ($type === 'group') {
      $vals = is_array($val) ? $val : [];
      $subs = isset($f['sub_fields']) ? $f['sub_fields'] : [];
      echo '<div class="cff-group">';
      render_group_fields('cff_values[' . $name . ']', $subs, $vals, $post->ID, $name, []);
      echo '</div>';
    } elseif ($type === 'flexible') {
      $rows = is_array($val) ? $val : [];
      $layouts = isset($f['layouts']) ? $f['layouts'] : [];
      $layout_map = [];
      foreach ($layouts as $l) $layout_map[$l['name']] = $l;

      echo '<div class="cff-flexible" data-field="'.esc_attr($name).'">';
      echo '<div class="cff-flex-rows">';
      foreach ($rows as $i => $row) {
        render_flexible_row($name, $layouts, $layout_map, $row, $i, $post->ID, $name, []);
      }
      echo '</div>';

      echo '<div class="cff-flex-add">';
      echo '<select class="cff-flex-layout">';
      echo '<option value="">Add layout…</option>';
      foreach ($layouts as $l) {
        echo '<option value="'.esc_attr($l['name']).'">'.esc_html($l['label']).'</option>';
      }
      echo '</select> ';
      echo '<button type="button" class="button cff-flex-add-btn">Add</button>';
      echo '</div>';

      echo '<div class="cff-flex-templates" style="display:none">';
      foreach ($layouts as $l) {
        echo '<template class="cff-flex-template" data-layout="'.esc_attr($l['name']).'">';
        render_flexible_row($name, $layouts, $layout_map, ['layout'=>$l['name'], 'fields'=>[]], '__INDEX__', $post->ID, $name, []);
        echo '</template>';
      }
      echo '</div>';

      echo '</div>';
    } else {
      echo '<input class="widefat" type="text" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
    }
    cff_render_copy_to_translations_field_action([
      'root' => $name,
      'field' => $name,
      'key' => sanitize_key($f['key'] ?? ''),
      'path' => [],
    ]);
    echo '</div></div>';
  }

  function render_picker_input($name_attr, $value, $placeholder_attr, $required_attr, $type, $use_time = true) {
    $is_datetime = ($type === 'datetime_picker');
    $use_time = $is_datetime ? !empty($use_time) : false;
    $input_type = ($is_datetime && $use_time) ? 'datetime-local' : 'date';
    if (!$use_time && is_string($value) && strlen($value) >= 10) {
      $value = substr($value, 0, 10);
    }
    printf(
      '<input class="widefat" type="%s" name="%s" value="%s"%s%s>',
      esc_attr($input_type),
      esc_attr($name_attr),
      esc_attr($value),
      $placeholder_attr,
      $required_attr
    );
  }

  function render_url_input($name_attr, $value, $placeholder_attr, $required_attr) {
    echo '<input class="widefat" type="url" name="'.esc_attr($name_attr).'" value="'.esc_attr($value).'"'.$placeholder_attr.$required_attr.'>';
    echo '<p class="description">' . esc_html__('Simple URL field for a direct link only.', 'cff') . '</p>';
  }

  function render_choice_input($name_attr, $choices, $display, $value, $required_attr, $default_value = '') {
    $display = sanitize_key($display ?? '');
    $allowed = ['select','checkbox','radio','button_group','true_false'];
    if (!in_array($display, $allowed, true)) $display = 'select';
    $default_value = is_scalar($default_value) ? (string) $default_value : '';

    if ($value === '' || $value === null || $value === []) {
      if ($display === 'checkbox') {
        $value = $default_value !== '' ? [$default_value] : [];
      } elseif ($display === 'true_false') {
        $value = ($default_value === '1') ? '1' : $value;
      } elseif ($default_value !== '') {
        $value = $default_value;
      }
    }

    if (empty($choices)) {
      echo '<div class="cff-muted">' . esc_html__('No choices configured', 'cff') . '</div>';
      return;
    }

    if ($display === 'select') {
      echo '<select class="widefat" name="'.esc_attr($name_attr).'"'.$required_attr.'>';
      foreach ($choices as $choice) {
        $val = $choice['value'] ?: ($choice['label'] ?? '');
        $selected = ((string)$value === (string)$val) ? ' selected' : '';
        echo '<option value="'.esc_attr($val).'"'.$selected.'>'.esc_html($choice['label'] ?: $val).'</option>';
      }
      echo '</select>';
      return;
    }

    if ($display === 'checkbox') {
      $selected = is_array($value) ? array_map('strval', $value) : [];
      echo '<div class="cff-choice-checkboxes">';
      echo '<input type="hidden" name="'.esc_attr($name_attr).'[]" value="__cff_choice_empty__">';
      foreach ($choices as $choice) {
        $val = $choice['value'] ?: ($choice['label'] ?? '');
        $checked = in_array((string)$val, $selected, true) ? ' checked' : '';
        echo '<label><input type="checkbox" name="'.esc_attr($name_attr).'[]" value="'.esc_attr($val).'"'.$checked.'> '.esc_html($choice['label'] ?: $val).'</label>';
      }
      echo '</div>';
      return;
    }

    if ($display === 'radio' || $display === 'button_group') {
      $selected = (string)$value;
      $class = $display === 'button_group' ? ' cff-choice-button-group' : ' cff-choice-radios';
      echo '<div class="cff-choice-buttons'.esc_attr($class).'">';
      $first = true;
      foreach ($choices as $choice) {
        $val = $choice['value'] ?: ($choice['label'] ?? '');
        $checked = $selected === (string)$val ? ' checked' : '';
        $input_required = ($required_attr && $first) ? $required_attr : '';
        $first = false;
        $input = '<input type="radio" name="'.esc_attr($name_attr).'" value="'.esc_attr($val).'"'.$checked.$input_required.'>';
        $label = esc_html($choice['label'] ?: $val);
        if ($display === 'button_group') {
          echo '<label class="cff-choice-button">'.$input.'<span>'.$label.'</span></label>';
        } else {
          echo '<label>'.$input.' '.$label.'</label>';
        }
      }
      echo '</div>';
      return;
    }

    // true_false
    $is_true = !empty($value);
    echo '<input type="hidden" name="'.esc_attr($name_attr).'" value="0">';
    echo '<label class="cff-choice-true-toggle">';
    echo '<input type="checkbox" name="'.esc_attr($name_attr).'" value="1"'.($is_true ? ' checked' : '').$required_attr.'>';
    echo '<span class="cff-choice-true-label">'.esc_html__('True / False', 'cff').'</span>';
    echo '</label>';
  }

  function cff_build_repeater_row_title($row, $row_label_field, $display_index) {
    $base = sprintf(__('Row %d', 'cff'), max(1, intval($display_index)));
    $row_label_field = sanitize_key($row_label_field);
    if (!$row_label_field || !is_array($row)) return $base;

    $raw = $row[$row_label_field] ?? '';
    if (is_array($raw)) {
      $raw = implode(', ', array_map('sanitize_text_field', array_filter(array_map('strval', $raw))));
    }
    $raw = trim(sanitize_text_field((string) $raw));
    if ($raw === '') return $base;
    return $base . ': ' . $raw;
  }

  function render_repeater_row($parent, $subs, $row, $i, $post_id, $name_prefix = null, $layout = 'default', $row_label_field = '', $collapsed_default = false, $root_name = '', $path = []) {
    $layout = in_array($layout, ['simple','grid','row','gallery','default'], true) ? $layout : 'default';
    $row_classes = 'cff-rep-row';
    if ($layout === 'grid') {
      $row_classes .= ' cff-rep-row-grid';
    } elseif ($layout === 'row') {
      $row_classes .= ' cff-rep-row-row';
    } elseif ($layout === 'simple') {
      $row_classes .= ' cff-rep-row-simple';
    } elseif ($layout === 'gallery') {
      $row_classes .= ' cff-rep-row-gallery';
    }
    if (!in_array($layout, ['simple', 'gallery'], true) && $collapsed_default) {
      $row_classes .= ' is-collapsed';
    }
    $display_index = (is_numeric($i) ? intval($i) + 1 : 1);
    $row_title = cff_build_repeater_row_title($row, $row_label_field, $display_index);
    $row_id = ($i === '__INDEX__') ? '__ROWID__' : cff_generate_row_id($row['__cff_row_id'] ?? '');
    echo '<div class="'.esc_attr($row_classes).'" data-i="'.esc_attr($i).'" data-row-id="'.esc_attr($row_id).'">';
    $head_class = 'cff-rep-row-head';
    if ($layout === 'simple') {
      $head_class .= ' cff-rep-row-head-simple ui-sortable-handle';
    } elseif ($layout === 'grid') {
      $head_class .= ' cff-rep-row-head-grid';
    } elseif ($layout === 'row') {
      $head_class .= ' cff-rep-row-head-row';
    } elseif ($layout === 'gallery') {
      $head_class .= ' cff-rep-row-head-gallery';
    }
    if ($layout === 'simple') {
      echo '<div class="'.esc_attr($head_class).'"><span class="cff-rep-drag" title="Drag"></span><button type="button" class="button-link cff-rep-clone" title="Clone row" aria-label="Clone row"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" class="button-link cff-rep-remove" title="Remove row" aria-label="Remove row"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div>';
    } elseif ($layout === 'gallery') {
      echo '<div class="'.esc_attr($head_class).'"><div class="cff-rep-left"><strong class="cff-rep-row-title">'.esc_html($row_title).'</strong></div><div class="cff-rep-actions"><span class="cff-rep-drag" title="Drag"></span><button type="button" class="button-link cff-rep-clone" title="Clone row" aria-label="Clone row"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" class="button-link cff-rep-remove" title="Remove row" aria-label="Remove row"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div></div>';
    } else {
      echo '<div class="'.esc_attr($head_class).'"><div class="cff-rep-left"><span class="cff-rep-drag" title="Drag"></span><button type="button" class="cff-rep-toggle" title="Collapse"></button><strong class="cff-rep-row-title">'.esc_html($row_title).'</strong></div><div class="cff-rep-actions"><button type="button" class="button-link cff-rep-clone" title="Clone row" aria-label="Clone row"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" class="button-link cff-rep-remove" title="Remove row" aria-label="Remove row"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div></div>';
    }
    echo '<div class="cff-rep-row-body">';
    $row_prefix = ($name_prefix !== null ? $name_prefix : 'cff_values['.$parent.']') . '['.$i.']';
    echo '<input type="hidden" class="cff-row-id" name="'.esc_attr($row_prefix . '[__cff_row_id]').'" value="'.esc_attr($row_id).'">';
    foreach ($subs as $s) {
      $sname = $s['name'];
      $stype = $s['type'];
      $label = $s['label'] ?? $sname;
      $v = isset($row[$sname]) ? $row[$sname] : '';
      $placeholder = cff_resolve_placeholder($s['placeholder'] ?? '', $stype);
      $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
      $name_attr = $row_prefix . '[' . $sname . ']';
      $required = !empty($s['required']);
      $label_text = esc_html($label);
      $required_attr = $required ? ' required aria-required="true"' : '';
      if ($required) $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
      $sub_field_key = sanitize_key($s['key'] ?? '');
      if (!$sub_field_key) $sub_field_key = sanitize_key($sname);
      $current_path = array_merge((array) $path, [[
        'name' => sanitize_key($sname),
        'type' => sanitize_key($stype),
      ]]);
      echo '<div class="cff-subfield-input" data-field-name="' . esc_attr(sanitize_key($sname)) . '" data-field-key="' . esc_attr($sub_field_key) . '"' . cff_conditional_attrs($s) . '>';
      echo '<label>'.$label_text.'</label>';
      if ($stype === 'textarea') {
        echo '<textarea class="widefat" rows="3" name="'.esc_attr($name_attr).'"'.$placeholder_attr.$required_attr.'>'.esc_textarea($v).'</textarea>';
      } elseif ($stype === 'color') {
        echo '<div class="cff-color">';
        echo '<input class="cff-color-picker" type="color" value="'.esc_attr($v ?: '#ffffff').'">';
        echo '<input class="widefat cff-color-value" type="text" placeholder="#ffffff" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$required_attr.'>';
        echo '</div>';
      } elseif ($stype === 'checkbox') {
        $checked = !empty($v) ? 'checked' : '';
        echo '<input type="hidden" name="'.esc_attr($name_attr).'" value="0">';
        echo '<label><input type="checkbox" name="'.esc_attr($name_attr).'" value="1" '.$checked.$required_attr.'> ' . esc_html($label) . '</label>';
    } elseif ($stype === 'url') {
          render_url_input($name_attr, $v, $placeholder_attr, $required_attr);
    } elseif ($stype === 'choice') {
        render_choice_input($name_attr, $s['choices'] ?? [], $s['choice_display'] ?? '', $v, $required_attr, $s['choice_default'] ?? '');
      } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        $sub_use_time = ($stype === 'datetime_picker') ? !array_key_exists('datetime_use_time', $s) || !empty($s['datetime_use_time']) : true;
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype, $sub_use_time);
      } elseif ($stype === 'link') {
        render_link_field($name_attr, $v);
      } elseif ($stype === 'wysiwyg') {
        $editor_id = 'cff_wys_rep_' . sanitize_key($parent) . '_' . sanitize_key($sname) . '_' . $post_id . '_' . $i;

        echo '<textarea
          id="' . esc_attr($editor_id) . '"
          class="widefat cff-wysiwyg"
          name="' . esc_attr($name_attr) . '"'.$required_attr.'
          rows="8"
        >' . esc_textarea($v) . '</textarea>';

        echo '<input type="hidden"
          class="cff-wysiwyg-settings"
          data-editor-id="' . esc_attr($editor_id) . '"
          value="' . esc_attr(wp_json_encode([
            'tinymce' => [
              'wpautop'  => true,
              'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_adv',
              'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
              'menubar'  => false,
            ],
            'quicktags'    => true,
            'mediaButtons' => true,
          ])) . '">';
      } elseif ($stype === 'image' || $stype === 'file') {
        $id = intval($v);
        $url = $id ? wp_get_attachment_url($id) : '';
        $url_attr = $row_prefix . '[' . $sname . '_url]';
        echo '<div class="cff-media cff-media-inline" data-type="'.esc_attr($stype).'">';
        echo '<input type="hidden" class="cff-media-id" name="'.esc_attr($name_attr).'" value="'.esc_attr($id).'">';
        echo '<input type="hidden" class="cff-media-url" name="'.esc_attr($url_attr).'" value="'.esc_attr($url).'">';
        echo '<div class="cff-media-preview">' . cff_media_preview_html($stype, $id) . '</div>';
        cff_render_media_action_buttons();
        echo '</div>';
      } elseif ($stype === 'gallery') {
        cff_render_gallery_field($name_attr, $v);
      } elseif ($stype === 'repeater') {
        $rsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $rows = is_array($v) ? $v : [];
        $min = isset($s['min']) ? (int) $s['min'] : 1;
        $max = isset($s['max']) ? (int) $s['max'] : 0;
        if ($max > 0 && count($rows) > $max) {
          $rows = array_slice($rows, 0, $max);
        }
        $field_key = sanitize_key($s['key'] ?? '');
        if (!$field_key) $field_key = sanitize_key($sname);
        $repeater_prefix = $name_attr;
        $rep_layout = isset($s['repeater_layout']) ? $s['repeater_layout'] : 'default';
        $rep_layout = in_array($rep_layout, ['simple','grid','row','gallery','default'], true) ? $rep_layout : 'default';
        $row_label_field = sanitize_key($s['repeater_row_label'] ?? '');
        $collapsed_default = !empty($s['repeater_collapsed']);
        echo '<div class="cff-repeater" data-field="'.esc_attr($field_key).'" data-min="'.esc_attr($min).'" data-max="'.esc_attr($max).'" data-layout="'.esc_attr($rep_layout).'" data-row-label="'.esc_attr($row_label_field).'" data-collapsed-default="'.($collapsed_default ? '1' : '0').'">';
        echo '<input type="hidden" class="cff-rep-present" name="'.esc_attr($repeater_prefix).'[__cff_present]" value="1">';
        echo '<div class="cff-rep-rows">';
        foreach ($rows as $idx => $row) {
          render_repeater_row($sname, $rsubs, $row, $idx, $post_id, $repeater_prefix, $rep_layout, $row_label_field, $collapsed_default, $root_name, $current_path);
        }
        echo '</div>';
        echo '<p><button type="button" class="button cff-rep-add">Add Row</button></p>';
        echo '<template class="cff-rep-template">';
        render_repeater_row($sname, $rsubs, [], '__INDEX__', $post_id, $repeater_prefix, $rep_layout, $row_label_field, $collapsed_default, $root_name, $current_path);
        echo '</template>';
        echo '</div>';
      } elseif ($stype === 'relational') {
        render_relational_input(
          $name_attr,
          $s['relational_type'] ?? 'post',
          $s['relational_subtype'] ?? '',
          $s['relational_display'] ?? 'select',
          $v,
          !empty($s['relational_multiple']),
          $required_attr
        );
      } elseif ($stype === 'group') {
        $gsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $gvals = is_array($v) ? $v : [];
        $group_prefix = $row_prefix . '[' . $sname . ']';
        echo '<div class="cff-group cff-group-nested">';
        render_group_fields($group_prefix, $gsubs, $gvals, $post_id, $root_name, $current_path);
        echo '</div>';
      } else {
        echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$required_attr.'>';
      }
      cff_render_copy_to_translations_field_action([
        'root' => $root_name ?: sanitize_key($parent),
        'field' => sanitize_key($sname),
        'key' => $sub_field_key,
        'path' => $current_path,
      ]);
      echo '</div>';
    }
    echo '</div></div>';
  }

function render_group_fields($parent_prefix, $subs, $vals, $post_id, $root_name = '', $path = []) {
    foreach ($subs as $s) {
      $sname = $s['name'];
      $stype = $s['type'];
      $label = $s['label'] ?? $sname;
      $v = isset($vals[$sname]) ? $vals[$sname] : '';
      $placeholder = $s['placeholder'] ?? '';
      $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
      $name_attr = $parent_prefix . '[' . $sname . ']';
      $required = !empty($s['required']);
      $label_text = esc_html($label);
      $required_attr = $required ? ' required aria-required="true"' : '';
      if ($required) $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
      $sub_field_key = sanitize_key($s['key'] ?? '');
      if (!$sub_field_key) $sub_field_key = sanitize_key($sname);
      $current_path = array_merge((array) $path, [[
        'name' => sanitize_key($sname),
        'type' => sanitize_key($stype),
      ]]);
      echo '<div class="cff-subfield-input" data-field-name="' . esc_attr(sanitize_key($sname)) . '" data-field-key="' . esc_attr($sub_field_key) . '"' . cff_conditional_attrs($s) . '>';
      echo '<label>'.$label_text.'</label>';
      if ($stype === 'textarea') {
        echo '<textarea class="widefat" rows="3" name="'.esc_attr($name_attr).'"'.$placeholder_attr.$required_attr.'>'.esc_textarea($v).'</textarea>';
      } elseif ($stype === 'color') {
        echo '<div class="cff-color">';
        echo '<input class="cff-color-picker" type="color" value="'.esc_attr($v ?: '#ffffff').'">';
        echo '<input class="widefat cff-color-value" type="text" placeholder="#ffffff" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$required_attr.'>';
        echo '</div>';
      } elseif ($stype === 'checkbox') {
        $checked = !empty($v) ? 'checked' : '';
        echo '<input type="hidden" name="'.esc_attr($name_attr).'" value="0">';
        echo '<label><input type="checkbox" name="'.esc_attr($name_attr).'" value="1" '.$checked.$required_attr.'> ' . esc_html($label) . '</label>';
      } elseif ($stype === 'url') {
        render_url_input($name_attr, $v, $placeholder_attr, $required_attr);
      } elseif ($stype === 'choice') {
        render_choice_input($name_attr, $s['choices'] ?? [], $s['choice_display'] ?? '', $v, $required_attr, $s['choice_default'] ?? '');
      } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        $sub_use_time = ($stype === 'datetime_picker') ? !array_key_exists('datetime_use_time', $s) || !empty($s['datetime_use_time']) : true;
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype, $sub_use_time);
      } elseif ($stype === 'link') {
        render_link_field($name_attr, $v);
      } elseif ($stype === 'wysiwyg') {
        $editor_id = 'cff_wys_group_' . sanitize_key($parent_prefix) . '_' . sanitize_key($sname) . '_' . $post_id;

        echo '<textarea
          id="' . esc_attr($editor_id) . '"
          class="widefat cff-wysiwyg"
          name="' . esc_attr($name_attr) . '"'.$required_attr.'
          rows="8"
        >' . esc_textarea($v) . '</textarea>';

        echo '<input type="hidden"
          class="cff-wysiwyg-settings"
          data-editor-id="' . esc_attr($editor_id) . '"
          value="' . esc_attr(wp_json_encode([
            'tinymce' => [
              'wpautop'  => true,
              'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_adv',
              'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
              'menubar'  => false,
            ],
            'quicktags'    => true,
            'mediaButtons' => true,
          ])) . '">';
      } elseif ($stype === 'image' || $stype === 'file') {
        $id = intval($v);
        $url = $id ? wp_get_attachment_url($id) : '';
        $url_attr = $parent_prefix . '[' . $sname . '_url]';

        echo '<div class="cff-media cff-media-inline" data-type="'.esc_attr($stype).'">';
        echo '<input type="hidden" class="cff-media-id" name="'.esc_attr($name_attr).'" value="'.esc_attr($id).'">';
        echo '<input type="hidden" class="cff-media-url" name="'.esc_attr($url_attr).'" value="'.esc_attr($url).'">';
        echo '<div class="cff-media-preview">' . cff_media_preview_html($stype, $id) . '</div>';
        cff_render_media_action_buttons();
        echo '</div>';
      } elseif ($stype === 'gallery') {
        cff_render_gallery_field($name_attr, $v);
      } elseif ($stype === 'repeater') {
        $rsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $rows = is_array($v) ? $v : [];
        $min = isset($s['min']) ? (int) $s['min'] : 1;
        $max = isset($s['max']) ? (int) $s['max'] : 0;
        if ($max > 0 && count($rows) > $max) {
          $rows = array_slice($rows, 0, $max);
        }
        $field_key = sanitize_key($s['key'] ?? '');
        if (!$field_key) $field_key = sanitize_key($sname);
        $repeater_prefix = $name_attr;
        $rep_layout = isset($s['repeater_layout']) ? $s['repeater_layout'] : 'default';
        $rep_layout = in_array($rep_layout, ['simple','grid','row','gallery','default'], true) ? $rep_layout : 'default';
        $row_label_field = sanitize_key($s['repeater_row_label'] ?? '');
        $collapsed_default = !empty($s['repeater_collapsed']);
        echo '<div class="cff-repeater" data-field="'.esc_attr($field_key).'" data-min="'.esc_attr($min).'" data-max="'.esc_attr($max).'" data-layout="'.esc_attr($rep_layout).'" data-row-label="'.esc_attr($row_label_field).'" data-collapsed-default="'.($collapsed_default ? '1' : '0').'">';
        echo '<input type="hidden" class="cff-rep-present" name="'.esc_attr($repeater_prefix).'[__cff_present]" value="1">';
        echo '<div class="cff-rep-rows">';
        foreach ($rows as $idx => $row) {
          render_repeater_row($sname, $rsubs, $row, $idx, $post_id, $repeater_prefix, $rep_layout, $row_label_field, $collapsed_default, $root_name, $current_path);
        }
        echo '</div>';
        echo '<p><button type="button" class="button cff-rep-add">Add Row</button></p>';
        echo '<template class="cff-rep-template">';
        render_repeater_row($sname, $rsubs, [], '__INDEX__', $post_id, $repeater_prefix, $rep_layout, $row_label_field, $collapsed_default, $root_name, $current_path);
        echo '</template>';
        echo '</div>';
      } elseif ($stype === 'group') {
        $gsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $gvals = is_array($v) ? $v : [];
        echo '<div class="cff-group cff-group-nested">';
        render_group_fields($name_attr, $gsubs, $gvals, $post_id, $root_name, $current_path);
        echo '</div>';
      } elseif ($stype === 'relational') {
        render_relational_input(
          $name_attr,
          $s['relational_type'] ?? 'post',
          $s['relational_subtype'] ?? '',
          $s['relational_display'] ?? 'select',
          $v,
          !empty($s['relational_multiple']),
          $required_attr
        );
      } else {
        echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
      }
      cff_render_copy_to_translations_field_action([
        'root' => $root_name ?: sanitize_key($sname),
        'field' => sanitize_key($sname),
        'key' => $sub_field_key,
        'path' => $current_path,
      ]);
      echo '</div>';
    }
  }

  function cff_resolve_placeholder($value, $type) {
    $value = trim((string) ($value ?? ''));
    if ($value !== '') {
      return $value;
    }

    $defaults = [
      'text' => 'Enter text…',
      'number' => 'Enter number…',
      'textarea' => 'Enter text…',
      'url' => 'https://example.com',
      'link' => 'https://example.com',
      'email' => 'name@example.com',
      'color' => '#ffffff',
      'date_picker' => 'YYYY-MM-DD',
      'datetime_picker' => 'YYYY-MM-DD HH:MM',
      'embed' => '<iframe src="https://xxxxxx" width="560" height="315"></iframe>',
      'choice' => 'Add choice value',
    ];

    $type = sanitize_key($type ?? '');
    return $defaults[$type] ?? '';
  }

  function cff_normalize_link_scalar($value) {
    if (is_scalar($value)) {
      return (string) $value;
    }
    return '';
  }

  function render_link_field($name_attr, $value) {
    $link = is_array($value) ? $value : [];

    $url    = cff_normalize_link_scalar($link['url'] ?? '');
    $target = cff_normalize_link_scalar($link['target'] ?? '');

    $post_type_filter = sanitize_key($link['post_type_filter'] ?? '');
    if (!$post_type_filter) $post_type_filter = 'any';

    $internal_id = isset($link['internal_id']) ? absint($link['internal_id']) : 0;
    $title       = isset($link['title']) ? sanitize_text_field($link['title']) : '';

    // fallback title dari post
    if (!$title && $internal_id) {
      $title = get_the_title($internal_id);
    }

    $mode = sanitize_key($link['mode'] ?? '');
    if (!$mode) $mode = $internal_id ? 'internal' : 'custom';

    if ($internal_id) {
      $p = get_post($internal_id);
      if ($p) {
        if ($title === '') $title = $p->post_title;
        if ($url === '') $url = get_permalink($p);
      }
    }

    $mode_class = ($mode === 'internal') ? ' is-mode-internal' : ' is-mode-custom';

    echo '<div class="cff-link cff-link-picker' . esc_attr($mode_class) . '" data-mode="' . esc_attr($mode) . '">';
    echo '<p class="description">' . esc_html__('Use this for URL + label button, with internal/custom source and target options.', 'cff') . '</p>';

    echo '<div class="cff-link-mode">';
    echo '<label><input type="radio" name="' . esc_attr($name_attr) . '[mode]" value="internal" ' . checked($mode, 'internal', false) . '> Internal</label>';
    echo '<label><input type="radio" name="' . esc_attr($name_attr) . '[mode]" value="custom" ' . checked($mode, 'custom', false) . '> Custom</label>';
    echo '</div>';

    // INTERNAL
    echo '<div class="cff-link-internal">';

    echo '<div class="cff-link-post-type" style="margin-bottom: 10px;">';
    echo '<label>' . esc_html__('Filter by post type', 'cff') . '</label>';
    echo '<select class="cff-input cff-link-post-type-select" name="' . esc_attr($name_attr) . '[post_type_filter]">';
    echo '<option value="any"' . selected($post_type_filter, 'any', false) . '>All post types</option>';

    $post_types = get_post_types(['public' => true], 'objects');
    foreach ($post_types as $pt_slug => $pt_obj) {
      $pt_label = $pt_obj->labels->singular_name ?? $pt_obj->label ?? $pt_slug;
      echo '<option value="' . esc_attr($pt_slug) . '"' . selected($post_type_filter, $pt_slug, false) . '>' . esc_html($pt_label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<select class="cff-link-select" data-post-type="' . esc_attr($post_type_filter) . '" data-placeholder="Search content…">';
    if ($internal_id) {
      $select_label = get_the_title($internal_id);
      if (!$select_label) $select_label = '#' . $internal_id;

      echo '<option value="' . esc_attr($internal_id) . '" selected>' . esc_html($select_label) . '</option>';
    }
    echo '</select>';

    echo '<input type="hidden" class="cff-link-internal-id" name="' . esc_attr($name_attr) . '[internal_id]" value="' . esc_attr($internal_id) . '">';

    // NOTE: class input dibedakan
    echo '<input class="widefat cff-link-title-input cff-title-internal" type="text" placeholder="Title" name="' . esc_attr($name_attr) . '[title]" value="' . esc_attr($title) . '" style="margin-top: 10px;">';

    echo '</div>';

    // CUSTOM
    echo '<div class="cff-link-custom">';
    echo '<div class="cff-link-url">';
    echo '<input class="widefat cff-url-custom" type="url" placeholder="URL" name="' . esc_attr($name_attr) . '[url]" value="' . esc_attr($url) . '">';
    echo '</div>';

    // wrapper class dibedakan, input class dibedakan
    echo '<div class="cff-link-title-wrap">';
    echo '<input class="widefat cff-link-title-input cff-title-custom" type="text" placeholder="Title" name="' . esc_attr($name_attr) . '[title]" value="' . esc_attr($title) . '" style="margin-top: 10px;">';
    echo '</div>';

    echo '</div>';

    echo '<div class="cff-link-target">';
    echo '<input type="hidden" name="' . esc_attr($name_attr) . '[target]" value="">';
    echo '<br>';
    echo '<label><input type="checkbox" name="' . esc_attr($name_attr) . '[target]" value="_blank" ' . checked($target, '_blank', false) . '> Open in new tab</label>';
    echo '</div>';

    echo '</div>';
  }

  function render_flexible_row($parent, $layouts, $layout_map, $row, $i, $post_id, $root_name = '', $path = []) {
    $layout = isset($row['layout']) ? sanitize_key($row['layout']) : '';
    $fields = isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : [];
    $l = isset($layout_map[$layout]) ? $layout_map[$layout] : null;
    $label = $l ? ($l['label'] ?? $layout) : $layout;
    $row_id = ($i === '__INDEX__') ? '__ROWID__' : cff_generate_row_id($row['__cff_row_id'] ?? '');

    echo '<div class="cff-flex-row" data-i="'.esc_attr($i).'" data-layout="'.esc_attr($layout).'" data-row-id="'.esc_attr($row_id).'">';
    echo '<div class="cff-flex-head"><strong>'.esc_html($label).'</strong> <span class="cff-pill">'.esc_html($layout).'</span> ';
    echo '<button type="button" class="button-link cff-flex-remove">Remove</button></div>';
    echo '<input type="hidden" name="cff_values['.esc_attr($parent).']['.esc_attr($i).'][layout]" value="'.esc_attr($layout).'">';
    echo '<input type="hidden" class="cff-row-id" name="cff_values['.esc_attr($parent).']['.esc_attr($i).'][__cff_row_id]" value="'.esc_attr($row_id).'">';
    echo '<div class="cff-flex-body">';
    if ($l) {
      $layout_path = array_merge((array) $path, [[
        'name' => sanitize_key($layout),
        'type' => 'layout',
      ]]);
      foreach (($l['sub_fields'] ?? []) as $sf) {
        $sname = $sf['name'];
        $stype = $sf['type'];
        $slabel = $sf['label'] ?? $sname;
        $v = $fields[$sname] ?? '';
        $name_attr = 'cff_values['.$parent.']['.$i.'][fields]['.$sname.']';
        $placeholder = $sf['placeholder'] ?? '';
        $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
        $required = !empty($sf['required']);
        $label_text = esc_html($slabel);
        $required_attr = $required ? ' required aria-required="true"' : '';
        if ($required) $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
        $sub_field_key = sanitize_key($sf['key'] ?? '');
        if (!$sub_field_key) $sub_field_key = sanitize_key($sname);
        $current_path = array_merge($layout_path, [[
          'name' => sanitize_key($sname),
          'type' => sanitize_key($stype),
        ]]);
        echo '<div class="cff-subfield-input" data-field-name="' . esc_attr(sanitize_key($sname)) . '" data-field-key="' . esc_attr($sub_field_key) . '"' . cff_conditional_attrs($sf) . '>';
        echo '<label>'.$label_text.'</label>';
        if ($stype === 'textarea') {
          echo '<textarea class="widefat" rows="4" name="'.esc_attr($name_attr).'"'.$placeholder_attr.$required_attr.'>'.esc_textarea($v).'</textarea>';
        } elseif ($stype === 'color') {
          echo '<div class="cff-color">';
          echo '<input class="cff-color-picker" type="color" value="'.esc_attr($v ?: '#ffffff').'">';
          echo '<input class="widefat cff-color-value" type="text" placeholder="#ffffff" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$required_attr.'>';
          echo '</div>';
        } elseif ($stype === 'checkbox') {
          $checked = !empty($v) ? 'checked' : '';
          echo '<input type="hidden" name="'.esc_attr($name_attr).'" value="0">';
          echo '<label><input type="checkbox" name="'.esc_attr($name_attr).'" value="1" '.$checked.$required_attr.'> ' . esc_html($slabel) . '</label>';
        } elseif ($stype === 'url') {
          render_url_input($name_attr, $v, $placeholder_attr, $required_attr);
        } elseif ($stype === 'choice') {
          render_choice_input($name_attr, $sf['choices'] ?? [], $sf['choice_display'] ?? '', $v, $required_attr, $sf['choice_default'] ?? '');
        } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        $sub_use_time = ($stype === 'datetime_picker') ? !array_key_exists('datetime_use_time', $sf) || !empty($sf['datetime_use_time']) : true;
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype, $sub_use_time);
      } elseif ($stype === 'link') {
        render_link_field($name_attr, $v);
        } elseif ($stype === 'wysiwyg') {
          $editor_id = 'cff_wys_flex_' . sanitize_key($parent) . '_' . sanitize_key($layout) . '_' . sanitize_key($sname) . '_' . $post_id . '_' . $i;

          echo '<textarea'
            . ' id="' . esc_attr($editor_id) . '"'
            . ' class="widefat cff-wysiwyg"'
            . ' rows="6"'
            . ' name="' . esc_attr($name_attr) . '"'.$required_attr.''
            . '>' . esc_textarea($v) . '</textarea>';

          echo '<input type="hidden"'
            . ' class="cff-wysiwyg-settings"'
            . ' data-editor-id="' . esc_attr($editor_id) . '"'
            . ' value="' . esc_attr(wp_json_encode([
                'tinymce' => true,
                'quicktags' => true,
                'mediaButtons' => true,
              ])) . '">';
        } elseif ($stype === 'image' || $stype === 'file') {
          $id = intval($v);
          $url = $id ? wp_get_attachment_url($id) : '';
          $url_attr = 'cff_values['.$parent.']['.$i.'][fields]['.$sname.'_url]';
          echo '<div class="cff-media cff-media-inline" data-type="'.esc_attr($stype).'">';
          echo '<input type="hidden" class="cff-media-id" name="'.esc_attr($name_attr).'" value="'.esc_attr($id).'">';
          echo '<input type="hidden" class="cff-media-url" name="'.esc_attr($url_attr).'" value="'.esc_attr($url).'">';
          echo '<div class="cff-media-preview">' . cff_media_preview_html($stype, $id) . '</div>';
          cff_render_media_action_buttons();
          echo '</div>';
        } elseif ($stype === 'gallery') {
          cff_render_gallery_field($name_attr, $v);
        } elseif ($stype === 'group') {
          $gsubs = isset($sf['sub_fields']) ? $sf['sub_fields'] : [];
          $gvals = is_array($v) ? $v : [];
          $group_prefix = 'cff_values['.$parent.']['.$i.'][fields]['.$sname.']';
          echo '<div class="cff-group cff-group-nested">';
          render_group_fields($group_prefix, $gsubs, $gvals, $post_id, $root_name, $current_path);
          echo '</div>';
        } else {
          echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
        }
        cff_render_copy_to_translations_field_action([
          'root' => $root_name ?: sanitize_key($parent),
          'field' => sanitize_key($sname),
          'key' => $sub_field_key,
          'path' => $current_path,
        ]);
        echo '</div>';
      }
    } else {
      echo '<div class="cff-muted">Layout not found in group settings.</div>';
    }
    echo '</div></div>';
  }

  function save_content_fields_impl($plugin, $post_id, $post) {
    if ($post->post_type === 'cff_group') return;
    if (!isset($_POST['cff_content_nonce']) || !wp_verify_nonce($_POST['cff_content_nonce'], 'cff_content_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $copy_all_to_translations = !empty($_POST['cff_copy_all_to_translations_trigger']);
    $copy_field_to_translations = isset($_POST['cff_copy_field_to_translations']) ? wp_unslash((string) $_POST['cff_copy_field_to_translations']) : '';

    if (isset($_POST['cff_group_field_order']) && is_array($_POST['cff_group_field_order'])) {
      foreach ((array) $_POST['cff_group_field_order'] as $group_id => $raw_order) {
        $group_id = absint($group_id);
        if (!$group_id) continue;

        $items = [];
        $raw_order = wp_unslash($raw_order);
        if (is_array($raw_order)) {
          $items = array_map('sanitize_key', $raw_order);
        } elseif (is_string($raw_order)) {
          $items = array_map('sanitize_key', explode(',', $raw_order));
        }
        $items = array_values(array_filter(array_unique($items)));

        $meta_key = '_cff_group_field_order_' . $group_id;
        if ($items) {
          update_post_meta($post_id, $meta_key, $items);
        } else {
          delete_post_meta($post_id, $meta_key);
        }
      }
    }

    if (!isset($_POST['cff_values']) || !is_array($_POST['cff_values'])) {
      if ($copy_all_to_translations) {
        cff_copy_values_to_polylang_translations($post_id);
      } elseif ($copy_field_to_translations) {
        cff_copy_field_to_polylang_translations($plugin, $post_id, $copy_field_to_translations);
      }
      return;
    }

    $vals = $_POST['cff_values'];

    foreach ($vals as $name => $value) {
      $name = sanitize_key($name);
      $key  = $plugin->meta_key($name);

      if (is_array($value)) {
        $existing = get_post_meta($post_id, $key, true);
        if (is_array($existing) && is_assoc_array($value) && is_assoc_array($existing)) {
          $value = deep_merge_assoc($existing, $value);
        }
        $value = deep_sanitize($value);

        // ✅ PENTING: kalau repeater/flex sudah kosong -> hapus meta lama
        if (empty($value)) {
          delete_post_meta($post_id, $key);
          continue;
        }
      } else {
        $value = wp_kses_post((string) $value);

        // opsional: kalau string kosong, hapus
        if ($value === '') {
          delete_post_meta($post_id, $key);
          continue;
        }
      }

      update_post_meta($post_id, $key, $value);
    }

    if ($copy_all_to_translations) {
      cff_copy_values_to_polylang_translations($post_id);
    } elseif ($copy_field_to_translations) {
      cff_copy_field_to_polylang_translations($plugin, $post_id, $copy_field_to_translations);
    }
  }

  function cff_store_copy_to_translations_result($status) {
    if (!is_user_logged_in()) return;
    set_transient('cff_copy_to_translations_result_' . get_current_user_id(), sanitize_key((string) $status), MINUTE_IN_SECONDS * 10);
  }

  function cff_copy_values_to_polylang_translations($post_id) {
    $source_meta = get_post_meta($post_id);
    if (!is_array($source_meta)) {
      $source_meta = [];
    }

    $cff_meta = [];
    foreach ($source_meta as $meta_key => $meta_values) {
      if (strpos((string) $meta_key, '_cff_') !== 0) continue;
      $cff_meta[$meta_key] = is_array($meta_values) ? $meta_values : [];
    }

    return cff_copy_meta_to_polylang_translations($post_id, $cff_meta, 'copied_all');
  }

  function cff_row_match_key($row, $index = 0, $layout_name = '') {
    if (!is_array($row)) {
      return 'idx:' . intval($index);
    }
    $row_id = sanitize_key($row['__cff_row_id'] ?? '');
    if ($layout_name !== '') {
      $layout_name = sanitize_key($layout_name);
    } else {
      $layout_name = sanitize_key($row['layout'] ?? '');
    }
    if ($row_id) {
      return ($layout_name ? $layout_name . ':' : '') . $row_id;
    }
    return ($layout_name ? $layout_name . ':' : 'idx:') . intval($index);
  }

  function cff_remove_nested_field_value($target, $segments) {
    if (!is_array($target) || empty($segments)) {
      return is_array($target) ? $target : [];
    }

    $segment = array_shift($segments);
    $name = sanitize_key($segment['name'] ?? '');
    $type = sanitize_key($segment['type'] ?? '');
    if (!$name || !$type) return $target;

    if ($type === 'repeater') {
      if (!isset($target[$name]) || !is_array($target[$name])) {
        return $target;
      }
      if (empty($segments)) {
        unset($target[$name]);
        return $target;
      }
      foreach ($target[$name] as $index => $row) {
        if (!is_array($row)) continue;
        $target[$name][$index] = cff_remove_nested_field_value($row, $segments);
      }
      return $target;
    }

    if ($type === 'layout') {
      if (empty($segments)) {
        return is_array($target) ? $target : [];
      }
      foreach ($target as $index => $row) {
        if (!is_array($row)) continue;
        $row_layout = sanitize_key($row['layout'] ?? '');
        if ($row_layout !== $name) continue;
        $row_fields = isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : [];
        $target[$index]['fields'] = cff_remove_nested_field_value($row_fields, $segments);
      }
      return $target;
    }

    if (empty($segments)) {
      unset($target[$name]);
      return $target;
    }

    $child = isset($target[$name]) && is_array($target[$name]) ? $target[$name] : [];
    $target[$name] = cff_remove_nested_field_value($child, $segments);
    return $target;
  }

  function cff_copy_nested_field_value($source, $target, $segments) {
    if (empty($segments)) {
      return $source;
    }

    if (!is_array($target)) {
      $target = [];
    }
    if (!is_array($source)) {
      $source = [];
    }

    $segment = array_shift($segments);
    $name = sanitize_key($segment['name'] ?? '');
    $type = sanitize_key($segment['type'] ?? '');
    if (!$name || !$type) return $target;

    if ($type === 'repeater') {
      $source_rows = isset($source[$name]) && is_array($source[$name]) ? $source[$name] : [];
      $target_rows = isset($target[$name]) && is_array($target[$name]) ? $target[$name] : [];

      if (empty($segments)) {
        if ($source_rows) {
          $target[$name] = $source_rows;
        } else {
          unset($target[$name]);
        }
        return $target;
      }

      $target_index_map = [];
      foreach ($target_rows as $index => $target_row) {
        $target_index_map[cff_row_match_key($target_row, $index)] = $index;
      }

      foreach ($source_rows as $index => $source_row) {
        $match_key = cff_row_match_key($source_row, $index);
        $target_index = array_key_exists($match_key, $target_index_map) ? $target_index_map[$match_key] : null;
        $target_row = ($target_index !== null && isset($target_rows[$target_index]) && is_array($target_rows[$target_index])) ? $target_rows[$target_index] : [];
        $merged_row = cff_copy_nested_field_value($source_row, $target_row, $segments);
        $merged_row['__cff_row_id'] = cff_generate_row_id($source_row['__cff_row_id'] ?? $target_row['__cff_row_id'] ?? '');
        if ($target_index !== null) {
          $target_rows[$target_index] = $merged_row;
        } else {
          $target_rows[] = $merged_row;
        }
      }

      foreach ($target_rows as $index => $target_row) {
        $match_key = cff_row_match_key($target_row, $index);
        if (isset($target_index_map[$match_key]) && !array_filter($source_rows, function($source_row, $source_index) use ($match_key) {
          return cff_row_match_key($source_row, $source_index) === $match_key;
        }, ARRAY_FILTER_USE_BOTH)) {
          if (is_array($target_row)) {
            $target_rows[$index] = cff_remove_nested_field_value($target_row, $segments);
          }
        }
      }

      $target[$name] = $target_rows;
      return $target;
    }

    if ($type === 'layout') {
      $source_rows = is_array($source) ? $source : [];
      $target_rows = is_array($target) ? $target : [];
      $target_index_map = [];
      foreach ($target_rows as $index => $target_row) {
        if (!is_array($target_row)) continue;
        if (sanitize_key($target_row['layout'] ?? '') !== $name) continue;
        $target_index_map[cff_row_match_key($target_row, $index, $name)] = $index;
      }

      foreach ($source_rows as $index => $source_row) {
        if (!is_array($source_row)) continue;
        if (sanitize_key($source_row['layout'] ?? '') !== $name) continue;
        $match_key = cff_row_match_key($source_row, $index, $name);
        $target_index = array_key_exists($match_key, $target_index_map) ? $target_index_map[$match_key] : null;
        $target_row = ($target_index !== null && isset($target_rows[$target_index]) && is_array($target_rows[$target_index])) ? $target_rows[$target_index] : [
          'layout' => $name,
          '__cff_row_id' => cff_generate_row_id($source_row['__cff_row_id'] ?? ''),
          'fields' => [],
        ];
        $source_fields = isset($source_row['fields']) && is_array($source_row['fields']) ? $source_row['fields'] : [];
        $target_fields = isset($target_row['fields']) && is_array($target_row['fields']) ? $target_row['fields'] : [];
        $target_row['fields'] = cff_copy_nested_field_value($source_fields, $target_fields, $segments);
        $target_row['layout'] = $name;
        $target_row['__cff_row_id'] = cff_generate_row_id($source_row['__cff_row_id'] ?? $target_row['__cff_row_id'] ?? '');
        if ($target_index !== null) {
          $target_rows[$target_index] = $target_row;
        } else {
          $target_rows[] = $target_row;
        }
      }

      foreach ($target_rows as $index => $row) {
        if (!is_array($row)) continue;
        $target_layout = sanitize_key($row['layout'] ?? '');
        if ($target_layout !== $name) continue;
        $match_key = cff_row_match_key($row, $index, $name);
        $exists = false;
        foreach ($source_rows as $source_index => $source_row) {
          if (!is_array($source_row)) continue;
          if (sanitize_key($source_row['layout'] ?? '') !== $name) continue;
          if (cff_row_match_key($source_row, $source_index, $name) === $match_key) {
            $exists = true;
            break;
          }
        }
        if (!$exists) {
          $target_fields = isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : [];
          $target_rows[$index]['fields'] = cff_remove_nested_field_value($target_fields, $segments);
        }
      }

      return $target_rows;
    }

    $source_value = $source[$name] ?? null;
    if (empty($segments)) {
      if (array_key_exists($name, $source)) {
        $target[$name] = $source_value;
      } else {
        unset($target[$name]);
      }
      return $target;
    }

    $target_child = isset($target[$name]) && is_array($target[$name]) ? $target[$name] : [];
    $source_child = is_array($source_value) ? $source_value : [];
    $target[$name] = cff_copy_nested_field_value($source_child, $target_child, $segments);
    return $target;
  }

  function cff_copy_field_to_polylang_translations($plugin, $post_id, $field_request_raw) {
    $request = cff_decode_copy_field_request($field_request_raw);
    $root_name = sanitize_key($request['root'] ?? '');
    if (!$root_name) {
      return '';
    }

    $meta_key = $plugin->meta_key($root_name);
    $path = isset($request['path']) && is_array($request['path']) ? $request['path'] : [];

    if (!$path) {
      $meta_values = get_post_meta($post_id, $meta_key, false);
      $cff_meta = [
        $meta_key => is_array($meta_values) ? $meta_values : [],
      ];
      return cff_copy_meta_to_polylang_translations($post_id, $cff_meta, 'copied_field');
    }

    if (!function_exists('pll_get_post_translations')) {
      cff_store_copy_to_translations_result('missing_polylang');
      return 'missing_polylang';
    }

    $translations = pll_get_post_translations($post_id);
    if (!is_array($translations) || count($translations) < 2) {
      cff_store_copy_to_translations_result('missing_translation_page');
      return 'missing_translation_page';
    }

    $source_root = get_post_meta($post_id, $meta_key, true);
    if (!is_array($source_root)) {
      $source_root = [];
    }

    $copied = 0;
    foreach ($translations as $translated_post_id) {
      $translated_post_id = absint($translated_post_id);
      if (!$translated_post_id || $translated_post_id === absint($post_id)) continue;

      $target_root = get_post_meta($translated_post_id, $meta_key, true);
      if (!is_array($target_root)) {
        $target_root = [];
      }

      $merged = cff_copy_nested_field_value($source_root, $target_root, $path);
      if (empty($merged)) {
        delete_post_meta($translated_post_id, $meta_key);
      } else {
        update_post_meta($translated_post_id, $meta_key, $merged);
      }
      $copied++;
    }

    $status = $copied > 0 ? 'copied_field' : 'missing_translation_page';
    cff_store_copy_to_translations_result($status);
    return $status;
  }

  function cff_copy_meta_to_polylang_translations($post_id, $cff_meta, $success_status = 'copied_all') {
    if (!function_exists('pll_get_post_translations')) {
      cff_store_copy_to_translations_result('missing_polylang');
      return 'missing_polylang';
    }

    $translations = pll_get_post_translations($post_id);
    if (!is_array($translations) || count($translations) < 2) {
      cff_store_copy_to_translations_result('missing_translation_page');
      return 'missing_translation_page';
    }

    $success_status = sanitize_key($success_status ?: 'copied_all');
    $copied = 0;
    foreach ($translations as $translated_post_id) {
      $translated_post_id = absint($translated_post_id);
      if (!$translated_post_id || $translated_post_id === absint($post_id)) continue;

      foreach ($cff_meta as $meta_key => $meta_values) {
        delete_post_meta($translated_post_id, $meta_key);
      }

      foreach ($cff_meta as $meta_key => $meta_values) {
        foreach ((array) $meta_values as $meta_value) {
          add_post_meta($translated_post_id, $meta_key, maybe_unserialize($meta_value));
        }
      }

      $copied++;
    }

    $status = $copied > 0 ? $success_status : 'missing_translation_page';
    cff_store_copy_to_translations_result($status);
    return $status;
  }

  function deep_sanitize($v) {
    if (is_array($v)) {
      $out = [];
      foreach ($v as $k => $val) {
        if ($val === '__INDEX__') continue;

        if ($k === '__cff_present') continue;

        if ($val === '__cff_choice_empty__') continue;

        if ($k === '__cff_row_id') {
          $row_id = sanitize_key($val);
          if ($row_id !== '') {
            $out[$k] = $row_id;
          }
          continue;
        }

        $out[$k] = deep_sanitize($val);
      }
      return $out;
    }
    return wp_kses_post((string) $v);
  }

  function is_assoc_array($arr) {
    if (!is_array($arr)) return false;
    $keys = array_keys($arr);
    return $keys !== range(0, count($keys) - 1);
  }

  function deep_merge_assoc($base, $override) {
    foreach ($override as $k => $v) {
      if (is_array($v) && isset($base[$k]) && is_array($base[$k])
        && is_assoc_array($v) && is_assoc_array($base[$k])) {
        $base[$k] = deep_merge_assoc($base[$k], $v);
      } else {
        $base[$k] = $v;
      }
    }
    return $base;
  }

  function cff_resolve_relational_subtype($rel_type, $rel_subtype) {
    if ($rel_type === 'post_type') {
      $resolved = cff_normalize_post_type_slug($rel_subtype);
      if ($resolved) return $resolved;
      return 'post';
    }
    if ($rel_type === 'taxonomy' && $rel_subtype) {
      return sanitize_key($rel_subtype);
    }
    if ($rel_type === 'taxonomy') {
      return 'category';
    }
    return sanitize_key($rel_subtype ?? '');
  }

  function cff_normalize_post_type_slug($slug) {
    $slug = sanitize_key($slug ?? '');
    if (!$slug) return '';
    $pt = get_post_type_object($slug);
    if ($pt) return $pt->name;

    $types = get_post_types(['public' => true], 'objects');
    foreach ($types as $name => $obj) {
      if (isset($obj->rewrite['slug']) && sanitize_key($obj->rewrite['slug']) === $slug) {
        return $name;
      }
      $label = $obj->labels->singular_name ?? $obj->labels->name ?? '';
      if ($label && sanitize_key($label) === $slug) {
        return $name;
      }
    }
    return '';
  }

  function render_relational_input($name_attr, $rel_type, $rel_subtype, $rel_display, $value, $rel_multiple, $required_attr) {
    $rel_subtype = cff_resolve_relational_subtype($rel_type, $rel_subtype);
    $options = cff_get_relational_items($rel_type, $rel_subtype);

    if (empty($options)) {
      echo '<div class="cff-muted">' . esc_html__('No items available', 'cff') . '</div>';
      return;
    }

    $selected = $rel_multiple ? (is_array($value) ? array_map('strval', $value) : []) : (string)$value;

    if ($rel_display === 'select') {
      $name_suffix = $rel_multiple ? '[]' : '';
      $rel_subtype = cff_resolve_relational_subtype($rel_type, $rel_subtype);
      echo '<select class="widefat cff-relational-select cff-select2" name="'.esc_attr($name_attr).$name_suffix.'"' . ($rel_multiple ? ' multiple' : '') . $required_attr
        . ' data-placeholder="' . esc_attr__('Select...', 'cff') . '"'
        . ' data-relational-type="'.esc_attr($rel_type).'"'
        . ' data-relational-subtype="'.esc_attr($rel_subtype).'"'
        . ' data-relational-display="'.esc_attr($rel_display).'"'
        . ' data-relational-multiple="'.($rel_multiple ? '1':'0').'"'
        . '>';
      if (!$rel_multiple) echo '<option value="">' . esc_html__('Select...', 'cff') . '</option>';
      foreach ($options as $id => $title) {
        $is_selected = $rel_multiple ? in_array((string)$id, $selected, true) : ((string)$selected === (string)$id);
        $selected_attr = $is_selected ? ' selected' : '';
        echo '<option value="'.esc_attr($id).'"'.$selected_attr.'>'.esc_html($title).'</option>';
      }
      echo '</select>';
      return;
    }

    if ($rel_display === 'checkbox' && $rel_multiple) {
      echo '<div class="cff-relational-checkboxes">';
      echo '<input type="hidden" name="'.esc_attr($name_attr).'[]" value="__cff_rel_empty__">';
      foreach ($options as $id => $title) {
        $checked = in_array((string)$id, $selected, true) ? ' checked' : '';
        echo '<label><input type="checkbox" name="'.esc_attr($name_attr).'[]" value="'.esc_attr($id).'"'.$checked.'> '.esc_html($title).'</label>';
      }
      echo '</div>';
      return;
    }

    if ($rel_display === 'radio' && !$rel_multiple) {
      echo '<div class="cff-relational-radios">';
      foreach ($options as $id => $title) {
        $checked = (string)$selected === (string)$id ? ' checked' : '';
        echo '<label><input type="radio" name="'.esc_attr($name_attr).'" value="'.esc_attr($id).'"'.$checked.$required_attr.'> '.esc_html($title).'</label>';
      }
      echo '</div>';
      return;
    }

    // Fallback to select
    $rel_subtype = cff_resolve_relational_subtype($rel_type, $rel_subtype);
    echo '<select class="widefat cff-relational-select cff-select2" name="'.esc_attr($name_attr).'"'.$required_attr
      . ' data-placeholder="' . esc_attr__('Select...', 'cff') . '"'
      . ' data-relational-type="'.esc_attr($rel_type).'"'
      . ' data-relational-subtype="'.esc_attr($rel_subtype).'"'
      . ' data-relational-display="'.esc_attr($rel_display).'"'
      . ' data-relational-multiple="'.($rel_multiple ? '1':'0').'"'
      . '>';
    echo '<option value="">' . esc_html__('Select...', 'cff') . '</option>';
    foreach ($options as $id => $title) {
      $selected_attr = (string)$selected === (string)$id ? ' selected' : '';
      echo '<option value="'.esc_attr($id).'"'.$selected_attr.'>'.esc_html($title).'</option>';
    }
    echo '</select>';
  }

  function cff_get_relational_items($type, $subtype = '') {
    $items = [];

    if ($type === 'post') {
      // Get posts only
      $args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false
      ];
      $posts = get_posts($args);
      if (!empty($posts)) {
        foreach ($posts as $p) {
          $items[$p->ID] = !empty($p->post_title) ? $p->post_title : '(no title)';
        }
      }
    } elseif ($type === 'page') {
      // Get pages only
      $args = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false
      ];
      $posts = get_posts($args);
      if (!empty($posts)) {
        foreach ($posts as $p) {
          $items[$p->ID] = !empty($p->post_title) ? $p->post_title : '(no title)';
        }
      }
    } elseif ($type === 'post_and_page') {
      // Get posts and pages
      $post_types = ['post', 'page'];
      $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false
      ];
      $posts = get_posts($args);
      if (!empty($posts)) {
        foreach ($posts as $p) {
          $items[$p->ID] = !empty($p->post_title) ? $p->post_title : '(no title)';
        }
      }
    } elseif ($type === 'post_type' && $post_type = cff_normalize_post_type_slug($subtype)) {
      // Get items from specific custom post type
      $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false
      ];
      $posts = get_posts($args);
      if (!empty($posts)) {
        foreach ($posts as $p) {
          $items[$p->ID] = !empty($p->post_title) ? $p->post_title : '(no title)';
        }
      }
    } elseif ($type === 'taxonomy' && !empty($subtype)) {
      // Get terms from specific taxonomy
      $terms = get_terms([
        'taxonomy' => sanitize_key($subtype),
        'hide_empty' => false,
        'number' => 200,
        'orderby' => 'name',
      ]);
      if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
          $items[$term->term_id] = $term->name;
        }
      }
    } elseif ($type === 'user') {
      // Get users
      $users = get_users([
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => 200,
      ]);
      if (!empty($users)) {
        foreach ($users as $user) {
          $items[$user->ID] = $user->display_name;
        }
      }
    }

    return $items;
  }

}
