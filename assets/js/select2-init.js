jQuery(document).ready(function ($) {
  function initSelect2(ctx){
    $(ctx).find('select.cff-select2').each(function(){
      var $el = $(this);
      if ($el.data('select2')) return;
      var placeholder = $el.data('placeholder') || 'Select…';
      var options = {
        width: '100%',
        placeholder: placeholder,
        allowClear: true
      };

      if ($el.hasClass('cff-relational-ajax') && window.CFFP) {
        options.minimumInputLength = 0;
        options.ajax = {
          url: CFFP.ajax,
          type: 'POST',
          dataType: 'json',
          delay: 250,
          data: function(params){
            return {
              action: 'cff_search_relational',
              nonce: CFFP.nonce,
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

  initSelect2(document);

  // If plugin dynamically adds content, it can trigger this:
  $(document).on('cff:refresh', function(e, ctx){
    initSelect2(ctx || document);
  });
});
