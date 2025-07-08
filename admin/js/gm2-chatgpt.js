jQuery(function($){
    $('#gm2-chatgpt-form').on('submit', function(e){
        e.preventDefault();
        var prompt = $('#gm2_chatgpt_prompt').val();
        var $out = $('#gm2-chatgpt-output').text(gm2ChatGPT.loading);
        $.post(gm2ChatGPT.ajax_url, {
            action: 'gm2_chatgpt_prompt',
            prompt: prompt,
            _ajax_nonce: gm2ChatGPT.nonce
        }, function(resp){
            if(resp && resp.success){
                $out.text(resp.data);
            } else {
                $out.text(resp.data || gm2ChatGPT.error);
            }
        });
    });
});
