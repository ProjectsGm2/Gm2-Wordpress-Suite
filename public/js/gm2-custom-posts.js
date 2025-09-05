(function($){
    const dom = window.aePerf?.dom;
    const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
    const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

    // Basic frontend helpers for custom post fields
    mutate(() => {
        $(document).on('input', '.gm2-relationship', function(){
            var val;
            measure(() => {
                val = $(this).val();
            });
            mutate(() => {
                $(this).val(val.replace(/[^0-9a-z_,]/gi,''));
            });
        });
    });
})(jQuery);
