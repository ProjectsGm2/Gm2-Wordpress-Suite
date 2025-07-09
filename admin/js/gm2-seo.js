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

$(document).on('click', '.gm2-upload-image', function(e){
    e.preventDefault();
    var button = $(this);
    var field  = $('#' + button.data('target'));
    var frame = wp.media({
        title: 'Select Image',
        button: { text: 'Use image' },
        multiple: false
    });
    frame.on('select', function(){
        var attachment = frame.state().get('selection').first().toJSON();
        field.val(attachment.id);
        button.siblings('.gm2-image-preview').html('<img src="'+attachment.url+'" style="max-width:100%;height:auto;" />');
    });
    frame.open();
});

});
