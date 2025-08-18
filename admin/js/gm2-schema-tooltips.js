jQuery(function($){
    function setTitle(selector, text) {
        $(document).on('mouseenter', selector, function(){
            var $el = $(this);
            if(!$el.attr('title')) {
                var msg = typeof text === 'function' ? text.call(this) : text;
                $el.attr('title', msg);
            }
        });
    }

    setTitle('[data-schema]', function(){ return $(this).data('schema'); });

    if (typeof gm2CPTHelp === 'object') {
        $.each(gm2CPTHelp, function(sel, msg){
            setTitle(sel, msg);
        });
    }
});
