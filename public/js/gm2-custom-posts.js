(function($){
    // Basic frontend helpers for custom post fields
    $(document).on('input', '.gm2-relationship', function(){
        var val = $(this).val();
        $(this).val(val.replace(/[^0-9,]/g,''));
    });
})(jQuery);
