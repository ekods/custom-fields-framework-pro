/* =========================
 * CFF Admin JS (Modular Fixed)
 * - no replaceAll()
 * - safe template helper
 * - guards for missing templates
 * - modular (FieldBuilder, LocationBuilder, Presentation, Tools, Dashicons, Multiselect)
 * ========================= */
(function($){

  /* -------------------------
   * Utils
   * ------------------------- */
  var CFF = window.CFF || (window.CFF = {});

  CFF.utils = {
    sanitizeName: function(v){
      return String(v||'')
        .toLowerCase()
        .replace(/[^a-z0-9_]/g,'_')
        .replace(/_+/g,'_')
        .replace(/^_+|_+$/g,'');
    },

    escapeHtml: function(s){
      return String(s||'').replace(/[&<>"']/g, function(m){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
      });
    },

    // Replace {{key}} safely
    tmpl: function(str, map){
      str = String(str || '');
      map = map || {};
      Object.keys(map).forEach(function(k){
        var re = new RegExp('{{\\s*' + k + '\\s*}}', 'g');
        str = str.replace(re, String(map[k]));
      });
      return str;
    },

    // JSON helpers
    jsonParse: function(raw, fallback){
      try { return raw ? JSON.parse(raw) : fallback; } catch(e){ return fallback; }
    },

    // Very small debounce
    debounce: function(fn, wait){
      var t;
      return function(){
        var args = arguments, ctx = this;
        clearTimeout(t);
        t = setTimeout(function(){ fn.apply(ctx, args); }, wait || 100);
      };
    }
  };

  /* -------------------------
   * Tabs
   * ------------------------- */
  CFF.tabs = (function(){
    function init(){
      $(document).on('click', '.cff-tabbar .cff-tab', function(){
        var t = $(this).data('tab');
        $('.cff-tab').removeClass('active'); $(this).addClass('active');
        $('.cff-tabpanel').removeClass('active');
        $('.cff-tabpanel[data-panel="'+t+'"]').addClass('active');

        // trigger module refresh when tab opened
        $(document).trigger('cff:tab:' + t);
      });

      // if a tab already active on load, trigger it
      var $active = $('.cff-tabbar .cff-tab.active');
      if ($active.length) $(document).trigger('cff:tab:' + $active.data('tab'));
    }
    return { init:init };
  })();

  /* -------------------------
   * Field Builder
   * ------------------------- */
  CFF.fieldBuilder = (function(){
    var $input, $root;
    var tplField, tplSub, tplLayout;
    var placeholderTypes = {
      text: true,
      number: true,
      textarea: true,
      url: true,
      link: true,
      embed: true
    };

    function init(){
      $input = $('#cff_fields_json');
      $root  = $('#cff-fields-builder');
      if (!$root.length || !$input.length) return;

      tplField  = $('#tmpl-cff-field').html();
      tplSub    = $('#tmpl-cff-subfield').html();
      tplLayout = $('#tmpl-cff-layout').html();

      // Fallback templates if missing from PHP
      if (!tplField) tplField = fallbackFieldTpl();
      if (!tplSub) tplSub = fallbackSubTpl();
      if (!tplLayout) tplLayout = fallbackLayoutTpl();

      bindEvents();
      render();
    }

    function fallbackFieldTpl(){
      return (
        '<div class="cff-field-row" data-i="{{i}}">' +
          '<div class="cff-handle-wrap">' +
            '<button type="button" class="cff-acc-toggle" aria-expanded="true"></button>' +
            '<div class="cff-handle"></div>' +
          '</div>' +
          '<div class="cff-field-structure">' +
            '<div class="cff-field-head">' +
              '<div class="cff-col">' +
                '<label>Label</label>' +
                '<input type="text" class="cff-input cff-label" value="{{label}}">' +
              '</div>' +
              '<div class="cff-col">' +
                '<label>Name</label>' +
                '<input type="text" class="cff-input cff-name" value="{{name}}">' +
              '</div>' +
              '<div class="cff-col cff-row-type">' +
                '<div class="cff-row-type-main">' +
                  '<label>Type</label>' +
                  '<select class="cff-input cff-type cff-select2">' +
                  '<option value="text">Text</option>' +
                  '<option value="number">Number</option>' +
                  '<option value="textarea">Textarea</option>' +
                  '<option value="wysiwyg">WYSIWYG</option>' +
                  '<option value="color">Color</option>' +
                  '<option value="url">URL</option>' +
                  '<option value="link">Link</option>' +
                  '<option value="embed">Embed</option>' +
                    '<option value="choice">Choice</option>' +
                    '<option value="relational">Relational</option>' +
                    '<option value="relational">Relational</option>' +
                    '<option value="date_picker">Date Picker</option>' +
                    '<option value="datetime_picker">Date Time Picker</option>' +
                    '<option value="checkbox">Checkbox</option>' +
                  '<option value="image">Image</option>' +
                  '<option value="file">File</option>' +
                  '<option value="repeater">Repeater</option>' +
                  '<option value="group">Group</option>' +
                    '<option value="flexible">Flexible Content</option>' +
                  '</select>' +
                '</div>' +
              '</div>' +
              '<div class="cff-col cff-actions">' +
                '<button type="button" class="button cff-icon-button cff-duplicate" aria-label="Duplicate field">' +
                  '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>' +
                '</button>' +
                '<button type="button" class="button cff-icon-button cff-remove" aria-label="Remove field">' +
                  '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
                '</button>' +
              '</div>' +
            '</div>' +
              '<div class="cff-field-meta-row">' +
          '<div class="cff-row-placeholder">' +
            '<label>Placeholder</label>' +
            '<input type="text" class="cff-input cff-placeholder" placeholder="Placeholder" value="{{placeholder}}">' +
          '</div>' +
          '<div class="cff-row-repeater-options">' +
            '<label>Repeater Layout</label>' +
            '<select class="cff-input cff-repeater-layout cff-select2">' +
                    '<option value="default">Default (stacked rows)</option>' +
              '<option value="simple">Simple (Remove-only header)</option>' +
              '<option value="grid">Grid (multi-column body)</option>' +
            '</select>' +
            '<p class="description">Change how each repeater row is rendered in the editor.</p>' +
          '</div>' +
        '<div class="cff-row-repeater-options">' +
          '<label>Repeater Layout</label>' +
          '<select class="cff-input cff-repeater-layout cff-select2">' +
                '<option value="default">Default (stacked rows)</option>' +
            '<option value="simple">Simple (Remove-only header)</option>' +
            '<option value="grid">Grid (multi-column body)</option>' +
          '</select>' +
          '<p class="description">Change how each repeater row is rendered in the editor.</p>' +
        '</div>' +
          '<div class="cff-row-repeater-options">' +
            '<label>Repeater Layout</label>' +
            '<select class="cff-input cff-repeater-layout cff-select2">' +
                  '<option value="default">Default (stacked rows)</option>' +
              '<option value="simple">Simple (Remove-only header)</option>' +
              '<option value="grid">Grid (multi-column body)</option>' +
            '</select>' +
            '<p class="description">Change how each repeater row is rendered in the editor.</p>' +
          '</div>' +
              '<div class="cff-row-repeater-options">' +
                '<label>Repeater Layout</label>' +
                '<select class="cff-input cff-repeater-layout cff-select2">' +
                  '<option value="default">Default (stacked rows)</option>' +
                  '<option value="simple">Simple (Remove-only header)</option>' +
                  '<option value="grid">Grid (multi-column body)</option>' +
                '</select>' +
                '<p class="description">Change how each repeater row is rendered in the editor.</p>' +
              '</div>' +
                '<div class="cff-row-required">' +
                  '<span class="cff-tools-toggles">' +
                    '<div>' +
                      '<strong>Required</strong>' +
                    '</div>' +
                    '<label class="cff-switch">' +
                      '<input type="checkbox" class="cff-required-toggle">' +
                      '<span class="cff-slider"></span>' +
                    '</label>' +
                  '</span>' +
                '</div>' +
              '</div>' +
              '<div class="cff-field-choice is-hidden">' +
                '<div class="cff-subhead">' +
                  '<strong>Choices</strong> ' +
                  '<button type="button" class="button cff-add-choice">Add Choice</button>' +
                '</div>' +
                '<div class="cff-row-choice-display">' +
                  '<label>Display</label>' +
                  '<select class="cff-input cff-choice-display cff-select2">' +
                    '<option value="select">Select</option>' +
                    '<option value="checkbox">Checkbox</option>' +
                    '<option value="radio">Radio Button</option>' +
                    '<option value="button_group">Button Group</option>' +
                    '<option value="true_false">True / False</option>' +
                  '</select>' +
                '</div>' +
                '<div class="cff-choices-list"></div>' +
              '</div>' +
            '</div>' +
          '<div class="cff-advanced">' +
            '<div class="cff-subbuilder" data-kind="repeater">' +
              '<div class="cff-subhead"><strong>Sub Fields (Repeater)</strong> ' +
                '<button type="button" class="button cff-add-sub">Add Sub Field</button>' +
              '</div>' +
              '<div class="cff-subfields"></div>' +
            '</div>' +
            '<div class="cff-groupbuilder" data-kind="group">' +
              '<div class="cff-subhead"><strong>Group Fields</strong> ' +
                '<button type="button" class="button cff-add-group-sub">Add Field</button>' +
              '</div>' +
              '<div class="cff-group-fields"></div>' +
            '</div>' +
            '<div class="cff-flexbuilder" data-kind="flexible">' +
              '<div class="cff-subhead"><strong>Layouts (Flexible Content)</strong> ' +
                '<button type="button" class="button cff-add-layout">Add Layout</button>' +
              '</div>' +
              '<div class="cff-layouts"></div>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
    }

    function fallbackSubTpl(){
      return (
        '<div class="cff-subfield" data-si="{{si}}">' +
          '<div class="cff-handle-wrap">' +
            '<button type="button" class="cff-sub-acc-toggle" aria-expanded="true"></button>' +
            '<div class="cff-handle"></div>' +
          '</div>' +
          '<div class="cff-subfield-structure">' +
            '<div class="cff-field-head">' +
              '<div class="cff-col">' +
                '<label>Label</label>' +
                '<input type="text" class="cff-input cff-slabel" value="{{label}}">' +
              '</div>' +
              '<div class="cff-col">' +
                '<label>Name</label>' +
                '<input type="text" class="cff-input cff-sname" value="{{name}}">' +
              '</div>' +
              '<div class="cff-col cff-row-type">' +
                '<div class="cff-row-type-main">' +
                  '<label>Type</label>' +
                  '<select class="cff-input cff-stype cff-select2">' +
                  '<option value="text">Text</option>' +
                  '<option value="number">Number</option>' +
                  '<option value="textarea">Textarea</option>' +
                    '<option value="wysiwyg">WYSIWYG</option>' +
                    '<option value="color">Color</option>' +
                  '<option value="url">URL</option>' +
                  '<option value="link">Link</option>' +
                  '<option value="embed">Embed</option>' +
                    '<option value="choice">Choice</option>' +
                    '<option value="relational">Relational</option>' +
                    '<option value="date_picker">Date Picker</option>' +
                    '<option value="datetime_picker">Date Time Picker</option>' +
                    '<option value="checkbox">Checkbox</option>' +
                    '<option value="image">Image</option>' +
                    '<option value="file">File</option>' +
                    '<option value="repeater">Repeater</option>' +
                    '<option value="group">Group</option>' +
                  '</select>' +
                '</div>' +
              '</div>' +
              '<div class="cff-col cff-actions">' +
                '<button type="button" class="button cff-icon-button cff-remove-sub" aria-label="Remove sub field">' +
                  '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
                '</button>' +
              '</div>' +
            '</div>' +
              '<div class="cff-field-meta-row">' +
                '<div class="cff-row-placeholder">' +
                  '<label>Placeholder</label>' +
                  '<input type="text" class="cff-input cff-placeholder" placeholder="Placeholder" value="{{placeholder}}">' +
                '</div>' +
                '<div class="cff-row-required">' +
                  '<span class="cff-tools-toggles">' +
                    '<div>' +
                      '<strong>Required</strong>' +
                    '</div>' +
                    '<label class="cff-switch">' +
                      '<input type="checkbox" class="cff-required-toggle">' +
                      '<span class="cff-slider"></span>' +
                    '</label>' +
                  '</span>' +
                '</div>' +
              '</div>' +
            '<div class="cff-field-choice is-hidden">' +
              '<div class="cff-subhead">' +
                '<strong>Choices</strong> ' +
                '<button type="button" class="button cff-add-choice">Add Choice</button>' +
              '</div>' +
              '<div class="cff-choices-list"></div>' +
              '<div class="cff-row-choice-display">' +
                '<label>Display</label>' +
                '<select class="cff-input cff-choice-display cff-select2">' +
                  '<option value="select">Select</option>' +
                  '<option value="checkbox">Checkbox</option>' +
                  '<option value="radio">Radio Button</option>' +
                  '<option value="button_group">Button Group</option>' +
                  '<option value="true_false">True / False</option>' +
                '</select>' +
              '</div>' +
            '</div>' +
            '<div class="cff-field-relational is-hidden">' +
              '<div class="cff-subhead">' +
                '<strong>Relational Settings</strong> ' +
              '</div>' +
              '<div class="cff-row-relational-type">' +
                '<label>Relation Type</label>' +
                '<select class="cff-input cff-relational-type cff-select2">' +
                  '<option value="post">Post Only</option>' +
                  '<option value="page">Page Only</option>' +
                  '<option value="post_and_page">Post & Page</option>' +
                  '<option value="post_type">Custom Post Type</option>' +
                  '<option value="taxonomy">Taxonomy</option>' +
                  '<option value="user">User</option>' +
                '</select>' +
              '</div>' +
              '<div class="cff-row-relational-subtype" style="display:none;">' +
                '<label>Select Type</label>' +
                '<select class="cff-input cff-relational-subtype cff-select2"></select>' +
              '</div>' +
              '<div class="cff-row-relational-display">' +
                '<label>Display</label>' +
                '<select class="cff-input cff-relational-display cff-select2">' +
                  '<option value="select">Select</option>' +
                  '<option value="checkbox">Checkbox</option>' +
                  '<option value="radio">Radio Button</option>' +
                '</select>' +
              '</div>' +
              '<div class="cff-row-relational-multiple">' +
                '<span class="cff-tools-toggles">' +
                  '<div><strong>Multiple</strong></div>' +
                  '<label class="cff-switch">' +
                    '<input type="checkbox" class="cff-relational-multiple-toggle">' +
                    '<span class="cff-slider"></span>' +
                  '</label>' +
                '</span>' +
              '</div>' +
              '<div class="cff-row-relational-archives">' +
                '<strong>Archive Links</strong>' +
                '<div class="cff-relational-archive-list"></div>' +
              '</div>' +
            '</div>' +
            '</div>' +
        '</div>'
      );
    }    function fallbackLayoutTpl(){
      return (
        '<div class="cff-layout" data-li="{{li}}">' +
          '<div class="cff-layout-head">' +
            '<div class="cff-handle"></div>' +
            '<div class="cff-col"><label>Layout Label</label><input type="text" class="cff-input cff-llabel" value="{{label}}"></div>' +
            '<div class="cff-col"><label>Layout Name</label><input type="text" class="cff-input cff-lname" value="{{name}}"></div>' +
            '<div class="cff-col cff-actions">' +
              '<button type="button" class="button cff-toggle-layout">Edit Fields</button> ' +
              '<button type="button" class="button cff-remove-layout">Remove</button>' +
            '</div>' +
          '</div>' +
          '<div class="cff-layout-body">' +
            '<div class="cff-subhead"><strong>Layout Fields</strong> ' +
              '<button type="button" class="button cff-add-layout-field">Add Field</button>' +
            '</div>' +
            '<div class="cff-layout-fields"></div>' +
          '</div>' +
        '</div>'
      );
    }

    function load(){
      return CFF.utils.jsonParse($input.val() || '[]', []);
    }

    function save(data){
      $input.val(JSON.stringify(data || []));
      $input.trigger('change');
    }

    function toggleBuilders($field){
      var t = $field.find('.cff-type').val();
      $field.find('> .cff-advanced > .cff-subbuilder').toggle(t === 'repeater');
      $field.find('> .cff-advanced > .cff-groupbuilder').toggle(t === 'group');
      $field.find('> .cff-advanced > .cff-flexbuilder').toggle(t === 'flexible');
      toggleRepeaterOptions($field, t);
      toggleDatetimeOptions($field, t);
    }

    function togglePlaceholderRow($element, type){
      if (!$element || !$element.length) return;
      var $row = $element.find('.cff-row-placeholder').first();
      if (!$row.length) return;
      var allowed = placeholderTypes[String(type || '').trim()];
      $row.toggle(!!allowed);
    }

    function toggleRepeaterOptions($element, type){
      if (!$element || !$element.length) return;
      var $row = $element.find('.cff-row-repeater-options').first();
      if (!$row.length) return;
      var selected = String(type || '').trim();
      if (!selected) {
        selected = ($element.find('.cff-type').val() || $element.find('.cff-stype').val() || '').trim();
      }
      $row.toggle(selected === 'repeater');
    }

    function toggleDatetimeOptions($element, type){
      if (!$element || !$element.length) return;
      var $row = $element.find('.cff-row-datetime-options').first();
      if (!$row.length) return;
      var selected = String(type || '').trim();
      if (!selected) {
        selected = ($element.find('.cff-type').val() || $element.find('.cff-stype').val() || '').trim();
      }
      $row.toggle(selected === 'datetime_picker');
    }

    function toggleSubGroup($sub){
      var t = $sub.find('.cff-stype').val();
      var $builder = $sub.children('.cff-groupbuilder').first();
      var $wrap = $sub.find('.cff-handle-wrap');
      if ($wrap.length && !$wrap.find('.cff-sub-acc-toggle').length) {
        $wrap.prepend('<button type="button" class="cff-sub-acc-toggle" aria-expanded="true"></button>');
      }
      if (!$builder.length) {
        $builder = $(
          '<div class="cff-groupbuilder cff-subgroupbuilder" data-kind="group">' +
            '<div class="cff-subhead">' +
              '<strong>Group Fields</strong>' +
              '<button type="button" class="button cff-add-group-sub">Add Field</button>' +
            '</div>' +
            '<div class="cff-group-fields"></div>' +
          '</div>'
        );
        $sub.append($builder);
      }
      var isGroup = (t === 'group');
      $sub.toggleClass('is-group', isGroup);
      $builder.toggle(isGroup);
      $sub.find('.cff-sub-acc-toggle').toggle(isGroup);
      if (t !== 'group') {
        $sub.removeClass('is-collapsed');
        $sub.find('.cff-sub-acc-toggle').attr('aria-expanded', 'true');
      }
      if (t === 'group') {
        sortableSubs($builder.find('> .cff-group-fields').first());
        $(document).trigger('cff:refresh', $builder);
      }
    }

    function toggleSubRepeater($sub){
      var t = $sub.find('.cff-stype').val();
      var $builder = $sub.find('> .cff-subbuilder').first();
      if (!$builder.length) return;
      var isRepeater = (t === 'repeater');
      $sub.toggleClass('is-repeater', isRepeater);
      $builder.toggle(isRepeater);
      if (isRepeater) {
        sortableSubs($builder.find('> .cff-subfields').first());
      }
    }

    function sortableSubs($container){
      $container.sortable({
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); }
      });
    }

    function sortableLayouts($container){
      $container.sortable({
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); }
      });
    }

    function renderSub(s, si){
      var html = CFF.utils.tmpl(tplSub, {
        si: si,
        label: CFF.utils.escapeHtml(s.label || ''),
        name:  CFF.utils.escapeHtml(s.name  || ''),
        placeholder: CFF.utils.escapeHtml(s.placeholder || '')
      });
      var $el = $(html);

      if (s && s._tmp) {
        $el.attr('data-tmpkey', s._tmp);
      }

      var $wrap = $el.find('.cff-handle-wrap');
      if (!$wrap.length) {
        var $handle = $el.find('.cff-handle').first();
        if ($handle.length) {
          $wrap = $('<div class="cff-handle-wrap"></div>');
          $handle.before($wrap);
          $wrap.append($handle);
        }
      }
      if ($wrap.length && !$wrap.find('.cff-sub-acc-toggle').length) {
        $wrap.prepend('<button type="button" class="cff-sub-acc-toggle" aria-expanded="true"></button>');
      }
      $el.find('.cff-stype').val(s.type || 'text');
      toggleSubGroup($el);
      toggleSubRepeater($el);
      togglePlaceholderRow($el, s.type || 'text');
      renderRelationalPanel($el, {
        relational_type: s.relational_type,
        relational_subtype: s.relational_subtype,
        relational_display: s.relational_display,
        relational_multiple: s.relational_multiple,
      });
      toggleRelationalPanel($el, s.type || 'text');
      $el.find('.cff-placeholder').val(s.placeholder || '');
      $el.find('.cff-required-toggle').prop('checked', !!s.required);
      $el.find('.cff-datetime-use-time-toggle').prop('checked', (s.datetime_use_time !== false));
      toggleDatetimeOptions($el, s.type || 'text');
      renderConditionalPanel($el, s);
      if (s.type === 'group' && Array.isArray(s.sub_fields)) {
        var $gf = $el.find('> .cff-groupbuilder > .cff-group-fields').first();
        s.sub_fields.forEach(function(sf, sfi){ $gf.append(renderSub(sf, sfi)); });
        sortableSubs($gf);
      }
      if (s.type === 'repeater' && Array.isArray(s.sub_fields)) {
        var $sr = $el.find('> .cff-subbuilder > .cff-subfields').first();
        if ($sr.length) {
          s.sub_fields.forEach(function(sf, sfi){ $sr.append(renderSub(sf, sfi)); });
          sortableSubs($sr);
        }
      }
      renderChoicesPanel($el, s);
      toggleChoicePanel($el, s.type);
      var subLayoutValue = s.repeater_layout || 'default';
      $el.find('.cff-repeater-layout').val(subLayoutValue);
      toggleRepeaterOptions($el, s.type || 'text');
      $(document).trigger('cff:refresh', $el);
      return $el;
    }

    function renderLayout(l, li){
      var html = CFF.utils.tmpl(tplLayout, {
        li: li,
        label: CFF.utils.escapeHtml(l.label || ''),
        name:  CFF.utils.escapeHtml(l.name  || '')
      });

      var $el = $(html);
      var $fields = $el.find('.cff-layout-fields');
      (l.sub_fields || []).forEach(function(s, si){
        $fields.append(renderSub(s, si));
      });

      sortableSubs($fields);
      return $el;
    }

    function renderChoiceRow(choice){
      var html =
        '<div class="cff-choice-row">' +
          '<input type="text" class="cff-input cff-choice-label" placeholder="Label">' +
          '<input type="text" class="cff-input cff-choice-value" placeholder="Value">' +
          '<button type="button" class="button-link cff-choice-remove" aria-label="Remove choice">×</button>' +
        '</div>';
      var $row = $(html);
      $row.find('.cff-choice-label').val(choice.label || '');
      $row.find('.cff-choice-value').val(choice.value || '');
      return $row;
    }

    function populateChoiceList($panel, choices){
      var $list = $panel.find('.cff-choices-list');
      $list.empty();
      var items = Array.isArray(choices) && choices.length ? choices : [{}];
      items.forEach(function(choice){
        $list.append(renderChoiceRow(choice));
      });
    }

    function renderChoicesPanel($element, data){
      var $panel = $element.find('.cff-field-choice').first();
      if (!$panel.length) return;
      populateChoiceList($panel, data.choices || []);
      $panel.find('.cff-choice-display').val(data.choice_display || 'select');
    }

    function hasChoiceValues($panel){
      var filled = 0;
      $panel.children('.cff-choice-row').each(function(){
        var label = ($(this).find('.cff-choice-label').val() || '').trim();
        var value = ($(this).find('.cff-choice-value').val() || '').trim();
        if (label || value) filled++;
      });
      return filled > 0;
    }

    function toggleChoicePanel($element, type){
      var $panel = $element.find('.cff-field-choice').first();
      if (!$panel.length) return;
      var hasValues = hasChoiceValues($panel);
      var visible = (type === 'choice') || hasValues;
      $panel.toggleClass('is-hidden', !visible);
    }

    function toggleConditionalValueInput($element){
      var $config = $element.find('.cff-conditional-config').first();
      if (!$config.length) return;
      var op = $config.find('.cff-conditional-operator').val() || '==';
      var needsValue = !(op === 'empty' || op === 'not_empty');
      $config.find('.cff-conditional-value-row').toggle(needsValue);
    }

    function updateConditionalFieldOptions($element, forcedSelected){
      var $select = $element.find('.cff-conditional-field').first();
      if (!$select.length) return;

      var current = (forcedSelected !== undefined)
        ? String(forcedSelected || '')
        : String($select.val() || '');

      var names = [];
      if ($element.hasClass('cff-field-row')) {
        $('#cff-field-list .cff-field-row').each(function(){
          var n = CFF.utils.sanitizeName($(this).find('.cff-name').val() || '');
          if (n) names.push(n);
        });
      } else {
        var $container = $element.parent();
        $container.children('.cff-subfield').each(function(){
          var n = CFF.utils.sanitizeName($(this).find('.cff-sname').val() || '');
          if (n) names.push(n);
        });
      }

      var seen = {};
      names = names.filter(function(n){
        if (seen[n]) return false;
        seen[n] = true;
        return true;
      });

      $select.empty().append('<option value="">Select field…</option>');
      names.forEach(function(n){
        $select.append($('<option></option>').attr('value', n).text(n));
      });

      if (current && !seen[current]) {
        $select.append($('<option></option>').attr('value', current).text(current + ' (missing)'));
      }

      $select.val(current);
    }

    function refreshConditionalFieldDropdowns(){
      $root.find('.cff-field-row, .cff-subfield').each(function(){
        updateConditionalFieldOptions($(this));
      });
    }

    function renderConditionalPanel($element, data){
      var $toggle = $element.find('.cff-conditional-enabled').first();
      var $config = $element.find('.cff-conditional-config').first();
      if (!$toggle.length || !$config.length) return;

      var logic = (data && typeof data === 'object') ? (data.conditional_logic || {}) : {};
      var enabled = !!(logic && logic.enabled);
      $toggle.prop('checked', enabled);
      $config.toggle(enabled);
      updateConditionalFieldOptions($element, (logic && logic.field) || '');
      $config.find('.cff-conditional-operator').val((logic && logic.operator) || '==');
      $config.find('.cff-conditional-value').val((logic && logic.value) || '');
      toggleConditionalValueInput($element);
    }

    function readConditionalLogic($element){
      var $toggle = $element.find('.cff-conditional-enabled').first();
      var $config = $element.find('.cff-conditional-config').first();
      if (!$toggle.length || !$config.length || !$toggle.is(':checked')) return null;

      var field = CFF.utils.sanitizeName($config.find('.cff-conditional-field').val() || '');
      if (!field) return null;

      return {
        enabled: true,
        field: field,
        operator: $config.find('.cff-conditional-operator').val() || '==',
        value: $config.find('.cff-conditional-value').val() || ''
      };
    }

    var relationalPostTypesCache = null;

    function loadRelationalPostTypes(cb){
      if (!cb) return;
      if (relationalPostTypesCache) {
        cb(relationalPostTypesCache);
        return;
      }

      var options = [];
      var seen = {};

      function add(items){
        (items || []).forEach(function(pt){
          if (!pt) return;
          var value = pt.value || pt.id || '';
          if (!value) return;
          if (seen[value]) return;
          seen[value] = true;
          var label = pt.label || pt.text || value;
          options.push({ value: value, label: label });
        });
      }

      if (window.CFFP && Array.isArray(CFFP.relational_post_types)) {
        add(CFFP.relational_post_types);
      }

      var finalize = function(){
        relationalPostTypesCache = options;
        cb(options);
      };

      if (!window.CFFP || !CFFP.ajax) {
        finalize();
        return;
      }

      $.post(CFFP.ajax, { action: 'cff_get_post_types', nonce: CFFP.nonce }, function(res){
        if (res && res.success && Array.isArray(res.data)) {
          add(res.data);
        }
      }).always(finalize);
    }

    function renderRelationalPanel($element, data){
      var $panel = $element.find('.cff-field-relational').first();
      if (!$panel.length) return;

      $panel.find('.cff-relational-type').val(data.relational_type || 'post');
      $panel.find('.cff-relational-subtype').val(data.relational_subtype || '');
      $panel.find('.cff-relational-display').val(data.relational_display || 'select');
      $panel.find('.cff-relational-multiple-toggle').prop('checked', !!data.relational_multiple);

      // ✅ simpan di $element (ctx), biar updateRelationalSubtypeOptions bisa baca
      $element.data('cff-relational-subtype', data.relational_subtype || '');

      updateRelationalSubtypeOptions($element);
      renderArchiveLinks($element);
    }

    function renderArchiveLinks($element){
      var $panel = $element.find('.cff-field-relational').first();
      if (!$panel.length) return;
      var $list = $panel.find('.cff-relational-archive-list').first();
      if (!$list.length) return;
      var archives = [];
      if (window.CFFP && Array.isArray(CFFP.archives)) {
        archives = CFFP.archives;
      }
      if (!archives.length) {
        $list.html('<div class="cff-relational-archive-empty">Archive links not available.</div>');
        return;
      }
      var html = archives.map(function(item){
        var label = CFF.utils.escapeHtml(item.label || item.slug || '');
        var url = item.url || '';
        var safeUrl = url ? CFF.utils.escapeHtml(url) : '';
        var anchor = url
          ? '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">' + label + '</a>'
          : label;
        var urlLine = url ? '<div class="cff-relational-archive-url">' + safeUrl + '</div>' : '';
        return '<div class="cff-relational-archive-item">' + anchor + urlLine + '</div>';
      }).join('');
      $list.html(html);
    }

    function toggleRelationalPanel($element, type){
      var $panel = $element.find('.cff-field-relational').first();
      if (!$panel.length) return;
      $panel.toggleClass('is-hidden', type !== 'relational');
    }

    function updateRelationalSubtypeOptions($ctx) {
      var type = $ctx.find('.cff-relational-type').val();
      var $wrap = $ctx.find('.cff-row-relational-subtype');
      var $select = $ctx.find('.cff-relational-subtype');

      // ✅ ambil dari select dulu, baru fallback ke data ctx
      var currentValue = ($select.val() || '') || ($ctx.data('cff-relational-subtype') || '');

      $select.empty();
      $wrap.hide();

      function applyCurrent(){
        if (currentValue) $select.val(String(currentValue));
        if (!$select.val() && $select.find('option').length) $select.prop('selectedIndex', 0);
        // ✅ simpan balik supaya stabil
        $ctx.data('cff-relational-subtype', $select.val() || '');
      }

      if (type === 'post') {
        $wrap.show();
        $select.append('<option value="post">Post</option>');
        applyCurrent();
        return;
      }

      if (type === 'page') {
        $wrap.show();
        $select.append('<option value="page">Page</option>');
        applyCurrent();
        return;
      }

      if (type === 'post_and_page') {
        $wrap.show();
        $select.append('<option value="post">Post</option>');
        $select.append('<option value="page">Page</option>');
        applyCurrent();
        return;
      }

      if (type === 'post_type') {
        $wrap.show();
        $select.append('<option value="">Loading…</option>');

        loadRelationalPostTypes(function(postTypeOptions){
          $select.empty();

          var resolved = (postTypeOptions && postTypeOptions.length) ? postTypeOptions : [
            { value: 'post', label: 'Post' },
            { value: 'page', label: 'Page' },
          ];

          resolved.forEach(function(pt){
            $select.append($('<option>', { value: pt.value, text: pt.label }));
          });

          applyCurrent();
          // optional: trigger kalau memang ada UI lain yang ngandelin change
          // $select.trigger('change');
        });

        return;
      }
    }


    function readChoices($panel){
      var list = [];
      $panel.children('.cff-choice-row').each(function(){
        var $row = $(this);
        var label = $row.find('.cff-choice-label').val() || '';
        var value = $row.find('.cff-choice-value').val() || '';
        if (!label && !value) return;
        list.push({ label: label, value: value });
      });
      return list;
    }

    function readFromDOM(){
      var data = [];
      $('#cff-field-list .cff-field-row').each(function(){
        var $f = $(this);
        var label = $f.find('.cff-label').val() || '';
        var name  = CFF.utils.sanitizeName($f.find('.cff-name').val() || '');
        var type  = $f.find('.cff-type').val() || 'text';

        var item = { label: label, name: name, type: type };
        item.required = $f.find('.cff-required-toggle').is(':checked');
        item.placeholder = $f.find('.cff-placeholder').val() || '';
        if (type === 'datetime_picker') {
          item.datetime_use_time = $f.find('.cff-datetime-use-time-toggle').is(':checked');
        }
        var conditionalLogic = readConditionalLogic($f);
        if (conditionalLogic) {
          item.conditional_logic = conditionalLogic;
        }
        if (type === 'choice') {
          item.choices = readChoices($f.find('.cff-choices-list'));
          item.choice_display = $f.find('.cff-choice-display').val() || 'select';
        }

        if (type === 'relational') {
          item.relational_type = $f.find('.cff-relational-type').val() || 'post';
          item.relational_subtype = $f.find('.cff-relational-subtype').val() || '';
          item.relational_display = $f.find('.cff-relational-display').val() || 'select';
          item.relational_multiple = $f.find('.cff-relational-multiple-toggle').is(':checked');
        }

        if (type === 'repeater') {
          item.sub_fields = readSubfields(
            $f.find('> .cff-advanced > .cff-subbuilder > .cff-subfields').first()
          );
          item.repeater_layout = $f.find('.cff-repeater-layout').val() || 'default';
        }

        if (type === 'group') {
          item.sub_fields = readSubfields(
            $f.find('> .cff-advanced > .cff-groupbuilder > .cff-group-fields').first()
          );
        }

        if (type === 'flexible') {
          var layouts = [];
          $f.find('.cff-layouts .cff-layout').each(function(){
            var $l = $(this);
            var litem = {
              label: $l.find('.cff-llabel').val() || '',
              name:  CFF.utils.sanitizeName($l.find('.cff-lname').val() || ''),
              sub_fields: []
            };

            litem.sub_fields = readSubfields($l.find('.cff-layout-fields'));

            layouts.push(litem);
          });
          item.layouts = layouts;
        }

        data.push(item);
      });
      return data;
    }


    var fieldViewMode = 'builder';

    function commit(){
      save(readFromDOM());
      $input.trigger('change'); // bantu WP detect dirty
    }

    function commitFromDOM(){
      save(readFromDOM());
    }


    function setFieldViewMode(mode){
      fieldViewMode = (mode === 'reorder') ? 'reorder' : 'builder';
      $root.find('.cff-field-builder-root').attr('data-view-mode', fieldViewMode);
      $root.find('#cff-field-view-mode').val(fieldViewMode);
    }

    function refreshReorderList(){
      var $reorderList = $root.find('#cff-field-reorder-list');
      var $list = $('#cff-field-list');
      if (!$list.length || !$reorderList.length) return;
      $reorderList.empty();
      $list.find('.cff-field-row').each(function(){
        var $row = $(this);
        var key = $row.attr('data-field-key') || $row.data('field-key') || '';
        var label = $row.find('.cff-label').val() || $row.find('.cff-name').val() || '';
        if (!label) label = 'Field ' + (key || '');
        var item = '<li class="cff-field-reorder-item" data-field-key="' + key + '">' +
          '<span class="dashicons dashicons-menu cff-field-reorder-handle" aria-hidden="true"></span>' +
          '<span class="cff-field-reorder-label">' + CFF.utils.escapeHtml(label) + '</span>' +
        '</li>';
        $reorderList.append(item);
      });
    }

    function applyReorderFromReorderList(){
      var $list = $('#cff-field-list');
      var $reorderList = $root.find('#cff-field-reorder-list');
      if (!$list.length || !$reorderList.length) return;
      var order = [];
      $reorderList.find('.cff-field-reorder-item').each(function(){
        var key = $(this).attr('data-field-key');
        if (key) order.push(key);
      });
      if (!order.length) return;
      order.forEach(function(key){
        var $row = $list.find('.cff-field-row[data-field-key="' + key + '"]').first();
        if ($row.length) $row.appendTo($list);
      });
      save(readFromDOM());
      refreshReorderList();
      refreshConditionalFieldDropdowns();
    }

    function render(){
      var data = load();
      $root.empty();

      var builderHead = '<div class="cff-builder-head">' +
        '<strong>Fields</strong>' +
        '<button type="button" class="button button-primary" id="cff-add-field">Add Field</button>' +
      '</div>';
      var builderHeadRow = '<div class="cff-builder-head-row">' +
        '<div class="cff-head-spacer"></div>' +
        '<div class="cff-head">Label</div>' +
        '<div class="cff-head">Name</div>' +
        '<div class="cff-head">Type</div>' +
        '<div class="cff-head">Actions</div>' +
      '</div>';
      var viewControl = '<div class="cff-field-view-controls">' +
        '<label for="cff-field-view-mode">Type</label>' +
        '<select id="cff-field-view-mode">' +
          '<option value="builder">Builder</option>' +
          '<option value="reorder">Reorder</option>' +
        '</select>' +
      '</div>';

      var $builderRoot = $('<div class="cff-field-builder-root" data-view-mode="' + fieldViewMode + '"></div>');
      $builderRoot.append(builderHead);
      $builderRoot.append(viewControl);

      var $builderView = $('<div class="cff-field-builder-view"></div>');
      $builderView.append(builderHeadRow);

      $root.append(
        $builderRoot
      );

      var $list = $('<div id="cff-field-list"></div>');
      $builderView.append($list);
      $builderRoot.append($builderView);

      var $reorderView = $(
        '<div class="cff-field-reorder-view">' +
          '<p class="cff-field-reorder-description">Drag the fields below to update their order inside the builder.</p>' +
          '<ul id="cff-field-reorder-list" class="cff-field-reorder-list"></ul>' +
        '</div>'
      );
      $builderRoot.append($reorderView);

      data.forEach(function(f, i){
        var html = CFF.utils.tmpl(tplField, {
          i: i,
          label: CFF.utils.escapeHtml(f.label || ''),
          name:  CFF.utils.escapeHtml(f.name  || ''),
          placeholder: CFF.utils.escapeHtml(f.placeholder || '')
        });

        var $el = $(html);
        $el.addClass('is-collapsed');
        $el.find('.cff-type').val(f.type || 'text');

        toggleBuilders($el);
        renderChoicesPanel($el, f);
        toggleChoicePanel($el, f.type);
        renderRelationalPanel($el, f);
        toggleRelationalPanel($el, f.type);
        togglePlaceholderRow($el, f.type || 'text');
        var layoutValue = f.repeater_layout || 'default';
        $el.find('.cff-repeater-layout').val(layoutValue);
        toggleRepeaterOptions($el, f.type || 'text');
        toggleDatetimeOptions($el, f.type || 'text');
        $el.find('.cff-placeholder').val(f.placeholder || '');
        $el.find('.cff-required-toggle').prop('checked', !!f.required);
        $el.find('.cff-datetime-use-time-toggle').prop('checked', (f.datetime_use_time !== false));
        renderConditionalPanel($el, f);
        var fieldKey = f._key || 'cff-field-' + i + '-' + Math.random().toString(36).slice(2);
        $el.attr('data-field-key', fieldKey);
        $el.data('field-key', fieldKey);

        if (f.type === 'repeater' && Array.isArray(f.sub_fields)) {
          var $sf = $el.find('> .cff-advanced > .cff-subbuilder > .cff-subfields').first();
          f.sub_fields.forEach(function(s, si){ $sf.append(renderSub(s, si)); });
          sortableSubs($sf);
        }

        if (f.type === 'group' && Array.isArray(f.sub_fields)) {
          var $gf = $el.find('> .cff-advanced > .cff-groupbuilder > .cff-group-fields').first();
          f.sub_fields.forEach(function(s, si){ $gf.append(renderSub(s, si)); });
          sortableSubs($gf);
        }

        if (f.type === 'flexible' && Array.isArray(f.layouts)) {
          var $layouts = $el.find('.cff-layouts');
          f.layouts.forEach(function(l, li){ $layouts.append(renderLayout(l, li)); });
          sortableLayouts($layouts);
        }

        $list.append($el);
      });

      refreshReorderList();

      $('#cff-field-list').sortable({
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); refreshReorderList(); }
      });

      var $reorderList = $builderRoot.find('#cff-field-reorder-list');
      $reorderList.sortable({
        handle: '.cff-field-reorder-handle',
        update: function(){ applyReorderFromReorderList(); }
      });

      setFieldViewMode(fieldViewMode);

      $root.find('.cff-subfield').each(function(){
        var $sub = $(this);
        toggleSubGroup($sub);
        toggleSubRepeater($sub);
      });

      $(document).trigger('cff:refresh', $root);
    }

    function readSubfields($container){
      if (!$container || !$container.length) return []; 

      var subs = [];
      var seen = new Set();

      function getTmpKey($sub){
        var k = $sub.attr('data-tmpkey');
        if (!k) {
          k = 'tmp_' + Date.now() + '_' + Math.random().toString(16).slice(2);
          $sub.attr('data-tmpkey', k);
        }
        return k;
      }

      $container.children('.cff-subfield').each(function(){
        var $sub = $(this);
        var stype = $sub.find('.cff-stype').val() || 'text';

        var rawName = $sub.find('.cff-sname').val() || '';
        var name = CFF.utils.sanitizeName(rawName);

        // pakai name kalau ada, kalau kosong pakai tmpkey
        var key = name || getTmpKey($sub);

        if (seen.has(key)) return;
        seen.add(key);

        var item = {
          label: $sub.find('.cff-slabel').val() || '',
          name:  name,                 // boleh kosong sementara
          _tmp:  name ? '' : key,       // simpan tmp id biar stabil
          type:  stype,
          required: $sub.find('.cff-required-toggle').is(':checked')
        };

        item.placeholder = $sub.find('.cff-placeholder').val() || '';
        if (stype === 'datetime_picker') {
          item.datetime_use_time = $sub.find('.cff-datetime-use-time-toggle').is(':checked');
        }
        var conditionalLogic = readConditionalLogic($sub);
        if (conditionalLogic) {
          item.conditional_logic = conditionalLogic;
        }

        if (stype === 'choice') {
          item.choices = readChoices($sub.find('.cff-choices-list'));
          item.choice_display = $sub.find('.cff-choice-display').val() || 'select';
        }

        if (stype === 'relational') {
          item.relational_type = $sub.find('.cff-relational-type').val() || 'post';
          item.relational_subtype = $sub.find('.cff-relational-subtype').val() || '';
          item.relational_display = $sub.find('.cff-relational-display').val() || 'select';
          item.relational_multiple = $sub.find('.cff-relational-multiple-toggle').is(':checked');
        }

        if (stype === 'group') {
          item.sub_fields = readSubfields(
            $sub.find('> .cff-groupbuilder > .cff-group-fields').first()
          );
        }
        if (stype === 'repeater') {
          item.sub_fields = readSubfields(
            $sub.find('> .cff-subbuilder > .cff-subfields').first()
          );
          item.repeater_layout = $sub.find('.cff-repeater-layout').val() || 'default';
        }

        subs.push(item);
      });

      return subs;
    }

    function autoNameFromLabel($row){
      var $name = $row.find('.cff-name');
      if ($name.data('manual') === 1) return;

      var label = $row.find('.cff-label').val() || '';
      if (!label) return;

      var slug = CFF.utils.sanitizeName(label);
      if (!slug) return;

      var current  = CFF.utils.sanitizeName($name.val() || '');
      var prevAuto = $name.data('auto') || '';

      if (!current || current === prevAuto) {
        $name.val(slug);
        $name.data('auto', slug);
      }
    }

    function autoNameFromSubLabel($sub){
      var $name = $sub.find('.cff-sname');
      if (!$name.length) return;
      if ($name.data('manual') === 1) return;

      var label = $sub.find('.cff-slabel').val() || '';
      if (!label) return;

      var slug = CFF.utils.sanitizeName(label);
      if (!slug) return;

      var $container = $sub.parent();
      var used = new Set();
      $container.children('.cff-subfield').not($sub).each(function(){
        var n = CFF.utils.sanitizeName($(this).find('.cff-sname').val() || '');
        if (n) used.add(n);
      });

      var unique = slug;
      var i = 2;
      while (used.has(unique)) {
        unique = slug + '_' + i;
        i++;
      }

      var current  = CFF.utils.sanitizeName($name.val() || '');
      var prevAuto = $name.data('auto') || '';

      if (!current || current === prevAuto) {
        $name.val(unique);
        $name.data('auto', unique);
      }
    }

    function bindEvents(){
      // Duplicate
      $root.on('click', '.cff-duplicate', function(){
        var gi = $(this).closest('.cff-field-row').index();
        var data = readFromDOM();
        var item = data[gi] ? JSON.parse(JSON.stringify(data[gi])) : null;
        if (!item) return;

        item.name = '';
        item.label = (item.label ? item.label + ' (Copy)' : '');
        data.splice(gi + 1, 0, item);

        save(data);
        render();
      });

      // Add field
      $root.on('click', '#cff-add-field', function(){
        var data = readFromDOM();
        data.push({ label:'', name:'', type:'text' });
        save(data);
        render();
      });

      // Name input manual
      $root.on('input', '.cff-name', function(){
        $(this).data('manual', 1);
        $(this).val(CFF.utils.sanitizeName($(this).val()));
        refreshConditionalFieldDropdowns();
      });

      // Label -> auto name
      $root.on('input', '.cff-label', function(){
        autoNameFromLabel($(this).closest('.cff-field-row'));
      });

      // Save on blur
      $root.on('blur', '.cff-label, .cff-name', function(){
        save(readFromDOM());
      });

      // Subfields auto name
      $root.on('input', '.cff-sname', function(){
        $(this).data('manual', 1);
        $(this).val(CFF.utils.sanitizeName($(this).val()));
        refreshConditionalFieldDropdowns();
      });

      $root.on('input', '.cff-slabel', function(){
        autoNameFromSubLabel($(this).closest('.cff-subfield'));
      });

      $root.on('blur', '.cff-slabel, .cff-sname', function(){
        save(readFromDOM());
      });


      $root.on('input', '.cff-slabel, .cff-sname, .cff-stype, .cff-placeholder, .cff-choice-label, .cff-choice-value', CFF.utils.debounce(function(){
        save(readFromDOM());
      }, 150));

      $root.on('input', '.cff-conditional-value', CFF.utils.debounce(function(){
        save(readFromDOM());
      }, 150));

      $root.on('click', '.cff-add-choice', function(){
        var $panel = $(this).closest('.cff-field-choice');
        if (!$panel.length) return;
        var $row = renderChoiceRow({});
        $panel.find('.cff-choices-list').append($row);
        save(readFromDOM());
      });

      $root.on('click', '.cff-choice-remove', function(){
        $(this).closest('.cff-choice-row').remove();
        save(readFromDOM());
      });

      $root.on('change', '.cff-choice-display', function(){
        save(readFromDOM());
      });

      $root.on('change', '.cff-repeater-layout', function(){
        save(readFromDOM());
      });

      $root.on('change', '.cff-datetime-use-time-toggle', function(){
        save(readFromDOM());
      });

      $root.on('change', '.cff-conditional-enabled', function(){
        var $ctx = $(this).closest('.cff-field-row, .cff-subfield');
        var enabled = $(this).is(':checked');
        updateConditionalFieldOptions($ctx);
        $ctx.find('.cff-conditional-config').first().toggle(enabled);
        toggleConditionalValueInput($ctx);
        save(readFromDOM());
      });

      $root.on('change', '.cff-conditional-field', function(){
        save(readFromDOM());
      });

      $root.on('change', '.cff-conditional-operator', function(){
        var $ctx = $(this).closest('.cff-field-row, .cff-subfield');
        toggleConditionalValueInput($ctx);
        save(readFromDOM());
      });

      // Layout name sanitize
      $root.on('input', '.cff-lname', function(){
        $(this).val(CFF.utils.sanitizeName($(this).val()));
      });

      // Type change
      $root.on('change', '.cff-type', function(){
        var $row = $(this).closest('.cff-field-row');
        toggleBuilders($row);
        toggleChoicePanel($row, $(this).val());
        toggleRelationalPanel($row, $(this).val());
        renderRelationalPanel($row, {
          relational_type: $row.find('.cff-relational-type').val() || 'post',
          relational_subtype: $row.find('.cff-relational-subtype').val() || '',
          relational_display: $row.find('.cff-relational-display').val() || 'select',
          relational_multiple: $row.find('.cff-relational-multiple-toggle').is(':checked'),
        });
        togglePlaceholderRow($row, $(this).val());
        toggleDatetimeOptions($row, $(this).val());
        save(readFromDOM());
      });

      $root.on('change', '#cff-field-view-mode', function(){
        setFieldViewMode($(this).val());
      });

      $root.on('change', '.cff-stype', function(){
        var $sub = $(this).closest('.cff-subfield');
        toggleSubGroup($sub);
        toggleSubRepeater($sub);
        toggleChoicePanel($sub, $(this).val());
        togglePlaceholderRow($sub, $(this).val());
        renderRelationalPanel($sub, {
          relational_type: $sub.find('.cff-relational-type').val() || 'post',
          relational_subtype: $sub.find('.cff-relational-subtype').val() || '',
          relational_display: $sub.find('.cff-relational-display').val() || 'select',
          relational_multiple: $sub.find('.cff-relational-multiple-toggle').is(':checked'),
        });
        toggleRelationalPanel($sub, $(this).val());
        toggleRepeaterOptions($sub, $(this).val());
        toggleDatetimeOptions($sub, $(this).val());
        save(readFromDOM());
      });

      $root.on('click', '.cff-acc-toggle', function(){
        var $row = $(this).closest('.cff-field-row');
        $row.toggleClass('is-collapsed');
        $(this).attr('aria-expanded', !$row.hasClass('is-collapsed'));
      });

      $root.on('click', '.cff-sub-acc-toggle', function(){
        var $sub = $(this).closest('.cff-subfield');
        $sub.toggleClass('is-collapsed');
        $(this).attr('aria-expanded', !$sub.hasClass('is-collapsed'));
      });

      $root.on('blur', '.cff-llabel, .cff-lname', function(){
        save(readFromDOM());
      });

      // Remove field
      $root.on('click', '.cff-remove', function(){
        if (!window.confirm('Are you sure you want to remove this field?')) return;
        $(this).closest('.cff-field-row').remove();
        save(readFromDOM());
      });

      // Repeater add/remove sub
      $root.on('click', '.cff-add-sub', function(){
        var $builder = $(this).closest('.cff-subbuilder');
        var $container = $builder.find('> .cff-subfields').first();
        if (!$container.length) return;
        var $sub = renderSub({ label:'', name:'', type:'text' }, Date.now());
        $container.append($sub);
        sortableSubs($container);
        save(readFromDOM());
        $(document).trigger('cff:refresh', $sub);
      });

      $root.on('click', '.cff-add-group-sub', function(){
        var $builder = $(this).closest('.cff-groupbuilder');
        var $sub = renderSub({ label:'', name:'', type:'text' }, Date.now());
        var $fields = $builder.find('> .cff-group-fields').first();
        if (!$fields.length) $fields = $builder.find('.cff-group-fields').first();
        $fields.append($sub);
        save(readFromDOM());
        $(document).trigger('cff:refresh', $sub);
      });

      $root.on('click', '.cff-remove-sub', function(){
        if (!window.confirm('Remove this sub field?')) return;
        $(this).closest('.cff-subfield').remove();
        save(readFromDOM());
      });

      // Flexible add/remove layout
      $root.on('click', '.cff-add-layout', function(){
        var $f = $(this).closest('.cff-field-row');
        var $layout = renderLayout({ label:'', name:'', sub_fields:[] }, Date.now());
        $f.find('.cff-layouts').append($layout);
        save(readFromDOM());
        $(document).trigger('cff:refresh', $layout);
      });

      $root.on('click', '.cff-remove-layout', function(){
        if (!window.confirm('Discard this layout?')) return;
        $(this).closest('.cff-layout').remove();
        save(readFromDOM());
      });

      $root.on('click', '.cff-toggle-layout', function(){
        $(this).closest('.cff-layout').toggleClass('open');
      });

      $root.on('click', '.cff-add-layout-field', function(){
        var $layout = $(this).closest('.cff-layout');
        var $sub = renderSub({ label:'', name:'', type:'text' }, Date.now());
        $layout.find('.cff-layout-fields').append($sub);
        save(readFromDOM());
        $(document).trigger('cff:refresh', $sub);
      });

      $root.on('change', '.cff-required-toggle', function(){
        commit();
      });

      // Relational field configuration
      $root.on('change', '.cff-relational-type', function(){
        var $ctx = $(this).closest('.cff-field-row');
        if (!$ctx.length) {
          $ctx = $(this).closest('.cff-subfield');
        }
        updateRelationalSubtypeOptions($ctx);
        save(readFromDOM());
      });

      $root.on(
        'change',
        '.cff-relational-subtype, .cff-relational-display, .cff-relational-multiple-toggle',
        function(){
          var $ctx = $(this).closest('.cff-field-row');
          if (!$ctx.length) $ctx = $(this).closest('.cff-subfield');
          save(readFromDOM());
        }
      );


      $root.on('change', '.cff-relational-subtype', function(){
        var $ctx = $(this).closest('.cff-field-row');
        if (!$ctx.length) $ctx = $(this).closest('.cff-subfield');

        $ctx.data('cff-relational-subtype', $(this).val() || '');
        save(readFromDOM());
      });

    }

    $(document).on('cff:refresh', function(_, $target){
      if (!$target || !$target.length) return;
      var $rowTargets = $target.is('.cff-field-row') ? $target : $target.find('.cff-field-row');
      $rowTargets.each(function(){
        var $row = $(this);
        toggleRepeaterOptions($row, $row.find('.cff-type').val() || 'text');
        toggleDatetimeOptions($row, $row.find('.cff-type').val() || 'text');
      });
      var $subTargets = $target.is('.cff-subfield') ? $target : $target.find('.cff-subfield');
      $subTargets.each(function(){
        var $sub = $(this);
        toggleRepeaterOptions($sub, $sub.find('.cff-stype').val() || 'text');
        toggleDatetimeOptions($sub, $sub.find('.cff-stype').val() || 'text');
      });
    });
    return { init:init, render:render };
  })();

  /* -------------------------
   * Location Builder
   * ------------------------- */
   /* -------------------------
    * Location Builder (FIX SAVE)
    * - Jangan save() saat render awal (hydration)
    * - Save hanya pada user interaction
    * ------------------------- */
   CFF.locationBuilder = (function(){
     var $locInput, $locRoot, tplGroup, tplRule;
     var isHydrating = false;

     function init(){
       $locInput = $('#cff_location_json');
       $locRoot  = $('#cff-location-builder');
       if (!$locRoot.length || !$locInput.length) return;

       tplGroup = $('#tmpl-cff-loc-group').html();
       tplRule  = $('#tmpl-cff-loc-rule').html();

       if (!tplGroup) tplGroup =
         '<div class="cff-loc-group" data-gi="{{gi}}">' +
           '<div class="cff-loc-head"><strong>Rule Group (OR)</strong>' +
           '<button type="button" class="button cff-loc-remove-group">Remove group</button></div>' +
           '<div class="cff-loc-rules"></div>' +
           '<p><button type="button" class="button cff-loc-add-rule">Add rule (AND)</button></p>' +
         '</div>';

       if (!tplRule) tplRule =
         '<div class="cff-loc-rule" data-ri="{{ri}}">' +
           '<select class="cff-input cff-loc-param">' +
             '<option value="post_type">Post Type</option>' +
             '<option value="page_template">Page Template</option>' +
             '<option value="post">Specific Post</option>' +
             '<option value="page">Specific Page</option>' +
             '<option value="options_page">Options Page</option>' +
           '</select>' +
           '<select class="cff-input cff-loc-op">' +
             '<option value="==">is equal to</option>' +
             '<option value="!=">is not equal to</option>' +
           '</select>' +
           '<select class="cff-input cff-loc-value"></select>' +
           '<button type="button" class="button cff-loc-remove-rule">×</button>' +
         '</div>';

       bindEvents();
       render();

       $(document).on('cff:tab:location', function(){ render(); });
     }

     function load(){
       var v = CFF.utils.jsonParse($locInput.val() || '[]', []);
       if (!Array.isArray(v) || !v.length) {
         v = [[{param:'post_type',operator:'==',value:'post'}]];
         // ini default baru boleh disave karena beneran kosong
         $locInput.val(JSON.stringify(v));
       }
       return v;
     }

     function save(data){
       $locInput.val(JSON.stringify(data || []));
     }

     function fetchOptions(param, cb){
       if (!window.CFFP || !CFFP.ajax) { cb([]); return; }

       if (param === 'post_type') {
         $.post(CFFP.ajax, {action:'cff_get_post_types', nonce:CFFP.nonce}, function(res){
           cb(res && res.success ? res.data : []);
         });
         return;
       }

       if (param === 'page_template') {
         $.post(CFFP.ajax, {action:'cff_get_templates', nonce:CFFP.nonce}, function(res){
           cb(res && res.success ? res.data : []);
         });
         return;
       }

       if (param === 'options_page') {
         cb([{id:'global', text:'Global Settings'}]);
         return;
       }

       var pt = (param === 'page') ? 'page' : 'post';
       $.post(CFFP.ajax, {action:'cff_search_posts', nonce:CFFP.nonce, q:'', post_type:pt}, function(res){
         cb(res && res.success ? res.data : []);
       });
     }

     function populateRuleValue($rule, param, selected){
       var $val = $rule.find('.cff-loc-value');
       $val.empty().append('<option value="">Loading…</option>');

       fetchOptions(param, function(opts){
         $val.empty();

         (opts || []).forEach(function(o){
           $val.append($('<option></option>').attr('value', o.id).text(o.text));
         });

         // set selected if exists
         if (selected) $val.val(String(selected));

         // jika selected kosong, set index 0 (tapi jangan save saat hydration)
         if (!$val.val() && $val.find('option').length) $val.prop('selectedIndex', 0);

         // ✅ penting: jangan save saat render awal
         if (!isHydrating) save(readFromDOM());
       });
     }

     function readFromDOM(){
       var out = [];
       $locRoot.find('.cff-loc-group').each(function(){
         var g = [];
         $(this).find('.cff-loc-rule').each(function(){
           var param = $(this).find('.cff-loc-param').val() || 'post_type';
           var op    = $(this).find('.cff-loc-op').val() || '==';
           var val   = $(this).find('.cff-loc-value').val() || '';
           if (val) g.push({param:param, operator:op, value:val});
         });
         if (g.length) out.push(g);
       });
       return out;
     }

     function render(){
       isHydrating = true;

       var groups = load();
       $locRoot.empty();

       groups.forEach(function(rules, gi){
         var $g = $(CFF.utils.tmpl(tplGroup, {gi:gi}));
         var $rules = $g.find('.cff-loc-rules');

         (rules || []).forEach(function(rule, ri){
           var $r = $(CFF.utils.tmpl(tplRule, {ri:ri}));
           $r.find('.cff-loc-param').val(rule.param || 'post_type');
           $r.find('.cff-loc-op').val(rule.operator || '==');
           $rules.append($r);

           populateRuleValue($r, rule.param || 'post_type', rule.value || '');
         });

         $locRoot.append($g);
       });

       $locRoot.append('<p><button type="button" class="button button-primary" id="cff-loc-add-group">Add rule group (OR)</button></p>');

       // ✅ jangan save di sini. biarkan tetap value lama sampai user ubah
       setTimeout(function(){ isHydrating = false; }, 0);
     }

     function bindEvents(){
       $locRoot.on('click', '#cff-loc-add-group', function(){
         var groups = load();
         groups.push([{param:'post_type',operator:'==',value:'post'}]);
         save(groups);
         render();
       });

       $locRoot.on('click', '.cff-loc-add-rule', function(){
         var gi = $(this).closest('.cff-loc-group').data('gi');
         var groups = load();
         if (!groups[gi]) groups[gi] = [];
         groups[gi].push({param:'post_type',operator:'==',value:'post'});
         save(groups);
         render();
       });

       $locRoot.on('click', '.cff-loc-remove-group', function(){
         var gi = $(this).closest('.cff-loc-group').data('gi');
         var groups = load();
         groups.splice(gi,1);
         if (!groups.length) groups = [[{param:'post_type',operator:'==',value:'post'}]];
         save(groups);
         render();
       });

       $locRoot.on('click', '.cff-loc-remove-rule', function(){
         $(this).closest('.cff-loc-rule').remove();
         var v = readFromDOM();
         if (!v.length) v = [[{param:'post_type',operator:'==',value:'post'}]];
         save(v);
       });

       $locRoot.on('change', '.cff-loc-param', function(){
         var $r = $(this).closest('.cff-loc-rule');
         populateRuleValue($r, $(this).val(), '');
         // save akan dipanggil setelah options ready (karena isHydrating=false saat user action)
       });

       $locRoot.on('change', '.cff-loc-op, .cff-loc-value', function(){
         if (isHydrating) return;
         save(readFromDOM());
       });
     }

     return { init:init, render:render };
   })();

  /* -------------------------
   * Presentation
   * ------------------------- */
   CFF.presentation = (function(){
     var $input, state;

     function init(){
       $input = $('#cff_presentation_json');
       if (!$input.length) return;

       state = $.extend(true, {
         style: 'standard',
         position: 'normal',
         label_placement: 'top',
         instruction_placement: 'below_labels',
         order: 0,
         hide_on_screen: {}
       }, load());

       if (!state.hide_on_screen || Array.isArray(state.hide_on_screen)) state.hide_on_screen = {};

       bindEvents();
       sync();

       // Sync saat tab dibuka (jangan commit)
       $(document).off('cfftabpres.cffpres').on('cff:tab:presentation.cffpres', function(){
         sync();
       });

       $('#post')
         .off('submit.cffFields')
         .on('submit.cffFields', function(){
           commit();
         });

       $(document)
         .off('click.cffFieldsPublish',
           '#publish, #save-post, #post-preview, .editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-publish-panel__header-publish-button'
         )
         .on('click.cffFieldsPublish',
           '#publish, #save-post, #post-preview, .editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-publish-panel__header-publish-button',
           function(){
             commit();
           }
         );
     }

     function load(){
       return CFF.utils.jsonParse($input.val(), {});
     }

     function save(){
       $input.val(JSON.stringify(state || {}));
       // bantu WP sadar ada perubahan
       $input.trigger('change');
     }

     // ✅ Ambil kondisi checkbox langsung dari DOM -> state -> hidden input
     function commitFromDOM(){
       var $panel = $('.cff-tabpanel[data-panel="presentation"]');
       if (!$panel.length) {
         save();
         return;
       }

       // ambil tombol aktif (kalau ada UI button-group)
       $panel.find('.cff-btn-group').each(function(){
         var key = $(this).data('name');
         var $active = $(this).find('button.active');
         if ($active.length) state[key] = $active.data('value');
       });

       // order
       var orderVal = parseInt($('#cff-order').val() || 0, 10);
       state.order = isNaN(orderVal) ? 0 : orderVal;

       // hide_on_screen
       var hide = {};
       $panel.find('.cff-hide-screen input[type=checkbox]').each(function(){
         var k = $(this).data('key');
         if (!k || k === 'toggle_all') return;
         if ($(this).is(':checked')) hide[k] = true;
       });
       state.hide_on_screen = hide;

       save();
     }

     function sync(){
       var $panel = $('.cff-tabpanel[data-panel="presentation"]');
       if (!$panel.length) return;

       $panel.find('.cff-btn-group').each(function(){
         var key = $(this).data('name');
         $(this).find('button').removeClass('active')
           .filter('[data-value="'+state[key]+'"]').addClass('active');
       });

       $('#cff-order').val(parseInt(state.order || 0, 10));

       $panel.find('.cff-hide-screen input[type=checkbox]').each(function(){
         var k = $(this).data('key');
         if (k === 'toggle_all') return;
         $(this).prop('checked', !!(state.hide_on_screen && state.hide_on_screen[k]));
       });

       save();
     }

     function bindEvents(){
       // button groups
       $(document).on('click', '.cff-tabpanel[data-panel="presentation"] .cff-btn-group button', function(){
         var $g = $(this).closest('.cff-btn-group');
         state[$g.data('name')] = $(this).data('value');
         sync();
       });

       // order
       $(document).on('input', '#cff-order', function(){
         state.order = parseInt($(this).val() || 0, 10);
         save();
       });

       // hide on screen
       $(document).on('change', '.cff-tabpanel[data-panel="presentation"] .cff-hide-screen input[type=checkbox]', function(){
         var $panel = $('.cff-tabpanel[data-panel="presentation"]');
         var k = $(this).data('key');

         // ✅ TOGGLE ALL
         if (k === 'toggle_all') {
           var on = $(this).is(':checked');

           $panel.find('.cff-hide-screen input[type=checkbox]')
             .not(this)
             .prop('checked', on);

           commitFromDOM(); // ✅ simpan ke hidden input
           return;
         }

         // ✅ kalau checkbox individual berubah, update toggle_all (optional tapi enak)
         var $items = $panel.find('.cff-hide-screen input[type=checkbox]').not('[data-key="toggle_all"]');
         var allChecked = ($items.length && $items.filter(':checked').length === $items.length);
         $panel.find('.cff-hide-screen input[data-key="toggle_all"]').prop('checked', allChecked);

         commitFromDOM(); // ✅ simpan ke hidden input
       });
     }

     return { init:init, sync:sync, commit:commitFromDOM };
   })();

  /* -------------------------
   * Tools UI helpers
   * ------------------------- */
  CFF.toolsUI = (function(){
    function init(){
      $(document).on('ready cff:tools', function(){
        $('.cff-tools-card').each(function(){
          filterSelect($(this));
          advToggle($(this));
        });
      });
      $(document).trigger('cff:tools');
    }

    function filterSelect($wrap){
      var $input = $wrap.find('.cff-select-filter');
      var $sel = $wrap.find('select[multiple]');
      if (!$input.length || !$sel.length) return;

      $input.on('input', function(){
        var q = ($(this).val()||'').toLowerCase();
        $sel.find('option').each(function(){
          var t = ($(this).text()||'').toLowerCase();
          $(this).toggle(!q || t.indexOf(q) !== -1);
        });
      });
    }

    function advToggle($scope){
      var $toggle = $scope.find('input.cff-adv-toggle');
      if (!$toggle.length) return;

      function apply(){
        var on = $toggle.is(':checked');
        $scope.find('.cff-adv-section').toggle(on);
      }

      $toggle.on('change', apply);
      apply();
    }

    return { init:init };
  })();

  /* -------------------------
   * Dashicon Picker
   * ------------------------- */
  CFF.dashicons = (function(){
    var icons = [
      'dashicons-admin-appearance','dashicons-admin-comments','dashicons-admin-home','dashicons-admin-media','dashicons-admin-page','dashicons-admin-post','dashicons-admin-settings','dashicons-admin-tools','dashicons-admin-users','dashicons-analytics','dashicons-awards','dashicons-book','dashicons-calendar','dashicons-camera','dashicons-cart','dashicons-category','dashicons-chart-area','dashicons-chart-bar','dashicons-chart-line','dashicons-chart-pie','dashicons-clipboard','dashicons-cloud','dashicons-controls-play','dashicons-download','dashicons-edit','dashicons-email','dashicons-external','dashicons-facebook','dashicons-format-image','dashicons-format-video','dashicons-format-gallery','dashicons-format-audio','dashicons-heart','dashicons-id','dashicons-images-alt2','dashicons-info','dashicons-instagram','dashicons-location','dashicons-lock','dashicons-marker','dashicons-megaphone','dashicons-money-alt','dashicons-networking','dashicons-phone','dashicons-portfolio','dashicons-products','dashicons-search','dashicons-share','dashicons-shield','dashicons-star-filled','dashicons-store','dashicons-tag','dashicons-thumbs-up','dashicons-tickets','dashicons-translation','dashicons-twitter','dashicons-video-alt3','dashicons-visibility','dashicons-warning','dashicons-welcome-learn-more','dashicons-welcome-write-blog'
    ];

    function init(){
      var $modal = $('#cff-icon-modal');
      if (!$modal.length) return;

      var $grid = $modal.find('.cff-icon-grid');
      renderIcons($grid, '');

      $(document).on('click', '.cff-icon-picker', function(){
        $modal.show();
        $modal.data('target', $(this).closest('.cff-icon-row').find('input[name="menu_icon"]'));
        $modal.find('.cff-icon-search').val('').trigger('input');
      });

      $(document).on('click', '.cff-modal-close', function(){ $modal.hide(); });
      $(document).on('click', '#cff-icon-modal', function(e){ if (e.target === this) $modal.hide(); });

      $modal.on('input', '.cff-icon-search', function(){
        renderIcons($grid, $(this).val());
      });

      $modal.on('click', '.cff-icon-btn', function(){
        var icon = $(this).data('icon');
        var $target = $modal.data('target');
        if ($target && $target.length) $target.val(icon).trigger('change');
        $modal.hide();
      });
    }

    function renderIcons($grid, q){
      q = (q||'').toLowerCase();
      $grid.empty();
      icons.filter(function(i){ return !q || i.indexOf(q) !== -1; })
        .forEach(function(i){
          var $b = $('<button type="button" class="cff-icon-btn"><span class="dashicons '+i+'"></span><span class="cff-icon-name">'+i+'</span></button>');
          $b.data('icon', i);
          $grid.append($b);
        });
    }

    return { init:init };
  })();

  /* -------------------------
   * ACF-like Multi-select
   * ------------------------- */
  CFF.multiselect = (function(){
    function init(){
      initAll();
      $(document).on('cff:refresh', initAll);
    }

    function initAll(){
      $('select.cff-multiselect').not('.cff-select2').each(function(){
        initOne($(this));
      });
    }

    function initOne($select){
      if ($select.data('cffDone')) return;
      $select.data('cffDone', true);
      if (!$select.prop('multiple')) return;

      var id = $select.attr('id') || ('cff-ms-' + Math.random().toString(36).slice(2));
      $select.attr('id', id);

      var $wrap = $('<div class="cff-ms-wrap"></div>');
      var $list = $('<div class="cff-ms-list"></div>');
      $select.after($wrap);
      $wrap.append($list).append($select);
      $select.addClass('cff-ms-hidden');

      function render(){
        $list.empty();
        $select.find('option').each(function(){
          var $opt = $(this);
          var val = $opt.attr('value');
          var label = $opt.text();
          var checked = $opt.prop('selected');

          var $row = $('<label class="cff-ms-item"><input type="checkbox"> <span></span></label>');
          $row.find('input').prop('checked', checked).data('value', val);
          $row.find('span').text(label);
          $list.append($row);
        });
      }

      render();

      function cssEscape(v){
        if (window.CSS && CSS.escape) return CSS.escape(String(v));
        return String(v).replace(/["\\#.:;,[\]=<>+~*^$()|!?{}]/g, '\\$&');
      }

      $list.on('change', 'input[type=checkbox]', function(){
        var val = $(this).data('value');
        var isChecked = $(this).is(':checked');
        $select.find('option[value="' + cssEscape(val) + '"]').prop('selected', isChecked);
      });

      var $filter = $select.closest('td, .cff-tools-field').find('.cff-select-filter[data-target="#'+id+'"]');
      if ($filter.length){
        $filter.on('input', function(){
          var q = ($(this).val()||'').toLowerCase();
          $list.find('.cff-ms-item').each(function(){
            var t = $(this).text().toLowerCase();
            $(this).toggle(!q || t.indexOf(q) !== -1);
          });
        });
      }
    }

    return { init:init };
  })();


  /* -------------------------
 * Presentation UI Builder (if panel empty)
 * - memastikan panel "Presentation" ada isi markup seperti screenshot
 * - JS presentation module kamu akan langsung jalan di markup ini
 * ------------------------- */
  CFF.presentationUI = (function(){
  function init(){
    var $panel = $('.cff-tabpanel[data-panel="presentation"]');
    if (!$panel.length) return;

    var $wrap = $('#cff-presentation-builder');
    if (!$wrap.length) return;

    // jika sudah ada tombol/checkbox, jangan timpa
    if ($wrap.find('.cff-btn-group').length || $wrap.find('.cff-hide-screen').length) return;

    // build UI
    $wrap.html(
      '<div class="cff-presentation-grid">' +
        '<div class="cff-pres-left">' +

          section('Style',
            btnGroup('style', [
              ['standard','Standard (WP metabox)'],
              ['seamless','Seamless (no metabox)']
            ])
          ) +

          section('Position',
            btnGroup('position', [
              ['high','High (after title)'],
              ['normal','Normal (after content)'],
              ['side','Side']
            ])
          ) +

          section('Label Placement',
            btnGroup('label_placement', [
              ['top','Top aligned'],
              ['left','Left aligned']
            ])
          ) +

          section('Instruction Placement',
            btnGroup('instruction_placement', [
              ['below_labels','Below labels'],
              ['below_fields','Below fields']
            ])
          ) +

          '<div class="cff-pres-section">' +
            '<h3>Order No.</h3>' +
            '<input type="number" id="cff-order" class="cff-order-input" value="0">' +
            '<p class="description">Field groups with a lower order will appear first</p>' +
          '</div>' +

        '</div>' +

        '<div class="cff-pres-right">' +
          '<div class="cff-pres-section cff-hide-screen">' +
            '<div class="cff-pres-right-head">' +
              '<h3>Hide on screen</h3>' +
              '<span class="cff-pres-help">?</span>' +
            '</div>' +

            checkbox('toggle_all','Toggle All') +

            checkbox('permalink','Permalink') +
            checkbox('editor','Content Editor') +
            checkbox('excerpt','Excerpt') +
            checkbox('discussion','Discussion') +
            checkbox('comments','Comments') +
            checkbox('revisions','Revisions') +
            checkbox('slug','Slug') +
            checkbox('author','Author') +
            checkbox('format','Format') +
            checkbox('page_attributes','Page Attributes') +
            checkbox('featured_image','Featured Image') +
            checkbox('categories','Categories') +
            checkbox('tags','Tags') +
            checkbox('trackbacks','Send Trackbacks') +

          '</div>' +
        '</div>' +
      '</div>'
    );

    // setelah UI dibuat, trigger sync biar state dari hidden input langsung ke-render
    $(document).trigger('cff:tab:presentation');
  }

  function section(title, inner){
    return '<div class="cff-pres-section"><h3>' + esc(title) + '</h3>' + inner + '</div>';
  }

  function btnGroup(name, items){
    var out = '<div class="cff-btn-group" data-name="' + escAttr(name) + '">';
    items.forEach(function(it){
      out += '<button type="button" data-value="' + escAttr(it[0]) + '">' + esc(it[1]) + '</button>';
    });
    out += '</div>';
    return out;
  }

  function checkbox(key, label){
    return (
      '<label class="cff-check">' +
        '<input type="checkbox" data-key="' + escAttr(key) + '">' +
        '<span>' + esc(label) + '</span>' +
      '</label>'
    );
  }

  function esc(s){
    return String(s||'').replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function escAttr(s){ return esc(s).replace(/"/g,'&quot;'); }

  return { init:init };
})();

  /* -------------------------
   * Metabox Reorder
   * ------------------------- */
  CFF.metaboxReorder = (function(){
    var MAX_RETRIES = 3;

    function init(){
      $('.cff-metabox').each(function(){
        var $meta = $(this);
        if ($meta.data('cffMetaboxInit')) return;
        var $select = $meta.find('.cff-field-view-mode--metabox');
        var $reorder = $meta.find('.cff-metabox-reorder');
        var $list = $reorder.find('.cff-metabox-reorder-list');
        if (!$select.length || !$reorder.length || !$list.length) return;

        $meta.data('cffMetaboxInit', true);
        console.log('[CFF.metaboxReorder] init metabox', $meta[0]);
        buildList($list, $meta, 0);
        toggleReorder($meta, $select.val());

        $select.on('change', function(){
          var mode = $(this).val();
          if (mode === 'reorder') {
            buildList($list, $meta, 0);
          }
          toggleReorder($meta, mode);
        });

        $list.sortable({
          handle: '.cff-metabox-reorder-handle',
          placeholder: 'cff-metabox-reorder-placeholder',
          update: function(){
            applyOrder($meta, $list);
          }
        });
      });
    }

    function getFields($meta){
      var $fields = $meta.find('.cff-metabox-fields > .cff-field[data-field-name]');
      if (!$fields.length) {
        $fields = $meta.find('.cff-field[data-field-name]');
      }
      return $fields;
    }

    function buildList($list, $meta, attempt){
      console.log('[CFF.metaboxReorder] buildList, meta', $meta[0], 'attempt', attempt);
      var $fields = getFields($meta);
      if (!$fields.length) {
        if (attempt < MAX_RETRIES) {
          setTimeout(function(){
            buildList($list, $meta, attempt + 1);
          }, 150);
        } else {
          $list.html('<li class="cff-metabox-reorder-empty">' + esc('No fields could be detected') + '</li>');
        }
        return;
      }

      $list.empty();
      $fields.each(function(){
        var $field = $(this);
        var name = $field.attr('data-field-name') || '';
        var labelCandidates = [
          $.trim($field.find('.cff-label-head label').first().text()),
          $.trim($field.find('.cff-field-head label').first().text()),
          $.trim($field.find('.hndle').first().text()),
          $.trim(name)
        ];
        var label = '';
        labelCandidates.some(function(candidate){
          if (candidate) {
            label = candidate;
            return true;
          }
          return false;
        });
        if ($field.hasClass('cff-field-repeater') && label.indexOf('(Repeater)') === -1) {
          label += ' (Repeater)';
        }
        var item =
          '<li class="cff-metabox-reorder-item" data-field-name="' + escAttr(name) + '">' +
            '<span class="dashicons dashicons-menu cff-metabox-reorder-handle" aria-hidden="true"></span>' +
            '<span class="cff-metabox-reorder-label">' + esc(label) + '</span>' +
          '</li>';
        $list.append(item);
      });
      syncOrderInput($meta, $list);
    }

    function applyOrder($meta, $list){
      var order = [];
      $list.children('.cff-metabox-reorder-item').each(function(){
        order.push($(this).attr('data-field-name'));
      });
      if (!order.length) return;
      var $fieldsWrapper = $meta.find('.cff-metabox-fields').first();
      order.forEach(function(name){
        var $field = $meta.find('.cff-field[data-field-name="' + name + '"]').first();
        if ($field.length) {
          if ($fieldsWrapper.length) {
            $field.appendTo($fieldsWrapper);
          } else {
            $field.appendTo($meta);
          }
        }
      });
      buildList($list, $meta, 0);
    }

    function syncOrderInput($meta, $list){
      var $input = $meta.find('.cff-metabox-order-input').first();
      if (!$input.length) return;
      var order = [];
      $list.children('.cff-metabox-reorder-item').each(function(){
        var name = $(this).attr('data-field-name') || '';
        if (name) order.push(name);
      });
      $input.val(order.join(','));
    }

    function toggleReorder($meta, mode){
      var show = (mode === 'reorder');
      $meta.toggleClass('is-reorder', show);
      $meta.find('.cff-metabox-reorder').attr('aria-hidden', !show);
      if (show) {
        buildList($meta.find('.cff-metabox-reorder-list').first(), $meta, 0);
      }
    }

    function esc(str){
      return String(str||'').replace(/[&<>"']/g, function(m){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
      });
    }

    function escAttr(str){
      return esc(str).replace(/"/g,'&quot;');
    }

    return { init:init };
  })();

  $(document).on('ready cff:refresh', function(){
    CFF.metaboxReorder.init();
  });
  $(window).on('load', function(){
    CFF.metaboxReorder.init();
  });

  /* -------------------------
   * Reorder (Posts/Terms)
   * ------------------------- */
  CFF.reorder = (function(){
    function init(){
      var $root = $('#cff-reorder');
      if (!$root.length) return;

      var $postSelect = $root.find('#cff-reorder-post-type');
      var $taxSelect = $root.find('#cff-reorder-taxonomy');
      var $postList = $root.find('.cff-reorder-list[data-kind="post"]');
      var $termList = $root.find('.cff-reorder-list[data-kind="term"]');
      var $groupList = $root.find('.cff-reorder-list[data-kind="group"]');

      $postList.sortable({ handle: '.cff-reorder-handle' });
      $termList.sortable({ handle: '.cff-reorder-handle' });
      $groupList.sortable({ handle: '.cff-reorder-handle' });

      $root.on('click', '#cff-reorder-load-posts', function(){
        loadPosts($postSelect.val(), $postList);
      });

      $root.on('click', '#cff-reorder-save-posts', function(){
        savePosts($postSelect.val(), $postList);
      });

      $root.on('click', '#cff-reorder-load-terms', function(){
        loadTerms($taxSelect.val(), $termList);
      });

      $root.on('click', '#cff-reorder-save-terms', function(){
        saveTerms($taxSelect.val(), $termList);
      });

      $root.on('click', '#cff-reorder-load-groups', function(){
        loadGroups($groupList);
      });

      $root.on('click', '#cff-reorder-save-groups', function(){
        saveGroups($groupList);
      });

      if ($groupList.length) {
        loadGroups($groupList);
      }
    }

    function renderList($list, items){
      $list.empty();
      if (!items || !items.length) {
        $list.append('<li class="cff-reorder-empty">No items found.</li>');
        return;
      }
      items.forEach(function(it){
        var metaParts = [];
        if (it.order !== undefined) {
          metaParts.push('<span class="cff-reorder-meta">Order ' + esc(String(it.order)) + '</span>');
        }
        if (it.status) {
          metaParts.push('<span class="cff-reorder-meta">' + esc(it.status) + '</span>');
        }
        if (it.count !== undefined) {
          metaParts.push('<span class="cff-reorder-meta">(' + esc(String(it.count)) + ')</span>');
        }
        var meta = metaParts.join(' ');
        var metaHtml = meta ? ' ' + meta : '';
        $list.append(
          '<li class="cff-reorder-item" data-id="' + escAttr(String(it.id)) + '">' +
            '<span class="cff-reorder-handle">≡</span>' +
            '<span class="cff-reorder-title">' + esc(it.title || '') + '</span>' +
            metaHtml +
          '</li>'
        );
      });
    }

    function loadPosts(postType, $list){
      if (!postType) return;
      $list.html('<li class="cff-reorder-empty">Loading…</li>');
      $.post(CFFP.ajax, { action:'cff_reorder_get_posts', nonce:CFFP.nonce, post_type:postType }, function(res){
        renderList($list, res && res.success ? res.data : []);
      });
    }

    function savePosts(postType, $list){
      if (!postType) return;
      var order = [];
      $list.find('.cff-reorder-item').each(function(){
        order.push($(this).data('id'));
      });
      $.post(CFFP.ajax, { action:'cff_reorder_save_posts', nonce:CFFP.nonce, post_type:postType, order:order }, function(res){
        if (!res || !res.success) {
          alert('Failed to save order.');
        } else {
          alert('Order saved.');
        }
      });
    }

    function loadTerms(taxonomy, $list){
      if (!taxonomy) return;
      $list.html('<li class="cff-reorder-empty">Loading…</li>');
      $.post(CFFP.ajax, { action:'cff_reorder_get_terms', nonce:CFFP.nonce, taxonomy:taxonomy }, function(res){
        renderList($list, res && res.success ? res.data : []);
      });
    }

    function saveTerms(taxonomy, $list){
      if (!taxonomy) return;
      var order = [];
      $list.find('.cff-reorder-item').each(function(){
        order.push($(this).data('id'));
      });
      $.post(CFFP.ajax, { action:'cff_reorder_save_terms', nonce:CFFP.nonce, taxonomy:taxonomy, order:order }, function(res){
        if (!res || !res.success) {
          alert('Failed to save order.');
        } else {
          alert('Order saved.');
        }
      });
    }

    function loadGroups($list){
      $list.html('<li class="cff-reorder-empty">Loading…</li>');
      $.post(CFFP.ajax, { action:'cff_reorder_get_groups', nonce:CFFP.nonce }, function(res){
        renderList($list, res && res.success ? res.data : []);
      });
    }

    function saveGroups($list){
      var order = [];
      $list.find('.cff-reorder-item').each(function(){
        order.push($(this).data('id'));
      });
      $.post(CFFP.ajax, { action:'cff_reorder_save_groups', nonce:CFFP.nonce, order:order }, function(res){
        if (!res || !res.success) {
          alert('Failed to save order.');
        } else {
          alert('Order saved.');
        }
      });
    }

    function esc(s){
      return String(s||'').replace(/[&<>"']/g, function(m){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
      });
    }
    function escAttr(s){ return esc(s).replace(/"/g,'&quot;'); }

  return { init:init };
})();

  /* -------------------------
   * Language tabs
   * ------------------------- */
  CFF.langTabs = (function(){
    function init(){
      $(document).on('click', '.cff-lang-tab', function(){
        var $btn = $(this);
        var lang = $btn.data('lang');
        var $wrap = $btn.closest('.cff-lang-tabs');
        $wrap.find('.cff-lang-tab').removeClass('active');
        $btn.addClass('active');
        $wrap.find('.cff-lang-panel').removeClass('active')
          .filter('[data-lang="'+lang+'"]').addClass('active');
      });

      $('.cff-lang-tabs').each(function(){
        var $wrap = $(this);
        var def = $wrap.data('default');
        var $btn = def ? $wrap.find('.cff-lang-tab[data-lang="'+def+'"]') : $wrap.find('.cff-lang-tab').first();
        if ($btn.length) $btn.trigger('click');
      });
    }
    return { init:init };
  })();

  /* -------------------------
   * Auto slug (CPT/Taxonomy)
   * ------------------------- */
  CFF.autoSlug = (function(){
    function slugify(v){
      return String(v || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    }

    function init(){
      var $doc = $(document);

      $doc.on('input', 'input[name="cpt_plural"]', function(){
        var $form = $(this).closest('form');
        var $slug = $form.find('input[name="cpt_slug"]');
        maybeSetSlug($slug, $(this).val());
      });

      $doc.on('input', 'input[name^="cpt_plural_i18n["]', function(){
        var name = $(this).attr('name') || '';
        var match = name.match(/^cpt_plural_i18n\[(.+)\]$/);
        if (!match) return;
        var lang = match[1];
        var $form = $(this).closest('form');
        var $slug = $form.find('input[name="cpt_slug_i18n['+lang+']"]');
        maybeSetSlug($slug, $(this).val());
      });

      $doc.on('input', 'input[name="singular"], input[name="plural"]', function(){
        var $form = $(this).closest('form');
        var $slug = $form.find('input[name="slug"]');
        maybeSetSlug($slug, $(this).val());
      });

      $doc.on('input', 'input[name^="singular_i18n["], input[name^="plural_i18n["]', function(){
        var name = $(this).attr('name') || '';
        var match = name.match(/^(?:singular|plural)_i18n\[(.+)\]$/);
        if (!match) return;
        var lang = match[1];
        var $form = $(this).closest('form');
        var $slug = $form.find('input[name="slug_i18n['+lang+']"]');
        maybeSetSlug($slug, $(this).val());
      });

      $doc.on('input', 'input[name="cpt_slug"], input[name^="cpt_slug_i18n["], input[name="slug"], input[name^="slug_i18n["]', function(){
        $(this).data('cffSlugAuto', false);
      });
    }

    function maybeSetSlug($slug, sourceVal){
      if (!$slug.length) return;
      var current = String($slug.val() || '');
      var auto = $slug.data('cffSlugAuto');
      if (current && auto === false) return;
      var next = slugify(sourceVal);
      if (!next) return;
      $slug.val(next);
      $slug.data('cffSlugAuto', true);
    }

    return { init:init };
  })();

  CFF.conditionalLogic = (function(){
    function init(){
      var $scope = $('.cff-metabox, .wrap.cff-admin');
      if (!$scope.length) return;

      applyAll();

      $(document).on('input change', ':input[name^="cff_values["]', function(){
        applyAll();
      });

      $(document).on('cff:refresh', function(){
        applyAll();
      });
    }

    function applyAll(){
      $('[data-cff-conditional-enabled="1"]').each(function(){
        var $target = $(this);
        var sourceField = String($target.data('cffConditionalField') || '');
        var operator = String($target.data('cffConditionalOperator') || '==');
        var expected = String($target.data('cffConditionalValue') || '');
        if (!sourceField) return;

        var $container = $target.closest('.cff-metabox');
        if (!$container.length) {
          $container = $(document.body);
        }

        var sourceVal = readFieldValue($container, sourceField);
        var visible = compareValue(sourceVal, operator, expected);
        toggleVisibility($target, visible);
      });
    }

    function getNamedInputs($container, baseName){
      return $container.find(':input').filter(function(){
        var n = this.name || '';
        return n === baseName || n === (baseName + '[]');
      });
    }

    function readFieldValue($container, fieldName){
      var baseName = 'cff_values[' + fieldName + ']';
      var $inputs = getNamedInputs($container, baseName);
      if (!$inputs.length) return '';

      var isArray = $inputs.filter(function(){ return (this.name || '').slice(-2) === '[]'; }).length > 0;
      if (isArray) {
        var arr = [];
        $inputs.each(function(){
          var $el = $(this);
          if ($el.is(':checkbox')) {
            if ($el.is(':checked')) arr.push(String($el.val() || ''));
            return;
          }
          if ($el.is('select[multiple]')) {
            ($el.val() || []).forEach(function(v){ arr.push(String(v)); });
            return;
          }
          var v = $el.val();
          if (v !== null && v !== '') arr.push(String(v));
        });
        return arr;
      }

      var $radios = $inputs.filter(':radio');
      if ($radios.length) {
        var $checkedRadio = $radios.filter(':checked').first();
        return $checkedRadio.length ? String($checkedRadio.val() || '') : '';
      }

      var $checkboxes = $inputs.filter(':checkbox');
      if ($checkboxes.length) {
        var $checked = $checkboxes.filter(':checked').last();
        if ($checked.length) return String($checked.val() || '1');
        var $hidden = $inputs.filter(':hidden').first();
        return $hidden.length ? String($hidden.val() || '0') : '0';
      }

      var $selectMulti = $inputs.filter('select[multiple]').first();
      if ($selectMulti.length) return ($selectMulti.val() || []).map(String);

      return String($inputs.last().val() || '');
    }

    function isEmptyValue(value){
      if ($.isArray(value)) return value.length === 0;
      return String(value || '') === '';
    }

    function compareValue(actual, operator, expected){
      var actualStr = $.isArray(actual) ? actual.map(String) : String(actual || '');
      var expectedStr = String(expected || '');

      if (operator === 'empty') return isEmptyValue(actual);
      if (operator === 'not_empty') return !isEmptyValue(actual);

      if ($.isArray(actualStr)) {
        var has = actualStr.indexOf(expectedStr) !== -1;
        if (operator === '==') return has;
        if (operator === '!=') return !has;
        if (operator === 'contains') return has;
        if (operator === 'not_contains') return !has;
        return false;
      }

      if (operator === '==') return actualStr === expectedStr;
      if (operator === '!=') return actualStr !== expectedStr;
      if (operator === 'contains') return actualStr.indexOf(expectedStr) !== -1;
      if (operator === 'not_contains') return actualStr.indexOf(expectedStr) === -1;

      return false;
    }

    function toggleVisibility($target, visible){
      $target.toggle(visible);

      $target.find(':input').each(function(){
        var $input = $(this);
        if ($input.hasClass('cff-conditional-enabled') || $input.hasClass('cff-conditional-field') || $input.hasClass('cff-conditional-operator') || $input.hasClass('cff-conditional-value')) {
          return;
        }

        if (visible) {
          $input.prop('disabled', false);
          if ($input.data('cffRequiredBackup')) {
            $input.prop('required', true);
            $input.attr('aria-required', 'true');
          }
        } else {
          if ($input.prop('required')) {
            $input.data('cffRequiredBackup', true);
          }
          $input.prop('required', false);
          $input.removeAttr('aria-required');
          $input.prop('disabled', true);
        }
      });
    }

    return { init:init, applyAll:applyAll };
  })();

  function sanitizeCptKey(str){
    return String(str || '')
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function sanitizeCptSlug(str){
    return String(str || '')
      .toLowerCase()
      .replace(/[^a-z0-9\-]/g, '-')
      .replace(/\-+/g, '-')
      .replace(/^\-+|\-+$/g, '');
  }

  function initCptAutoSlug(){
    var $form = $('.cff-cpt-form');
    if (!$form.length) return;
    var $singular = $form.find('input[name="cpt_singular"]');
    var $plural = $form.find('input[name="cpt_plural"]');
    var $key = $form.find('input[name="cpt_key"]');
    var $slug = $form.find('input[name="cpt_slug"]');
    if (!$singular.length || !$plural.length || !$key.length || !$slug.length) return;

    var keyAuto = !$key.prop('readonly') && !$key.val();
    var slugAuto = !$slug.val();

    function syncFromLabels(){
      var singular = $singular.val() || '';
      var plural = $plural.val() || '';
      var nextKey = sanitizeCptKey(singular);
      var nextSlug = sanitizeCptSlug(plural);
      if (keyAuto && !$key.prop('readonly')) {
        $key.val(nextKey);
      }
      if (slugAuto) {
        $slug.val(nextSlug);
      }
    }

    $singular.on('input.cffCptSlugs', function(){
      syncFromLabels();
    });
    $plural.on('input.cffCptSlugs', function(){
      syncFromLabels();
    });

    $key.on('input.cffCptSlugs', function(e){
      if (!e.originalEvent) return;
      keyAuto = this.value === '';
    });
    $slug.on('input.cffCptSlugs', function(e){
      if (!e.originalEvent) return;
      slugAuto = this.value === '';
    });

    syncFromLabels();
  }

  /* -------------------------
   * Boot
   * ------------------------- */
  $(function(){
    CFF.tabs.init();
    CFF.fieldBuilder.init();
     CFF.locationBuilder.init();
     CFF.presentationUI.init();
     CFF.presentation.init();
     CFF.toolsUI.init();
     CFF.dashicons.init();
     CFF.multiselect.init();
     CFF.reorder.init();
    CFF.langTabs.init();
    CFF.autoSlug.init();
    CFF.conditionalLogic.init();
    initCptAutoSlug();
  });
})(jQuery);
