jQuery(function($){
    $(document).on('click','.gm2-qd-option',function(e){
        e.preventDefault();
        var $option = $(this);
        $option.addClass('active').siblings('.gm2-qd-option').removeClass('active');
        var qty = $option.data('qty');
        var $form = $('form.cart');
        var $input = $form.find('input.qty');
        if($input.length){
            $input.val(qty).trigger('change');
        }
        if($form.length){
            var $btn = $form.find('[name=add-to-cart]');
            if($btn.length){
                $btn.addClass('loading');
                $option.addClass('loading');
                var $spinner = $('<span class="loading-spinner"></span>');
                $option.append($spinner);
                $form.one('ajaxComplete', function(){
                    $btn.removeClass('loading');
                    $option.removeClass('loading');
                    $spinner.remove();
                });
                $btn.trigger('click');
            }
        }
    });
});
