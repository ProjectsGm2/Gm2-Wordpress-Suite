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
    $('.gm2-research-rules').on('click', function(e){
        e.preventDefault();
        if(!window.gm2ContentRules) return;
        var $btn = $(this);
        var base = $btn.data('base');
        var cat  = $btn.data('category');
        if(!base) return;
        var promptText = gm2ContentRules.prompt || 'Enter rule categories (comma separated):';
        var cats = prompt(promptText, cat);
        if(cats === null || !cats.trim()) return;
        var loadingText = gm2ContentRules.loading || 'Researching...';
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(loadingText);
        $.post(gm2ContentRules.ajax_url, {
            action: 'gm2_research_content_rules',
            target: base,
            categories: cats,
            _ajax_nonce: gm2ContentRules.nonce
        }).done(function(resp){
            if(resp && resp.success && typeof resp.data === 'object'){
                if($.isEmptyObject(resp.data)){
                    alert('No recognized rules returned. Check the categories or server logs.');
                }else{
                    $.each(resp.data, function(key,val){
                        var selector = 'textarea[name="gm2_content_rules['+base+']['+mapAlias(key)+']"]';
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
