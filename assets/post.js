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
    var $preview = $wrap.find('.cff-media-preview');

    $id.val(id || '');

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
      title: (type === 'image') ? 'Select image' : 'Select file',
      button: { text: 'Use this' },
      multiple: false,
      library: (type === 'image') ? { type: 'image' } : {}
    });

    frame.on('select', function(){
      var sel = frame.state().get('selection');
      var model = sel && sel.first && sel.first();
      if (!model) return;

      var att = model.toJSON();
      if (!att || !att.id) return;

      $wrap.find('input.cff-media-id').val(att.id).trigger('change');

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
