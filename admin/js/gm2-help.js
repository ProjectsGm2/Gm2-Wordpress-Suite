jQuery(function($){
    if (typeof gm2CPTHelp === 'object') {
        $.each(gm2CPTHelp, function(selector, text){
            $(document).on('mouseenter', selector, function(){
                var $el = $(this);
                if (!$el.attr('title')) {
                    $el.attr('title', text);
                }
            });
        });
    }
});
