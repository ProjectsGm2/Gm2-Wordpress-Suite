jQuery(function($){
    function switchTab($container, tab){
        $container.find('.gm2-nav-tab')
            .removeClass('active')
            .attr('aria-selected','false');
        var $newTab = $container.find('.gm2-nav-tab[data-tab="'+tab+'"]')
            .addClass('active')
            .attr('aria-selected','true');
        $container.find('.gm2-tab-panel').removeClass('active').hide();
        $container.find('#'+tab).addClass('active').show();
        return $newTab;
    }

    $(document).on('click', '.gm2-nav-tab', function(e){
        e.preventDefault();
        var $c = $(this).closest('.gm2-seo-tabs');
        switchTab($c, $(this).data('tab')).focus();
    });

    $(document).on('keydown', '.gm2-nav-tab', function(e){
        if($.inArray(e.which, [37,38,39,40,13,32]) === -1){
            return;
        }
        e.preventDefault();
        var $tab = $(this);
        var $c = $tab.closest('.gm2-seo-tabs');
        var $tabs = $c.find('.gm2-nav-tab');
        var idx = $tabs.index($tab);
        if(e.which === 37 || e.which === 38){
            idx = (idx > 0) ? idx - 1 : $tabs.length - 1;
        }else if(e.which === 39 || e.which === 40){
            idx = (idx < $tabs.length - 1) ? idx + 1 : 0;
        }else if(e.which === 13 || e.which === 32){
            switchTab($c, $tab.data('tab'));
            return;
        }
        var $target = $tabs.eq(idx);
        switchTab($c, $target.data('tab'));
        $target.focus();
    });

    $(document).on('click', '.gm2-upload-image', function(e){
        e.preventDefault();
        var button = $(this);
        var field  = $('#' + button.data('target'));
        var frame = wp.media({
            title: gm2Seo.i18n.selectImage,
            button: { text: gm2Seo.i18n.useImage },
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

    function gm2ShowNotice(message, type){
        var $notice = $('<div class="notice notice-'+type+' is-dismissible gm2-optimizer-notice"><p>'+message+'</p></div>');
        $('.gm2-optimizer-notice').remove();
        $('.wrap').first().prepend($notice);
    }

    function gm2HandlePurge(selector, action){
        $(document).on('click', selector, function(e){
            e.preventDefault();
            var $btn = $(this);
            wp.ajax.post(action, { nonce: $btn.data('nonce') })
                .done(function(response){
                    var msg = response && response.data && response.data.message ? response.data.message : 'Done.';
                    gm2ShowNotice(msg, 'success');
                })
                .fail(function(response){
                    var msg = 'Error';
                    if(response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message){
                        msg = response.responseJSON.data.message;
                    }
                    gm2ShowNotice(msg, 'error');
                });
        });
    }

    gm2HandlePurge('.gm2-purge-critical-css', 'gm2_purge_critical_css');
    gm2HandlePurge('.gm2-purge-js-map', 'gm2_purge_js_map');
    gm2HandlePurge('.gm2-purge-optimizer-cache', 'gm2_purge_optimizer_cache');
});
