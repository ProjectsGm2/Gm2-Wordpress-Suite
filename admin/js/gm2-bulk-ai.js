jQuery(function($){
    $('#gm2-bulk-ai').on('click','#gm2-bulk-select-all',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-ai').on('click','#gm2-bulk-analyze',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        ids.forEach(function(id){
            var row=$('#gm2-row-'+id);row.find('.gm2-result').text('...');
            $.post(gm2BulkAi.ajax_url,{action:'gm2_ai_research',post_id:id,_ajax_nonce:gm2BulkAi.nonce})
            .done(function(resp){
                if(resp&&resp.success&&resp.data){
                    var html='';
                    if(resp.data.seo_title){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="'+resp.data.seo_title.replace(/"/g,'&quot;')+'"> '+resp.data.seo_title+'</label></p>';}
                    if(resp.data.description){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="'+resp.data.description.replace(/"/g,'&quot;')+'"> '+resp.data.description+'</label></p>';}
                    if(resp.data.slug){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="slug" data-value="'+resp.data.slug+'"> Slug: '+resp.data.slug+'</label></p>';}
                    if(resp.data.page_name){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="title" data-value="'+resp.data.page_name.replace(/"/g,'&quot;')+'"> Title: '+resp.data.page_name+'</label></p>';}
                    html+='<p><button class="button gm2-apply-btn" data-id="'+id+'">Apply</button></p>';
                    row.find('.gm2-result').html(html);
                }else{row.find('.gm2-result').text('Error');}
            })
            .fail(function(){row.find('.gm2-result').text('Error');});
        });
    });
    $('#gm2-bulk-list').on('click','.gm2-apply-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var data={action:'gm2_bulk_ai_apply',post_id:id,_ajax_nonce:gm2BulkAi.apply_nonce};
        $(this).closest('.gm2-result').find('.gm2-apply:checked').each(function(){
            data[$(this).data('field')]= $(this).data('value');
        });
        $.post(gm2BulkAi.ajax_url,data).done(function(){
            $('#gm2-row-'+id+' .gm2-result').append('<span> ✓</span>');
        });
    });
});
