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

    function updateRule(id, pass){
        var $el = $(id);
        $el.toggleClass('pass', pass).toggleClass('fail', !pass);
        $el.find('.dashicons').removeClass('dashicons-no dashicons-yes')
            .addClass(pass ? 'dashicons-yes' : 'dashicons-no');
    }

    function analyze(){
        var title = $('#gm2_seo_title').val() || '';
        var desc = $('#gm2_seo_description').val() || '';
        var focus = $('#gm2_focus_keywords').val() || '';
        var content = '';
        if(typeof wp !== 'undefined' && wp.data){
            content = wp.data.select('core/editor').getEditedPostContent() || '';
            content = $('<div>').html(content).text();
        }
        var words = content.trim().split(/\s+/).filter(Boolean);
        updateRule('#gm2-rule-title', title.length >= 30 && title.length <= 60);
        updateRule('#gm2-rule-description', desc.length >= 50 && desc.length <= 160);
        updateRule('#gm2-rule-focus', focus.trim().length > 0);
        updateRule('#gm2-rule-content', words.length >= 300);
    }

    analyze();
    $('#gm2_seo_title,#gm2_seo_description,#gm2_focus_keywords').on('input', analyze);
    if(typeof wp !== 'undefined' && wp.data){
        wp.data.subscribe(analyze);
    }
});
