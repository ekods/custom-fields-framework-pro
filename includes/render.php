<?php
namespace CFF;
if (!defined('ABSPATH')) exit;

if (!function_exists(__NAMESPACE__ . '\render_field_impl')) {
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
          'style' => 'max-width:150px;height:auto;display:block;'
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


  function render_field_impl($plugin, $post, $f) {
    $type = $f['type'];
    $name = $f['name'];
    $label = $f['label'] ?? $name;
    $key = $plugin->meta_key($name);
    $val = get_post_meta($post->ID, $key, true);

    $required = !empty($f['required']);
    $required_attr = $required ? ' required aria-required="true"' : '';
    $label_text = esc_html($label);
    if ($required) {
      $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
    }
    $placeholder = $f['placeholder'] ?? '';
    $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';

    $is_accordion = ($type === 'repeater');
    $field_classes = 'cff-field cff-field-' . $type . ($is_accordion ? ' postbox' : '');
    echo '<div class="' . esc_attr($field_classes) . '">';
    $type_label = ucfirst(str_replace('_', ' ', $type));
    if ($is_accordion) {
      echo '<div class="postbox-header">';
      echo '<h2 class="hndle">'.$label_text.'</h2>';
      echo '<div class="handle-actions hide-if-no-js">';
      echo '<button type="button" class="handlediv" aria-expanded="false">';
      echo '<span class="screen-reader-text">'.esc_html__('Toggle panel', 'cff').'</span>';
      echo '<span class="toggle-indicator" aria-hidden="true"></span>';
      echo '</button>';
      echo '</div>';
      echo '</div>';
      echo '<div class="inside cff-input">';
      echo '<div class="description cff-meta-type">Type <b>'.esc_html($type_label).'</b></div>';
      echo '<div class="description cff-meta-name">'.esc_html($name).'</div>';
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

    if ($type === 'text') {
      echo '<input class="widefat" type="text" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
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
      echo '<input class="widefat" type="url" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
    } elseif ($type === 'relational') {
      echo '<input class="widefat" type="text" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
    } elseif ($type === 'choice') {
      render_choice_input('cff_values['.esc_attr($name).']', $f['choices'] ?? [], $f['choice_display'] ?? '', $val, $required_attr);
    } elseif ($type === 'date_picker' || $type === 'datetime_picker') {
      $name_attr = 'cff_values[' . sanitize_key($name) . ']';
      render_picker_input($name_attr, $val, $placeholder_attr, $required_attr, $type);
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
      echo '<p><button type="button" class="button cff-media-select">Select</button> <button type="button" class="button cff-media-clear">Clear</button></p>';
      echo '</div>';
    } elseif ($type === 'repeater') {
      $rows = is_array($val) ? $val : [];
      $subs = isset($f['sub_fields']) ? $f['sub_fields'] : [];

      $min = isset($f['min']) ? (int) $f['min'] : 1;
      $max = isset($f['max']) ? (int) $f['max'] : 0;

      echo '<div class="cff-repeater"
        data-field="'.esc_attr($f['key'] ?? '').'"
        data-min="'.esc_attr($min).'"
        data-max="'.esc_attr($max).'"
      >';

      // ✅ FLAG: supaya key repeater tetap ada di $_POST walau 0 row
      echo '<input type="hidden" class="cff-rep-present" name="cff_values['.esc_attr($name).'][__cff_present]" value="1">';

      echo '<div class="cff-rep-rows">';
      foreach ($rows as $i => $row) {
        render_repeater_row($name, $subs, $row, $i, $post->ID);
      }
      echo '</div>';

      echo '<p><button type="button" class="button cff-rep-add">Add Row</button></p>';

      echo '<script type="text/template" class="cff-rep-template">';
      render_repeater_row($name, $subs, [], '__INDEX__', $post->ID);
      echo '</script>';

      echo '</div>';
    } elseif ($type === 'group') {
      $vals = is_array($val) ? $val : [];
      $subs = isset($f['sub_fields']) ? $f['sub_fields'] : [];
      echo '<div class="cff-group">';
      render_group_fields('cff_values[' . $name . ']', $subs, $vals, $post->ID);
      echo '</div>';
    } elseif ($type === 'flexible') {
      $rows = is_array($val) ? $val : [];
      $layouts = isset($f['layouts']) ? $f['layouts'] : [];
      $layout_map = [];
      foreach ($layouts as $l) $layout_map[$l['name']] = $l;

      echo '<div class="cff-flexible" data-field="'.esc_attr($name).'">';
      echo '<div class="cff-flex-rows">';
      foreach ($rows as $i => $row) {
        render_flexible_row($name, $layouts, $layout_map, $row, $i, $post->ID);
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
        echo '<script type="text/template" class="cff-flex-template" data-layout="'.esc_attr($l['name']).'">';
        render_flexible_row($name, $layouts, $layout_map, ['layout'=>$l['name'], 'fields'=>[]], '__INDEX__', $post->ID);
        echo '</script>';
      }
      echo '</div>';

      echo '</div>';
    } else {
      echo '<input class="widefat" type="text" name="cff_values['.esc_attr($name).']" value="'.esc_attr($val).'"'.$placeholder_attr.$required_attr.'>';
    }

    echo '</div></div>';
  }

  function render_picker_input($name_attr, $value, $placeholder_attr, $required_attr, $type) {
    $input_type = $type === 'datetime_picker' ? 'datetime-local' : 'date';
    printf(
      '<input class="widefat" type="%s" name="%s" value="%s"%s%s>',
      esc_attr($input_type),
      esc_attr($name_attr),
      esc_attr($value),
      $placeholder_attr,
      $required_attr
    );
  }

  function render_choice_input($name_attr, $choices, $display, $value, $required_attr) {
    $display = sanitize_key($display ?? '');
    $allowed = ['select','checkbox','radio','button_group','true_false'];
    if (!in_array($display, $allowed, true)) $display = 'select';

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

  function render_repeater_row($parent, $subs, $row, $i, $post_id) {
    echo '<div class="cff-rep-row" data-i="'.esc_attr($i).'">';
    echo '<div class="cff-rep-row-head"><div class="cff-rep-left"><span class="cff-rep-drag" title="Drag"></span><button type="button" class="cff-rep-toggle" title="Collapse"></button><strong>Row</strong></div><button type="button" class="button-link cff-rep-remove">Remove</button></div>';
    echo '<div class="cff-rep-row-body">';
    foreach ($subs as $s) {
      $sname = $s['name'];
      $stype = $s['type'];
      $label = $s['label'] ?? $sname;
      $v = isset($row[$sname]) ? $row[$sname] : '';
      $placeholder = $s['placeholder'] ?? '';
      $placeholder_attr = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
      $name_attr = 'cff_values['.$parent.']['.$i.']['.$sname.']';
      $required = !empty($s['required']);
      $label_text = esc_html($label);
      $required_attr = $required ? ' required aria-required="true"' : '';
      if ($required) $label_text .= ' <span class="cff-required-indicator" aria-hidden="true">*</span>';
      echo '<div class="cff-subfield-input">';
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
        echo '<input class="widefat" type="url" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
      } elseif ($stype === 'choice') {
        render_choice_input($name_attr, $s['choices'] ?? [], $s['choice_display'] ?? '', $v, $required_attr);
      } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype);
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
        $url_attr = 'cff_values['.$parent.']['.$i.']['.$sname.'_url]';
        echo '<div class="cff-media cff-media-inline" data-type="'.esc_attr($stype).'">';
        echo '<input type="hidden" class="cff-media-id" name="'.esc_attr($name_attr).'" value="'.esc_attr($id).'">';
        echo '<input type="hidden" class="cff-media-url" name="'.esc_attr($url_attr).'" value="'.esc_attr($url).'">';
        echo '<div class="cff-media-preview">' . cff_media_preview_html($stype, $id) . '</div>';
        echo '<p><button type="button" class="button cff-media-select">Select</button> <button type="button" class="button cff-media-clear">Clear</button></p>';
        echo '</div>';
      } elseif ($stype === 'group') {
        $gsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $gvals = is_array($v) ? $v : [];
        $group_prefix = 'cff_values['.$parent.']['.$i.']['.$sname.']';
        echo '<div class="cff-group cff-group-nested">';
        render_group_fields($group_prefix, $gsubs, $gvals, $post_id);
        echo '</div>';
      } else {
        echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$required_attr.'>';
      }
      echo '</div>';
    }
    echo '</div></div>';
  }

