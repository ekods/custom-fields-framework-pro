(function ($) {
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
        if (!relational || instance.options.get('ajax')) return;
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
            return response.data;
          }
        };
      }

      $el.select2(options);
    });
  }

  window.cffInitSelect2 = initSelect2;
  $(function(){ initSelect2(document); });

  // If plugin dynamically adds content, it can trigger this:
  $(document).on('cff:refresh', function(e, ctx){
    initSelect2(ctx || document);
  });
})(jQuery);
