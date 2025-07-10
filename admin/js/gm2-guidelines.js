jQuery(function($){
    $('.gm2-research-guidelines').on('click', function(e){
        e.preventDefault();
        var $btn = $(this);
        var target = $btn.data('target');
        if(!target){
            return;
        }
        var cats = prompt('Enter guideline categories (comma separated):', 'general, keyword research, titles');
        if(cats === null || !cats.trim()){
            return;
        }
        var $ta = $('textarea[name="' + target + '"]');
        $.post(gm2Guidelines.ajax_url, {
            action: 'gm2_research_guidelines',
            categories: cats,
            target: target,
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
