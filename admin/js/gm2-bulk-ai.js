jQuery(function($){
    var totalPosts = 0;
    var applied = 0;
    var stopProcessing = false;
    var selectedIds = [];

    // Restore selection state if stored in sessionStorage
    var storedSel = sessionStorage.getItem('gm2BulkAiSelected');
    var storedIds = sessionStorage.getItem('gm2BulkAiSelectedIds');
    if(storedSel){
        try{ selectedIds = storedIds ? JSON.parse(storedIds).map(String) : []; }catch(e){ selectedIds = []; }
        if(selectedIds.length){
            $('#gm2-bulk-list .gm2-select').each(function(){
                var id = $(this).val();
                if($.inArray(id, selectedIds) !== -1){ $(this).prop('checked', true); }
            });
            $('.gm2-bulk-select-filtered').data('selected', true).text(gm2BulkAi.i18n.unselectAllPosts||'Un-Select All');
        }else{
            $('.gm2-bulk-select-filtered').data('selected', false).text(gm2BulkAi.i18n.selectAllPosts||'Select All');
            sessionStorage.removeItem('gm2BulkAiSelected');
            sessionStorage.removeItem('gm2BulkAiSelectedIds');
        }
    }

    // Mark all rows as new on initial load
    var $rows = $('#gm2-bulk-list tr[id^="gm2-row-"]').addClass('gm2-status-new');

    // If AI suggestions already exist, mark the row as analyzed
    $rows.each(function(){
        var $row = $(this);
        var $res = $row.find('.gm2-result');
        if($res.find('.gm2-apply').length || $.trim($res.text()).length){
            $row.removeClass('gm2-status-new').addClass('gm2-status-analyzed');
        }
    });

    function initBar(max){
        var $bars = $('.gm2-bulk-progress-bar');
        if(!$bars.length){
            $bars = $('<progress>',{id:'gm2-bulk-progress-bar',class:'gm2-bulk-progress-bar',value:0,max:max,style:'width:100%;',role:'progressbar','aria-live':'polite'});
            $('.gm2-bulk-analyze').last().parent().after($bars);
        }
        $bars.each(function(){
            $(this).attr({max:max,role:'progressbar','aria-live':'polite'}).val(0).show();
        });
        $('.gm2-bulk-progress').text('');
    }

    function updateBar(val){
        $('.gm2-bulk-progress-bar').val(val);
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

    function getSelectedIds(){
        if(selectedIds.length){
            return selectedIds.slice();
        }
        var ids=[];
        $('#gm2-bulk-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        return ids;
    }

    function getFilterTerms(){
        var terms=[];
        $('select[name^="gm2_term"]').each(function(){
            var name=$(this).attr('name');
            var match=name.match(/gm2_term\[(.+)\]\[]/);
            if(match){
                var tax=match[1];
                var vals=$(this).val()||[];
                $.each(vals,function(i,val){
                    if(val){terms.push(tax+':'+val);} });
            }
        });
        return terms;
    }

    $('#gm2-bulk-ai').on('click','#gm2-bulk-select-all',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-list').on('change','.gm2-row-select-all',function(){
        var checked=$(this).prop('checked');
        $(this).closest('td').find('.gm2-apply').prop('checked',checked);
    });

    $('#gm2-bulk-list').on('change','.gm2-select',function(){
        if(!selectedIds.length) return;
        var id=$(this).val();
        if($(this).is(':checked')){
            if($.inArray(id, selectedIds) === -1){selectedIds.push(id);}
        }else{
            selectedIds=$.grep(selectedIds,function(v){return v!=id;});
        }
        if(selectedIds.length){
            sessionStorage.setItem('gm2BulkAiSelectedIds', JSON.stringify(selectedIds));
        }else{
            sessionStorage.removeItem('gm2BulkAiSelectedIds');
            sessionStorage.removeItem('gm2BulkAiSelected');
        }
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-select-filtered',function(e){
        e.preventDefault();
        var $btn=$(this);
        if($btn.data('selected')){
            selectedIds=[];
            $('#gm2-bulk-list .gm2-select').prop('checked',false);
            $btn.data('selected',false).text(gm2BulkAi.i18n.selectAllPosts||'Select All');
            sessionStorage.removeItem('gm2BulkAiSelectedIds');
            sessionStorage.removeItem('gm2BulkAiSelected');
            return;
        }
        var data={
            action:'gm2_bulk_ai_fetch_ids',
            status:$('select[name="status"]').val(),
            post_type:$('select[name="gm2_post_type"]').val(),
            seo_status:$('select[name="seo_status"]').val(),
            terms:getFilterTerms(),
            missing_title:$('input[name="gm2_missing_title"]').is(':checked')?1:0,
            missing_desc:$('input[name="gm2_missing_description"]').is(':checked')?1:0,
            search:$('input[name="s"]').val()||'',
            _ajax_nonce:gm2BulkAi.fetch_nonce
        };
        $btn.prop('disabled',true);
        $.post(gm2BulkAi.ajax_url,data).done(function(resp){
            $btn.prop('disabled',false);
            if(resp&&resp.success){
                selectedIds=(resp.data.ids||[]).map(String);
                $('#gm2-bulk-list .gm2-select').prop('checked',true);
                $btn.data('selected',true).text(gm2BulkAi.i18n.unselectAllPosts||'Un-Select All');
                sessionStorage.setItem('gm2BulkAiSelectedIds', JSON.stringify(selectedIds));
                sessionStorage.setItem('gm2BulkAiSelected', '1');
            }
        }).fail(function(){
            $btn.prop('disabled',false);
        });
    });

    // Clear stored selections when filter forms submit
    $('#gm2-bulk-ai').on('submit','form',function(){
        sessionStorage.removeItem('gm2BulkAiSelectedIds');
        sessionStorage.removeItem('gm2BulkAiSelected');
    });
    $('#gm2-bulk-ai').on('click','.gm2-bulk-analyze',function(e){
        e.preventDefault();
        stopProcessing = false;
        var ids=getSelectedIds();
        if(!ids.length) return;

        totalPosts = ids.length;
        applied = 0;
        initBar(totalPosts);

        var $progresses = $('.gm2-bulk-progress');
        if(!$progresses.length){
            $('.gm2-bulk-progress-bar').each(function(){
                var $p = $('<p>',{class:'gm2-bulk-progress'});
                $(this).after($p);
            });
            $progresses = $('.gm2-bulk-progress');
        }
        $progresses.text('');
        var total = ids.length, processed = 0, fatal = false;

        function updateProgress(msg){
            if(msg){
                $progresses.text(msg);
            }else{
                var procText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.processing : 'Processing %1$s / %2$s';
                $progresses.text(procText.replace('%1$s', processed).replace('%2$s', total));
            }
            updateBar(processed);
        }

        function processNext(){
            if(fatal || stopProcessing){
                $('.gm2-bulk-progress-bar').hide();
                $progresses.text('');
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
        $('.gm2-bulk-progress-bar').hide();
        $('.gm2-bulk-progress').text('');
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
        var $btn=$(this);
        var $btnSpinner=$('<span>',{class:'spinner is-active gm2-btn-spinner'});
        $btn.prop('disabled',true).after($btnSpinner);
        showSpinner($res);
        var request=$.post(gm2BulkAi.ajax_url,data);
        request.done(function(resp){
                if(resp && resp.success){
                    row.find('td').eq(0).text(resp.data.title);
                    row.find('td').eq(1).text(resp.data.seo_title);
                    row.find('td').eq(2).text(resp.data.description);
                    row.find('td').eq(3).text(resp.data.slug);
                    row.find('.gm2-result .gm2-undo-btn').remove();
                    $res.find('.gm2-result-icon').remove();
                    row.find('.gm2-result').append(' <button class="button gm2-undo-btn" data-id="'+id+'">'+(gm2BulkAi.i18n?gm2BulkAi.i18n.undo:'Undo')+'</button> <span class="gm2-result-icon">✅</span>');
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
                    $res.find('.gm2-result-icon').remove();
                    $res.append(' <span class="gm2-result-icon">❌</span>');
                }
            });
        request.fail(function(jqXHR, textStatus){
                var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                    ? jqXHR.responseJSON.data
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
                $res.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
                $res.find('.gm2-result-icon').remove();
                $res.append(' <span class="gm2-result-icon">❌</span>');
            });
        request.always(function(){
                hideSpinner($res);
                $btnSpinner.remove();
                $btn.prop('disabled',false);
            });
    });

    $('#gm2-bulk-list').on('click','.gm2-refresh-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result').empty();
        var $btn=$(this);
        var $btnSpinner=$('<span>',{class:'spinner is-active gm2-btn-spinner'});
        $btn.prop('disabled',true).after($btnSpinner);
        showSpinner($res);
        var request=$.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research',post_id:id,refresh:1,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        });
        request.done(function(resp){
            if(resp&&resp.success&&resp.data){
                $res.html(buildHtml(resp.data,id,resp.data.undo));
                row.removeClass('gm2-status-new gm2-status-applied gm2-applied').addClass('gm2-status-analyzed');
                $res.find('.gm2-result-icon').remove();
                $res.append(' <span class="gm2-result-icon">✅</span>');
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error');
                $res.text(msg);
                $res.find('.gm2-result-icon').remove();
                $res.append(' <span class="gm2-result-icon">❌</span>');
            }
        });
        request.fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $res.text(msg || (window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            $res.find('.gm2-result-icon').remove();
            $res.append(' <span class="gm2-result-icon">❌</span>');
        });
        request.always(function(){
            hideSpinner($res);
            $btnSpinner.remove();
            $btn.prop('disabled',false);
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-clear-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result').empty();
        var $btn=$(this);
        var $btnSpinner=$('<span>',{class:'spinner is-active gm2-btn-spinner'});
        $btn.prop('disabled',true).after($btnSpinner);
        showSpinner($res);
        var request=$.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_ai_research_clear',post_id:id,_ajax_nonce:gm2BulkAi.nonce},
            dataType:'json'
        });
        request.done(function(resp){
            if(resp&&resp.success){
                $res.empty();
                $res.append(' <span class="gm2-result-icon">✅</span>');
                row.removeClass('gm2-applied gm2-status-analyzed gm2-status-applied')
                   .addClass('gm2-status-new');
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error');
                $res.text(msg);
                $res.find('.gm2-result-icon').remove();
                $res.append(' <span class="gm2-result-icon">❌</span>');
            }
        });
        request.fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $res.text(msg||(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error'));
            $res.find('.gm2-result-icon').remove();
            $res.append(' <span class="gm2-result-icon">❌</span>');
        });
        request.always(function(){
            hideSpinner($res);
            $btnSpinner.remove();
            $btn.prop('disabled',false);
        });
    });

    $('#gm2-bulk-list').on('click','.gm2-undo-btn',function(e){
        e.preventDefault();
        var id=$(this).data('id');
        var row=$('#gm2-row-'+id);
        var $res=row.find('.gm2-result');
        var $btn=$(this);
        var $btnSpinner=$('<span>',{class:'spinner is-active gm2-btn-spinner'});
        $btn.prop('disabled',true).after($btnSpinner);
        showSpinner($res);
        var request=$.ajax({
            url:gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_undo',post_id:id,_ajax_nonce:gm2BulkAi.apply_nonce},
            dataType:'json'
        });
        request.done(function(resp){
            if(resp&&resp.success){
                row.find('td').eq(0).text(resp.data.title);
                row.find('td').eq(1).text(resp.data.seo_title);
                row.find('td').eq(2).text(resp.data.description);
                row.find('td').eq(3).text(resp.data.slug);
                $res.empty();
                $res.append(' <span class="gm2-result-icon">✅</span>');
                row.removeClass('gm2-status-applied gm2-status-analyzed gm2-applied')
                   .addClass('gm2-status-new');
            }else{
                var msg=(resp&&resp.data)?(resp.data.message||resp.data):(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error');
                $res.text(msg);
                $res.find('.gm2-result-icon').remove();
                $res.append(' <span class="gm2-result-icon">❌</span>');
            }
        });
        request.fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $res.text(msg || (window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error'));
            $res.find('.gm2-result-icon').remove();
            $res.append(' <span class="gm2-result-icon">❌</span>');
        });
        request.always(function(){
            hideSpinner($res);
            $btnSpinner.remove();
            $btn.prop('disabled',false);
        });
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-select-analyzed',function(e){
        e.preventDefault();
        var $btn=$(this);
        var selectText=$btn.data('select')||(gm2BulkAi.i18n&&gm2BulkAi.i18n.selectAnalyzed)||'Select Analyzed';
        var unselectText=$btn.data('unselect')||(gm2BulkAi.i18n&&gm2BulkAi.i18n.unselectAnalyzed)||'Unselect Analyzed';
        if($btn.data('selected')){
            $('#gm2-bulk-list tr.gm2-status-analyzed').each(function(){
                $(this).find('.gm2-select').prop('checked',false);
                $(this).find('.gm2-row-select-all').prop('checked',false).trigger('change');
            });
            $btn.data('selected',false).text(selectText);
        }else{
            $('#gm2-bulk-list tr.gm2-status-analyzed').each(function(){
                $(this).find('.gm2-select').prop('checked',true);
                $(this).find('.gm2-row-select-all').prop('checked',true).trigger('change');
            });
            $btn.data('selected',true).text(unselectText);
        }
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-apply-all',function(e){
        e.preventDefault();
        var queue=[];
        var sel=selectedIds.slice();
        $('#gm2-bulk-list tr').each(function(){
            var id=$(this).attr('id');
            if(!id) return;id=id.replace('gm2-row-','');
            if(sel.length && $.inArray(id, sel) === -1){return;}
            var fields={};
            $(this).find('.gm2-apply:checked').each(function(){
                fields[$(this).data('field')]=$(this).data('value');
            });
            if(Object.keys(fields).length){queue.push({id:id,fields:fields});}
        });
        if(!queue.length) return;
        var $msg=$('#gm2-bulk-apply-msg');
        var total=queue.length;
        var processed=0;
        applied=0;
        initBar(total);
        var savingText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.saving:'Saving %1$s / %2$s...';

        function updateProgress(){
            $msg.text(savingText.replace('%1$s', processed).replace('%2$s', total));
            updateBar(processed);
        }

        function processNext(){
            if(!queue.length){
                var doneText=window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.done:'Done (%1$s/%2$s)';
                $msg.text(doneText.replace('%1$s', processed).replace('%2$s', total));
                return;
            }
            var item=queue.shift();
            var row=$('#gm2-row-'+item.id);
            var $cell=row.find('.gm2-result');
            showSpinner($cell);
            $.ajax({
                url:gm2BulkAi.ajax_url,
                method:'POST',
                data:{action:'gm2_bulk_ai_apply_batch',posts:JSON.stringify({[item.id]:item.fields}),_ajax_nonce:gm2BulkAi.apply_nonce},
                dataType:'json'
            }).done(function(resp){
                hideSpinner($cell);
                if(resp&&resp.success&&resp.data&&resp.data.updated&&resp.data.updated[item.id]){
                    var data=resp.data.updated[item.id];
                    row.find('td').eq(0).text(data.title);
                    row.find('td').eq(1).text(data.seo_title);
                    row.find('td').eq(2).text(data.description);
                    row.find('td').eq(3).text(data.slug);
                    $cell.find('.gm2-undo-btn').remove();
                    $cell.append(' <button class="button gm2-undo-btn" data-id="'+item.id+'">'+(gm2BulkAi.i18n?gm2BulkAi.i18n.undo:'Undo')+'</button> <span> ✓</span>');
                    row.removeClass('gm2-status-new gm2-status-analyzed').addClass('gm2-status-applied gm2-applied');
                    setTimeout(function(){row.removeClass('gm2-applied');},3000);
                }else{
                    var msg=(resp&&resp.data)?(resp.data.message||resp.data):(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error');
                    $cell.text(msg);
                }
                processed++;
                applied=processed;
                updateProgress();
                processNext();
            }).fail(function(jqXHR,textStatus){
                hideSpinner($cell);
                var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
                $cell.text(msg||(window.gm2BulkAi&&gm2BulkAi.i18n?gm2BulkAi.i18n.error:'Error'));
                processed++;
                applied=processed;
                updateProgress();
                processNext();
            });
        }

        updateProgress();
        processNext();
    });

    $('#gm2-bulk-ai').on('click','.gm2-bulk-reset-selected',function(e){
        e.preventDefault();
        var ids=getSelectedIds();
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-apply-msg');
        var total = ids.length, processed = 0;
        initBar(total);
        var resettingText = (window.gm2BulkAi && gm2BulkAi.i18n && (gm2BulkAi.i18n.resettingProgress || gm2BulkAi.i18n.resetting))
            ? (gm2BulkAi.i18n.resettingProgress || gm2BulkAi.i18n.resetting)
            : 'Resetting %1$s / %2$s...';
        function updateProgress(){
            $msg.text(resettingText.replace('%1$s', processed).replace('%2$s', total));
            updateBar(processed);
        }
        updateProgress();
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_reset',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAi.reset_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(ids,function(i,id){
                    var row=$('#gm2-row-'+id);
                    row.find('td').eq(1).text('');
                    row.find('td').eq(2).text('');
                    row.find('td').eq(3).text('');
                    row.find('.gm2-select').prop('checked',false);
                    row.find('.gm2-result').empty();
                    row.removeClass('gm2-status-applied gm2-status-analyzed').addClass('gm2-status-new');
                    processed++;
                    updateProgress();
                });
                var resetCount = resp.data && resp.data.reset ? resp.data.reset : processed;
                var clearedCount = resp.data && resp.data.cleared ? resp.data.cleared : 0;
                var doneText = (window.gm2BulkAi && gm2BulkAi.i18n && gm2BulkAi.i18n.resetClearedDone)
                    ? gm2BulkAi.i18n.resetClearedDone
                    : 'Reset %1$s posts; cleared AI suggestions for %2$s posts';
                $msg.text(doneText.replace('%1$s', resetCount).replace('%2$s', clearedCount));
                updateBar(resetCount);
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
        var ids=getSelectedIds();
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-apply-msg');
        var total = ids.length, cleared = 0;
        initBar(total);
        var clearingText = (window.gm2BulkAi && gm2BulkAi.i18n && (gm2BulkAi.i18n.clearingProgress || gm2BulkAi.i18n.clearing))
            ? (gm2BulkAi.i18n.clearingProgress || gm2BulkAi.i18n.clearing)
            : 'Clearing %1$s / %2$s...';
        function updateProgress(){
            $msg.text(clearingText.replace('%1$s', cleared).replace('%2$s', total));
            updateBar(cleared);
        }
        updateProgress();
        $.ajax({
            url: gm2BulkAi.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_clear',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAi.clear_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                var rows=(resp.data && resp.data.ids)?resp.data.ids:ids;
                $.each(rows,function(i,id){
                    var row=$('#gm2-row-'+id);
                    row.find('.gm2-result').empty();
                    row.removeClass('gm2-status-applied gm2-status-analyzed').addClass('gm2-status-new');
                    cleared++;
                    updateProgress();
                });
                $('.gm2-bulk-progress-bar').hide();
                var doneText = window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.clearDone : 'Cleared AI suggestions for %s posts';
                $msg.text(doneText.replace('%s', resp.data && resp.data.cleared ? resp.data.cleared : cleared));
            }else{
                $('.gm2-bulk-progress-bar').hide();
                $msg.text((resp&&resp.data)?resp.data:(window.gm2BulkAi && gm2BulkAi.i18n ? gm2BulkAi.i18n.error : 'Error'));
            }
        }).fail(function(jqXHR,textStatus){
            $('.gm2-bulk-progress-bar').hide();
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
            terms:getFilterTerms(),
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
        var ids=getSelectedIds();
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
