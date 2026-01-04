jQuery(function($){
  function setPreview($wrap, id, url){
    $wrap.find('.cff-term-image-id').val(id || '');
    var $preview = $wrap.find('.cff-term-image-preview');
    $preview.empty();
    if (url) {
      $('<img>', { src: url, class: 'cff-term-thumb' }).appendTo($preview);
    } else {
      $preview.append($('<span/>', { class: 'description', text: 'No image selected' }));
    }
  }

  $(document).on('click', '.cff-term-image-select', function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.cff-term-image');
    if (!window.wp || !wp.media) return;

    var frame = wp.media({
      title: 'Select image',
      button: { text: 'Use this' },
      multiple: false,
      library: { type: 'image' }
    });

    frame.on('select', function(){
      var sel = frame.state().get('selection');
      var model = sel && sel.first && sel.first();
      if (!model) return;
      var att = model.toJSON();
      if (!att || !att.id) return;
      setPreview($wrap, att.id, att.url || '');
    });

    frame.open();
  });

  $(document).on('click', '.cff-term-image-clear', function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.cff-term-image');
    setPreview($wrap, '', '');
  });
});
