jQuery(function($){
    var totalPosts = 0;
    var applied = 0;

    function initBar(max){
        var $bar = $('#gm2-bulk-progress-bar');
        if(!$bar.length){
            $bar = $('<progress>',{id:'gm2-bulk-progress-bar',value:0,max:max,style:'width:100%;'});
            $('#gm2-bulk-analyze').parent().after($bar);
        }
        $bar.attr('max',max).val(0).show();
    }

    function updateBar(val){
        $('#gm2-bulk-progress-bar').val(val);
    }

    function buildHtml(data,id){
        var html='';
        if(data.seo_title){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="'+data.seo_title.replace(/"/g,'&quot;')+'"> '+data.seo_title+'</label></p>';}
        if(data.description){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="'+data.description.replace(/"/g,'&quot;')+'"> '+data.description+'</label></p>';}
        if(data.slug){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="slug" data-value="'+data.slug+'"> Slug: '+data.slug+'</label></p>';}
        if(data.page_name){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="title" data-value="'+data.page_name.replace(/"/g,'&quot;')+'"> Title: '+data.page_name+'</label></p>';}
        html+='<p><button class="button gm2-apply-btn" data-id="'+id+'">Apply</button> <button class="button gm2-refresh-btn" data-id="'+id+'">Refresh</button> <button class="button gm2-clear-btn" data-id="'+id+'">Clear</button></p>';
        return html;
    }

    $('#gm2-bulk-ai').on('click','#gm2-bulk-select-all',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-ai').on('click','#gm2-bulk-analyze',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;

        totalPosts = ids.length;
        applied = 0;
        initBar(totalPosts);

        var $progress = $('#gm2-bulk-progress');
        if(!$progress.length){
            $progress = $('<p>',{id:'gm2-bulk-progress'});
            $('#gm2-bulk-progress-bar').after($progress);
        }
        var total = ids.length, processed = 0, fatal = false;

        function updateProgress(msg){
            if(msg){
                $progress.text(msg);
            }else{
                $progress.text('Processing '+processed+' / '+total);
            }
            updateBar(processed);
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
                    var html = buildHtml(resp.data,id);
                    row.find('.gm2-result').html(html);
                    processed++;
                    updateProgress();
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
            applied++;
            updateBar(applied);
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-refresh-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        row.find('.gm2-result').text('...');
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research',post_id:id,refresh:1,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success&&resp.data){
                row.find('.gm2-result').html(buildHtml(resp.data,id));
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):'Error';
                row.find('.gm2-result').text(msg);
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            row.find('.gm2-result').text(msg||'Error');
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-clear-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        row.find('.gm2-result').text('...');
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research_clear',post_id:id,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                row.find('.gm2-result').empty();
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):'Error';
                row.find('.gm2-result').text(msg);
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            row.find('.gm2-result').text(msg||'Error');
        });
    });

    $('#gm2-bulk-ai').on('click','#gm2-bulk-apply-all',function(e){
        e.preventDefault();
        var posts={};
        $('#gm2-bulk-list tr').each(function(){
            var id=$(this).attr('id');
            if(!id) return;id=id.replace('gm2-row-','');
            var fields={};
            $(this).find('.gm2-apply:checked').each(function(){
                fields[$(this).data('field')]= $(this).data('value');
            });
            if(Object.keys(fields).length){posts[id]=fields;}
        });
        if($.isEmptyObject(posts)) return;
        var $msg=$('#gm2-bulk-apply-msg');
        $msg.text('Saving...');
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_apply_batch',posts:JSON.stringify(posts),_ajax_nonce:gm2BulkAi.apply_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(posts,function(id){
                    $('#gm2-row-'+id+' .gm2-result').append('<span> ✓</span>');
                });
                $msg.text('Done');
                applied += Object.keys(posts).length;
                updateBar(applied);
            }else{
                $msg.text((resp&&resp.data)?resp.data:'Error');
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||'Error');
        });
    });
});
