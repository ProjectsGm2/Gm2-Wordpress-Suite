jQuery(function($){
    var stop=false;
    $('#gm2-bulk-term-select-all').on('click',function(){
        var c=$(this).prop('checked');
        $('#gm2-bulk-term-list .gm2-select').prop('checked',c);
    });
    $('#gm2-bulk-term-list').on('change','.gm2-row-select-all',function(){
        var checked=$(this).prop('checked');
        $(this).closest('td').find('.gm2-apply').prop('checked',checked);
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-analyze',function(e){
        e.preventDefault();
        stop=false;
        var ids=[];
        $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        $('#gm2-bulk-term-progress-bar').attr('max',ids.length).val(0).show();
        function next(){
            if(stop||!ids.length){$('#gm2-bulk-term-progress-bar').hide();return;}
            var key=ids.shift();
            var parts=key.split(':');
            var tax=parts[0], id=parts[1];
            var row=$('#gm2-term-'+tax+'-'+id);
            var cell=row.find('.gm2-result').empty();
            $('<span>',{class:'spinner is-active gm2-ai-spinner'}).appendTo(cell);
            $.ajax({
                url: gm2BulkAiTax.ajax_url,
                method:'POST',
                data:{action:'gm2_ai_research',term_id:id,taxonomy:tax,_ajax_nonce:gm2BulkAiTax.nonce},
                dataType:'json'
            }).done(function(resp){
                cell.find('.gm2-ai-spinner').remove();
                if(resp&&resp.success&&resp.data){
                    var html='<p><label><input type="checkbox" class="gm2-row-select-all"> '+gm2BulkAiTax.i18n.selectAll+'</label></p>';
                    if(resp.data.seo_title){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="'+resp.data.seo_title.replace(/"/g,'&quot;')+'"> '+resp.data.seo_title+'</label></p>';}
                    if(resp.data.description){html+='<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="'+resp.data.description.replace(/"/g,'&quot;')+'"> '+resp.data.description+'</label></p>';}
                    html+='<p><button class="button gm2-apply-btn" data-key="'+key+'">'+gm2BulkAiTax.i18n.apply+'</button></p>';
                    cell.html(html);
                }else{cell.text(gm2BulkAiTax.i18n.error);}
            }).fail(function(){
                cell.find('.gm2-ai-spinner').remove();
                cell.text(gm2BulkAiTax.i18n.error);
            }).always(function(){
                var done = parseInt($('#gm2-bulk-term-progress-bar').val(),10)+1;
                $('#gm2-bulk-term-progress-bar').val(done);
                next();
            });
        }
        next();
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-cancel',function(e){
        e.preventDefault();stop=true;$('#gm2-bulk-term-progress-bar').hide();
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-desc',function(e){
        e.preventDefault();
        stop=false;
        var ids=[];
        $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        $('#gm2-bulk-term-progress-bar').attr('max',ids.length).val(0).show();
        function next(){
            if(stop||!ids.length){$('#gm2-bulk-term-progress-bar').hide();return;}
            var key=ids.shift();
            var parts=key.split(':');
            var tax=parts[0], id=parts[1];
            var row=$('#gm2-term-'+tax+'-'+id);
            var cell=row.find('.gm2-tax-desc').empty();
            $('<span>',{class:'spinner is-active gm2-ai-spinner'}).appendTo(cell);
            var name=row.find('td').eq(0).text();
            $.ajax({
                url: gm2BulkAiTax.ajax_url,
                method:'POST',
                data:{action:'gm2_ai_generate_tax_description',term_id:id,taxonomy:tax,name:name,_ajax_nonce:gm2BulkAiTax.desc_nonce},
                dataType:'json'
            }).done(function(resp){
                cell.find('.gm2-ai-spinner').remove();
                if(resp&&resp.success&&resp.data){
                    cell.text(resp.data);
                }else{cell.text(gm2BulkAiTax.i18n.error);}
            }).fail(function(){
                cell.find('.gm2-ai-spinner').remove();
                cell.text(gm2BulkAiTax.i18n.error);
            }).always(function(){
                var done=parseInt($('#gm2-bulk-term-progress-bar').val(),10)+1;
                $('#gm2-bulk-term-progress-bar').val(done);
                next();
            });
        }
        next();
    });
    $('#gm2-bulk-term-list').on('click','.gm2-apply-btn',function(e){
        e.preventDefault();
        var key=$(this).data('key');
        var parts=key.split(':');
        var data={action:'gm2_bulk_ai_tax_apply',term_id:parts[1],taxonomy:parts[0],_ajax_nonce:gm2BulkAiTax.apply_nonce};
        $(this).closest('.gm2-result').find('.gm2-apply:checked').each(function(){
            data[$(this).data('field')]=$(this).data('value');
        });
        var cell=$(this).closest('.gm2-result');
        $('<span>',{class:'spinner is-active gm2-ai-spinner'}).appendTo(cell);
        $.post(gm2BulkAiTax.ajax_url,data).done(function(){
            cell.find('.gm2-ai-spinner').remove();
        }).fail(function(){
            cell.find('.gm2-ai-spinner').remove();
            cell.text(gm2BulkAiTax.i18n.error);
        });
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-select-analyzed',function(e){
        e.preventDefault();
        $('#gm2-bulk-term-list .gm2-row-select-all').each(function(){
            $(this).prop('checked',true).trigger('change');
        });
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-apply-all',function(e){
        e.preventDefault();
        var items={};
        $('#gm2-bulk-term-list tr').each(function(){
            var k=$(this).data('key');if(!k) return;
            var fields={};
            $(this).find('.gm2-apply:checked').each(function(){fields[$(this).data('field')]=$(this).data('value');});
            if(Object.keys(fields).length){items[k]=fields;}
        });
        if($.isEmptyObject(items)) return;
        $.ajax({
            url: gm2BulkAiTax.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_tax_apply_batch',terms:JSON.stringify(items),_ajax_nonce:gm2BulkAiTax.apply_nonce},
            dataType:'json'
        }).fail(function(){alert(gm2BulkAiTax.i18n.error);});
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-schedule',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        $.post(gm2BulkAiTax.ajax_url,{action:'gm2_ai_tax_batch_schedule',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAiTax.batch_nonce});
    });
    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-cancel-batch',function(e){
        e.preventDefault();
        $.post(gm2BulkAiTax.ajax_url,{action:'gm2_ai_tax_batch_cancel',_ajax_nonce:gm2BulkAiTax.batch_nonce});
    });

    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-reset-selected',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-term-msg');
        $msg.text(gm2BulkAiTax.i18n.resetting);
        $.ajax({
            url: gm2BulkAiTax.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_tax_reset',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAiTax.reset_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(ids,function(i,key){
                    var parts=key.split(':');
                    var row=$('#gm2-term-'+parts[0]+'-'+parts[1]);
                    row.find('.column-seo_title').text('');
                    row.find('.column-description').text('');
                    row.find('.gm2-result').empty();
                });
                $msg.text(gm2BulkAiTax.i18n.resetDone.replace('%s',resp.data.reset));
            }else{
                $msg.text((resp&&resp.data)?resp.data:gm2BulkAiTax.i18n.error);
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||gm2BulkAiTax.i18n.error);
        });
    });

    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-reset-ai',function(e){
        e.preventDefault();
        var ids=[];
        $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        var $msg=$('#gm2-bulk-term-msg');
        $msg.text(gm2BulkAiTax.i18n.resetting);
        $.ajax({
            url: gm2BulkAiTax.ajax_url,
            method:'POST',
            data:{action:'gm2_bulk_ai_tax_clear',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAiTax.clear_nonce},
            dataType:'json'
        }).done(function(resp){
            if(resp&&resp.success){
                $.each(ids,function(i,key){
                    var parts=key.split(':');
                    var row=$('#gm2-term-'+parts[0]+'-'+parts[1]);
                    row.find('.gm2-result').empty();
                });
                $msg.text(gm2BulkAiTax.i18n.clearDone.replace('%s',resp.data.cleared));
            }else{
                $msg.text((resp&&resp.data)?resp.data:gm2BulkAiTax.i18n.error);
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||gm2BulkAiTax.i18n.error);
        });
    });

    $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-reset-all',function(e){
        e.preventDefault();
        var data={
            action:'gm2_bulk_ai_tax_reset',
            all:1,
            taxonomy:$('select[name="gm2_taxonomy"]').val(),
            status:$('select[name="gm2_tax_status"]').val(),
            search:$('input[name="gm2_tax_search"]').val()||'',
            seo_status:$('select[name="gm2_tax_seo_status"]').val(),
            missing_title:$('input[name="gm2_bulk_ai_tax_missing_title"]').is(':checked')?1:0,
            missing_desc:$('input[name="gm2_bulk_ai_tax_missing_description"]').is(':checked')?1:0,
            _ajax_nonce:gm2BulkAiTax.reset_nonce
        };
        var $msg=$('#gm2-bulk-term-msg');
        $msg.text(gm2BulkAiTax.i18n.resetting);
        $.post(gm2BulkAiTax.ajax_url,data).done(function(resp){
            if(resp&&resp.success){
                location.reload();
            }else{
                $msg.text((resp&&resp.data)?resp.data:gm2BulkAiTax.i18n.error);
            }
        }).fail(function(jqXHR,textStatus){
            var msg=(jqXHR&&jqXHR.responseJSON&&jqXHR.responseJSON.data)?jqXHR.responseJSON.data:(jqXHR&&jqXHR.responseText?jqXHR.responseText:textStatus);
            $msg.text(msg||gm2BulkAiTax.i18n.error);
        });
    });
});
