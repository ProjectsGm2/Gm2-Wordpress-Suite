jQuery(function($){
    if(!gm2KeywordResearch.enabled){
        $('#gm2-keyword-research-form button[type="submit"]').prop('disabled', true);
    }
    $('#gm2-keyword-research-form').on('submit', function(e){
        e.preventDefault();
        var kw = $('#gm2_seed_keyword').val();
        var $list = $('#gm2-keyword-results').empty();
        $list.text('Loading...');
        $.post(gm2KeywordResearch.ajax_url, {
            action: 'gm2_keyword_ideas',
            query: kw,
            _ajax_nonce: gm2KeywordResearch.nonce
        }).done(function(resp){
            $list.empty();
            if(resp && resp.success && Array.isArray(resp.data)){
                resp.data.forEach(function(k){
                    $('<li>').text(k).appendTo($list);
                });
            } else {
                var msg = 'No results';
                if(resp && resp.data){
                    if(typeof resp.data === 'string'){
                        msg = resp.data;
                    } else if(resp.data.message){
                        msg = resp.data.message;
                    } else if(resp.data && resp.data.errors){
                        var code = Object.keys(resp.data.errors)[0];
                        msg = resp.data.errors[code][0];
                    }
                }
                $list.append($('<li>').text(msg));
            }
        }).fail(function(){
            $list.empty().append($('<li>').text('Request failed'));
        });
    });
});
