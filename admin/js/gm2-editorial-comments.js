jQuery(function ($) {
  var cfg = window.GM2EditorialComments || {};
  $('[data-gm2-comment]').each(function () {
    var field = $(this);
    var ctx = field.data('gm2-comment');
    var btn = $('<button type="button" class="gm2-editorial-comment-button" aria-label="Add comment">💬</button>');
    btn.insertAfter(field);
    btn.on('click', function () {
      var content = window.prompt('Add comment');
      if (!content) {
        return;
      }
      $.post(cfg.ajaxurl, {
        action: 'gm2_add_editorial_comment',
        post_id: $('#post_ID').val(),
        context: ctx,
        message: content,
        _ajax_nonce: cfg.nonce
      });
    });
  });
});
