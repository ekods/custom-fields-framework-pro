jQuery(function($){

  function safeText(v){ return String(v == null ? '' : v); }
  function cffReplaceAll(str, find, rep){
    return String(str || '').split(find).join(rep);
  }
  function escapeReg(s){
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }
  function generateRowId(){
    return 'row_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
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

  function getMediaMaxUploadMb($wrap){
    var value = parseInt($wrap.attr('data-max-upload-mb') || '2', 10) || 0;
    return value > 0 ? value : 2;
  }

  function clearMediaNotice($wrap){
    $wrap.find('.cff-media-notice').remove();
    $wrap.removeClass('is-error');
  }

  function setMediaNotice($wrap, message){
    var text = String(message || '').trim();
    clearMediaNotice($wrap);
    if (!text) return;
    $('<div/>', {
      'class': 'cff-media-notice',
      text: text
    }).appendTo($wrap);
    $wrap.addClass('is-error');
  }

  function getAttachmentFilesizeBytes(att){
    if (!att) return 0;
    if (typeof att.filesizeInBytes === 'number' && att.filesizeInBytes > 0) return att.filesizeInBytes;
    if (typeof att.filesizeRaw === 'number' && att.filesizeRaw > 0) return att.filesizeRaw;
    if (att.filesize && typeof att.filesize === 'object') {
      if (typeof att.filesize.bytes === 'number' && att.filesize.bytes > 0) return att.filesize.bytes;
      if (typeof att.filesize.raw === 'number' && att.filesize.raw > 0) return att.filesize.raw;
    }
    if (typeof att.filesize === 'number' && att.filesize > 0) return att.filesize;
    return 0;
  }

  function formatMegabytes(bytes){
    return (bytes / (1024 * 1024)).toFixed(2).replace(/\.00$/, '');
  }

  function updateMediaFrameUploadText(frame, maxUploadMb){
    if (!frame || !frame.$el) return;
    var text = 'Maximum upload file size: ' + maxUploadMb + ' MB.';
    frame.$el.find('.max-upload-size').text(text);
  }

  function getGlobalUploaderMultipartParams(){
    if (!window.wp || !wp.Uploader || !wp.Uploader.defaults) return null;
    if (!wp.Uploader.defaults.multipart_params) {
      wp.Uploader.defaults.multipart_params = {};
    }
    return wp.Uploader.defaults.multipart_params;
  }

  function getGlobalUploaderFilters(){
    if (!window.wp || !wp.Uploader || !wp.Uploader.defaults) return null;
    if (!wp.Uploader.defaults.filters) {
      wp.Uploader.defaults.filters = {};
    }
    return wp.Uploader.defaults.filters;
  }

  function getMediaViewSettings(){
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.settings) return null;
    return wp.media.view.settings;
  }

  function overrideMediaUtilsValidateFileSize(maxUploadMb){
    if (!window.wp || !wp.mediaUtils || typeof wp.mediaUtils.validateFileSize !== 'function') {
      return null;
    }

    var original = wp.mediaUtils.validateFileSize;
    var overrideBytes = parseInt(maxUploadMb, 10) > 0 ? (parseInt(maxUploadMb, 10) * 1024 * 1024) : 0;

    wp.mediaUtils.validateFileSize = function(file, maxUploadFileSize){
      var effectiveMax = maxUploadFileSize;
      if (overrideBytes > 0 && (!effectiveMax || effectiveMax < overrideBytes)) {
        effectiveMax = overrideBytes;
      }
      try {
        return original.call(this, file, effectiveMax);
      } catch (error) {
        if (error && error.code === 'SIZE_ABOVE_LIMIT') {
          error.message = replaceMaxUploadMessage(error.message, parseInt(maxUploadMb, 10) || 0);
        }
        throw error;
      }
    };

    return original;
  }

  function restoreMediaUtilsValidateFileSize(original){
    if (!original || !window.wp || !wp.mediaUtils) return;
    wp.mediaUtils.validateFileSize = original;
  }

  function replaceMaxUploadMessage(message, maxUploadMb){
    var text = String(message || '');
    var limit = parseInt(maxUploadMb, 10) || 0;
    if (!limit) return text;

    var suffix = ' Maximum allowed size: ' + limit + ' MB.';
    if (text.indexOf('This file exceeds the maximum upload size for this site.') !== -1) {
      return text.replace('This file exceeds the maximum upload size for this site.', 'This file exceeds the maximum upload size for this site.' + suffix);
    }
    return text + suffix;
  }

  function overridePluploadSizeLimitMessage(maxUploadMb){
    if (typeof window.pluploadL10n !== 'object' || !window.pluploadL10n) {
      return null;
    }

    var original = window.pluploadL10n.file_exceeds_size_limit;
    var limit = parseInt(maxUploadMb, 10) || 0;
    if (!limit) {
      return original;
    }

    window.pluploadL10n.file_exceeds_size_limit = '%s exceeds the maximum upload size for this site. Maximum allowed size: ' + limit + ' MB.';
    return original;
  }

  function restorePluploadSizeLimitMessage(original){
    if (typeof window.pluploadL10n !== 'object' || !window.pluploadL10n || original == null) {
      return;
    }
    window.pluploadL10n.file_exceeds_size_limit = original;
  }

  function setMediaFrameUploadLimit(frame, maxUploadMb){
    var value = String(maxUploadMb || '');
    var maxFileSize = value ? (value + 'mb') : '';
    var maxFileBytes = parseInt(value, 10) > 0 ? (parseInt(value, 10) * 1024 * 1024) : 0;
    var globalParams = getGlobalUploaderMultipartParams();
    var globalFilters = getGlobalUploaderFilters();
    var mediaViewSettings = getMediaViewSettings();
    if (globalParams) {
      globalParams.tk_upload_limit_mb = value;
    }
    if (globalFilters) {
      globalFilters.max_file_size = maxFileSize;
    }
    if (mediaViewSettings && maxFileBytes > 0) {
      mediaViewSettings.maxUploadFileSize = maxFileBytes;
    }

    if (!frame || !frame.uploader || !frame.uploader.uploader) return;

    var uploader = frame.uploader.uploader;
    if (typeof uploader.setOption === 'function') {
      var current = (typeof uploader.getOption === 'function')
        ? (uploader.getOption('multipart_params') || {})
        : {};
      uploader.setOption('multipart_params', $.extend({}, current, {
        tk_upload_limit_mb: value
      }));

      var currentFilters = (typeof uploader.getOption === 'function')
        ? (uploader.getOption('filters') || {})
        : {};
      uploader.setOption('filters', $.extend({}, currentFilters, {
        max_file_size: maxFileSize
      }));
      return;
    }

    if (uploader.settings) {
      uploader.settings.multipart_params = $.extend({}, uploader.settings.multipart_params || {}, {
        tk_upload_limit_mb: value
      });
      uploader.settings.filters = $.extend({}, uploader.settings.filters || {}, {
        max_file_size: maxFileSize
      });
    }
  }

  function restoreMediaFrameUploadLimit(frame, previousValue){
    var globalParams = getGlobalUploaderMultipartParams();
    var globalFilters = getGlobalUploaderFilters();
    var mediaViewSettings = getMediaViewSettings();
    if (globalParams) {
      if (previousValue === undefined || previousValue === null || previousValue === '') {
        delete globalParams.tk_upload_limit_mb;
      } else {
        globalParams.tk_upload_limit_mb = previousValue;
      }
    }
    if (globalFilters) {
      if (previousValue === undefined || previousValue === null || previousValue === '') {
        delete globalFilters.max_file_size;
      } else {
        globalFilters.max_file_size = String(previousValue) + 'mb';
      }
    }
    if (mediaViewSettings) {
      if (previousValue === undefined || previousValue === null || previousValue === '') {
        delete mediaViewSettings.maxUploadFileSize;
      } else {
        mediaViewSettings.maxUploadFileSize = parseInt(previousValue, 10) * 1024 * 1024;
      }
    }

    if (!frame || !frame.uploader || !frame.uploader.uploader) return;

    var uploader = frame.uploader.uploader;
    var current = {};

    if (typeof uploader.getOption === 'function') {
      current = uploader.getOption('multipart_params') || {};
    } else if (uploader.settings && uploader.settings.multipart_params) {
      current = uploader.settings.multipart_params;
    }

    current = $.extend({}, current);
    if (previousValue === undefined || previousValue === null || previousValue === '') {
      delete current.tk_upload_limit_mb;
    } else {
      current.tk_upload_limit_mb = previousValue;
    }

    var currentFilters = {};
    if (typeof uploader.getOption === 'function') {
      currentFilters = uploader.getOption('filters') || {};
    } else if (uploader.settings && uploader.settings.filters) {
      currentFilters = uploader.settings.filters;
    }

    currentFilters = $.extend({}, currentFilters);
    if (previousValue !== undefined && previousValue !== null && previousValue !== '') {
      currentFilters.max_file_size = String(previousValue) + 'mb';
    } else {
      delete currentFilters.max_file_size;
    }

    if (typeof uploader.setOption === 'function') {
      uploader.setOption('multipart_params', current);
      uploader.setOption('filters', currentFilters);
      return;
    }

    if (uploader.settings) {
      uploader.settings.multipart_params = current;
      uploader.settings.filters = currentFilters;
    }
  }

  function galleryItemPreviewHtml(att){
    var id = att && att.id ? att.id : '';
    var imgUrl =
      (att && att.sizes && att.sizes.medium && att.sizes.medium.url) ? att.sizes.medium.url :
      (att && att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url :
      (att && att.url) ? att.url : '';

    var $item = $('<div/>', { 'class': 'cff-gallery-item', 'data-id': String(id || '') });
    $('<button/>', {
      type: 'button',
      'class': 'button-link cff-gallery-remove',
      'aria-label': 'Remove image',
      title: 'Remove image'
    }).append($('<span/>', { 'class': 'dashicons dashicons-no-alt', 'aria-hidden': 'true' })).appendTo($item);

    var $preview = $('<div/>', { 'class': 'cff-gallery-item-preview' }).appendTo($item);
    if (imgUrl) {
      $('<img/>', { src: imgUrl, alt: '' }).appendTo($preview);
    } else {
      $('<span/>', { 'class': 'cff-muted', text: id ? ('Attachment ID: ' + id) : 'No image selected' }).appendTo($preview);
    }
    return $item;
  }

  function initGallerySortable($wrap){
    var $items = $wrap.find('.cff-gallery-items').first();
    if (!$.fn.sortable || !$items.length) return;
    if ($items.data('ui-sortable')) {
      try { $items.sortable('destroy'); } catch(e){}
    }
    $items.sortable({
      items: '> .cff-gallery-item',
      update: function(){
        $wrap.closest('form').trigger('change');
      }
    });
  }

  function renderGalleryItems($wrap, attachments){
    var $items = $wrap.find('.cff-gallery-items').first();
    var name = $wrap.data('name') || '';
    if (!$items.length || !name) return;

    $items.empty();
    (attachments || []).forEach(function(att){
      if (!att || !att.id) return;
      var $item = galleryItemPreviewHtml(att);
      $('<input/>', {
        type: 'hidden',
        name: name + '[]',
        value: att.id
      }).prependTo($item);
      $items.append($item);
    });

    initGallerySortable($wrap);
    $wrap.closest('form').trigger('change');
  }

  function collectGalleryAttachments($wrap){
    var items = [];
    $wrap.find('.cff-gallery-item').each(function(){
      var $item = $(this);
      var id = parseInt($item.attr('data-id') || '0', 10);
      if (!id) return;
      var imgUrl = $item.find('img').attr('src') || '';
      items.push({ id: id, url: imgUrl });
    });
    return items;
  }

  $(document).on('click', '.cff-media-select', function(e){
    e.preventDefault();

    var $wrap = $(this).closest('.cff-media');
    if (!$wrap.length) return;

    var type = $wrap.data('type') || 'file';
    if (!window.wp || !wp.media) return;

    var maxUploadMb = getMediaMaxUploadMb($wrap);
    var globalParams = getGlobalUploaderMultipartParams();
    var previousUploadLimit = globalParams && Object.prototype.hasOwnProperty.call(globalParams, 'tk_upload_limit_mb')
      ? globalParams.tk_upload_limit_mb
      : '';
    var globalFilters = getGlobalUploaderFilters();
    var previousGlobalMaxFileSize = globalFilters && Object.prototype.hasOwnProperty.call(globalFilters, 'max_file_size')
      ? globalFilters.max_file_size
      : '';
    var mediaViewSettings = getMediaViewSettings();
    var previousMediaViewMaxUploadSize = mediaViewSettings && Object.prototype.hasOwnProperty.call(mediaViewSettings, 'maxUploadFileSize')
      ? mediaViewSettings.maxUploadFileSize
      : '';
    var originalValidateFileSize = overrideMediaUtilsValidateFileSize(maxUploadMb);
    var originalPluploadSizeLimitMessage = overridePluploadSizeLimitMessage(maxUploadMb);

    // Set uploader defaults before creating the media frame, because the frame
    // can snapshot plupload settings during construction.
    setMediaFrameUploadLimit(null, maxUploadMb);

    var frame = wp.media({
      title: (type === 'image') ? 'Select media' : 'Select file',
      button: { text: 'Use this' },
      multiple: false,
      library: (type === 'image') ? { type: ['image', 'video'] } : {}
    });

    frame.on('open', function(){
      setMediaFrameUploadLimit(frame, maxUploadMb);
      updateMediaFrameUploadText(frame, maxUploadMb);
    });

    frame.on('content:render:browse', function(){
      setMediaFrameUploadLimit(frame, maxUploadMb);
      updateMediaFrameUploadText(frame, maxUploadMb);
    });

    frame.on('content:activate', function(){
      setMediaFrameUploadLimit(frame, maxUploadMb);
      updateMediaFrameUploadText(frame, maxUploadMb);
    });

    frame.on('close', function(){
      restoreMediaFrameUploadLimit(frame, previousUploadLimit);
      if (globalFilters) {
        if (previousGlobalMaxFileSize === undefined || previousGlobalMaxFileSize === null || previousGlobalMaxFileSize === '') {
          delete globalFilters.max_file_size;
        } else {
          globalFilters.max_file_size = previousGlobalMaxFileSize;
        }
      }
      if (mediaViewSettings) {
        if (previousMediaViewMaxUploadSize === undefined || previousMediaViewMaxUploadSize === null || previousMediaViewMaxUploadSize === '') {
          delete mediaViewSettings.maxUploadFileSize;
        } else {
          mediaViewSettings.maxUploadFileSize = previousMediaViewMaxUploadSize;
        }
      }
      restoreMediaUtilsValidateFileSize(originalValidateFileSize);
      restorePluploadSizeLimitMessage(originalPluploadSizeLimitMessage);
    });

    frame.on('select', function(){
      var sel = frame.state().get('selection');
      var model = sel && sel.first && sel.first();
      if (!model) return;

      var att = model.toJSON();
      if (!att || !att.id) return;

      var fileSizeBytes = getAttachmentFilesizeBytes(att);
      if (fileSizeBytes > (maxUploadMb * 1024 * 1024)) {
        setMediaNotice($wrap, 'Maximum upload size for this field is ' + maxUploadMb + ' MB. Selected file size is ' + formatMegabytes(fileSizeBytes) + ' MB.');
        return;
      }

      clearMediaNotice($wrap);
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
    var $wrap = $(this).closest('.cff-media');
    clearMediaNotice($wrap);
    renderMedia($wrap, '');
  });

  $(document).on('click', '.cff-gallery-select', function(e){
    e.preventDefault();

    var $wrap = $(this).closest('.cff-gallery');
    if (!$wrap.length || !window.wp || !wp.media) return;

    var existingIds = collectGalleryAttachments($wrap).map(function(item){ return item.id; });
    var frame = wp.media({
      title: 'Select gallery images',
      button: { text: 'Use images' },
      multiple: true,
      library: { type: ['image'] }
    });

    frame.on('open', function(){
      var selection = frame.state().get('selection');
      existingIds.forEach(function(id){
        var attachment = wp.media.attachment(id);
        if (attachment) {
          attachment.fetch();
          selection.add(attachment);
        }
      });
    });

    frame.on('select', function(){
      var attachments = [];
      frame.state().get('selection').each(function(model){
        var att = model.toJSON();
        if (att && att.id) attachments.push(att);
      });
      renderGalleryItems($wrap, attachments);
    });

    frame.open();
  });

  $(document).on('click', '.cff-gallery-clear', function(e){
    e.preventDefault();
    renderGalleryItems($(this).closest('.cff-gallery'), []);
  });

  $(document).on('click', '.cff-gallery-remove', function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.cff-gallery');
    $(this).closest('.cff-gallery-item').remove();
    if ($wrap.length) {
      $wrap.closest('form').trigger('change');
    }
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
       var $internal   = $wrap.find('.cff-link-internal');
       var $custom     = $wrap.find('.cff-link-custom');
       var $select     = $wrap.find('.cff-link-select');
       var $internalId = $wrap.find('.cff-link-internal-id');
       var $url        = $wrap.find('input[name$="[url]"]');

       // 🔒 TITLE INPUTS — berdiri sendiri
       var $internalTitle = $wrap.find('.cff-title-internal');
       var $customTitle   = $wrap.find('.cff-title-custom');

       function applyMode(mode){
         var isInternal = (mode === 'internal');

         $wrap.toggleClass('is-mode-internal', isInternal);
         $wrap.toggleClass('is-mode-custom', !isInternal);

         $internal.toggle(isInternal);
         $custom.toggle(!isInternal);

         // disable title yang tidak aktif
         if ($internalTitle.length) $internalTitle.prop('disabled', !isInternal);
         if ($customTitle.length)   $customTitle.prop('disabled', isInternal);

         // custom mode: kosongkan internal_id & select
         if (!isInternal) {
           $internalId.val('');
           if ($select.data('select2')) {
             $select.val(null).trigger('change');
           }
         }
       }

       // init mode
       var mode = $modeInputs.filter(':checked').val() || $wrap.data('mode') || 'custom';
       $modeInputs.filter('[value="' + mode + '"]').prop('checked', true);
       applyMode(mode);

       // init select2 (TANPA title logic)
       if ($select.length && $.fn.select2 && !$select.data('select2')) {
         $select.select2({
           width: '100%',
           placeholder: $select.data('placeholder') || 'Search…',
           allowClear: true,
           ajax: {
             delay: 250,
             transport: function(params, success, failure){
               var term = params.data && params.data.term ? params.data.term : '';
               var postType = $select.data('post-type') || 'any';

               $.post(CFFP.ajax, {
                 action: 'cff_search_posts',
                 nonce: CFFP.nonce,
                 q: term,
                 post_type: postType
               }, function(res){
                 success(res && res.success ? { results: res.data } : { results: [] });
               }, 'json').fail(failure);
             },
             processResults: function(data){ return data; }
           },
           templateResult: function(item){ return item.text || ''; },
           templateSelection: function(item){ return item.text || ''; },
           escapeMarkup: function(m){ return m; }
         });
       }

       // select2 events — TANPA TITLE
       $select.off('.cffLink');
       $select.on('select2:select.cffLink', function(e){
         var data = e.params && e.params.data ? e.params.data : {};
         $internalId.val(data.id || '');
         if (data.url) $url.val(data.url);

         // auto pindah ke internal mode
         $modeInputs.filter('[value="internal"]').prop('checked', true);
         applyMode('internal');
       });

       $select.on('select2:clear.cffLink', function(){
         $internalId.val('');
       });

       // ganti mode manual
       $modeInputs.off('.cffLink');
       $modeInputs.on('change.cffLink', function(){
         var m = $modeInputs.filter(':checked').val() || 'custom';
         applyMode(m);
       });
     });
   }

  /* -------------------------
   * Field accordion
   * ------------------------- */
  function togglePostbox($field){
    var isClosed = !$field.hasClass('closed');
    $field.toggleClass('closed', isClosed);
    $field.find('> .postbox-header .cff-acc-toggle').attr('aria-expanded', !isClosed);
    $field.children('.inside').toggle(!isClosed);
  }

  $(document).on('click', '.cff-field.cff-postbox > .postbox-header .cff-acc-toggle', function(e){
    e.preventDefault();
    togglePostbox($(this).closest('.cff-field.cff-postbox'));
  });

  $(document).on('click', '.cff-field.cff-postbox > .postbox-header', function(e){
    var $target = $(e.target);
    if ($target.closest('button, a, input, select, textarea, label').length) return;
    togglePostbox($(this).closest('.cff-field.cff-postbox'));
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
    if (!parent) {
      var presentName = $rep.find('> .cff-rep-present').attr('name') || '';
      var mParent = presentName.match(/\[([^\[\]]+)\]\[__cff_present\]$/);
      if (mParent && mParent[1]) parent = String(mParent[1]);
    }
    if (!parent) return;

    var $rowsWrap = $rep.children('.cff-rep-rows').first();
    $rowsWrap.children('.cff-rep-row').each(function(newIndex){
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

    updateRepeaterRowTitles($rep);
    $rep.closest('form').trigger('change');
  }

  function getRepeaterMinMax($rep){
    var min = parseInt($rep.data('min'), 10);
    var max = parseInt($rep.data('max'), 10);
    if (isNaN(min) || min < 0) min = 0;
    if (isNaN(max) || max < 0) max = 0;
    if (max > 0 && max < min) max = min;
    return { min: min, max: max };
  }

  function getRepeaterRowLabelValue($row, rowLabelField){
    if (!rowLabelField) return '';
    var escaped = escapeReg(rowLabelField);
    var $candidate = $row.find(
      ':input[name$="[' + rowLabelField + ']"], :input[name$="[' + rowLabelField + '][]"]'
    ).not('[type=hidden]').first();
    if (!$candidate.length) {
      $candidate = $row.find(':input[name]').filter(function(){
        var name = $(this).attr('name') || '';
        return new RegExp('\\[' + escaped + '\\](?:\\[\\])?$').test(name);
      }).not('[type=hidden]').first();
    }
    if (!$candidate.length) return '';

    if ($candidate.is(':checkbox')) {
      if ($candidate.attr('name') && /\[\]$/.test($candidate.attr('name'))) {
        var out = [];
        $row.find(':checkbox[name="' + $candidate.attr('name') + '"]:checked').each(function(){
          out.push($(this).val());
        });
        return out.join(', ');
      }
      return $candidate.is(':checked') ? ($candidate.val() || '1') : '';
    }
    if ($candidate.is(':radio')) {
      var radioName = $candidate.attr('name') || '';
      return $row.find(':radio[name="' + radioName + '"]:checked').val() || '';
    }
    var value = $candidate.val();
    if (Array.isArray(value)) {
      value = value.join(', ');
    }
    return $.trim(String(value || ''));
  }

  function updateRepeaterRowTitles($rep){
    var rowLabelField = String($rep.data('row-label') || '');
    $rep.children('.cff-rep-rows').first().children('.cff-rep-row').each(function(index){
      var $row = $(this);
      var title = 'Row ' + (index + 1);
      var labelValue = getRepeaterRowLabelValue($row, rowLabelField);
      if (labelValue) {
        title += ': ' + labelValue;
      }
      $row.find('> .cff-rep-row-head .cff-rep-row-title').text(title);
    });
  }

  function createRepeaterRowFromTemplate($rep){
    var tpl = $rep.children('.cff-rep-template').first().html();
    if (!tpl) return $();

    var $rows = $rep.children('.cff-rep-rows').first();
    var idx = $rows.children('.cff-rep-row').length;
    tpl = cffReplaceAll(tpl, '__INDEX__', String(idx));
    tpl = cffReplaceAll(tpl, '__ROWID__', generateRowId());
    var $new = $(tpl);

    var collapseDefault = String($rep.data('collapsed-default') || '') === '1';
    var layout = String($rep.data('layout') || '');
    if (collapseDefault && layout !== 'simple' && layout !== 'gallery') {
      $new.addClass('is-collapsed');
    }

    $rows.append($new);
    initSortable($rows);
    $new.find('.cff-rep-rows').each(function(){
      initSortable($(this));
    });
    $new.find('.cff-repeater').each(function(){
      var $nestedRep = $(this);
      ensureRepeaterMinRows($nestedRep);
      updateRepeaterRowTitles($nestedRep);
      updateRepeaterControls($nestedRep);
    });
    $new.find('.cff-gallery').each(function(){
      initGallerySortable($(this));
    });
    window.cffInitWysiwyg($new);
    initLinkPickers($new);
    updateRepeaterRowTitles($rep);
    return $new;
  }

  function ensureRepeaterRowIds($scope){
    ($scope && $scope.length ? $scope : $(document)).find('.cff-rep-row').each(function(){
      var $row = $(this);
      var rowId = String($row.attr('data-row-id') || '');
      if (!rowId) rowId = generateRowId();
      $row.attr('data-row-id', rowId);

      var $input = $row.find('> .cff-rep-row-body > .cff-row-id, > .cff-row-id').first();
      if (!$input.length) {
        return;
      }
      $input.val(rowId);
    });
  }

  function ensureFlexibleRowIds($scope){
    ($scope && $scope.length ? $scope : $(document)).find('.cff-flex-row').each(function(){
      var $row = $(this);
      var rowId = String($row.attr('data-row-id') || '');
      if (!rowId) rowId = generateRowId();
      $row.attr('data-row-id', rowId);

      var $input = $row.find('> .cff-row-id').first();
      if ($input.length) {
        $input.val(rowId);
      }
    });
  }

  function initFlexibleSortable($rows){
    if (!$.fn.sortable || !$rows.length) return;

    if ($rows.data('ui-sortable')) {
      try { $rows.sortable('destroy'); } catch(e){}
    }

    $rows.sortable({
      handle: '.cff-flex-head',
      items: '> .cff-flex-row',
      update: function(){
        var $flex = $rows.closest('.cff-flexible');
        reindexFlexible($flex);
      }
    });
  }

  function reindexFlexible($flex){
    var parent = String($flex.data('field') || '');
    if (!parent) return;

    var $rowsWrap = $flex.children('.cff-flex-rows').first();
    $rowsWrap.children('.cff-flex-row').each(function(newIndex){
      var $row = $(this);
      var oldIndex = $row.attr('data-i');
      if (oldIndex == null || oldIndex === '') {
        var n = $row.find('[name^="cff_values[' + parent + ']["]').first().attr('name') || '';
        var m = n.match(new RegExp('cff_values\\[' + escapeReg(parent) + '\\]\\[(\\d+)\\]'));
        oldIndex = m ? m[1] : '';
      }

      window.cffRemoveWysiwyg($row);
      $row.attr('data-i', newIndex);

      if (oldIndex !== '') {
        var pat = new RegExp('\\[' + escapeReg(parent) + '\\]\\[' + escapeReg(oldIndex) + '\\]', 'g');
        $row.find('[name]').each(function(){
          var name = $(this).attr('name');
          if (!name) return;
          $(this).attr('name', name.replace(pat, '[' + parent + '][' + newIndex + ']'));
        });
      }

      $row.find('textarea.cff-wysiwyg[id]').each(function(){
        this.id = this.id.replace(/(_)(\d+)$/, '$1' + newIndex);
      });

      $row.find('.cff-wysiwyg-settings[data-editor-id]').each(function(){
        var cur = $(this).attr('data-editor-id');
        if (!cur) return;
        $(this).attr('data-editor-id', cur.replace(/(_)(\d+)$/, '$1' + newIndex));
      });

      window.cffInitWysiwyg($row);
    });

    $flex.closest('form').trigger('change');
  }

  function createFlexibleRowFromTemplate($flex, layout){
    layout = String(layout || '');
    if (!layout) return $();

    var $tpl = $flex.find('.cff-flex-template[data-layout="' + layout + '"]').first();
    var tpl = $tpl.length ? $tpl.html() : '';
    if (!tpl) return $();

    var $rows = $flex.children('.cff-flex-rows').first();
    var idx = $rows.children('.cff-flex-row').length;
    tpl = cffReplaceAll(tpl, '__INDEX__', String(idx));
    tpl = cffReplaceAll(tpl, '__ROWID__', generateRowId());
    var $new = $(tpl);

    $rows.append($new);
    ensureFlexibleRowIds($new);
    initFlexibleSortable($rows);
    $new.find('.cff-gallery').each(function(){
      initGallerySortable($(this));
    });
    window.cffInitWysiwyg($new);
    initLinkPickers($new);
    return $new;
  }

  function copyRepeaterRowValues($sourceRow, $targetRow){
    if (!$sourceRow.length || !$targetRow.length) return;

    if (window.tinymce) {
      try { tinymce.triggerSave(); } catch(e){}
    }

    var $srcInputs = $sourceRow.find(':input[name]').not('.select2-search__field');
    var $dstInputs = $targetRow.find(':input[name]').not('.select2-search__field');
    var limit = Math.min($srcInputs.length, $dstInputs.length);

    for (var i = 0; i < limit; i++) {
      var $src = $($srcInputs[i]);
      var $dst = $($dstInputs[i]);
      var tag = ($dst.prop('tagName') || '').toLowerCase();
      var type = ($dst.attr('type') || '').toLowerCase();

      if (tag === 'select') {
        $dst.val($src.val());
        $dst.trigger('change');
        continue;
      }
      if (type === 'checkbox' || type === 'radio') {
        $dst.prop('checked', $src.is(':checked'));
        $dst.trigger('change');
        continue;
      }
      $dst.val($src.val());
    }
  }

  function ensureRepeaterMinRows($rep){
    var limits = getRepeaterMinMax($rep);
    var $rows = $rep.children('.cff-rep-rows').first();
    var guard = 0;
    while ($rows.children('.cff-rep-row').length < limits.min && guard < 100) {
      createRepeaterRowFromTemplate($rep);
      guard++;
    }
  }


  function updateRepeaterControls($rep){
    var $rowsWrap = $rep.children('.cff-rep-rows').first();
    var count = $rowsWrap.children('.cff-rep-row').length;
    var limits = getRepeaterMinMax($rep);
    var min = limits.min;
    var max = limits.max;
    var canRemove = count > min;

    // --- ADD: disable kalau sudah max (kecuali max=0 unlimited)
    var canAdd = (!max || count < max);

    $rowsWrap.children('.cff-rep-row').find('> .cff-rep-row-head .cff-rep-remove').each(function(){
      $(this)
        .toggleClass('is-disabled', !canRemove)
        .attr('aria-disabled', canRemove ? null : 'true');
      try { $(this).prop('disabled', !canRemove); } catch(e){}
    });
    $rowsWrap.children('.cff-rep-row').find('> .cff-rep-row-head .cff-rep-clone').each(function(){
      $(this)
        .toggleClass('is-disabled', !canAdd)
        .attr('aria-disabled', canAdd ? null : 'true');
      try { $(this).prop('disabled', !canAdd); } catch(e){}
    });

    $rep.children('p').find('> .cff-rep-add').each(function(){
      $(this)
        .toggleClass('is-disabled', !canAdd)
        .attr('aria-disabled', canAdd ? null : 'true');
      try { $(this).prop('disabled', !canAdd); } catch(e){}
    });

    // optional: tooltip kecil biar jelas
    if (!canAdd && max) {
      $rep.children('p').find('> .cff-rep-add').attr('title', 'Max rows: ' + max);
    } else {
      $rep.children('p').find('> .cff-rep-add').removeAttr('title');
    }
  }


  /* -------------------------
   * INIT existing
   * ------------------------- */
  $('.cff-rep-rows').each(function(){
    initSortable($(this));
  });
  ensureRepeaterRowIds($(document));
  $('.cff-flex-rows').each(function(){
    initFlexibleSortable($(this));
  });
  $('.cff-gallery').each(function(){
    initGallerySortable($(this));
  });
  ensureFlexibleRowIds($(document));
  window.cffInitWysiwyg($(document)); // sekali saja
  initLinkPickers($(document));

  $('.cff-repeater').each(function(){
    var $rep = $(this);
    ensureRepeaterMinRows($rep);
    updateRepeaterRowTitles($rep);
    updateRepeaterControls($rep);
  });


  /* -------------------------
   * ADD / REMOVE (single handler each)
   * ------------------------- */
  $(document)
    .off('click.cffRepAdd', '.cff-rep-add')
    .on('click.cffRepAdd', '.cff-rep-add', function(e){
      e.preventDefault();

      var $rep = $(this).closest('.cff-repeater');
      var limits = getRepeaterMinMax($rep);
      var count = $rep.children('.cff-rep-rows').first().children('.cff-rep-row').length;
      if (limits.max > 0 && count >= limits.max) return;

      createRepeaterRowFromTemplate($rep);

      updateRepeaterControls($rep);

      $rep.closest('form').trigger('change');
    });

  $(document)
    .off('click.cffRepRemove', '.cff-rep-remove')
    .on('click.cffRepRemove', '.cff-rep-remove', function(e){
      e.preventDefault();

      var $rep = $(this).closest('.cff-repeater');
      var $row = $(this).closest('.cff-rep-row');
      var limits = getRepeaterMinMax($rep);
      var count = $rep.children('.cff-rep-rows').first().children('.cff-rep-row').length;
      if (count <= limits.min) return;

      window.cffRemoveWysiwyg($row);
      $row.remove();

      reindexRepeater($rep);

      updateRepeaterControls($rep);

      $rep.closest('form').trigger('change');
    });

  $(document)
    .off('click.cffRepClone', '.cff-rep-clone')
    .on('click.cffRepClone', '.cff-rep-clone', function(e){
      e.preventDefault();
      var $rep = $(this).closest('.cff-repeater');
      var $source = $(this).closest('.cff-rep-row');
      var limits = getRepeaterMinMax($rep);
      var count = $rep.children('.cff-rep-rows').first().children('.cff-rep-row').length;
      if (limits.max > 0 && count >= limits.max) return;

      var $new = createRepeaterRowFromTemplate($rep);
      if ($new.length) {
        copyRepeaterRowValues($source, $new);
        var newRowId = generateRowId();
        $new.attr('data-row-id', newRowId);
        $new.find('> .cff-rep-row-body > .cff-row-id, > .cff-row-id').first().val(newRowId);
      }
      updateRepeaterRowTitles($rep);
      updateRepeaterControls($rep);
      $rep.closest('form').trigger('change');
    });

  $(document).on('input.cffRepLabel change.cffRepLabel', '.cff-repeater .cff-rep-row :input', function(){
    var $rep = $(this).closest('.cff-repeater');
    updateRepeaterRowTitles($rep);
  });

  $(document)
    .off('click.cffFlexAdd', '.cff-flex-add-btn')
    .on('click.cffFlexAdd', '.cff-flex-add-btn', function(e){
      e.preventDefault();
      var $flex = $(this).closest('.cff-flexible');
      var layout = $flex.find('.cff-flex-layout').first().val() || '';
      if (!layout) return;
      createFlexibleRowFromTemplate($flex, layout);
      $flex.closest('form').trigger('change');
    });

  $(document)
    .off('click.cffFlexRemove', '.cff-flex-remove')
    .on('click.cffFlexRemove', '.cff-flex-remove', function(e){
      e.preventDefault();
      var $flex = $(this).closest('.cff-flexible');
      var $row = $(this).closest('.cff-flex-row');
      window.cffRemoveWysiwyg($row);
      $row.remove();
      reindexFlexible($flex);
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