function render_group_fields($parent_prefix, $subs, $vals, $post_id) {
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
      echo '<div class="cff-subfield-input">';
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
        echo '<input class="widefat" type="url" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
      } elseif ($stype === 'choice') {
        render_choice_input($name_attr, $s['choices'] ?? [], $s['choice_display'] ?? '', $v, $required_attr);
      } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype);
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
        echo '<p><button type="button" class="button cff-media-select">Select</button> <button type="button" class="button cff-media-clear">Clear</button></p>';
        echo '</div>';
      } elseif ($stype === 'group') {
        $gsubs = isset($s['sub_fields']) ? $s['sub_fields'] : [];
        $gvals = is_array($v) ? $v : [];
        echo '<div class="cff-group cff-group-nested">';
        render_group_fields($name_attr, $gsubs, $gvals, $post_id);
        echo '</div>';
      } else {
        echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
      }
      echo '</div>';
    }
  }

  function render_link_field($name_attr, $value) {
    $link = is_array($value) ? $value : [];
    $url = $link['url'] ?? '';
    $title = $link['title'] ?? '';
    $target = $link['target'] ?? '';
    $internal_id = isset($link['internal_id']) ? intval($link['internal_id']) : 0;
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

    echo '<div class="cff-link-mode">';
    echo '<label><input type="radio" name="' . esc_attr($name_attr) . '[mode]" value="internal" ' . checked($mode, 'internal', false) . '> Internal</label>';
    echo '<label><input type="radio" name="' . esc_attr($name_attr) . '[mode]" value="custom" ' . checked($mode, 'custom', false) . '> Custom</label>';
    echo '</div>';

    echo '<div class="cff-link-internal">';
    echo '<select class="cff-link-select" data-post-type="any" data-placeholder="Search content…">';
    if ($internal_id) {
      echo '<option value="' . esc_attr($internal_id) . '" selected>' . esc_html($title ?: ('#' . $internal_id)) . '</option>';
    }
    echo '</select>';
    echo '<input type="hidden" class="cff-link-internal-id" name="' . esc_attr($name_attr) . '[internal_id]" value="' . esc_attr($internal_id) . '">';
    echo '</div>';

    echo '<div class="cff-link-custom">';
    echo '<input class="widefat" type="url" placeholder="URL" name="' . esc_attr($name_attr) . '[url]" value="' . esc_attr($url) . '" style="margin-bottom: 10px;">';
    echo '<input class="widefat" type="text" placeholder="Title" name="' . esc_attr($name_attr) . '[title]" value="' . esc_attr($title) . '">';
    echo '</div>';

    echo '<div class="cff-link-target">';
    echo '<input type="hidden" name="' . esc_attr($name_attr) . '[target]" value="">';
    echo '<label><input type="checkbox" name="' . esc_attr($name_attr) . '[target]" value="_blank" ' . checked($target, '_blank', false) . '> Open in new tab</label>';
    echo '</div>';

    echo '</div>';
  }

  function render_flexible_row($parent, $layouts, $layout_map, $row, $i, $post_id) {
    $layout = isset($row['layout']) ? sanitize_key($row['layout']) : '';
    $fields = isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : [];
    $l = isset($layout_map[$layout]) ? $layout_map[$layout] : null;
    $label = $l ? ($l['label'] ?? $layout) : $layout;

    echo '<div class="cff-flex-row" data-i="'.esc_attr($i).'" data-layout="'.esc_attr($layout).'">';
    echo '<div class="cff-flex-head"><strong>'.esc_html($label).'</strong> <span class="cff-pill">'.esc_html($layout).'</span> ';
    echo '<button type="button" class="button-link cff-flex-remove">Remove</button></div>';
    echo '<input type="hidden" name="cff_values['.esc_attr($parent).']['.esc_attr($i).'][layout]" value="'.esc_attr($layout).'">';
    echo '<div class="cff-flex-body">';
    if ($l) {
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
        echo '<div class="cff-subfield-input">';
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
          echo '<input class="widefat" type="url" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
        } elseif ($stype === 'choice') {
          render_choice_input($name_attr, $sf['choices'] ?? [], $sf['choice_display'] ?? '', $v, $required_attr);
        } elseif ($stype === 'date_picker' || $stype === 'datetime_picker') {
        render_picker_input($name_attr, $v, $placeholder_attr, $required_attr, $stype);
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
          echo '<p><button type="button" class="button cff-media-select">Select</button> <button type="button" class="button cff-media-clear">Clear</button></p>';
          echo '</div>';
        } elseif ($stype === 'group') {
          $gsubs = isset($sf['sub_fields']) ? $sf['sub_fields'] : [];
          $gvals = is_array($v) ? $v : [];
          $group_prefix = 'cff_values['.$parent.']['.$i.'][fields]['.$sname.']';
          echo '<div class="cff-group cff-group-nested">';
          render_group_fields($group_prefix, $gsubs, $gvals, $post_id);
          echo '</div>';
        } else {
          echo '<input class="widefat" type="text" name="'.esc_attr($name_attr).'" value="'.esc_attr($v).'"'.$placeholder_attr.$required_attr.'>';
        }
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
    if (!isset($_POST['cff_values']) || !is_array($_POST['cff_values'])) return;

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
  }

  function deep_sanitize($v) {
    if (is_array($v)) {
      $out = [];
      foreach ($v as $k => $val) {
        if ($val === '__INDEX__') continue;

        if ($k === '__cff_present') continue;

        if ($val === '__cff_choice_empty__') continue;

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

}
