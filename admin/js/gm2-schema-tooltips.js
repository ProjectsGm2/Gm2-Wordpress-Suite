jQuery(function($){
    $(document).on('mouseenter', '[data-schema]', function(){
        var $el = $(this);
        if(!$el.attr('title')) {
            $el.attr('title', $el.data('schema'));
        }
    });
});
