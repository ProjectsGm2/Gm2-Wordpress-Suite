jQuery(function($){
    $(document).on('click', '.gm2-open-in-code__trigger', function(){
        $(this).siblings('.gm2-open-in-code__modal').toggle();
    });
    $(document).on('click', '.gm2-open-in-code__copy', function(){
        var target = $(this).data('target');
        var text = $(this).closest('.gm2-open-in-code__modal').find('.gm2-open-in-code__' + target).val();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        }
    });
});
