jQuery(function($){
    $('#gm2-keyword-research-form').on('submit', function(e){
        e.preventDefault();
        var kw = $('#gm2_seed_keyword').val();
        var $list = $('#gm2-keyword-results').empty();
        $list.text('Loading...');
        $.post(gm2KeywordResearch.ajax_url, {
            action: 'gm2_keyword_ideas',
            query: kw,
            _ajax_nonce: gm2KeywordResearch.nonce
        }, function(resp){
            $list.empty();
            if(resp && resp.success && resp.data.length){
                resp.data.forEach(function(k){
                    $('<li>').text(k).appendTo($list);
                });
            } else {
                $list.append($('<li>').text('No results'));
            }
        });
    });
});
