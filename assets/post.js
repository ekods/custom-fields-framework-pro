jQuery(function($){

  function safeText(v){ return String(v == null ? '' : v); }

  // ✅ replace all token tanpa replaceAll()
  function replaceTokenAll(str, token, value){
    str = safeText(str);
    token = safeText(token);
    value = safeText(value);
    if (!token) return str;
    return str.split(token).join(value);
  }

  /* -------------------------
   * WYSIWYG init/remove (dynamic)
   * ------------------------- */
   function cffInitWysiwyg($scope){
     if (!window.wp || !wp.editor || !wp.editor.initialize) return;

     var defaults = (wp.editor.getDefaultSettings)
       ? wp.editor.getDefaultSettings()
       : { tinymce: {}, quicktags: true, mediaButtons: true };

     $scope.find('textarea.cff-wysiwyg').each(function(){
       var id = this.id;
       if (!id) return;
       if (window.tinymce && tinymce.get(id)) return;

       var settings = $.extend(true, {}, defaults, {
         tinymce: true,
         quicktags: true,
         mediaButtons: true
       });

       var $cfg = $(this).closest('.cff-field, .cff-subfield, .cff-repeater-row')
         .find('.cff-wysiwyg-settings[data-editor-id="'+id+'"]');

       if ($cfg.length) {
         try { settings = $.extend(true, settings, JSON.parse($cfg.val() || '{}')); } catch(e){}
       }

       wp.editor.initialize(id, settings);
     });
   }

   function cffRemoveWysiwyg($scope){
     if (!window.wp || !wp.editor || !wp.editor.remove) return;

     $scope.find('textarea.cff-wysiwyg').each(function(){
       var id = this.id;
       if (id) wp.editor.remove(id);
     });
   }

  // init existing wysiwyg on load (kalau ada template textarea)
  cffInitWysiwyg($(document));

  /* -------------------------
   * Media helpers
   * ------------------------- */
  function renderMedia($wrap, id){
    var $id = $wrap.find('.cff-media-id');
    var $preview = $wrap.find('.cff-media-preview');

    $id.val(id || '');

    if (!id){
      $preview.empty().append(
        $('<span/>', { class: 'cff-muted', text: 'No file selected' })
      );
      return;
    }

    $preview.empty().append(
      $('<span/>').append(
        document.createTextNode('Attachment ID: '),
        $('<strong/>', { text: String(id) })
      )
    );
  }

  function renderMediaLink($wrap, url, label){
    var $preview = $wrap.find('.cff-media-preview');
    $preview.empty();

    var href = safeText(url).trim();
    if (!/^https?:\/\//i.test(href)) return;

    var text = safeText(label || href);
    var $a = $('<a/>', {
      href: href,
      target: '_blank',
      rel: 'noopener noreferrer',
      text: text
    });

    $preview.append($a);
  }

  var cffFrames = {};

  $(document).on('click', '.cff-media-select', function(e){
    e.preventDefault();

    var $wrap = $(this).closest('.cff-media');
    if (!$wrap.length) return;

    var type = $wrap.data('type') || 'file';
    if (!window.wp || !wp.media) return;

    var frame = wp.media({
      title: (type === 'image') ? 'Select image' : 'Select file',
      button: { text: 'Use this' },
      multiple: false,
      library: (type === 'image') ? { type: 'image' } : {}
    });

    frame.on('select', function(){
      var sel = frame.state().get('selection');
      if (!sel) return;

      var model = sel.first();
      if (!model) return;

      var att = model.toJSON();
      if (!att || !att.id) return;

      // ✅ SET ID (ini yang bikin value gak lagi 0)
      $wrap.find('input.cff-media-id').val(att.id).trigger('change');

      // ✅ PREVIEW
      var $preview = $wrap.find('.cff-media-preview').empty();

      if (type === 'image') {
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

      // fallback file link
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
   * Sortable init (repeater rows)
   * ------------------------- */
  function initSortable($container){
    if (!$.fn.sortable || !$container || !$container.length) return;

    // avoid double init
    try {
      if ($container.data('ui-sortable')) return;
    } catch(_) {}

    $container.sortable({
      handle: '.cff-rep-drag',
      items: '> .cff-rep-row'
    });
  }

  $('.cff-rep-rows').each(function(){
    initSortable($(this));
  });

  function cffReplaceAll(str, find, rep){
      return String(str || '').split(find).join(rep);
    }

  // ===== expose ke window supaya bisa dipakai di mana-mana
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

      // ✅ FIX: cari settings dari parent yang bener (.cff-rep-row)
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

  // ===== sortable + reindex
  function initSortable($rows){
    if (!$.fn.sortable || !$rows.length) return;

    // reinit aman
    if ($rows.data('ui-sortable')) {
      $rows.sortable('destroy');
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

  function escapeReg(s){
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function reindexRepeater($rep){
    var parent = String($rep.data('field') || '');
    if (!parent) return;

    $rep.find('.cff-rep-row').each(function(newIndex){
      var $row = $(this);

      // deteksi oldIndex dari data-i (fallback dari name)
      var oldIndex = $row.attr('data-i');
      if (oldIndex == null || oldIndex === '') {
        var n = $row.find('[name^="cff_values['+parent+']["]').first().attr('name') || '';
        var m = n.match(new RegExp('cff_values\\['+escapeReg(parent)+'\\]\\[(\\d+)\\]'));
        oldIndex = m ? m[1] : '';
      }

      // matikan editor dulu sebelum ubah id/name
      window.cffRemoveWysiwyg($row);

      $row.attr('data-i', newIndex);

      // ✅ update semua name yang mengandung [parent][old]
      if (oldIndex !== '') {
        var pat = new RegExp('\\['+escapeReg(parent)+'\\]\\['+escapeReg(oldIndex)+'\\]', 'g');
        $row.find('[name]').each(function(){
          var name = $(this).attr('name');
          if (!name) return;
          $(this).attr('name', name.replace(pat, '['+parent+']['+newIndex+']'));
        });
      }

      // ✅ update ID editor: ..._<postid>_<old> -> ..._<postid>_<new>
      $row.find('textarea.cff-wysiwyg[id]').each(function(){
        // lebih aman: replace _<angka_di_akhir>
        this.id = this.id.replace(/_\d+$/, '_'+newIndex);
      });

      // sync data-editor-id settings
      $row.find('.cff-wysiwyg-settings[data-editor-id]').each(function(){
        var cur = $(this).attr('data-editor-id');
        if (cur) $(this).attr('data-editor-id', cur.replace(/_\d+$/, '_'+newIndex));
      });

      // hidupkan lagi editor
      window.cffInitWysiwyg($row);
    });

    // WP detect dirty
    $rep.closest('form').trigger('change');
  }

  // ===== INIT existing
  $('.cff-rep-rows').each(function(){
    initSortable($(this));
  });
  window.cffInitWysiwyg($(document));

  // ===== ADD ROW (PASTIKAN cuma 1 handler!)
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

      $rep.closest('form').trigger('change');
    });

  // ===== REMOVE ROW + REINDEX (juga hanya 1 handler)
  $(document)
    .off('click.cffRepRemove', '.cff-rep-remove')
    .on('click.cffRepRemove', '.cff-rep-remove', function(e){
      e.preventDefault();

      var $rep = $(this).closest('.cff-repeater');
      var $row = $(this).closest('.cff-rep-row');

      window.cffRemoveWysiwyg($row);
      $row.remove();

      reindexRepeater($rep);
      $rep.closest('form').trigger('change');
    });

  // ===== sync tinymce -> textarea sebelum submit (wajib)
  $(document).on('submit.cffTinymceSave', '#post', function(){
    if (window.tinymce) {
      try { tinymce.triggerSave(); } catch(e){}
    }
  });


  $(document).on('click', '.cff-rep-remove', function(e){
    e.preventDefault();

    var $rep = $(this).closest('.cff-repeater');
    var $row = $(this).closest('.cff-rep-row');

    if (window.wp && wp.editor) {
      $row.find('textarea.cff-wysiwyg[id]').each(function(){
        try { wp.editor.remove(this.id); } catch(e){}
      });
    }

    $row.remove();
    cffReindexRepeater($rep);

    $rep.closest('form').trigger('change');
  });


});
