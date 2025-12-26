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
          '<div class="cff-col"><label>Label</label><input type="text" class="cff-input cff-label" value="{{label}}"></div>' +
          '<div class="cff-col"><label>Name</label><input type="text" class="cff-input cff-name" value="{{name}}"></div>' +
          '<div class="cff-col"><label>Type</label>' +
            '<select class="cff-input cff-type">' +
              '<option value="text">Text</option>' +
              '<option value="textarea">Textarea</option>' +
              '<option value="wysiwyg">WYSIWYG</option>' +
              '<option value="color">Color</option>' +
              '<option value="url">URL</option>' +
              '<option value="link">Link</option>' +
              '<option value="checkbox">Checkbox</option>' +
              '<option value="image">Image</option>' +
              '<option value="file">File</option>' +
              '<option value="group">Group</option>' +
              '<option value="repeater">Repeater</option>' +
              '<option value="flexible">Flexible Content</option>' +
            '</select>' +
          '</div>' +
          '<div class="cff-col cff-actions">' +
            '<button type="button" class="button cff-duplicate">Duplicate</button> ' +
            '<button type="button" class="button cff-remove">Remove</button>' +
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
          '<div class="cff-handle"></div>' +
          '<div class="cff-col"><label>Label</label><input type="text" class="cff-input cff-slabel" value="{{label}}"></div>' +
          '<div class="cff-col"><label>Name</label><input type="text" class="cff-input cff-sname" value="{{name}}"></div>' +
          '<div class="cff-col"><label>Type</label>' +
            '<select class="cff-input cff-stype">' +
              '<option value="text">Text</option>' +
              '<option value="textarea">Textarea</option>' +
              '<option value="wysiwyg">WYSIWYG</option>' +
              '<option value="color">Color</option>' +
              '<option value="url">URL</option>' +
              '<option value="link">Link</option>' +
              '<option value="checkbox">Checkbox</option>' +
              '<option value="image">Image</option>' +
              '<option value="file">File</option>' +
            '</select>' +
          '</div>' +
          '<div class="cff-col cff-actions"><button type="button" class="button cff-remove-sub">Remove</button></div>' +
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
      $input.val(JSON.stringify(data || []));
      $input.trigger('change');
    }

    function toggleBuilders($field){
      var t = $field.find('.cff-type').val();
      $field.find('.cff-subbuilder').toggle(t === 'repeater');
      $field.find('.cff-groupbuilder').toggle(t === 'group');
      $field.find('.cff-flexbuilder').toggle(t === 'flexible');
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
        name:  CFF.utils.escapeHtml(s.name  || '')
      });
      var $el = $(html);
      $el.find('.cff-stype').val(s.type || 'text');
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

    function readFromDOM(){
      var data = [];
      $('#cff-field-list .cff-field-row').each(function(){
        var $f = $(this);
        var label = $f.find('.cff-label').val() || '';
        var name  = CFF.utils.sanitizeName($f.find('.cff-name').val() || '');
        var type  = $f.find('.cff-type').val() || 'text';

        var item = { label: label, name: name, type: type };

        if (type === 'repeater') {
          var subs = [];
          $f.find('.cff-subfields .cff-subfield').each(function(){
            subs.push({
              label: $(this).find('.cff-slabel').val() || '',
              name:  CFF.utils.sanitizeName($(this).find('.cff-sname').val() || ''),
              type:  $(this).find('.cff-stype').val() || 'text'
            });
          });
          item.sub_fields = subs;
        }

        if (type === 'group') {
          var gsubs = [];
          $f.find('.cff-group-fields .cff-subfield').each(function(){
            gsubs.push({
              label: $(this).find('.cff-slabel').val() || '',
              name:  CFF.utils.sanitizeName($(this).find('.cff-sname').val() || ''),
              type:  $(this).find('.cff-stype').val() || 'text'
            });
          });
          item.sub_fields = gsubs;
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

            $l.find('.cff-layout-fields .cff-subfield').each(function(){
              litem.sub_fields.push({
                label: $(this).find('.cff-slabel').val() || '',
                name:  CFF.utils.sanitizeName($(this).find('.cff-sname').val() || ''),
                type:  $(this).find('.cff-stype').val() || 'text'
              });
            });

            layouts.push(litem);
          });
          item.layouts = layouts;
        }

        data.push(item);
      });
      return data;
    }


    function commit(){
      save(readFromDOM());
      $input.trigger('change'); // bantu WP detect dirty
    }

    function commitFromDOM(){
      save(readFromDOM());
    }


    function render(){
      var data = load();
      $root.empty();

      $root.append(
        '<div class="cff-builder-head">' +
          '<strong>Fields</strong>' +
          '<button type="button" class="button button-primary" id="cff-add-field">Add Field</button>' +
        '</div>' +
        '<div class="cff-builder-head-row">' +
          '<div class="cff-head-spacer"></div>' +
          '<div class="cff-head">Label</div>' +
          '<div class="cff-head">Name</div>' +
          '<div class="cff-head">Type</div>' +
          '<div class="cff-head">Actions</div>' +
        '</div>'
      );

      var $list = $('<div id="cff-field-list"></div>');

      data.forEach(function(f, i){
        var html = CFF.utils.tmpl(tplField, {
          i: i,
          label: CFF.utils.escapeHtml(f.label || ''),
          name:  CFF.utils.escapeHtml(f.name  || '')
        });

        var $el = $(html);
        $el.addClass('is-collapsed');
        $el.find('.cff-type').val(f.type || 'text');

        toggleBuilders($el);

        if (f.type === 'repeater' && Array.isArray(f.sub_fields)) {
          var $sf = $el.find('.cff-subfields');
          f.sub_fields.forEach(function(s, si){ $sf.append(renderSub(s, si)); });
          sortableSubs($sf);
        }

        if (f.type === 'group' && Array.isArray(f.sub_fields)) {
          var $gf = $el.find('.cff-group-fields');
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

      $root.append($list);

      $('#cff-field-list').sortable({
        handle: '.cff-handle',
        update: function(){ save(readFromDOM()); }
      });
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

      var current  = CFF.utils.sanitizeName($name.val() || '');
      var prevAuto = $name.data('auto') || '';

      if (!current || current === prevAuto) {
        $name.val(slug);
        $name.data('auto', slug);
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
      });

      $root.on('input', '.cff-slabel', function(){
        autoNameFromSubLabel($(this).closest('.cff-subfield'));
      });

      $root.on('blur', '.cff-slabel, .cff-sname', function(){
        save(readFromDOM());
      });


      $root.on('input', '.cff-slabel, .cff-sname, .cff-stype', CFF.utils.debounce(function(){
        save(readFromDOM());
      }, 150));

      // Layout name sanitize
      $root.on('input', '.cff-lname', function(){
        $(this).val(CFF.utils.sanitizeName($(this).val()));
      });

      // Type change
      $root.on('change', '.cff-type', function(){
        toggleBuilders($(this).closest('.cff-field-row'));
        save(readFromDOM());
      });

      $root.on('click', '.cff-acc-toggle', function(){
        var $row = $(this).closest('.cff-field-row');
        $row.toggleClass('is-collapsed');
        $(this).attr('aria-expanded', !$row.hasClass('is-collapsed'));
      });

      $root.on('blur', '.cff-llabel, .cff-lname', function(){
        save(readFromDOM());
      });

      // Remove field
      $root.on('click', '.cff-remove', function(){
        $(this).closest('.cff-field-row').remove();
        save(readFromDOM());
      });

      // Repeater add/remove sub
      $root.on('click', '.cff-add-sub', function(){
        var $f = $(this).closest('.cff-field-row');
        $f.find('.cff-subfields').append(renderSub({ label:'', name:'', type:'text' }, Date.now()));
        save(readFromDOM());
      });

      $root.on('click', '.cff-add-group-sub', function(){
        var $f = $(this).closest('.cff-field-row');
        $f.find('.cff-group-fields').append(renderSub({ label:'', name:'', type:'text' }, Date.now()));
        save(readFromDOM());
      });

      $root.on('click', '.cff-remove-sub', function(){
        $(this).closest('.cff-subfield').remove();
        save(readFromDOM());
      });

      // Flexible add/remove layout
      $root.on('click', '.cff-add-layout', function(){
        var $f = $(this).closest('.cff-field-row');
        $f.find('.cff-layouts').append(renderLayout({ label:'', name:'', sub_fields:[] }, Date.now()));
        save(readFromDOM());
      });

      $root.on('click', '.cff-remove-layout', function(){
        $(this).closest('.cff-layout').remove();
        save(readFromDOM());
      });

      $root.on('click', '.cff-toggle-layout', function(){
        $(this).closest('.cff-layout').toggleClass('open');
      });

      $root.on('click', '.cff-add-layout-field', function(){
        var $layout = $(this).closest('.cff-layout');
        $layout.find('.cff-layout-fields').append(renderSub({ label:'', name:'', type:'text' }, Date.now()));
        save(readFromDOM());
      });
    }

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
   });
})(jQuery);
