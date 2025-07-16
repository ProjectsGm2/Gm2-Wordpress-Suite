jQuery(function($){
    $(document).on('click','.gm2-qd-option',function(e){
        e.preventDefault();
        var qty = $(this).data('qty');
        var form = $('form.cart');
        var input = form.find('input.qty');
        if(input.length){
            input.val(qty).trigger('change');
        }
        if(form.length){
            form.find('[name=add-to-cart]').trigger('click');
        }
    });
});
