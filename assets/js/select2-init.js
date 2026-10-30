(function ($) {
  function protectRelationalLabels($el, instance){
    if (!instance || instance.cffRelationalLabelGuard) return;
    var labels = Object.create(null);
    var decoded = document.createElement('textarea');
    function decodeLabel(text){
      decoded.innerHTML = String(text == null ? '' : text);
      return decoded.value;
    }
    $el.find('option').each(function(){ if (this.value !== '') labels[String(this.value)] = decodeLabel(this.text); });
    function renderLabel(item){
      var key = item.id == null ? null : String(item.id);
      var text = key !== null && Object.prototype.hasOwnProperty.call(labels, key) ? labels[key] : decodeLabel(item.text);
      return $('<span/>').text(text);
    }
    function normalizeResults(items){
      return (items || []).map(function(item){
        var result = $.extend({}, item, { text: decodeLabel(item.text) });
        if (item.id != null) labels[String(item.id)] = result.text;
        if (item.children) result.children = normalizeResults(item.children);
        return result;
      });
    }
    var adapter = instance.dataAdapter;
    var originalProcess = adapter.processResults;
    if (typeof originalProcess === 'function') {
      var processResults = function(response, params){
        var data = originalProcess.call(this, response, params) || {};
        return $.extend({}, data, { results: normalizeResults(data.results) });
      };
      adapter.processResults = processResults;
      // Preserve the integration's provider, request parameters, pagination and transport.
      var ajax = instance.options.get('ajax');
      if (ajax) instance.options.set('ajax', $.extend({}, ajax, { processResults: processResults }));
    }
    instance.options.set('templateResult', renderLabel);
    instance.options.set('templateSelection', renderLabel);
    instance.options.set('cffRelationalLabels', true);
    instance.cffRelationalLabelGuard = true;
    adapter.current(function(data){ instance.selection.update(data); });
  }

  // Themes can destroy/recreate Select2 after CFF initializes it. Decorate the current
  // instance before it queries or selects, without replacing the theme's AJAX provider.
  $(document).on('select2:opening.cffRelationalLabels select2:selecting.cffRelationalLabels', 'select.cff-relational-ajax', function(){
    protectRelationalLabels($(this), $(this).data('select2'));
  });

  function initSelect2(ctx){
    if (!$.fn.select2) return;
    $(ctx || document).find('select.cff-select2').addBack('select.cff-select2').each(function(){
      var $el = $(this);
      var relational = $el.hasClass('cff-relational-ajax');
      var config = window.CFFP;
      // Wait for the AJAX configuration rather than initializing an empty static select.
      if (relational && (!config || !config.ajax || !config.nonce)) return;
      var instance = $el.data('select2');
      if (instance && instance.$element && instance.$element[0] === this) {
        if (!relational) return;
        if (instance.options.get('ajax')) {
          protectRelationalLabels($el, instance);
          return;
        }
        $el.select2('destroy');
      }
      // HTML/data cloned from an initialized row must not reuse its Select2 cache.
      if ($el.hasClass('select2-hidden-accessible')) {
        $el.removeAttr('tabindex aria-hidden');
        $el.off('.select2');
      }
      if (this.id && document.getElementById(this.id) !== this) $el.removeAttr('id');
      $el.removeData('select2').removeAttr('data-select2-id').removeClass('select2-hidden-accessible');
      $el.find('option, optgroup').removeAttr('data-select2-id');
      $el.next('.select2-container').remove();
      var placeholder = $el.data('placeholder') || 'Select…';
      var options = {
        width: '100%',
        placeholder: placeholder,
        allowClear: true
      };

      if (relational) {
        options.minimumInputLength = 0;
        options.ajax = {
          url: config.ajax,
          type: 'POST',
          dataType: 'json',
          delay: 250,
          data: function(params){
            return {
              action: 'cff_search_relational',
              nonce: window.CFFP.nonce,
              relational_type: $el.data('relational-type') || 'post',
              relational_subtype: $el.data('relational-subtype') || '',
              q: params.term || '',
              page: params.page || 1
            };
          },
          processResults: function(response){
            if (!response || !response.success) return { results: [] };
            return response.data || { results: [] };
          }
        };
      }

      $el.select2(options);
      if (relational) protectRelationalLabels($el, $el.data('select2'));
    });
  }

  window.cffInitSelect2 = initSelect2;
  $(function(){ initSelect2(document); });

  // If plugin dynamically adds content, it can trigger this:
  $(document).on('cff:refresh', function(e, ctx){
    initSelect2(ctx || document);
  });
})(jQuery);
