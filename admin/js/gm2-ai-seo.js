jQuery(function($){
    $('#gm2-ai-seo').on('click', '.gm2-ai-research', function(e){
        e.preventDefault();
        var $out = $('#gm2-ai-results').text('Researching...');
        var data = {
            action: 'gm2_ai_research',
            _ajax_nonce: (window.gm2AiSeo && gm2AiSeo.nonce) ? gm2AiSeo.nonce : ''
        };
        if(window.gm2AiSeo){
            if(gm2AiSeo.post_id){
                data.post_id = gm2AiSeo.post_id;
            }
            if(gm2AiSeo.term_id){
                data.term_id = gm2AiSeo.term_id;
                data.taxonomy = gm2AiSeo.taxonomy;
            }
        }
        $.post((window.gm2AiSeo ? gm2AiSeo.ajax_url : ajaxurl), data)
        .done(function(resp){
            if(resp && resp.success){
                if(typeof resp.data === 'object'){
                    $out.text(JSON.stringify(resp.data, null, 2));
                } else {
                    $out.text(resp.data);
                }
            } else {
                $out.text(resp && resp.data ? resp.data : 'Error');
            }
        })
        .fail(function(){
            $out.text('Request failed');
        });
    });
    $('#gm2-ai-seo').on('click', '.gm2-ai-implement', function(e){
        e.preventDefault();
        // TODO: implement applying selected results
        alert('Implement selected results');
    });
});
