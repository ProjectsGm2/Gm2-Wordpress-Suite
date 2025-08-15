jQuery(function($){
    if (typeof inlineEditPost !== 'undefined') {
        var wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id){
            wp_inline_edit.apply(this, arguments);
            var postId = 0;
            if (typeof(id) === 'object') {
                postId = parseInt(this.getId(id));
            }
            if (postId > 0 && window.gm2ListTable) {
                var $row = $('#post-' + postId);
                window.gm2ListTable.fields.forEach(function(f){
                    var val = $row.find('.column-' + f.slug).text().trim();
                    $('#edit-' + postId).find('input[name="' + f.slug + '"]').val(val);
                });
            }
        };
    }
});

