jQuery(function($){
    function mapAlias(key){
        var map = {
            'content_in_post': 'content',
            'content_in_page': 'content',
            'content_in_custom_post': 'content',
            'content_in_product': 'content'
        };
        return map[key] || key;
    }
    function flatten(val){
        if($.isArray(val)){
            return $.map(val, flatten).join("\n");
        }else if(val && typeof val === "object"){
            return $.map(Object.values(val), flatten).join("\n");
        }
        return String(val);
    }
    var ruleSlugs = [];
    if(window.gm2GuidelineRules && gm2GuidelineRules.categories){
        ruleSlugs = gm2GuidelineRules.categories.split(',').map(function(slug){
            return slug.trim();
        }).filter(Boolean);
    }

    $('.gm2-research-guideline-rules').on('click', function(e){
        e.preventDefault();
        if(!window.gm2GuidelineRules) return;
        var $btn = $(this);
        var base = $btn.data('base');
        if(!base) return;
        var cats = ruleSlugs.join(',');
        var loadingText = gm2GuidelineRules.loading || 'Researching...';
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(loadingText);
        $.post(gm2GuidelineRules.ajax_url, {
            action: 'gm2_research_guideline_rules',
            target: base,
            categories: cats,
            _ajax_nonce: gm2GuidelineRules.nonce
        }).done(function(resp){
            if(resp && resp.success && typeof resp.data === 'object'){
                if($.isEmptyObject(resp.data)){
                    alert('No recognized rules returned. Check the categories or server logs.');
                }else{
                    $.each(resp.data, function(key,val){
                        var selector = 'textarea[name="gm2_guideline_rules['+base+']['+mapAlias(key)+']"]';
                        $(selector).val(flatten(val));
                    });
                }
            }else if(resp && resp.data){
                alert(resp.data);
            }else{
                alert('Error');
            }
        }).fail(function(){
            alert('Request failed');
        }).always(function(){
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
