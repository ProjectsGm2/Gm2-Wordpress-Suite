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

        var $progress = $('#gm2-bulk-progress');
        if(!$progress.length){
            $progress = $('<p>',{id:'gm2-bulk-progress'});
            $('#gm2-bulk-analyze').parent().after($progress);
        }
        var total = ids.length, processed = 0, fatal = false;

        function updateProgress(msg){
            if(msg){
                $progress.text(msg);
            }else{
                $progress.text('Processing '+processed+' / '+total);
            }
        }

        function processNext(){
            if(fatal){
                return;
            }
            if(!ids.length){
                updateProgress('Complete');
                return;
            }
            var id = ids.shift();
            processed++;
            updateProgress();
            var row=$('#gm2-row-'+id);row.find('.gm2-result').text('...');
            $.ajax({
                url: gm2BulkAi.ajax_url,
                method: 'POST',
                data: {action:'gm2_ai_research',post_id:id,_ajax_nonce:gm2BulkAi.nonce},
                dataType: 'json'
            })
            .done(function(resp){
                if(typeof resp === 'string'){
                    try{ resp = JSON.parse(resp); }catch(e){
                        fatal = true;
                        row.find('.gm2-result').text('Invalid JSON response');
                        updateProgress('Stopped: Invalid JSON');
                        return;
                    }
                }
                if(resp&&resp.success&&resp.data){
                    var html='';
                    if(resp.data.seo_title){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="'+resp.data.seo_title.replace(/"/g,'&quot;')+'"> '+resp.data.seo_title+'</label></p>';}
                    if(resp.data.description){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="'+resp.data.description.replace(/"/g,'&quot;')+'"> '+resp.data.description+'</label></p>';}
                    if(resp.data.slug){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="slug" data-value="'+resp.data.slug+'"> Slug: '+resp.data.slug+'</label></p>';}
                    if(resp.data.page_name){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="title" data-value="'+resp.data.page_name.replace(/"/g,'&quot;')+'"> Title: '+resp.data.page_name+'</label></p>';}
                    html+='<p><button class="button gm2-apply-btn" data-id="'+id+'">Apply</button></p>';
                    row.find('.gm2-result').html(html);
                    processNext();
                }else{
                    fatal = true;
                    var msg = (resp && resp.data) ? (resp.data.message || resp.data) : 'Error';
                    row.find('.gm2-result').text(msg);
                    updateProgress('Stopped: '+msg);
                }
            })
            .fail(function(jqXHR, textStatus){
                fatal = true;
                var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                    ? jqXHR.responseJSON.data
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
                if(textStatus === 'parsererror'){
                    msg = 'Invalid JSON response';
                }
                row.find('.gm2-result').text(msg || 'Error');
                updateProgress('Stopped: '+(msg || 'Error'));
            });
        }
        updateProgress();
        processNext();
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
