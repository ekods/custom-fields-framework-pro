jQuery(function($){

  function safeText(v){ return String(v == null ? '' : v); }
  function cffReplaceAll(str, find, rep){
    return String(str || '').split(find).join(rep);
  }
  function escapeReg(s){
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  /* -------------------------
   * WYSIWYG init/remove (single source of truth)
   * ------------------------- */
  window.cffInitWysiwyg = function($scope){
    if (!window.wp || !wp.editor || !wp.editor.initialize) return;

    var defaults = (wp.editor.getDefaultSettings)
      ? wp.editor.getDefaultSettings()
      : { tinymce: {}, quicktags: true, mediaButtons: true };

    $scope.find('textarea.cff-wysiwyg[id]').each(function(){
      var id = this.id;
      if (!id) return;
      if (window.tinymce && tinymce.get(id)) return;

      var settings = $.extend(true, {}, defaults, {
        tinymce: true,
        quicktags: true,
        mediaButtons: true
      });

      var $cfg = $(this).closest('.cff-field, .cff-subfield-input, .cff-rep-row, .cff-flex-row')
        .find('.cff-wysiwyg-settings[data-editor-id="'+id+'"]');

      if ($cfg.length) {
        try { settings = $.extend(true, settings, JSON.parse($cfg.val() || '{}')); } catch(e){}
      }

      wp.editor.initialize(id, settings);
    });
  };

  window.cffRemoveWysiwyg = function($scope){
    if (!window.wp || !wp.editor || !wp.editor.remove) return;
    $scope.find('textarea.cff-wysiwyg[id]').each(function(){
      try { wp.editor.remove(this.id); } catch(e){}
    });
  };

  /* -------------------------
   * Media picker (tetap, aman)
   * ------------------------- */
  function renderMedia($wrap, id){
    var $id = $wrap.find('.cff-media-id');
    var $url = $wrap.find('.cff-media-url');
    var $preview = $wrap.find('.cff-media-preview');

    $id.val(id || '');
    if ($url.length) $url.val('');

    if (!id){
      $preview.empty().append($('<span/>', { class: 'cff-muted', text: 'No file selected' }));
      return;
    }

    $preview.empty().append(
      $('<span/>').append(
        document.createTextNode('Attachment ID: '),
        $('<strong/>', { text: String(id) })
      )
    );
  }

  $(document).on('click', '.cff-media-select', function(e){
    e.preventDefault();

    var $wrap = $(this).closest('.cff-media');
    if (!$wrap.length) return;

    var type = $wrap.data('type') || 'file';
    if (!window.wp || !wp.media) return;

    var frame = wp.media({
      title: (type === 'image') ? 'Select media' : 'Select file',
      button: { text: 'Use this' },
      multiple: false,
      library: (type === 'image') ? { type: ['image', 'video'] } : {}
    });

    frame.on('select', function(){
      var sel = frame.state().get('selection');
      var model = sel && sel.first && sel.first();
      if (!model) return;

      var att = model.toJSON();
      if (!att || !att.id) return;

      $wrap.find('input.cff-media-id').val(att.id).trigger('change');
      $wrap.find('input.cff-media-url').val(att.url || '');

      var $preview = $wrap.find('.cff-media-preview').empty();

      if (att.type === 'video' || (att.mime && String(att.mime).indexOf('video/') === 0)) {
        if (att.url) {
          var $video = $('<video>', { class: 'cff-media-video', controls: true, preload: 'metadata' });
          $('<source>', { src: att.url, type: att.mime || '' }).appendTo($video);
          $video.appendTo($preview);
          return;
        }
      }

      if (type === 'image' && (att.type === 'image' || (att.mime && String(att.mime).indexOf('image/') === 0))) {
        var imgUrl =
          (att.sizes && att.sizes.medium && att.sizes.medium.url) ? att.sizes.medium.url :
          (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url :
          att.url;

        if (imgUrl) {
          $('<img>', { src: imgUrl, class: 'cff-media-thumb', css:{ maxWidth:'150px', height:'auto', display:'block' } })
            .appendTo($preview);
          return;
        }
      }

      if (att.url) {
        $('<a>', { href: att.url, target:'_blank', rel:'noopener noreferrer', text:(att.filename || att.url) })
          .appendTo($preview);
      } else {
        $preview.append($('<span/>', { class:'cff-muted', text:'Selected (ID: ' + att.id + ')' }));
      }
    });

    frame.open();
  });

  $(document).on('click', '.cff-media-clear', function(e){
    e.preventDefault();
    renderMedia($(this).closest('.cff-media'), '');
  });

  /* -------------------------
   * Color picker + hexa sync
   * ------------------------- */
  function normalizeHex(v){
    v = String(v || '').trim();
    if (!v) return '';
    if (v[0] !== '#') v = '#' + v;
    if (/^#[0-9a-fA-F]{3}$/.test(v)) {
      v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
    }
    return v;
  }

  $(document).on('input change', '.cff-color-picker', function(){
    var $wrap = $(this).closest('.cff-color');
    var $text = $wrap.find('.cff-color-value');
    if ($text.length) $text.val(this.value).trigger('change');
  });

  $(document).on('input change', '.cff-color-value', function(){
    var $wrap = $(this).closest('.cff-color');
    var $picker = $wrap.find('.cff-color-picker');
    var v = normalizeHex($(this).val());
    if ($picker.length && /^#[0-9a-fA-F]{6}$/.test(v)) {
      $picker.val(v);
    }
  });

  /* -------------------------
   * Link picker (internal/custom)
   * ------------------------- */
  function initLinkPickers($scope){
    $scope = $scope && $scope.length ? $scope : $(document);
    $scope.find('.cff-link-picker').each(function(){
      var $wrap = $(this);
      var $modeInputs = $wrap.find('input[type=radio][name$="[mode]"]');
      var $internal = $wrap.find('.cff-link-internal');
      var $custom = $wrap.find('.cff-link-custom');
      var $select = $wrap.find('.cff-link-select');
      var $internalId = $wrap.find('.cff-link-internal-id');
      var $url = $wrap.find('input[name$="[url]"]');
      var $title = $wrap.find('input[name$="[title]"]');

      function applyMode(mode){
        var isInternal = (mode === 'internal');
        $wrap.toggleClass('is-mode-internal', isInternal);
        $wrap.toggleClass('is-mode-custom', !isInternal);
        $internal.toggle(isInternal);
        $custom.toggle(!isInternal);
      }

      var mode = $modeInputs.filter(':checked').val() || $wrap.data('mode') || 'custom';
      $modeInputs.filter('[value="' + mode + '"]').prop('checked', true);
      applyMode(mode);

      if ($select.length && $.fn.select2 && !$select.data('select2')) {
        $select.select2({
          width: '100%',
          placeholder: $select.data('placeholder') || 'Search…',
          allowClear: true,
          ajax: {
            delay: 250,
            transport: function(params, success, failure){
              var term = params.data.term || '';
              var postType = $select.data('post-type') || 'any';
              $.post(CFFP.ajax, {
                action: 'cff_search_posts',
                nonce: CFFP.nonce,
                q: term,
                post_type: postType
              }, function(res){
                if (res && res.success) {
                  success({ results: res.data });
                } else {
                  success({ results: [] });
                }
              }, 'json').fail(failure);
            },
            processResults: function(data){ return data; }
          },
          templateResult: function(item){
            if (!item.id) return item.text;
            var meta = item.meta ? ' — ' + item.meta : '';
            return item.text + meta;
          },
          templateSelection: function(item){
            return item.text || item.id || '';
          },
          escapeMarkup: function(m){ return m; }
        });
      }

      $select.off('.cffLink');
      $select.on('select2:select.cffLink', function(e){
        var data = e.params && e.params.data ? e.params.data : {};
        $internalId.val(data.id || '');
        if (data.url) $url.val(data.url);
        if (data.text) $title.val(data.text);
        $modeInputs.filter('[value="internal"]').prop('checked', true);
        applyMode('internal');
      });
      $select.on('select2:clear.cffLink', function(){
        $internalId.val('');
      });

      $modeInputs.off('.cffLink');
      $modeInputs.on('change.cffLink', function(){
        var m = $modeInputs.filter(':checked').val() || 'custom';
        applyMode(m);
        if (m === 'custom') {
          $internalId.val('');
          if ($select.data('select2')) $select.val(null).trigger('change');
        }
      });
    });
  }

  /* -------------------------
   * Field accordion
   * ------------------------- */
  function togglePostbox($field){
    var isClosed = !$field.hasClass('closed');
    $field.toggleClass('closed', isClosed);
    $field.find('.handlediv').attr('aria-expanded', !isClosed);
    $field.children('.inside').toggle(!isClosed);
  }

  $(document).on('click', '.cff-field.postbox .postbox-header, .cff-field.postbox .handlediv, .cff-field.postbox .hndle', function(e){
    if ($(e.target).closest('a').length) return;
    e.preventDefault();
    togglePostbox($(this).closest('.cff-field.postbox'));
  });

  $(document).on('click', '.cff-rep-toggle', function(e){
    e.preventDefault();
    var $row = $(this).closest('.cff-rep-row');
    $row.toggleClass('is-collapsed');
  });

  /* -------------------------
   * Sortable + reindex
   * ------------------------- */
  function initSortable($rows){
    if (!$.fn.sortable || !$rows.length) return;

    if ($rows.data('ui-sortable')) {
      try { $rows.sortable('destroy'); } catch(e){}
    }

    $rows.sortable({
      handle: '.cff-rep-drag',
      items: '> .cff-rep-row',
      update: function(){
        var $rep = $rows.closest('.cff-repeater');
        reindexRepeater($rep);
      }
    });
  }

  function reindexRepeater($rep){
    var parent = String($rep.data('field') || '');
    if (!parent) return;

    $rep.find('.cff-rep-row').each(function(newIndex){
      var $row = $(this);

      // ambil oldIndex dari data-i atau dari name pertama
      var oldIndex = $row.attr('data-i');
      if (oldIndex == null || oldIndex === '') {
        var n = $row.find('[name^="cff_values['+parent+']["]').first().attr('name') || '';
        var m = n.match(new RegExp('cff_values\\['+escapeReg(parent)+'\\]\\[(\\d+)\\]'));
        oldIndex = m ? m[1] : '';
      }

      // matikan editor dulu sebelum ubah id/name
      window.cffRemoveWysiwyg($row);

      $row.attr('data-i', newIndex);

      // update name: [parent][old] -> [parent][new]
      if (oldIndex !== '') {
        var pat = new RegExp('\\['+escapeReg(parent)+'\\]\\['+escapeReg(oldIndex)+'\\]', 'g');
        $row.find('[name]').each(function(){
          var name = $(this).attr('name');
          if (!name) return;
          $(this).attr('name', name.replace(pat, '['+parent+']['+newIndex+']'));
        });
      }

      // update editor id: pakai pola yang lebih niat
      // contoh: cff_wysiwyg_xxx__INDEX -> cff_wysiwyg_xxx_0 dst
      $row.find('textarea.cff-wysiwyg[id]').each(function(){
        // ganti hanya angka index paling akhir
        this.id = this.id.replace(/(_)(\d+)$/, '$1' + newIndex);
      });

      // sync data-editor-id di settings hidden
      $row.find('.cff-wysiwyg-settings[data-editor-id]').each(function(){
        var cur = $(this).attr('data-editor-id');
        if (!cur) return;
        $(this).attr('data-editor-id', cur.replace(/(_)(\d+)$/, '$1' + newIndex));
      });

      // hidupkan lagi editor
      window.cffInitWysiwyg($row);
    });

    $rep.closest('form').trigger('change');
  }


  function updateRepeaterControls($rep){
    var count = $rep.find('.cff-rep-row').length;

    var min = parseInt($rep.data('min'), 10);
    var max = parseInt($rep.data('max'), 10);

    if (isNaN(min) || min < 0) min = 1;
    if (isNaN(max) || max < 0) max = 0; // 0 = unlimited

    // --- REMOVE: disable kalau count <= min (atau <=1 default safety)
    var canRemove = count > Math.max(1, min);

    $rep.find('.cff-rep-remove').each(function(){
      $(this)
        .toggleClass('is-disabled', !canRemove)
        .attr('aria-disabled', canRemove ? null : 'true');

      // kalau button, ini ngaruh. kalau link, tetap aman.
      try { $(this).prop('disabled', !canRemove); } catch(e){}
    });

    // --- ADD: disable kalau sudah max (kecuali max=0 unlimited)
    var canAdd = (!max || count < max);

    $rep.find('.cff-rep-add').each(function(){
      $(this)
        .toggleClass('is-disabled', !canAdd)
        .attr('aria-disabled', canAdd ? null : 'true');
      try { $(this).prop('disabled', !canAdd); } catch(e){}
    });

    // optional: tooltip kecil biar jelas
    if (!canAdd && max) {
      $rep.find('.cff-rep-add').attr('title', 'Max rows: ' + max);
    } else {
      $rep.find('.cff-rep-add').removeAttr('title');
    }
  }


  /* -------------------------
   * INIT existing
   * ------------------------- */
  $('.cff-rep-rows').each(function(){
    initSortable($(this));
  });
  window.cffInitWysiwyg($(document)); // sekali saja
  initLinkPickers($(document));

  $('.cff-repeater').each(function(){
    updateRepeaterControls($(this));
  });


  /* -------------------------
   * ADD / REMOVE (single handler each)
   * ------------------------- */
  $(document)
    .off('click.cffRepAdd', '.cff-rep-add')
    .on('click.cffRepAdd', '.cff-rep-add', function(e){
      e.preventDefault();

      var $rep = $(this).closest('.cff-repeater');
      var tpl  = $rep.find('script.cff-rep-template').first().html();
      if (!tpl) return;

      var idx = $rep.find('.cff-rep-row').length;
      tpl = cffReplaceAll(tpl, '__INDEX__', String(idx));

      var $rows = $rep.find('.cff-rep-rows');
      var $new  = $(tpl);

      $rows.append($new);

      initSortable($rows);
      window.cffInitWysiwyg($new);
      initLinkPickers($new);

      updateRepeaterControls($rep);

      if ($(this).hasClass('is-disabled')) return;

      $rep.closest('form').trigger('change');
    });

  $(document)
    .off('click.cffRepRemove', '.cff-rep-remove')
    .on('click.cffRepRemove', '.cff-rep-remove', function(e){
      e.preventDefault();

      var $rep = $(this).closest('.cff-repeater');
      var $row = $(this).closest('.cff-rep-row');

      window.cffRemoveWysiwyg($row);
      $row.remove();

      reindexRepeater($rep);

      updateRepeaterControls($rep);

      if ($(this).hasClass('is-disabled')) return;

      $rep.closest('form').trigger('change');
    });

  /* -------------------------
   * Sync TinyMCE to textarea before submit
   * ------------------------- */
  $(document).on('submit.cffTinymceSave', '#post', function(){
    if (window.tinymce) {
      try { tinymce.triggerSave(); } catch(e){}
    }
  });

});
