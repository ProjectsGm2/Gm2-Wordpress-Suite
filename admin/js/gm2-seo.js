jQuery(function($){
    function switchTab($container, tab){
        $container.find('.gm2-nav-tab').removeClass('active');
        $container.find('.gm2-nav-tab[data-tab="'+tab+'"]').addClass('active');
        $container.find('.gm2-tab-panel').removeClass('active').hide();
        $container.find('#'+tab).addClass('active').show();
    }

    $(document).on('click', '.gm2-nav-tab', function(e){
        e.preventDefault();
        var $c = $(this).closest('.gm2-seo-tabs');
        switchTab($c, $(this).data('tab'));
    });

});
