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
            if ((typeof resp === 'string' && resp === '0') || !resp || typeof resp !== 'object') {
                $list.append($('<li>').text('Invalid request or not logged in'));
                return;
            }
            if(resp.success && Array.isArray(resp.data)){
                resp.data.forEach(function(item){
                    var li = $('<li>');
                    if(typeof item === 'string'){
                        li.text(item);
                    }else{
                        var txt = item.text;
                        if (typeof txt === 'object') {
                            txt = txt.value || JSON.stringify(txt);
                        }
                        li.text(txt || '');
                        if(item.metrics){
                            var parts = [];
                            Object.keys(item.metrics).forEach(function(key){
                                var val = item.metrics[key];
                                if(val !== null && val !== ''){
                                    parts.push(key.replace(/_/g,' ') + ': ' + val);
                                }
                            });
                            if(parts.length){
                                li.append(' (' + parts.join(', ') + ')');
                            }
                        }
                    }
                    li.appendTo($list);
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
