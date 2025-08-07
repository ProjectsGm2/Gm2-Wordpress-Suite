jQuery(function($){
    var totalPosts = 0;
    var applied = 0;
    var stopProcessing = false;

    // Mark all rows as new on initial load
    $('#gm2-bulk-list tr[id^="gm2-row-"]').addClass('gm2-status-new');

    function initBar(max){
        var $bar = $('#gm2-bulk-progress-bar');
        if(!$bar.length){
            $bar = $('<progress>',{id:'gm2-bulk-progress-bar',value:0,max:max,style:'width:100%;',role:'progressbar','aria-live':'polite'});
            $('.gm2-bulk-analyze').last().parent().after($bar);
        }
        $bar.attr({max:max,role:'progressbar','aria-live':'polite'}).val(0).show();
    }

    function updateBar(val){
        $('#gm2-bulk-progress-bar').val(val);
    }

    function showSpinner($cell){
        if(!$cell.find('.gm2-ai-spinner').length){
            $('<span>',{class:'spinner is-active gm2-ai-spinner'}).appendTo($cell);
        } else {
            $cell.find('.gm2-ai-spinner').addClass('is-active');
        }
    }

    function hideSpinner($cell){
        $cell.find('.gm2-ai-spinner').remove();
    }

    function buildHtml(data,id,undo){
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
        var undoText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.undo : 'Undo';
        html+='<p><button class="button gm2-apply-btn" data-id="'+id+'" aria-label="'+applyText+'">'+applyText+'</button> <button class="button gm2-refresh-btn" data-id="'+id+'" aria-label="'+refreshText+'">'+refreshText+'</button> <button class="button gm2-clear-btn" data-id="'+id+'" aria-label="'+clearText+'">'+clearText+'</button>'+(undo? ' <button class="button gm2-undo-btn" data-id="'+id+'" aria-label="'+undoText+'">'+undoText+'</button>':'')+'</p>';
        return html;
    }

    $('#gm2-bulk-ai').on('click','#gm2-bulk-select-all',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-list').on('change','.gm2-row-select-all',function(){
        var checked=$(this).prop('checked');
        $(this).closest('td').find('.gm2-apply').prop('checked',checked);
    });
    $('#gm2-bulk-ai').on('click','.gm2-bulk-analyze',function(e){
        e.preventDefault();
        stopProcessing = false;
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
        $progress.text('');
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
            if(fatal || stopProcessing){
                $('#gm2-bulk-progress-bar').hide();
                $progress.text('');
                return;
            }
            if(!ids.length){
                var doneMsg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.complete : 'Complete';
                updateProgress(doneMsg);
                return;
            }
            var id = ids.shift();
            updateProgress();
            var row=$('#gm2-row-'+id);
            var $res=row.find('.gm2-result').empty();
            showSpinner($res);
            $.ajax({
                url: gm2BulkAi.ajax_url,
                method: 'POST',
                data: {action:'gm2_ai_research',post_id:id,_ajax_nonce:gm2BulkAi.nonce},
                dataType: 'json'
            })
            .done(function(resp){
                hideSpinner($res);
                if(typeof resp === 'string'){
                    try{ resp = JSON.parse(resp); }catch(e){
                        fatal = true;
                        var inv = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.invalidJson : 'Invalid JSON response';
                        $res.text(inv);
                        updateProgress((window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:')+' '+inv);
                        return;
                    }
                }
                if(resp&&resp.success&&resp.data){
                    var html = buildHtml(resp.data,id,resp.data.undo);
                    $res.html(html);
                    row.removeClass('gm2-status-new gm2-status-applied').addClass('gm2-status-analyzed');
                    processed++;
                    updateProgress();
                    processNext();
                }else{
                    fatal = true;
                    var msg = (resp && resp.data) ? (resp.data.message || resp.data) : (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error');
                    $res.text(msg);
                    updateProgress((window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:')+' '+msg);
                }
            })
            .fail(function(jqXHR, textStatus){
                hideSpinner($res);
                fatal = true;
                var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                    ? jqXHR.responseJSON.data
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
                if(textStatus === 'parsererror'){
                    msg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.invalidJson : 'Invalid JSON response';
                }
                $res.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
                var stopMsg = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.stopped : 'Stopped:';
                var errWord = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error';
                updateProgress(stopMsg+' '+(msg || errWord));
            });
        }
        updateProgress();
        processNext();
    });
    $('#gm2-bulk-ai').on('click','.gm2-bulk-cancel',function(e){
        e.preventDefault();
        stopProcessing = true;
        $('#gm2-bulk-progress-bar').hide();
        $('#gm2-bulk-progress').text('');
    });
    $('#gm2-bulk-list').on('click','.gm2-apply-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var data={action:'gm2_bulk_ai_apply',post_id:id,_ajax_nonce:gm2BulkAi.apply_nonce};
        $(this).closest('.gm2-result').find('.gm2-apply:checked').each(function(){
            data[$(this).data('field')]= $(this).data('value');
        });
        var row = $('#gm2-row-'+id);
        var $res=row.find('.gm2-result');
        showSpinner($res);
        $.post(gm2BulkAi.ajax_url,data)
            .done(function(resp){
                hideSpinner($res);
                if(resp && resp.success){
                    row.find('.gm2-result .gm2-undo-btn').remove();
                    row.find('.gm2-result').append(' <button class="button gm2-undo-btn" data-id="'+id+'">'+(gm2BulkAi.i18n?gm2BulkAi.i18n.undo:'Undo')+'</button> <span> ✓</span>');
                    row.removeClass('gm2-status-new gm2-status-analyzed')
                        .addClass('gm2-status-applied gm2-applied');
                    setTimeout(function(){
                        row.removeClass('gm2-applied');
                    },3000);
                    applied++;
                    updateBar(applied);
                }else{
                    var msg=(resp && resp.data)?(resp.data.message||resp.data):(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error');
                    $res.text(msg);
                }
            })
            .fail(function(jqXHR, textStatus){
                hideSpinner($res);
                var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                    ? jqXHR.responseJSON.data
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
                $res.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            });
    });

    $('#gm2-bulk-list').on('click','.gm2-refresh-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result').empty();
        showSpinner($res);
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research',post_id:id,refresh:1,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        }).done(function(resp){
            hideSpinner($res);
            if(resp&&resp.success&&resp.data){
                $res.html(buildHtml(resp.data,id,resp.data.undo));
                row.removeClass('gm2-status-new gm2-status-applied').addClass('gm2-status-analyzed');
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):'Error';
                $res.text(msg);
            }
        }).fail(function(jqXHR,textStatus){
            hideSpinner($res);
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $res.text(msg||'Error');
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-clear-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result').empty();
        showSpinner($res);
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research_clear',post_id:id,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        }).done(function(resp){
            hideSpinner($res);
            if(resp&&resp.success){
                $res.empty();
                row.removeClass('gm2-applied gm2-status-analyzed gm2-status-applied')
                   .addClass('gm2-status-new');
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):'Error';
                $res.text(msg);
            }
        }).fail(function(jqXHR,textStatus){
            hideSpinner($res);
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $res.text(msg||'Error');
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-undo-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result');
        showSpinner($res);
        $.post(gm2BulkAi.ajax_url,{action:'gm2_bulk_ai_undo',post_id:id,_ajax_nonce:gm2BulkAi.apply_nonce})
            .done(function(resp){
                hideSpinner($res);
                if(resp&&resp.success){
                    row.find('td').eq(0).text(resp.data.title);
                    row.find('td').eq(1).text(resp.data.seo_title);
                    row.find('td').eq(2).text(resp.data.description);
                    row.find('td').eq(3).text(resp.data.slug);
                    $res.empty();
                    row.removeClass('gm2-status-applied gm2-status-analyzed gm2-applied')
                       .addClass('gm2-status-new');
                }else{
                    var msg=(resp&&resp.data)?(resp.data.message||resp.data):'Error';
                    $res.text(msg);
                }
            })
            .fail(function(jqXHR,textStatus){
                hideSpinner($res);
                var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
                $res.text(msg||'Error');
            });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-select-analyzed',function(e){
        e.preventDefault();
        $('#gm2-bulk-list tr.gm2-status-analyzed').each(function(){
            var $cb = $(this).find('.gm2-row-select-all');
            $cb.prop('checked',true).trigger('change');
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-apply-all',function(e){
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
        var total = Object.keys(posts).length;
        var processed = 0;
        applied = 0;
        initBar(total);
        var savingText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.saving : 'Saving %1$s / %2$s...';
        $msg.text(savingText.replace('%1$s', processed).replace('%2$s', total));
        $.each(posts,function(id){
            showSpinner($('#gm2-row-'+id).find('.gm2-result'));
        });
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_apply_batch',posts:JSON.stringify(posts),_ajax_nonce:gm2BulkAi.apply_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(posts,function(id){
                    var row = $('#gm2-row-'+id);
                    var cell = row.find('.gm2-result');
                    hideSpinner(cell);
                    cell.find('.gm2-undo-btn').remove();
                    cell.append(' <button class="button gm2-undo-btn" data-id="'+id+'">'+(gm2BulkAi.i18n?gm2BulkAi.i18n.undo:'Undo')+'</button> <span> ✓</span>');
                    row.removeClass('gm2-status-new gm2-status-analyzed')
                        .addClass('gm2-status-applied gm2-applied');
                    setTimeout(function(){
                        row.removeClass('gm2-applied');
                    },3000);
                    processed++;
                    applied++;
                    updateBar(applied);
                    $msg.text(savingText.replace('%1$s', processed).replace('%2$s', total));
                });
                var doneText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.done : 'Done (%1$s/%2$s)';
                $msg.text(doneText.replace('%1$s', processed).replace('%2$s', total));
            }else{
                $.each(posts,function(id){
                    hideSpinner($('#gm2-row-'+id).find('.gm2-result'));
                });
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                ? jqXHR.responseJSON.data
                : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
            $.each(posts,function(id){
                var cell = $('#gm2-row-'+id).find('.gm2-result');
                hideSpinner(cell);
                cell.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            });
            $msg.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-reset-selected',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-apply-msg');
        var resettingText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.resetting : 'Resetting...';
        $msg.text(resettingText);
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_reset',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAi.reset_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(ids,function(i,id){
                    var row=$('#gm2-row-'+id);
                    row.find('td').eq(2).text('');
                    row.find('td').eq(3).text('');
                    row.find('.gm2-result').empty();
                    row.removeClass('gm2-status-applied gm2-status-analyzed').addClass('gm2-status-new');
                });
                var doneText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.resetDone : 'Reset %s posts';
                $msg.text(doneText.replace('%s', resp.data.reset));
            }else{
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR && jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-reset-ai',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-apply-msg');
        var resettingText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.resetting : 'Resetting...';
        $msg.text(resettingText);
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_clear',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAi.clear_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(ids,function(i,id){
                    var row=$('#gm2-row-'+id);
                    row.find('.gm2-result').empty();
                    row.removeClass('gm2-status-applied gm2-status-analyzed').addClass('gm2-status-new');
                });
                var doneText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.clearDone : 'Cleared AI suggestions for %s posts';
                $msg.text(doneText.replace('%s', resp.data.cleared));
            }else{
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR && jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-reset-all',function(e){
        e.preventDefault();
        var data={
            action:'gm2_bulk_ai_reset',
            all:1,
            status:$('select[name="status"]').val(),
            post_type:$('select[name="gm2_post_type"]').val(),
            seo_status:$('select[name="seo_status"]').val(),
            terms:$('select[name="term[]"]').val()||[],
            missing_title:$('input[name="gm2_missing_title"]').is(':checked')?1:0,
            missing_desc:$('input[name="gm2_missing_description"]').is(':checked')?1:0,
            search:$('input[name="s"]').val()||'',
            _ajax_nonce:gm2BulkAi.reset_nonce
        };
        var $msg=$('#gm2-bulk-apply-msg');
        var resettingText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.resetting : 'Resetting...';
        $msg.text(resettingText);
        $.post(gm2BulkAi.ajax_url,data).done(function(resp){
            if(resp&&resp.success){
                location.reload();
            }else{
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR && jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-schedule',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        var $btn=$(this);
        var $msg=$('#gm2-bulk-apply-msg');
        $btn.prop('disabled',true);
        var schedulingText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.scheduling:'Scheduling...';
        $msg.text(schedulingText);
        var $spinner=$('<span>',{class:'spinner is-active gm2-ai-spinner'}).insertAfter($btn);
        $.post(gm2BulkAi.ajax_url,{action:'gm2_ai_batch_schedule',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAi.batch_nonce})
        .done(function(resp){
            $spinner.remove();
            $btn.prop('disabled',false);
            if(resp&&resp.success){
                var scheduledText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.scheduled:'Batch scheduled';
                $msg.text(scheduledText);
            }else{
                var err=(resp&&resp.data)?resp.data:(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error');
                $msg.text(err);
            }
        })
        .fail(function(jqXHR,textStatus){
            $spinner.remove();
            $btn.prop('disabled',false);
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error'));
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-cancel-batch',function(e){
        e.preventDefault();
        var $btn=$(this);
        var $msg=$('#gm2-bulk-apply-msg');
        $btn.prop('disabled',true);
        var cancellingText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.cancelling:'Cancelling...';
        $msg.text(cancellingText);
        var $spinner=$('<span>',{class:'spinner is-active gm2-ai-spinner'}).insertAfter($btn);
        $.post(gm2BulkAi.ajax_url,{action:'gm2_ai_batch_cancel',_ajax_nonce:gm2BulkAi.batch_nonce})
        .done(function(resp){
            $spinner.remove();
            $btn.prop('disabled',false);
            if(resp&&resp.success){
                var cancelledText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.cancelled:'Batch cancelled';
                $msg.text(cancelledText);
            }else{
                var err=(resp&&resp.data)?resp.data:(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error');
                $msg.text(err);
            }
        })
        .fail(function(jqXHR,textStatus){
            $spinner.remove();
            $btn.prop('disabled',false);
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error'));
        });
    });
});
