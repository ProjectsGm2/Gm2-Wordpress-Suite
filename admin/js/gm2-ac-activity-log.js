jQuery(function($){
    function removeExisting(row){
        if(row.next().hasClass('gm2-ac-activity-row')){
            row.next().remove();
            return true;
        }
        return false;
    }
    $(document).on('click', '.gm2-ac-activity-log-button', function(e){
        e.preventDefault();
        var btn = $(this);
        var tr = btn.closest('tr');
        if(removeExisting(tr)){
            return;
        }
        btn.prop('disabled', true);
        $.post(gm2AcActivityLog.ajax_url, {
            action: 'gm2_ac_get_activity',
            nonce: gm2AcActivityLog.nonce,
            ip: btn.data('ip')
        }).done(function(response){
            var colspan = tr.children().length;
            var html = '<tr class="gm2-ac-activity-row"><td colspan="'+colspan+'">';
            if(response.success && response.data.length){
                html += '<ul class="gm2-ac-activity-log">';
                response.data.forEach(function(item){
                    html += '<li><strong>'+item.changed_at+'</strong> '+item.action+' '+item.sku+' x'+item.quantity+'</li>';
                });
                html += '</ul>';
            } else {
                html += '<em>'+gm2AcActivityLog.empty+'</em>';
            }
            html += '</td></tr>';
            tr.after(html);
        }).fail(function(){
            var colspan = tr.children().length;
            tr.after('<tr class="gm2-ac-activity-row"><td colspan="'+colspan+'"><em>'+gm2AcActivityLog.error+'</em></td></tr>');
        }).always(function(){
            btn.prop('disabled', false);
        });
    });
});
