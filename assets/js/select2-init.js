jQuery(document).ready(function ($) {
  function initSelect2(ctx){
    $(ctx).find('select.cff-select2').each(function(){
      var $el = $(this);
      if ($el.data('select2')) return;
      var placeholder = $el.data('placeholder') || 'Select…';
      $el.select2({
        width: '100%',
        placeholder: placeholder,
        allowClear: true
      });
    });
  }

  initSelect2(document);

  // If plugin dynamically adds content, it can trigger this:
  $(document).on('cff:refresh', function(e, ctx){
    initSelect2(ctx || document);
  });
});
