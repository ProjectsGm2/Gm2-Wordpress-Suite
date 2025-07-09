jQuery(function($){
    $('#gm2-research-guidelines').on('click', function(e){
        e.preventDefault();
        var cats = prompt('Enter guideline categories (comma separated):', 'general, keyword research, titles');
        if(cats === null || !cats.trim()){
            return;
        }
        var $ta = $('textarea[name="gm2_seo_guidelines"]');
        $.post(gm2Guidelines.ajax_url, {
            action: 'gm2_research_guidelines',
            categories: cats,
            _ajax_nonce: gm2Guidelines.nonce
        }).done(function(resp){
            if(resp && resp.success){
                $ta.val(resp.data);
            } else if(resp && resp.data){
                alert(resp.data);
            } else {
                alert('Error');
            }
        }).fail(function(){
            alert('Request failed');
        });
    });
});
