jQuery(function($){
    const labels = {
        avg_monthly_searches: 'Avg. Monthly Searches',
        competition: 'Competition',
        three_month_change: '3‑month change',
        yoy_change: 'YoY change'
    };
    var $msg = $('#gm2-keyword-msg');
    if(!gm2KeywordResearch.enabled){
        $('#gm2-keyword-research-form button[type="submit"]').prop('disabled', true);
    }
    $('#gm2-keyword-research-form').on('submit', function(e){
        e.preventDefault();
        var kw = $('#gm2_seed_keyword').val();
        var $list = $('#gm2-keyword-results').empty();
        $msg.addClass('hidden').removeClass('notice-error').text('');
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
                var metricsFound = false;
                resp.data.forEach(function(item){
                    var li = $('<li>');
                    if(typeof item === 'string'){
                        li.text(item);
                    }else{
                        var txt = item.text;
                        if (typeof txt === 'object') {
                            txt = txt.value || JSON.stringify(txt);
                        }
                        if(!txt){
                            // Fallback to stringify the entire item if text is missing
                            txt = JSON.stringify(item);
                        }
                        li.text(txt);
                        if(item.metrics){
                            var parts = [];
                            Object.keys(item.metrics).forEach(function(key){
                                if(key === 'monthly_search_volumes'){
                                    return;
                                }
                                var val = item.metrics[key];
                                if(val !== null && val !== ''){
                                    if(typeof val === 'object'){
                                        val = val.value || JSON.stringify(val);
                                    }
                                    var label = labels[key] || key.replace(/_/g,' ');
                                    parts.push(label + ': ' + val);
                                    if(key === 'avg_monthly_searches' || key === 'competition' || key === 'three_month_change' || key === 'yoy_change'){
                                        metricsFound = true;
                                    }
                                }
                            });
                            if(parts.length){
                                li.append(' (' + parts.join(', ') + ')');
                            }
                        }
                    }
                    li.appendTo($list);
                });
                if(!metricsFound){
                    $msg.text('Google Ads API did not return keyword metrics.').addClass('notice-error').removeClass('hidden');
                }
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
