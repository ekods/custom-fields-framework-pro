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
                    '<option value="url">URL (Simple)</option>' +
                    '<option value="link">Link (URL + Label Button)</option>' +
                    '<option value="embed">Embed</option>' +
                    '<option value="choice">Choice</option>' +
                    '<option value="relational">Relational</option>' +
                    '<option value="date_picker">Date Picker</option>' +
                    '<option value="datetime_picker">Date Time Picker</option>' +
                    '<option value="checkbox">Checkbox</option>' +
                    '<option value="image">Image</option>' +
                    '<option value="gallery">Gallery</option>' +
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
                '<option value="simple">Simple (remove-only header)</option>' +
                '<option value="grid">Grid (multi-column)</option>' +
                '<option value="row">Row (single horizontal row)</option>' +
                '<option value="gallery">Gallery Images</option>' +
              '</select>' +
              '<p class="description">Choose how each repeater row is presented while editing.</p>' +
              '<div class="cff-row-repeater-advanced">' +
                '<div class="cff-row-repeater-col">' +
                  '<label>Min Rows</label>' +
                  '<input type="number" class="cff-input cff-repeater-min" min="0" step="1" value="0">' +
                '</div>' +
                '<div class="cff-row-repeater-col">' +
                  '<label>Max Rows (0 = unlimited)</label>' +
                  '<input type="number" class="cff-input cff-repeater-max" min="0" step="1" value="0">' +
                '</div>' +
              '</div>' +
              '<div class="cff-row-repeater-col">' +
                '<label>Row Label Field (sub field name)</label>' +
                '<input type="text" class="cff-input cff-repeater-row-label" placeholder="title">' +
              '</div>' +
              '<span class="cff-tools-toggles cff-row-repeater-collapse">' +
                '<div><strong>Collapsed by default</strong></div>' +
                '<label class="cff-switch">' +
                  '<input type="checkbox" class="cff-repeater-collapsed-toggle">' +
                  '<span class="cff-slider"></span>' +
                '</label>' +
              '</span>' +
            '</div>' +
            '<div class="cff-row-datetime-options">' +
              '<span class="cff-tools-toggles">' +
                '<div><strong>Use Time</strong><div class="description">Enable time selector for datetime picker.</div></div>' +
                '<label class="cff-switch">' +
                  '<input type="checkbox" class="cff-datetime-use-time-toggle" checked>' +
                  '<span class="cff-slider"></span>' +
                '</label>' +
              '</span>' +
            '</div>' +
            '<div class="cff-row-media-options">' +
              '<label>Max Upload Size (MB)</label>' +
              '<input type="number" class="cff-input cff-max-upload-mb" min="1" step="1" value="2">' +
              '<p class="description">Default 2 MB. Set a larger value for this image or file field only.</p>' +
            '</div>' +
            '<div class="cff-row-rules">' +
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
              '<div class="cff-row-conditional">' +
                '<span class="cff-tools-toggles">' +
                  '<div><strong>Conditional Logic</strong></div>' +
                  '<label class="cff-switch">' +
                    '<input type="checkbox" class="cff-conditional-enabled">' +
                    '<span class="cff-slider"></span>' +
                  '</label>' +
                '</span>' +
                '<div class="cff-conditional-config" style="margin-top:8px;display:none;">' +
                  '<p><label>Field Name</label>' +
                    '<select class="cff-input cff-conditional-field cff-select2">' +
                      '<option value="">Select field…</option>' +
                    '</select>' +
                  '</p>' +
                  '<p><label>Operator</label>' +
                    '<select class="cff-input cff-conditional-operator cff-select2">' +
                      '<option value="==">is equal to</option>' +
                      '<option value="!=">is not equal to</option>' +
                      '<option value="contains">contains</option>' +
                      '<option value="not_contains">does not contain</option>' +
                      '<option value="empty">is empty</option>' +
                      '<option value="not_empty">is not empty</option>' +
                    '</select>' +
                  '</p>' +
                  '<p class="cff-conditional-value-row"><label>Value</label>' +
                  '<input type="text" class="cff-input cff-conditional-value" placeholder="value"></p>' +
                '</div>' +
              '</div>' +
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
            '<div class="cff-row-choice-default">' +
              '<label>Default Choice</label>' +
              '<select class="cff-input cff-choice-default cff-select2">' +
                '<option value="">None</option>' +
              '</select>' +
            '</div>' +
            '<div class="cff-choices-list"></div>' +
          '</div>' +
          '<div class="cff-field-relational is-hidden">' +
            '<div class="cff-subhead">' +
              '<strong>Relational Settings</strong>' +
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
                    '<option value="url">URL (Simple)</option>' +
                    '<option value="link">Link (URL + Label Button)</option>' +
                    '<option value="embed">Embed</option>' +
                    '<option value="choice">Choice</option>' +
                    '<option value="relational">Relational</option>' +
                    '<option value="date_picker">Date Picker</option>' +
                    '<option value="datetime_picker">Date Time Picker</option>' +
                    '<option value="checkbox">Checkbox</option>' +
                    '<option value="image">Image</option>' +
                    '<option value="gallery">Gallery</option>' +
                    '<option value="file">File</option>' +
                    '<option value="repeater">Repeater</option>' +
                    '<option value="group">Group</option>' +
                  '</select>' +
                '</div>' +
              '</div>' +
              '<div class="cff-col cff-actions">' +
                '<button type="button" class="button cff-icon-button cff-duplicate-sub" aria-label="Duplicate sub field">' +
                  '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>' +
                '</button>' +
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
              '<div class="cff-row-repeater-options">' +
                '<label>Repeater Layout</label>' +
                '<select class="cff-input cff-repeater-layout cff-select2">' +
                  '<option value="default">Default (stacked rows)</option>' +
                  '<option value="simple">Simple (remove-only header)</option>' +
                  '<option value="grid">Grid (multi-column)</option>' +
                  '<option value="row">Row (single horizontal row)</option>' +
                  '<option value="gallery">Gallery Images</option>' +
                '</select>' +
                '<p class="description">Choose how each repeater row is presented while editing.</p>' +
                '<div class="cff-row-repeater-advanced">' +
                  '<div class="cff-row-repeater-col">' +
                    '<label>Min Rows</label>' +
                    '<input type="number" class="cff-input cff-repeater-min" min="0" step="1" value="0">' +
                  '</div>' +
                  '<div class="cff-row-repeater-col">' +
                    '<label>Max Rows (0 = unlimited)</label>' +
                    '<input type="number" class="cff-input cff-repeater-max" min="0" step="1" value="0">' +
                  '</div>' +
                '</div>' +
                '<div class="cff-row-repeater-col">' +
                  '<label>Row Label Field (sub field name)</label>' +
                  '<input type="text" class="cff-input cff-repeater-row-label" placeholder="title">' +
                '</div>' +
                '<span class="cff-tools-toggles cff-row-repeater-collapse">' +
                  '<div><strong>Collapsed by default</strong></div>' +
                  '<label class="cff-switch">' +
                    '<input type="checkbox" class="cff-repeater-collapsed-toggle">' +
                    '<span class="cff-slider"></span>' +
                  '</label>' +
                '</span>' +
              '</div>' +
            '<div class="cff-row-datetime-options">' +
              '<span class="cff-tools-toggles">' +
                '<div><strong>Use Time</strong><div class="description">Enable time selector for datetime picker.</div></div>' +
                '<label class="cff-switch">' +
                  '<input type="checkbox" class="cff-datetime-use-time-toggle" checked>' +
                  '<span class="cff-slider"></span>' +
                '</label>' +
              '</span>' +
            '</div>' +
            '<div class="cff-row-media-options">' +
              '<label>Max Upload Size (MB)</label>' +
              '<input type="number" class="cff-input cff-max-upload-mb" min="1" step="1" value="2">' +
              '<p class="description">Default 2 MB. Set a larger value for this image or file field only.</p>' +
            '</div>' +
            '<div class="cff-row-rules">' +
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
                '<div class="cff-row-conditional">' +
                  '<span class="cff-tools-toggles">' +
                    '<div><strong>Conditional Logic</strong></div>' +
                    '<label class="cff-switch">' +
                      '<input type="checkbox" class="cff-conditional-enabled">' +
                      '<span class="cff-slider"></span>' +
                    '</label>' +
                  '</span>' +
                  '<div class="cff-conditional-config" style="margin-top:8px;display:none;">' +
                    '<p><label>Field Name</label>' +
                      '<select class="cff-input cff-conditional-field cff-select2">' +
                        '<option value="">Select field…</option>' +
                      '</select>' +
                    '</p>' +
                    '<p><label>Operator</label>' +
                      '<select class="cff-input cff-conditional-operator cff-select2">' +
                        '<option value="==">is equal to</option>' +
                        '<option value="!=">is not equal to</option>' +
                        '<option value="contains">contains</option>' +
                        '<option value="not_contains">does not contain</option>' +
                        '<option value="empty">is empty</option>' +
                        '<option value="not_empty">is not empty</option>' +
                      '</select>' +
                    '</p>' +
                    '<p class="cff-conditional-value-row"><label>Value</label>' +
                    '<input type="text" class="cff-input cff-conditional-value" placeholder="value"></p>' +
                  '</div>' +
                '</div>' +
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
              '<div class="cff-row-choice-default">' +
                '<label>Default Choice</label>' +
                '<select class="cff-input cff-choice-default cff-select2">' +
                  '<option value="">None</option>' +
                '</select>' +
              '</div>' +
              '<div class="cff-choices-list"></div>' +
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
          '<div class="cff-subbuilder cff-subrepeater" data-kind="repeater">' +
            '<div class="cff-subhead">' +
              '<strong>Sub Fields (Repeater)</strong>' +
              '<button type="button" class="button cff-add-sub">Add Sub Field</button>' +
            '</div>' +
            '<div class="cff-subfields"></div>' +
          '</div>' +
          '<div class="cff-groupbuilder cff-subgroupbuilder" data-kind="group">' +
            '<div class="cff-subhead">' +
              '<strong>Group Fields</strong>' +
              '<button type="button" class="button cff-add-group-sub">Add Field</button>' +
            '</div>' +
            '<div class="cff-group-fields"></div>' +
          '</div>' +
        '</div>'
      );
    }

    function fallbackLayoutTpl(){
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
      var normalized = Array.isArray(data) ? data : [];
      $input.val(JSON.stringify(normalized));
      $input.trigger('change');
      refreshSecondaryViews(normalized);
    }

    function parseAliasesAttr($el, attrName){
      var raw = $el.attr(attrName) || '';
      if (!raw) return [];
      try {
        var parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        var out = [];
        parsed.forEach(function(item){
          var key = CFF.utils.sanitizeName(item || '');
          if (key && out.indexOf(key) === -1) out.push(key);
        });
        return out;
      } catch (e) {
        return [];
      }
    }

    function setAliasesAttr($el, attrName, aliases){
      var out = [];
      (aliases || []).forEach(function(item){
        var key = CFF.utils.sanitizeName(item || '');
        if (key && out.indexOf(key) === -1) out.push(key);
      });
      $el.attr(attrName, JSON.stringify(out));
    }

    function toggleBuilders($field){
      var t = $field.find('.cff-type').val();
      $field.toggleClass('is-repeater', t === 'repeater');
      $field.toggleClass('is-group', t === 'group');
      $field.toggleClass('is-flexible', t === 'flexible');
      $field.find('> .cff-advanced > .cff-subbuilder').toggle(t === 'repeater');
      $field.find('> .cff-advanced > .cff-groupbuilder').toggle(t === 'group');
      $field.find('> .cff-advanced > .cff-flexbuilder').toggle(t === 'flexible');
      toggleRepeaterOptions($field, t);
      toggleDatetimeOptions($field, t);
      toggleMediaOptions($field, t);
    }

    function togglePlaceholderRow($element, type){
      if (!$element || !$element.length) return;
      var $meta = getFieldMetaRow($element);
      var $row = $meta.children('.cff-row-placeholder').first();
      if (!$row.length) return;
      var allowed = placeholderTypes[String(type || '').trim()];
      $row.toggle(!!allowed);
    }

    function getFieldMetaRow($element){
      if (!$element || !$element.length) return $();

      var $structure = $element.children('.cff-field-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-meta-row').first();
      }

      $structure = $element.children('.cff-subfield-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-meta-row').first();
      }

      return $();
    }

    function getFieldHeadRow($element){
      if (!$element || !$element.length) return $();

      var $structure = $element.children('.cff-field-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-head').first();
      }

      $structure = $element.children('.cff-subfield-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-head').first();
      }

      return $();
    }

    function toggleRepeaterOptions($element, type){
      if (!$element || !$element.length) return;
      var $meta = getFieldMetaRow($element);
      var $row = $meta.children('.cff-row-repeater-options').first();
      if (!$row.length) return;
      var selected = String(type || '').trim();
      if (!selected) {
        selected = ($element.find('.cff-type').val() || $element.find('.cff-stype').val() || '').trim();
      }
      $row.toggle(selected === 'repeater');
    }

    function toggleDatetimeOptions($element, type){
      if (!$element || !$element.length) return;
      var $meta = getFieldMetaRow($element);
      var $row = $meta.children('.cff-row-datetime-options').first();
      if (!$row.length) return;
      var selected = String(type || '').trim();
      if (!selected) {
        selected = ($element.find('.cff-type').val() || $element.find('.cff-stype').val() || '').trim();
      }
      $row.toggle(selected === 'datetime_picker');
    }

    function toggleMediaOptions($element, type){
      if (!$element || !$element.length) return;
      var $meta = getFieldMetaRow($element);
      var $row = $meta.children('.cff-row-media-options').first();
      if (!$row.length) return;
      var selected = String(type || '').trim();
      if (!selected) {
        selected = ($element.find('.cff-type').val() || $element.find('.cff-stype').val() || '').trim();
      }
      $row.toggle(selected === 'image' || selected === 'file');
    }

    function getFieldRepeaterOptions($element){
      var $meta = getFieldMetaRow($element);
      return {
        layout: $meta.children('.cff-row-repeater-options').find('.cff-repeater-layout').first(),
        min: $meta.children('.cff-row-repeater-options').find('.cff-repeater-min').first(),
        max: $meta.children('.cff-row-repeater-options').find('.cff-repeater-max').first(),
        rowLabel: $meta.children('.cff-row-repeater-options').find('.cff-repeater-row-label').first(),
        collapsed: $meta.children('.cff-row-repeater-options').find('.cff-repeater-collapsed-toggle').first()
      };
    }

    function getSubfieldRepeaterOptions($element){
      var options = getFieldRepeaterOptions($element);
      return {
        layout: options.layout,
        min: options.min,
        max: options.max,
        rowLabel: options.rowLabel,
        collapsed: options.collapsed
      };
    }

    function getMediaOptions($element){
      var $meta = getFieldMetaRow($element);
      return {
        maxUploadMb: $meta.children('.cff-row-media-options').find('.cff-max-upload-mb').first()
      };
    }

    function normalizeMediaMaxUploadMb(value){
      var parsed = parseInt(value, 10) || 0;
      return parsed > 0 ? parsed : 2;
    }

    function toggleSubGroup($sub){
      var $head = getFieldHeadRow($sub);
      var t = $head.find('.cff-stype').first().val();
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
      var $head = getFieldHeadRow($sub);
      var t = $head.find('.cff-stype').first().val();
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
      if (!$container || !$container.length) return;

      if ($container.hasClass('ui-sortable')) {
        $container.sortable('destroy');
      }

      var options = {
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); }
      };

      if ($container.hasClass('cff-group-fields')) {
        options.connectWith = '#cff-field-list, .cff-group-fields';
        options.receive = function(event, ui){
          if (ui.item.hasClass('cff-field-row')) {
            var item = readSingleField(ui.item);
            if (!item) return;
            if (!canNestInGroup(item.type)) {
              $(ui.sender).sortable('cancel');
              return;
            }
            var $replacement = renderSub(item, Date.now());
            ui.item.replaceWith($replacement);
            save(readFromDOM());
            refreshReorderList();
            refreshConditionalFieldDropdowns();
            $(document).trigger('cff:refresh', $replacement);
          } else {
            save(readFromDOM());
            refreshConditionalFieldDropdowns();
          }
        };
      }

      $container.sortable(options);
    }

    function canNestInGroup(type){
      var allowed = {
        text: true,
        number: true,
        textarea: true,
        wysiwyg: true,
        color: true,
        url: true,
        link: true,
        embed: true,
        choice: true,
        relational: true,
        date_picker: true,
        datetime_picker: true,
        checkbox: true,
        image: true,
        gallery: true,
        file: true,
        repeater: true,
        group: true
      };

      return !!allowed[String(type || '').trim()];
    }

    function sortableLayouts($container){
      if (!$container || !$container.length) return;
      if ($container.hasClass('ui-sortable')) {
        $container.sortable('destroy');
      }
      $container.sortable({
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); }
      });
    }

    function getOwnGroupFieldsContainer($element){
      if (!$element || !$element.length) return $();

      if ($element.hasClass('cff-field-row')) {
        return $element.find('> .cff-advanced > .cff-groupbuilder > .cff-group-fields').first();
      }

      if ($element.hasClass('cff-subfield')) {
        return $element.find('> .cff-groupbuilder > .cff-group-fields').first();
      }

      return $();
    }

    function ensureGroupDropTargetVisible($group){
      if (!$group || !$group.length) return;

      if ($group.hasClass('cff-field-row')) {
        $group.removeClass('is-collapsed');
        $group.find('> .cff-handle-wrap > .cff-acc-toggle').attr('aria-expanded', 'true');
      } else if ($group.hasClass('cff-subfield')) {
        $group.removeClass('is-collapsed');
        $group.find('> .cff-handle-wrap > .cff-sub-acc-toggle').attr('aria-expanded', 'true');
      }
    }

    function moveItemIntoGroup($dragged, $group){
      if (!$dragged || !$dragged.length || !$group || !$group.length) return false;
      if ($group.is($dragged) || $group.has($dragged).length) return false;

      var item = $dragged.hasClass('cff-field-row') ? readSingleField($dragged) : readSingleSubfield($dragged);
      if (!item || !canNestInGroup(item.type)) return false;

      var $target = getOwnGroupFieldsContainer($group);
      if (!$target.length) return false;

      ensureGroupDropTargetVisible($group);

      var $replacement = renderSub(item, Date.now());
      $dragged.remove();
      $target.append($replacement);
      sortableSubs($target);
      save(readFromDOM());
      refreshReorderList();
      refreshConditionalFieldDropdowns();
      $(document).trigger('cff:refresh', $replacement);
      return true;
    }

    function initGroupDropzones($scope){
      if (!$.fn.droppable) return;

      var $targets = $scope && $scope.length
        ? $scope.filter('.cff-field-row.is-group, .cff-subfield.is-group').add($scope.find('.cff-field-row.is-group, .cff-subfield.is-group'))
        : $root.find('.cff-field-row.is-group, .cff-subfield.is-group');

      $targets.each(function(){
        var $group = $(this);
        if ($group.hasClass('ui-droppable')) {
          $group.droppable('destroy');
        }

        $group.droppable({
          accept: '.cff-field-row, .cff-subfield',
          tolerance: 'pointer',
          greedy: true,
          classes: {
            'ui-droppable-active': 'cff-group-dropzone-active',
            'ui-droppable-hover': 'cff-group-dropzone-hover'
          },
          over: function(){
            ensureGroupDropTargetVisible($group);
          },
          drop: function(event, ui){
            var $dragged = ui.draggable.closest('.cff-field-row, .cff-subfield');
            if (!$dragged.length) {
              $dragged = ui.draggable;
            }
            moveItemIntoGroup($dragged, $group);
          }
        });
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
      var subKey = CFF.utils.sanitizeName((s && s.key) || '');
      if (!subKey) subKey = 'fld_' + Math.random().toString(36).slice(2, 14);
      $el.attr('data-sub-key', subKey);
      $el.attr('data-original-name', CFF.utils.sanitizeName((s && s.name) || ''));
      setAliasesAttr($el, 'data-field-aliases', Array.isArray(s && s.aliases) ? s.aliases : []);

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
      getMediaOptions($el).maxUploadMb.val(normalizeMediaMaxUploadMb(s.max_upload_mb));
      $el.find('.cff-required-toggle').prop('checked', !!s.required);
      $el.find('.cff-datetime-use-time-toggle').prop('checked', (s.datetime_use_time !== false));
      toggleDatetimeOptions($el, s.type || 'text');
      toggleMediaOptions($el, s.type || 'text');
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
      var subRepeaterOptions = getSubfieldRepeaterOptions($el);
      subRepeaterOptions.layout.val(subLayoutValue);
      subRepeaterOptions.min.val(parseInt(s.min || 0, 10) || 0);
      subRepeaterOptions.max.val(parseInt(s.max || 0, 10) || 0);
      subRepeaterOptions.rowLabel.val(s.repeater_row_label || '');
      subRepeaterOptions.collapsed.prop('checked', !!s.repeater_collapsed);
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
      var $panel = getOwnChoicePanel($element);
      if (!$panel.length) return;
      populateChoiceList($panel, data.choices || []);
      $panel.find('.cff-choice-display').val(data.choice_display || 'select');
      updateChoiceDefaultOptions($panel, data.choice_default || '');
    }

    function updateChoiceDefaultOptions($panel, forcedSelected){
      if (!$panel || !$panel.length) return;
      var $select = $panel.find('.cff-choice-default').first();
      var $list = $panel.find('.cff-choices-list').first();
      if (!$select.length) return;
      var display = String($panel.find('.cff-choice-display').val() || 'select');

      var current = (forcedSelected !== undefined)
        ? String(forcedSelected || '')
        : String($select.val() || '');

      var seen = {};
      $select.empty().append('<option value="">None</option>');
      if (display === 'true_false') {
        seen['1'] = true;
        $select.append('<option value="1">True</option>');
      }
      $list.children('.cff-choice-row').each(function(){
        var $row = $(this);
        var label = ($row.find('.cff-choice-label').val() || '').trim();
        var value = ($row.find('.cff-choice-value').val() || '').trim();
        var resolved = value || label;
        if (!resolved || seen[resolved]) return;
        seen[resolved] = true;
        $select.append($('<option></option>').attr('value', resolved).text(label || resolved));
      });

      if (current && !seen[current]) {
        $select.append($('<option></option>').attr('value', current).text(current + ' (missing)'));
      }

      $select.val(current);
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

    function getOwnChoicePanel($element){
      if (!$element || !$element.length) return $();

      var $structure = $element.children('.cff-field-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-choice').first();
      }

      $structure = $element.children('.cff-subfield-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-choice').first();
      }

      return $();
    }

    function toggleChoicePanel($element, type){
      var $panel = getOwnChoicePanel($element);
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

    function getConditionalItemMeta($item){
      var isField = $item.hasClass('cff-field-row');
      var keyAttr = isField ? 'data-field-key' : 'data-sub-key';
      var $scope = isField ? $item : getFieldHeadRow($item);
      var labelClass = isField ? '.cff-label' : '.cff-slabel';
      var nameClass = isField ? '.cff-name' : '.cff-sname';
      var typeClass = isField ? '.cff-type' : '.cff-stype';
      return {
        key: CFF.utils.sanitizeName($item.attr(keyAttr) || ''),
        label: String($scope.find(labelClass).first().val() || '').trim(),
        name: CFF.utils.sanitizeName($scope.find(nameClass).first().val() || ''),
        type: String($scope.find(typeClass).first().val() || 'text').trim()
      };
    }

    function collectConditionalFieldReferences($items, pathParts, refs, currentKey){
      $items.each(function(){
        var $item = $(this);
        var meta = getConditionalItemMeta($item);
        if (!meta.key || !meta.name || meta.key === currentKey) return;

        var title = meta.label || meta.name;
        var nextPath = (pathParts || []).concat([title]);
        refs.push({
          key: meta.key,
          name: meta.name,
          type: meta.type,
          label: meta.label,
          path: nextPath.join(' -> ')
        });

        if (meta.type === 'group') {
          collectConditionalFieldReferences(
            $item.find('> .cff-advanced > .cff-groupbuilder > .cff-group-fields > .cff-subfield, > .cff-groupbuilder > .cff-group-fields > .cff-subfield'),
            nextPath,
            refs,
            currentKey
          );
        } else if (meta.type === 'repeater') {
          collectConditionalFieldReferences(
            $item.find('> .cff-advanced > .cff-subbuilder > .cff-subfields > .cff-subfield, > .cff-subbuilder > .cff-subfields > .cff-subfield'),
            nextPath,
            refs,
            currentKey
          );
        } else if (meta.type === 'flexible') {
          $item.find('> .cff-advanced > .cff-flexbuilder > .cff-layouts > .cff-layout').each(function(){
            var $layout = $(this);
            var layoutLabel = String($layout.find('.cff-llabel').first().val() || $layout.find('.cff-lname').first().val() || 'Layout').trim();
            collectConditionalFieldReferences(
              $layout.find('> .cff-layout-body > .cff-layout-fields > .cff-subfield'),
              nextPath.concat([layoutLabel]),
              refs,
              currentKey
            );
          });
        }
      });
    }

    function getConditionalFieldReferences($element){
      var refs = [];
      var seen = {};
      var currentKey = CFF.utils.sanitizeName($element.attr('data-field-key') || $element.attr('data-sub-key') || '');

      collectConditionalFieldReferences($root.find('#cff-field-list > .cff-field-row'), [], refs, currentKey);

      return refs.filter(function(ref){
        if (!ref.key || seen[ref.key]) return false;
        seen[ref.key] = true;
        return true;
      });
    }

    function updateConditionalFieldOptions($element, forcedSelected, forcedFieldName){
      var $select = $element.find('.cff-conditional-field').first();
      if (!$select.length) return;

      var current = (forcedSelected !== undefined)
        ? String(forcedSelected || '')
        : String($select.val() || '');
      var currentFieldName = (forcedFieldName !== undefined)
        ? CFF.utils.sanitizeName(forcedFieldName || '')
        : CFF.utils.sanitizeName($select.find('option:selected').attr('data-field-name') || current);

      var refs = getConditionalFieldReferences($element);
      var seenKeys = {};
      var seenNames = {};
      refs.forEach(function(ref){
        seenKeys[ref.key] = true;
        if (ref.name) seenNames[ref.name] = true;
      });

      $select.empty().append('<option value="">Select field…</option>');
      refs.forEach(function(ref){
        var text = ref.path;
        if (ref.name && ref.path !== ref.name) {
          text += ' [' + ref.name + ']';
        }
        $select.append(
          $('<option></option>')
            .attr('value', ref.key)
            .attr('data-field-key', ref.key)
            .attr('data-field-name', ref.name)
            .text(text)
        );
      });

      if (current && seenKeys[current]) {
        $select.val(current);
        return;
      }

      if (currentFieldName) {
        var matchedByName = refs.find(function(ref){ return ref.name === currentFieldName; });
        if (matchedByName) {
          $select.val(matchedByName.key);
          return;
        }
      }

      if ((current && !seenKeys[current]) || (currentFieldName && !seenNames[currentFieldName])) {
        var missingValue = current || currentFieldName;
        var missingLabel = currentFieldName || current || 'Unknown field';
        $select.append(
          $('<option></option>')
            .attr('value', missingValue)
            .attr('data-field-name', currentFieldName || '')
            .text(missingLabel + ' (missing)')
        );
        $select.val(missingValue);
        return;
      }

      $select.val('');
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
      updateConditionalFieldOptions($element, (logic && (logic.key || logic.field)) || '', (logic && logic.field) || '');
      $config.find('.cff-conditional-operator').val((logic && logic.operator) || '==');
      $config.find('.cff-conditional-value').val((logic && logic.value) || '');
      toggleConditionalValueInput($element);
    }

    function readConditionalLogic($element){
      var $toggle = $element.find('.cff-conditional-enabled').first();
      var $config = $element.find('.cff-conditional-config').first();
      if (!$toggle.length || !$config.length || !$toggle.is(':checked')) return null;

      var $fieldSelect = $config.find('.cff-conditional-field').first();
      var $selected = $fieldSelect.find('option:selected');
      var field = CFF.utils.sanitizeName($selected.attr('data-field-name') || $fieldSelect.val() || '');
      if (!field) return null;

      var logic = {
        enabled: true,
        field: field,
        operator: $config.find('.cff-conditional-operator').val() || '==',
        value: $config.find('.cff-conditional-value').val() || ''
      };

      var key = CFF.utils.sanitizeName($selected.attr('data-field-key') || '');
      if (key) {
        logic.key = key;
      }

      return logic;
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
      var $panel = getOwnRelationalPanel($element);
      if (!$panel.length) return;

      $panel.find('.cff-relational-type').first().val(data.relational_type || 'post');
      $panel.find('.cff-relational-subtype').first().val(data.relational_subtype || '');
      $panel.find('.cff-relational-display').first().val(data.relational_display || 'select');
      $panel.find('.cff-relational-multiple-toggle').first().prop('checked', !!data.relational_multiple);

      // ✅ simpan di $element (ctx), biar updateRelationalSubtypeOptions bisa baca
      $element.data('cff-relational-subtype', data.relational_subtype || '');

      updateRelationalSubtypeOptions($element);
      renderArchiveLinks($element);
    }

    function renderArchiveLinks($element){
      var $panel = getOwnRelationalPanel($element);
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
      var $panel = getOwnRelationalPanel($element);
      if (!$panel.length) return;
      $panel.toggleClass('is-hidden', type !== 'relational');
    }

    function getOwnRelationalPanel($element){
      if (!$element || !$element.length) return $();

      var $structure = $element.children('.cff-field-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-relational').first();
      }

      $structure = $element.children('.cff-subfield-structure').first();
      if ($structure.length) {
        return $structure.children('.cff-field-relational').first();
      }

      return $();
    }

    function getRelationalContext($source){
      var $ctx = $source.closest('.cff-subfield');
      if ($ctx.length) return $ctx;
      return $source.closest('.cff-field-row');
    }

    function updateRelationalSubtypeOptions($ctx) {
      var $panel = getOwnRelationalPanel($ctx);
      if (!$panel.length) return;

      var type = $panel.find('.cff-relational-type').first().val();
      var $wrap = $panel.find('.cff-row-relational-subtype').first();
      var $select = $panel.find('.cff-relational-subtype').first();

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

    function readSingleField($f){
      if (!$f || !$f.length) return null;
        var label = $f.find('.cff-label').val() || '';
        var name  = CFF.utils.sanitizeName($f.find('.cff-name').val() || '');
        var type  = $f.find('.cff-type').val() || 'text';

        var item = { label: label, name: name, type: type };
        item.key = CFF.utils.sanitizeName($f.attr('data-field-key') || '');
        if (!item.key) {
          item.key = 'fld_' + Math.random().toString(36).slice(2, 14);
          $f.attr('data-field-key', item.key);
        }
        var originalName = CFF.utils.sanitizeName($f.attr('data-original-name') || '');
        var aliases = parseAliasesAttr($f, 'data-field-aliases');
        if (originalName && originalName !== name && aliases.indexOf(originalName) === -1) {
          aliases.push(originalName);
        }
        aliases = aliases.filter(function(alias){ return alias && alias !== name; });
        if (aliases.length) item.aliases = aliases;
        $f.attr('data-original-name', name);
        setAliasesAttr($f, 'data-field-aliases', aliases);
        item.required = $f.find('.cff-required-toggle').is(':checked');
        item.placeholder = $f.find('.cff-placeholder').val() || '';
        if (type === 'image' || type === 'file') {
          item.max_upload_mb = normalizeMediaMaxUploadMb(getMediaOptions($f).maxUploadMb.val());
        }
        if (type === 'datetime_picker') {
          item.datetime_use_time = $f.find('.cff-datetime-use-time-toggle').is(':checked');
        }
        var conditionalLogic = readConditionalLogic($f);
        if (conditionalLogic) {
          item.conditional_logic = conditionalLogic;
        }
        if (type === 'choice') {
          var $fieldChoicePanel = getOwnChoicePanel($f);
          item.choices = readChoices($fieldChoicePanel.find('.cff-choices-list').first());
          item.choice_display = $fieldChoicePanel.find('.cff-choice-display').first().val() || 'select';
          item.choice_default = $fieldChoicePanel.find('.cff-choice-default').first().val() || '';
        }

        if (type === 'relational') {
          var $fieldRelPanel = getOwnRelationalPanel($f);
          item.relational_type = $fieldRelPanel.find('.cff-relational-type').first().val() || 'post';
          item.relational_subtype = $fieldRelPanel.find('.cff-relational-subtype').first().val() || ($f.data('cff-relational-subtype') || '');
          item.relational_display = $fieldRelPanel.find('.cff-relational-display').first().val() || 'select';
          item.relational_multiple = $fieldRelPanel.find('.cff-relational-multiple-toggle').first().is(':checked');
        }

        if (type === 'repeater') {
          var fieldRepeaterOptions = getFieldRepeaterOptions($f);
          item.sub_fields = readSubfields(
            $f.find('> .cff-advanced > .cff-subbuilder > .cff-subfields').first()
          );
          item.repeater_layout = fieldRepeaterOptions.layout.val() || 'default';
          item.min = Math.max(0, parseInt(fieldRepeaterOptions.min.val() || 0, 10) || 0);
          item.max = Math.max(0, parseInt(fieldRepeaterOptions.max.val() || 0, 10) || 0);
          if (item.max > 0 && item.max < item.min) item.max = item.min;
          item.repeater_row_label = CFF.utils.sanitizeName(fieldRepeaterOptions.rowLabel.val() || '');
          item.repeater_collapsed = fieldRepeaterOptions.collapsed.is(':checked');
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

      return item;
    }

    function readFromDOM(){
      var data = [];
      $('#cff-field-list .cff-field-row').each(function(){
        var item = readSingleField($(this));
        if (item) data.push(item);
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
      fieldViewMode = (mode === 'reorder' || mode === 'mapping') ? mode : 'builder';
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

    function typeLabel(type){
      var key = String(type || 'text').trim();
      var map = {
        url: 'URL (Simple)',
        link: 'Link (URL + Label Button)',
        gallery: 'Gallery'
      };
      if (map[key]) return map[key];
      return key.replace(/_/g, ' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });
    }

    function repeaterLayoutLabel(layout){
      var map = {
        default: 'Default',
        simple: 'Simple',
        grid: 'Grid',
        row: 'Row',
        gallery: 'Gallery Images'
      };
      var key = String(layout || 'default').trim();
      return map[key] || typeLabel(key);
    }

    function choiceDisplayLabel(display){
      var map = {
        select: 'Select',
        checkbox: 'Checkbox',
        radio: 'Radio',
        button_group: 'Button Group',
        true_false: 'True / False'
      };
      var key = String(display || 'select').trim();
      return map[key] || typeLabel(key);
    }

    function relationalTypeLabel(type){
      var map = {
        post: 'Post',
        page: 'Page',
        post_and_page: 'Post & Page',
        post_type: 'Custom Post Type',
        taxonomy: 'Taxonomy',
        user: 'User'
      };
      var key = String(type || 'post').trim();
      return map[key] || typeLabel(key);
    }

    function relationalDisplayLabel(display){
      var map = {
        select: 'Select',
        checkbox: 'Checkbox',
        radio: 'Radio'
      };
      var key = String(display || 'select').trim();
      return map[key] || typeLabel(key);
    }

    function conditionalOperatorLabel(operator){
      var map = {
        '==': 'is',
        '!=': 'is not',
        contains: 'contains',
        not_contains: 'does not contain',
        empty: 'is empty',
        not_empty: 'is not empty'
      };
      return map[String(operator || '==')] || String(operator || '==');
    }

    function collectFieldReferenceMap(fields, pathParts, refs){
      refs = refs || {};
      (fields || []).forEach(function(field){
        if (!field || typeof field !== 'object') return;
        var key = CFF.utils.sanitizeName(field.key || '');
        var name = CFF.utils.sanitizeName(field.name || '');
        var title = String(field.label || field.name || '').trim();
        var nextPath = (pathParts || []).concat([title || name]);
        var pathLabel = nextPath.join(' -> ');

        if (key) refs['key:' + key] = pathLabel;
        if (name && !refs['name:' + name]) refs['name:' + name] = pathLabel;

        if ((field.type === 'group' || field.type === 'repeater') && Array.isArray(field.sub_fields)) {
          collectFieldReferenceMap(field.sub_fields, nextPath, refs);
        }

        if (field.type === 'flexible' && Array.isArray(field.layouts)) {
          field.layouts.forEach(function(layout){
            if (!layout || !Array.isArray(layout.sub_fields)) return;
            var layoutTitle = String(layout.label || layout.name || 'Layout').trim();
            collectFieldReferenceMap(layout.sub_fields, nextPath.concat([layoutTitle]), refs);
          });
        }
      });
      return refs;
    }

    function mappingBadge(text, kind){
      var cls = 'cff-field-mapping-badge';
      if (kind) cls += ' is-' + kind;
      return '<span class="' + cls + '">' + CFF.utils.escapeHtml(text) + '</span>';
    }

    function buildMappingBadges(field){
      var badges = [];
      if (!field || typeof field !== 'object') return badges;

      if (field.required) {
        badges.push(mappingBadge('Required', 'required'));
      }

      if (field.conditional_logic && field.conditional_logic.enabled) {
        badges.push(mappingBadge('Conditional', 'conditional'));
      }

      if (field.type === 'choice' && field.choice_default) {
        badges.push(mappingBadge('Default: ' + field.choice_default, 'default'));
      }

      if (field.type === 'repeater') {
        var min = Math.max(0, parseInt(field.min || 0, 10) || 0);
        var max = Math.max(0, parseInt(field.max || 0, 10) || 0);
        badges.push(mappingBadge('Min ' + min, 'limit'));
        badges.push(mappingBadge(max > 0 ? ('Max ' + max) : 'Max unlimited', 'limit'));
      }

      return badges;
    }

    function resolveConditionalSourceLabel(field, refs){
      if (!field || !field.conditional_logic) return '';
      var logic = field.conditional_logic;
      var key = CFF.utils.sanitizeName(logic.key || '');
      var name = CFF.utils.sanitizeName(logic.field || '');
      if (key && refs['key:' + key]) return refs['key:' + key];
      if (name && refs['name:' + name]) return refs['name:' + name];
      return name || key || '';
    }

    function buildMappingSummary(field, refs){
      var parts = [];
      if (!field || typeof field !== 'object') return '';

      if (field.conditional_logic && field.conditional_logic.enabled) {
        var source = resolveConditionalSourceLabel(field, refs);
        var operator = field.conditional_logic.operator || '==';
        var value = field.conditional_logic.value || '';
        var rule = 'If ' + source + ' ' + conditionalOperatorLabel(operator);
        if (operator !== 'empty' && operator !== 'not_empty' && value !== '') {
          rule += ' ' + value;
        }
        parts.push(rule);
      }

      if (field.type === 'choice') {
        parts.push('Display: ' + choiceDisplayLabel(field.choice_display || 'select'));
        if (field.choice_default) {
          parts.push('Default choice: ' + field.choice_default);
        }
      }

      if (field.type === 'repeater') {
        var min = Math.max(0, parseInt(field.min || 0, 10) || 0);
        var max = Math.max(0, parseInt(field.max || 0, 10) || 0);
        parts.push('Rows: min ' + min + ', max ' + (max > 0 ? max : 'unlimited'));
      }

      if (field.type === 'datetime_picker') {
        parts.push('Time: ' + (field.datetime_use_time === false ? 'off' : 'on'));
      }

      if (field.type === 'relational') {
        parts.push('Source: ' + relationalTypeLabel(field.relational_type || 'post'));
        parts.push('Display: ' + relationalDisplayLabel(field.relational_display || 'select'));
        parts.push('Multiple: ' + (field.relational_multiple ? 'yes' : 'no'));
      }

      return parts.join(' • ');
    }

    function openFieldAncestors($target){
      if (!$target || !$target.length) return;

      $target.parents('.cff-field-row.is-collapsed').each(function(){
        var $row = $(this);
        $row.removeClass('is-collapsed');
        $row.find('> .cff-handle-wrap > .cff-acc-toggle').attr('aria-expanded', 'true');
        toggleBuilders($row);
      });

      $target.parents('.cff-subfield.is-collapsed').each(function(){
        var $sub = $(this);
        $sub.removeClass('is-collapsed');
        $sub.find('> .cff-handle-wrap > .cff-sub-acc-toggle').attr('aria-expanded', 'true');
        toggleSubGroup($sub);
        toggleSubRepeater($sub);
      });

      if ($target.is('.cff-field-row')) {
        toggleBuilders($target);
      }

      if ($target.is('.cff-subfield')) {
        toggleSubGroup($target);
        toggleSubRepeater($target);
      }
    }

    function flashMappingTarget($target){
      if (!$target || !$target.length) return;
      $root.find('.is-mapping-highlight').removeClass('is-mapping-highlight');
      $target.addClass('is-mapping-highlight');
      window.setTimeout(function(){
        $target.removeClass('is-mapping-highlight');
      }, 1800);
    }

    function jumpToMappedField(fieldKey){
      var key = CFF.utils.sanitizeName(fieldKey || '');
      if (!key) return;

      setFieldViewMode('builder');

      var $target = $root.find('.cff-field-row[data-field-key="' + key + '"]').first();
      if (!$target.length) {
        $target = $root.find('.cff-subfield[data-sub-key="' + key + '"]').first();
      }
      if (!$target.length) return;

      openFieldAncestors($target);
      $target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      flashMappingTarget($target);
    }

    function buildMappingRowsRecursive(fields, depth, refs){
      depth = depth || 0;
      var rows = '';
      (fields || []).forEach(function(f){
        if (!f || typeof f !== 'object') return;
        var label = (f.label || f.name || '').toString();
        var name = (f.name || '').toString();
        var key = CFF.utils.sanitizeName(f.key || '');
        if (!name) return;
        var type = (f.type || 'text').toString();
        var typeText = typeLabel(type);
        if (type === 'repeater') {
          typeText += ' · ' + repeaterLayoutLabel(f.repeater_layout || 'default');
        }
        var indent = new Array(depth + 1).join('&nbsp;&nbsp;&nbsp;');
        var badges = buildMappingBadges(f).join('');
        var summary = buildMappingSummary(f, refs || {});
        rows += '<tr' + (key ? ' data-field-key="' + CFF.utils.escapeHtml(key) + '"' : '') + '>' +
          '<td>' +
            '<div class="cff-field-mapping-label-row">' +
              '<div class="cff-field-mapping-label">' + indent + CFF.utils.escapeHtml(label) + '</div>' +
              (key ? '<button type="button" class="button-link cff-field-mapping-jump" data-field-key="' + CFF.utils.escapeHtml(key) + '">Jump</button>' : '') +
            '</div>' +
            (badges ? '<div class="cff-field-mapping-badges">' + badges + '</div>' : '') +
          '</td>' +
          '<td><code>' + CFF.utils.escapeHtml(name) + '</code></td>' +
          '<td>' +
            '<div class="cff-field-mapping-type">' + CFF.utils.escapeHtml(typeText) + '</div>' +
            (summary ? '<div class="cff-field-mapping-summary">' + CFF.utils.escapeHtml(summary) + '</div>' : '') +
          '</td>' +
        '</tr>';

        if ((type === 'group' || type === 'repeater') && Array.isArray(f.sub_fields) && f.sub_fields.length) {
          var section = (type === 'group') ? 'Group Fields' : 'Sub Fields (Repeater)';
          rows += '<tr class="cff-field-mapping-section"><td colspan="3">' +
            indent + CFF.utils.escapeHtml(section) +
          '</td></tr>';
          rows += buildMappingRowsRecursive(f.sub_fields, depth + 1, refs);
        }

        if (type === 'flexible' && Array.isArray(f.layouts) && f.layouts.length) {
          f.layouts.forEach(function(l){
            if (!l || typeof l !== 'object') return;
            var lLabel = (l.label || l.name || '').toString();
            var lName = (l.name || '').toString();
            if (!lName) return;
            var layoutFields = Array.isArray(l.sub_fields) ? l.sub_fields : [];
            if (!layoutFields.length) return;

            rows += '<tr class="cff-field-mapping-section"><td colspan="3">' +
              indent + CFF.utils.escapeHtml('Layout: ' + lLabel + ' (' + lName + ')') +
            '</td></tr>';
            rows += buildMappingRowsRecursive(layoutFields, depth + 1, refs);
          });
        }
      });
      return rows;
    }

    function buildMappingTable(meta, fields){
      var refs = (meta && meta.refs) ? meta.refs : collectFieldReferenceMap(fields, [], {});
      var rows = buildMappingRowsRecursive(fields, 0, refs);
      if (!rows) return '';
      var titleText = (meta && meta.title) ? meta.title : 'Fields';
      var contextText = (meta && meta.context) ? meta.context : '';
      return '' +
        '<div class="cff-field-mapping-block">' +
          '<h4 class="cff-field-mapping-title">' +
            CFF.utils.escapeHtml(titleText) +
            (contextText ? '<span class="cff-field-mapping-context">' + CFF.utils.escapeHtml(contextText) + '</span>' : '') +
          '</h4>' +
          '<table class="widefat striped cff-field-mapping-table">' +
            '<thead><tr><th>Label</th><th>Name</th><th>Type</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
          '</table>' +
        '</div>';
    }

    function buildMappingSections(fields, meta){
      var refs = collectFieldReferenceMap(fields, [], {});
      var rootMeta = $.extend({}, meta || {}, { refs: refs });
      var html = buildMappingTable(rootMeta, fields);
      (fields || []).forEach(function(f){
        if (!f || typeof f !== 'object') return;
        var label = (f.label || f.name || '').toString();
        var name = (f.name || '').toString();
        if (!name) return;
        var type = (f.type || 'text').toString();

        if (type === 'group' && Array.isArray(f.sub_fields) && f.sub_fields.length) {
          html += buildMappingTable({
            title: label + ' | ' + name + ' | ' + typeLabel(type),
            context: 'Group Fields',
            refs: refs
          }, f.sub_fields);
        } else if (type === 'repeater' && Array.isArray(f.sub_fields) && f.sub_fields.length) {
          html += buildMappingTable({
            title: label + ' | ' + name + ' | ' + typeLabel(type),
            context: 'Sub Fields (Repeater)',
            refs: refs
          }, f.sub_fields);
        } else if (type === 'flexible' && Array.isArray(f.layouts) && f.layouts.length) {
          var hasLayoutFields = f.layouts.some(function(l){
            return l && Array.isArray(l.sub_fields) && l.sub_fields.length;
          });
          if (hasLayoutFields) {
            html += buildMappingTable({
              title: label + ' | ' + name + ' | ' + typeLabel(type),
              context: 'Layouts',
              refs: refs
            }, [{ label: label, name: name, type: type, layouts: f.layouts }]);
          }
        }
      });
      return html;
    }

    function refreshMappingView(data){
      var $mappingView = $root.find('.cff-field-mapping-view').first();
      if (!$mappingView.length) return;

      var mappingSections = buildMappingSections(data || [], {
        title: 'Top-level Fields',
        context: 'Label | Name | Type'
      });

      if (!mappingSections) {
        mappingSections = '<p class="cff-field-mapping-empty">No fields available</p>';
      }

      $mappingView.html(mappingSections);
    }

    function refreshSecondaryViews(data){
      if (!$root || !$root.length) return;
      refreshReorderList();
      refreshMappingView(data || []);
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
          '<option value="mapping">Mapping</option>' +
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

      var mappingSections = buildMappingSections(data, {
        title: 'Top-level Fields',
        context: 'Label | Name | Type'
      });
      if (!mappingSections) {
        mappingSections = '<p class="cff-field-mapping-empty">No fields available</p>';
      }
      var $mappingView = $(
        '<div class="cff-field-mapping-view">' +
          mappingSections +
        '</div>'
      );
      $builderRoot.append($mappingView);

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
        getMediaOptions($el).maxUploadMb.val(normalizeMediaMaxUploadMb(f.max_upload_mb));
        var layoutValue = f.repeater_layout || 'default';
        var fieldRepeaterOptions = getFieldRepeaterOptions($el);
        fieldRepeaterOptions.layout.val(layoutValue);
        fieldRepeaterOptions.min.val(parseInt(f.min || 0, 10) || 0);
        fieldRepeaterOptions.max.val(parseInt(f.max || 0, 10) || 0);
        fieldRepeaterOptions.rowLabel.val(f.repeater_row_label || '');
        fieldRepeaterOptions.collapsed.prop('checked', !!f.repeater_collapsed);
        toggleRepeaterOptions($el, f.type || 'text');
        toggleDatetimeOptions($el, f.type || 'text');
        toggleMediaOptions($el, f.type || 'text');
        $el.find('.cff-placeholder').val(f.placeholder || '');
        $el.find('.cff-required-toggle').prop('checked', !!f.required);
        $el.find('.cff-datetime-use-time-toggle').prop('checked', (f.datetime_use_time !== false));
        renderConditionalPanel($el, f);
        var fieldKey = CFF.utils.sanitizeName(f.key || '');
        if (!fieldKey) fieldKey = 'fld_' + Math.random().toString(36).slice(2, 14);
        $el.attr('data-field-key', fieldKey);
        $el.data('field-key', fieldKey);
        $el.attr('data-original-name', CFF.utils.sanitizeName(f.name || ''));
        setAliasesAttr($el, 'data-field-aliases', Array.isArray(f.aliases) ? f.aliases : []);

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
        connectWith: '.cff-group-fields',
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); refreshReorderList(); },
        receive: function(event, ui){
          if (ui.item.hasClass('cff-subfield')) {
            var item = readSingleSubfield(ui.item);
            if (!item) return;
            var $replacement = renderField(item, Date.now());
            ui.item.replaceWith($replacement);
            save(readFromDOM());
            refreshReorderList();
            refreshConditionalFieldDropdowns();
            $(document).trigger('cff:refresh', $replacement);
          } else {
            save(readFromDOM());
            refreshReorderList();
            refreshConditionalFieldDropdowns();
          }
        }
      });

      var $reorderList = $builderRoot.find('#cff-field-reorder-list');
      $reorderList.sortable({
        handle: '.cff-field-reorder-handle',
        update: function(){ applyReorderFromReorderList(); }
      });

      setFieldViewMode(fieldViewMode);

      $root.find('.cff-field-row').each(function(){
        var $row = $(this);
        toggleBuilders($row);
        toggleChoicePanel($row, $row.find('.cff-type').val() || 'text');
        toggleRelationalPanel($row, $row.find('.cff-type').val() || 'text');
      });

      $root.find('.cff-subfield').each(function(){
        var $sub = $(this);
        toggleSubGroup($sub);
        toggleSubRepeater($sub);
      });

      initGroupDropzones($root);
      refreshConditionalFieldDropdowns();
      refreshSecondaryViews(data);
      $(document).trigger('cff:refresh', $root);
    }

    function renderField(f, i){
      var html = CFF.utils.tmpl(tplField, {
        i: i,
        label: CFF.utils.escapeHtml(f.label || ''),
        name: CFF.utils.escapeHtml(f.name || ''),
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
      getMediaOptions($el).maxUploadMb.val(normalizeMediaMaxUploadMb(f.max_upload_mb));
      var layoutValue = f.repeater_layout || 'default';
      var fieldRepeaterOptions = getFieldRepeaterOptions($el);
      fieldRepeaterOptions.layout.val(layoutValue);
      fieldRepeaterOptions.min.val(parseInt(f.min || 0, 10) || 0);
      fieldRepeaterOptions.max.val(parseInt(f.max || 0, 10) || 0);
      fieldRepeaterOptions.rowLabel.val(f.repeater_row_label || '');
      fieldRepeaterOptions.collapsed.prop('checked', !!f.repeater_collapsed);
      toggleRepeaterOptions($el, f.type || 'text');
      toggleDatetimeOptions($el, f.type || 'text');
      toggleMediaOptions($el, f.type || 'text');
      $el.find('.cff-placeholder').val(f.placeholder || '');
      $el.find('.cff-required-toggle').prop('checked', !!f.required);
      $el.find('.cff-datetime-use-time-toggle').prop('checked', (f.datetime_use_time !== false));
      renderConditionalPanel($el, f);
      var fieldKey = CFF.utils.sanitizeName(f.key || '');
      if (!fieldKey) fieldKey = 'fld_' + Math.random().toString(36).slice(2, 14);
      $el.attr('data-field-key', fieldKey);
      $el.data('field-key', fieldKey);
      $el.attr('data-original-name', CFF.utils.sanitizeName(f.name || ''));
      setAliasesAttr($el, 'data-field-aliases', Array.isArray(f.aliases) ? f.aliases : []);

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

      initGroupDropzones($el);
      return $el;
    }

    function getTmpSubKey($sub){
      var k = $sub.attr('data-tmpkey');
      if (!k) {
        k = 'tmp_' + Date.now() + '_' + Math.random().toString(16).slice(2);
        $sub.attr('data-tmpkey', k);
      }
      return k;
    }

    function readSingleSubfield($sub){
      if (!$sub || !$sub.length) return null;

        var $head = getFieldHeadRow($sub);
        var $meta = getFieldMetaRow($sub);
        var stype = $head.find('.cff-stype').first().val() || 'text';

        var rawName = $head.find('.cff-sname').first().val() || '';
        var name = CFF.utils.sanitizeName(rawName);

        // pakai name kalau ada, kalau kosong pakai tmpkey
        var key = name || getTmpSubKey($sub);

        var item = {
          label: $head.find('.cff-slabel').first().val() || '',
          name:  name,                 // boleh kosong sementara
          _tmp:  name ? '' : key,       // simpan tmp id biar stabil
          type:  stype,
          required: $meta.find('.cff-required-toggle').first().is(':checked')
        };
        item.key = CFF.utils.sanitizeName($sub.attr('data-sub-key') || '');
        if (!item.key) {
          item.key = 'fld_' + Math.random().toString(36).slice(2, 14);
          $sub.attr('data-sub-key', item.key);
        }
        var originalSubName = CFF.utils.sanitizeName($sub.attr('data-original-name') || '');
        var subAliases = parseAliasesAttr($sub, 'data-field-aliases');
        if (originalSubName && originalSubName !== name && subAliases.indexOf(originalSubName) === -1) {
          subAliases.push(originalSubName);
        }
        subAliases = subAliases.filter(function(alias){ return alias && alias !== name; });
        if (subAliases.length) item.aliases = subAliases;
        $sub.attr('data-original-name', name);
        setAliasesAttr($sub, 'data-field-aliases', subAliases);

        item.placeholder = $meta.find('.cff-placeholder').first().val() || '';
        if (stype === 'image' || stype === 'file') {
          item.max_upload_mb = normalizeMediaMaxUploadMb(getMediaOptions($sub).maxUploadMb.val());
        }
        if (stype === 'datetime_picker') {
          item.datetime_use_time = $meta.find('.cff-datetime-use-time-toggle').first().is(':checked');
        }
        var conditionalLogic = readConditionalLogic($sub);
        if (conditionalLogic) {
          item.conditional_logic = conditionalLogic;
        }

        if (stype === 'choice') {
          var $choicePanel = getOwnChoicePanel($sub);
          item.choices = readChoices($choicePanel.find('.cff-choices-list').first());
          item.choice_display = $choicePanel.find('.cff-choice-display').first().val() || 'select';
          item.choice_default = $choicePanel.find('.cff-choice-default').first().val() || '';
        }

        if (stype === 'relational') {
          var $relPanel = getOwnRelationalPanel($sub);
          item.relational_type = $relPanel.find('.cff-relational-type').first().val() || 'post';
          item.relational_subtype = $relPanel.find('.cff-relational-subtype').first().val() || ($sub.data('cff-relational-subtype') || '');
          item.relational_display = $relPanel.find('.cff-relational-display').first().val() || 'select';
          item.relational_multiple = $relPanel.find('.cff-relational-multiple-toggle').first().is(':checked');
        }

        if (stype === 'group') {
          item.sub_fields = readSubfields(
            $sub.find('> .cff-groupbuilder > .cff-group-fields').first()
          );
        }
        if (stype === 'repeater') {
          var subRepeaterOptions = getSubfieldRepeaterOptions($sub);
          item.sub_fields = readSubfields(
            $sub.find('> .cff-subbuilder > .cff-subfields').first()
          );
          item.repeater_layout = subRepeaterOptions.layout.val() || 'default';
          item.min = Math.max(0, parseInt(subRepeaterOptions.min.val() || 0, 10) || 0);
          item.max = Math.max(0, parseInt(subRepeaterOptions.max.val() || 0, 10) || 0);
          if (item.max > 0 && item.max < item.min) item.max = item.min;
          item.repeater_row_label = CFF.utils.sanitizeName(subRepeaterOptions.rowLabel.val() || '');
          item.repeater_collapsed = subRepeaterOptions.collapsed.is(':checked');
        }

      return item;
    }

    function readSubfields($container){
      if (!$container || !$container.length) return []; 

      var subs = [];
      var seen = new Set();

      $container.children('.cff-subfield').each(function(){
        var item = readSingleSubfield($(this));
        if (!item) return;
        var key = item.name || item._tmp || item.key || '';
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
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
        var $row = $(this).closest('.cff-field-row');
        var $list = $('#cff-field-list');
        var item = readSingleField($row);
        item = item ? JSON.parse(JSON.stringify(item)) : null;
        if (!item) return;

        item.name = '';
        item.key = '';
        item.label = (item.label ? item.label + ' (Copy)' : '');
        var $clone = renderField(item, Date.now());
        $row.after($clone);
        if ($list.length) {
          $list.sortable('refresh');
        }
        save(readFromDOM());
        $(document).trigger('cff:refresh', $clone);
      });

      // Add field
      $root.on('click', '#cff-add-field', function(){
        var $list = $('#cff-field-list');
        if (!$list.length) return;

        var $field = renderField({ label:'', name:'', type:'text' }, Date.now());
        $list.append($field);
        if ($list.hasClass('ui-sortable')) {
          $list.sortable('refresh');
        }
        save(readFromDOM());
        refreshReorderList();
        refreshConditionalFieldDropdowns();
        $(document).trigger('cff:refresh', $field);
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
        refreshConditionalFieldDropdowns();
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
        refreshConditionalFieldDropdowns();
      });

      $root.on('blur', '.cff-slabel, .cff-sname', function(){
        save(readFromDOM());
      });


      $root.on('input', '.cff-slabel, .cff-sname, .cff-stype, .cff-placeholder, .cff-choice-label, .cff-choice-value, .cff-max-upload-mb', CFF.utils.debounce(function(){
        var $panel = $(this).closest('.cff-field-choice');
        if ($panel.length) updateChoiceDefaultOptions($panel);
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
        updateChoiceDefaultOptions($panel);
        save(readFromDOM());
      });

      $root.on('click', '.cff-choice-remove', function(){
        var $panel = $(this).closest('.cff-field-choice');
        $(this).closest('.cff-choice-row').remove();
        updateChoiceDefaultOptions($panel);
        save(readFromDOM());
      });

      $root.on('change', '.cff-choice-display', function(){
        var $panel = $(this).closest('.cff-field-choice');
        updateChoiceDefaultOptions($panel);
        save(readFromDOM());
      });

      $root.on('change', '.cff-choice-default', function(){
        save(readFromDOM());
      });

      $root.on('change', '.cff-repeater-layout', function(){
        save(readFromDOM());
      });

      $root.on('input change', '.cff-repeater-min, .cff-repeater-max, .cff-repeater-row-label', CFF.utils.debounce(function(){
        if ($(this).hasClass('cff-repeater-row-label')) {
          $(this).val(CFF.utils.sanitizeName($(this).val()));
        }
        save(readFromDOM());
      }, 150));

      $root.on('change', '.cff-repeater-collapsed-toggle', function(){
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
        var $rowRelPanel = getOwnRelationalPanel($row);
        toggleBuilders($row);
        toggleChoicePanel($row, $(this).val());
        toggleRelationalPanel($row, $(this).val());
        renderRelationalPanel($row, {
          relational_type: $rowRelPanel.find('.cff-relational-type').first().val() || 'post',
          relational_subtype: $rowRelPanel.find('.cff-relational-subtype').first().val() || '',
          relational_display: $rowRelPanel.find('.cff-relational-display').first().val() || 'select',
          relational_multiple: $rowRelPanel.find('.cff-relational-multiple-toggle').first().is(':checked'),
        });
        togglePlaceholderRow($row, $(this).val());
        toggleDatetimeOptions($row, $(this).val());
        toggleMediaOptions($row, $(this).val());
        refreshConditionalFieldDropdowns();
        save(readFromDOM());
      });

      $root.on('change', '#cff-field-view-mode', function(){
        setFieldViewMode($(this).val());
      });

      $root.on('click', '.cff-field-mapping-jump', function(e){
        e.preventDefault();
        jumpToMappedField($(this).attr('data-field-key') || '');
      });

      $root.on('change', '.cff-stype', function(){
        var $sub = $(this).closest('.cff-subfield');
        var $subRelPanel = getOwnRelationalPanel($sub);
        toggleSubGroup($sub);
        toggleSubRepeater($sub);
        toggleChoicePanel($sub, $(this).val());
        togglePlaceholderRow($sub, $(this).val());
        renderRelationalPanel($sub, {
          relational_type: $subRelPanel.find('.cff-relational-type').first().val() || 'post',
          relational_subtype: $subRelPanel.find('.cff-relational-subtype').first().val() || '',
          relational_display: $subRelPanel.find('.cff-relational-display').first().val() || 'select',
          relational_multiple: $subRelPanel.find('.cff-relational-multiple-toggle').first().is(':checked'),
        });
        toggleRelationalPanel($sub, $(this).val());
        toggleRepeaterOptions($sub, $(this).val());
        toggleDatetimeOptions($sub, $(this).val());
        toggleMediaOptions($sub, $(this).val());
        refreshConditionalFieldDropdowns();
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

      $root.on('click', '.cff-duplicate-sub', function(){
        var $sub = $(this).closest('.cff-subfield');
        var $container = $sub.parent();
        if (!$sub.length || !$container.length) return;

        var item = readSingleSubfield($sub);
        item = item ? JSON.parse(JSON.stringify(item)) : null;
        if (!item) return;

        item.name = '';
        item._tmp = '';
        item.key = '';
        item.label = item.label ? item.label + ' (Copy)' : '';
        var $clone = renderSub(item, Date.now());
        $sub.after($clone);
        sortableSubs($container);
        save(readFromDOM());
        $(document).trigger('cff:refresh', $clone);
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
        var $ctx = getRelationalContext($(this));
        updateRelationalSubtypeOptions($ctx);
        save(readFromDOM());
      });

      $root.on(
        'change',
        '.cff-relational-subtype, .cff-relational-display, .cff-relational-multiple-toggle',
        function(){
          var $ctx = getRelationalContext($(this));
          save(readFromDOM());
        }
      );


      $root.on('change', '.cff-relational-subtype', function(){
        var $ctx = getRelationalContext($(this));

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
            checkbox('field_actions','Field Actions') +
            checkbox('copy_to_translations','Save + Copy CFF to Translations Button') +

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
          $.trim($field.find('.cff-hndle').first().text()),
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
        var depth = parseInt(it.depth, 10);
        if (isNaN(depth) || depth < 0) depth = 0;
        var itemClass = 'cff-reorder-item' + (depth ? ' is-child' : '');
        $list.append(
          '<li class="' + itemClass + '" data-id="' + escAttr(String(it.id)) + '" data-parent="' + escAttr(String(it.parent || 0)) + '" data-depth="' + escAttr(String(depth)) + '">' +
            '<span class="cff-reorder-handle">≡</span>' +
            '<span class="cff-reorder-title" style="padding-left:' + escAttr(String(depth * 20)) + 'px">' + esc(it.title || '') + '</span>' +
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
        var sourceKey = String($target.data('cffConditionalKey') || '');
        var sourceField = String($target.data('cffConditionalField') || '');
        var operator = String($target.data('cffConditionalOperator') || '==');
        var expected = String($target.data('cffConditionalValue') || '');
        if (!sourceKey && !sourceField) return;

        var sourceVal = readFieldValue($target, sourceKey, sourceField);
        var visible = compareValue(sourceVal, operator, expected);
        toggleVisibility($target, visible);
      });
    }

    function getNamedInputs($container, fieldName){
      var baseName = 'cff_values[' + fieldName + ']';
      var nestedName = '[' + fieldName + ']';
      var nestedArrayName = nestedName + '[]';

      return $container.find(':input').filter(function(){
        var n = this.name || '';
        return n === baseName
          || n === (baseName + '[]')
          || n.slice(-nestedName.length) === nestedName
          || n.slice(-nestedArrayName.length) === nestedArrayName;
      });
    }

    function readInputValue($inputs){
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

    function mergeConditionalValues(values){
      if (!values.length) return '';
      if (values.length === 1) return values[0];

      var merged = [];
      values.forEach(function(value){
        if ($.isArray(value)) {
          value.forEach(function(item){
            merged.push(String(item));
          });
          return;
        }

        var str = String(value || '');
        if (str !== '') merged.push(str);
      });

      return merged;
    }

    function readFieldValueFromWrappers($container, selector){
      var values = [];
      $container.find(selector).each(function(){
        var $wrapper = $(this);
        var $inputs = $wrapper.find(':input').filter(function(){
          return !$(this).closest(selector).not($wrapper).length;
        });
        if ($inputs.length) {
          values.push(readInputValue($inputs));
        }
      });
      return mergeConditionalValues(values);
    }

    function readFieldValue($target, fieldKey, fieldName){
      var $container = $target.closest('.cff-metabox');
      if (!$container.length) {
        $container = $(document.body);
      }

      var fieldKeySelector = fieldKey ? '[data-field-key="' + fieldKey + '"]' : '';
      var wrapperValue = fieldKeySelector ? readFieldValueFromWrappers($container, fieldKeySelector) : '';
      if (($.isArray(wrapperValue) && wrapperValue.length) || (!$.isArray(wrapperValue) && String(wrapperValue || '') !== '')) {
        return wrapperValue;
      }

      if (fieldName) {
        wrapperValue = readFieldValueFromWrappers($container, '[data-field-name="' + fieldName + '"]');
        if (($.isArray(wrapperValue) && wrapperValue.length) || (!$.isArray(wrapperValue) && String(wrapperValue || '') !== '')) {
          return wrapperValue;
        }
      }

      if (!fieldName) return '';

      var $inputs = getNamedInputs($container, fieldName);
      if (!$inputs.length) return '';

      var groups = {};
      var values = [];
      $inputs.each(function(){
        var name = this.name || '';
        if (!groups[name]) groups[name] = [];
        groups[name].push(this);
      });

      Object.keys(groups).forEach(function(name){
        values.push(readInputValue($(groups[name])));
      });

      return mergeConditionalValues(values);
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
