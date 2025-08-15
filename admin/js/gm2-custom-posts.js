(function($){
    function setupConditional(){
        $('.gm2-field[data-conditional-field]').each(function(){
            var $wrap = $(this);
            var target = $wrap.data('conditional-field');
            var expected = $wrap.data('conditional-value');
            var $controller = $('[name="'+target+'"]');
            function check(){
                var val;
                if ($controller.attr('type') === 'checkbox') {
                    val = $controller.is(':checked') ? '1' : '0';
                } else {
                    val = $controller.val();
                }
                if (String(val) === String(expected)) {
                    $wrap.show();
                } else {
                    $wrap.hide();
                }
            }
            $controller.on('change', check);
            check();
        });
    }
    $(document).ready(setupConditional);
})(jQuery);
