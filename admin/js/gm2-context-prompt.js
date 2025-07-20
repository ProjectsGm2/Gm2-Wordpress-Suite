jQuery(function($){
    $(document).on('click', '.gm2-build-ai-prompt', function(e){
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        var prompt = [
            'You are a strategic SEO assistant. Based on the detailed business information below, summarize the company\u2019s background into a concise, 1-paragraph context that can be prefixed to other SEO prompts. This context summary should:',
            '',
            '- Clearly define the business model, industry, offerings, and audience.',
            '- Highlight unique selling points and primary goals.',
            '- Mention tone/brand voice and geographic focus.',
            '- Be formatted in natural language, suitable for pasting into the beginning of SEO prompts.',
            '- Keep it under 150 words.',
            '',
            'Here is the business information:',
            '',
            'Business Model: ' + $('#gm2_context_business_model').val(),
            'Industry Category: ' + $('#gm2_context_industry_category').val(),
            'Target Audience: ' + $('#gm2_context_target_audience').val(),
            'Unique Selling Points: ' + $('#gm2_context_unique_selling_points').val(),
            'Revenue Streams: ' + $('#gm2_context_revenue_streams').val(),
            'Primary Goal: ' + $('#gm2_context_primary_goal').val(),
            'Brand Voice: ' + $('#gm2_context_brand_voice').val(),
            'Competitors: ' + $('#gm2_context_competitors').val(),
            'Core Offerings: ' + $('#gm2_context_core_offerings').val(),
            'Geographic Focus: ' + $('#gm2_context_geographic_focus').val(),
            'Keyword Data: ' + $('#gm2_context_keyword_data').val(),
            'Competitor Landscape: ' + $('#gm2_context_competitor_landscape').val(),
            'Success Metrics: ' + $('#gm2_context_success_metrics').val(),
            'Buyer Personas: ' + $('#gm2_context_buyer_personas').val(),
            'Project Description: ' + $('#gm2_context_project_description').val(),
            'Custom Prompts: ' + $('#gm2_context_custom_prompts').val()
        ].join('\n');
        if (window.gm2ChatGPT) {
            $.post({
                url: gm2ChatGPT.ajax_url,
                data: {
                    action: 'gm2_chatgpt_prompt',
                    prompt: prompt,
                    _ajax_nonce: gm2ChatGPT.nonce
                },
                dataType: 'json'
            }).done(function(resp){
                console.log('gm2-context-prompt response:', resp);
                if (resp === '0') {
                    $('#gm2_context_ai_prompt').val('ChatGPT disabled or endpoint missing');
                } else if (resp && resp.success) {
                    $('#gm2_context_ai_prompt').val(resp.data);
                } else if (resp && resp.data) {
                    $('#gm2_context_ai_prompt').val(resp.data);
                } else {
                    $('#gm2_context_ai_prompt').val(gm2ChatGPT.error || 'Error');
                }
            }).fail(function(jqXHR){
                var msg = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data;
                if(!msg){
                    msg = jqXHR && jqXHR.responseText ? jqXHR.responseText : 'Error';
                }
                if(msg === '0'){ msg = 'ChatGPT disabled or endpoint missing'; }
                $('#gm2_context_ai_prompt').val(msg);
            }).always(function(){
                $btn.prop('disabled', false);
            });
        } else {
            $('#gm2_context_ai_prompt').val(prompt);
            $btn.prop('disabled', false);
        }
    });
});

