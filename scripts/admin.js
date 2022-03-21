(function($) {

  $('input.js-show-hide-target').on('change', function() {
    var source = $(this);
    var target = $('#' + source.attr('data-target'));
    if ($('input[data-target='+source.attr('data-target')+']:checked').length) {
      target.show();
    } else {
      target.hide();
    }
  });

})( jQuery );
