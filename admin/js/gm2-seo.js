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

    var relMap = {};
    var $relInput = $('#gm2_link_rel_data');
    if($relInput.length && $relInput.val()){
        try{ relMap = JSON.parse($relInput.val()); }catch(err){ relMap = {}; }
    }
    function updateRelInput(){
        if($relInput.length){
            $relInput.val(JSON.stringify(relMap));
        }
    }
    function isExternal(url){
        var a = document.createElement('a');
        a.href = url;
        return a.hostname && a.hostname !== window.location.hostname;
    }
    if(typeof wpLink !== 'undefined'){
        var $relField = $('<p class="gm2-link-rel"><label>Rel <select id="gm2-link-rel"><option value="">None</option><option value="nofollow">nofollow</option><option value="sponsored">sponsored</option><option value="nofollow sponsored">nofollow &amp; sponsored</option></select></label></p>');
        $('#wp-link .link-target').after($relField);
        var openOrig = wpLink.open;
        wpLink.open = function(editorId){
            openOrig.call(wpLink, editorId);
            var href = $('#wp-link-url').val();
            if(isExternal(href)){
                $('#gm2-link-rel').val(relMap[href] || '');
                $relField.show();
            }else{
                $relField.hide();
            }
        };
        var updateOrig = wpLink.update;
        wpLink.update = function(){
            var href = $('#wp-link-url').val();
            var rel  = $('#gm2-link-rel').val();
            if(href && isExternal(href)){
                if(rel){
                    relMap[href] = rel;
                }else{
                    delete relMap[href];
                }
                updateRelInput();
            }
            updateOrig.call(wpLink);
        };
    }
});
