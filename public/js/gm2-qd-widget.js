jQuery(function($){
    const dom = window.aePerf?.dom;
    const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
    const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

    mutate(() => {
        $(document).on('click','.gm2-qd-option',function(e){
            e.preventDefault();
            var $option = $(this);
            mutate(() => {
                $option.addClass('active').siblings('.gm2-qd-option').removeClass('active');
            });
            var qty;
            measure(() => {
                qty = $option.data('qty');
            });
            var $form;
            measure(() => {
                $form = $('form.cart');
            });
            var $input;
            measure(() => {
                $input = $form.find('input.qty');
            });
            if($input.length){
                mutate(() => {
                    $input.val(qty).trigger('change');
                });
            }
            if($form.length){
                var $btn;
                measure(() => {
                    $btn = $form.find('[name=add-to-cart]');
                });
                if($btn.length){
                    mutate(() => {
                        $btn.addClass('loading');
                        $option.addClass('loading');
                    });
                    var $spinner;
                    mutate(() => {
                        $spinner = $('<span class="loading-spinner"></span>');
                        $option.append($spinner);
                    });
                    mutate(() => {
                        $form.one('ajaxComplete', function(){
                            $btn.removeClass('loading');
                            $option.removeClass('loading');
                            $spinner.remove();
                        });
                        $btn.trigger('click');
                    });
                }
            }
        });
    });
});
