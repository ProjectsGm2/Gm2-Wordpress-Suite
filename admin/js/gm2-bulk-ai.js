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
        var selectLabel = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.selectAll : 'Select all';
        var html='<p><label><input type="checkbox" class="gm2-row-select-all"> '+selectLabel+'</label></p>';
        if(data.seo_title){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="'+data.seo_title.replace(/"/g,'&quot;')+'"> '+data.seo_title+'</label></p>';}
        if(data.description){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="'+data.description.replace(/"/g,'&quot;')+'"> '+data.description+'</label></p>';}
        if(data.slug){
            var slugLabel = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.slug : 'Slug';
            html+='<p><label><input type="checkbox" class="gm2-apply" data-field="slug" data-value="'+data.slug+'"> '+slugLabel+': '+data.slug+'</label></p>';}
        if(data.page_name){
            var titleLabel = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.title : 'Title';
            html+='<p><label><input type="checkbox" class="gm2-apply" data-field="title" data-value="'+data.page_name.replace(/"/g,'&quot;')+'"> '+titleLabel+': '+data.page_name+'</label></p>';}
        var applyText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.apply : 'Apply';
        var refreshText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.refresh : 'Refresh';
        var clearText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.clear : 'Clear';
        html+='<p><button class="button gm2-apply-btn" data-id="'+id+'">'+applyText+'</button> <button class="button gm2-refresh-btn" data-id="'+id+'">'+refreshText+'</button> <button class="button gm2-clear-btn" data-id="'+id+'">'+clearText+'</button></p>';
        return html;
    }

    $('#gm2-bulk-ai').on('click','#gm2-bulk-select-all',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-list').on('click','.gm2-row-select-all',function(){
        var checked=$(this).prop('checked');
        $(this).closest('.gm2-result').find('.gm2-apply').prop('checked',checked);
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
                var procText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.processing : 'Processing %1$s / %2$s';
                $progress.text(procText.replace('%1$s', processed).replace('%2$s', total));
            }
            updateBar(processed);
        }

        function processNext(){
            if(fatal){
                return;
            }
            if(!ids.length){
                var doneMsg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.complete : 'Complete';
                updateProgress(doneMsg);
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
                        var inv = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.invalidJson : 'Invalid JSON response';
                        row.find('.gm2-result').text(inv);
                        updateProgress((window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:')+' '+inv);
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
                    var msg = (resp && resp.data) ? (resp.data.message || resp.data) : (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error');
                    row.find('.gm2-result').text(msg);
                    updateProgress((window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:')+' '+msg);
                }
            })
            .fail(function(jqXHR, textStatus){
                fatal = true;
                var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                    ? jqXHR.responseJSON.data
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
                if(textStatus === 'parsererror'){
                    msg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.invalidJson : 'Invalid JSON response';
                }
                row.find('.gm2-result').text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
                var stopMsg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:';
                var errWord = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error';
                updateProgress(stopMsg+' '+(msg || errWord));
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
        var savingText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.saving : 'Saving...';
        $msg.text(savingText);
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
                $msg.text(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.done : 'Done');
                applied += Object.keys(posts).length;
                updateBar(applied);
            }else{
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
        });
    });
});
