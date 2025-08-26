jQuery(function($){
    function removeExisting(row){
        if(row.next().hasClass('gm2-ac-activity-row')){
            row.next().remove();
            return true;
        }
        return false;
    }
    function loadPage(wrap){
        var page = wrap.data('page') || 1;
        if(wrap.data('loading')){
            return;
        }
        wrap.data('loading', true);
        $.post(gm2AcActivityLog.ajax_url, {
            action: 'gm2_ac_get_activity',
            nonce: gm2AcActivityLog.nonce,
            ip: wrap.data('ip'),
            page: page,
            per_page: gm2AcActivityLog.per_page
        }).done(function(response){
            var hasActivity = response.success && response.data && response.data.activity && response.data.activity.length;
            var hasVisits = response.success && response.data && response.data.visits && response.data.visits.length;
            var activityList = wrap.find('ul.gm2-ac-activity-log');
            var events = [];
            if(hasActivity){
                response.data.activity.forEach(function(item){
                    events.push({
                        time: new Date(item.changed_at),
                        html: '<li><strong>'+item.changed_at+'</strong> '+item.action+' '+item.sku+' x'+item.quantity+'</li>'
                    });
                });
            }
            if(hasVisits){
                response.data.visits.forEach(function(visit){
                    events.push({
                        time: new Date(visit.visit_start),
                        html: '<li>'+visit.ip_address+' Entry @ <strong>'+visit.visit_start+'</strong> \u2192 Revisit Entry URL '+visit.entry_url+'</li>'
                    });
                    if(visit.visit_end){
                        events.push({
                            time: new Date(visit.visit_end),
                            html: '<li>'+visit.ip_address+' Exit @ <strong>'+visit.visit_end+'</strong> \u2192 Revisit Exit URL '+visit.exit_url+'</li>'
                        });
                    }
                });
            }
            if(events.length){
                if(!activityList.length){
                    activityList = $('<ul class="gm2-ac-activity-log"></ul>').appendTo(wrap);
                }
                events.sort(function(a, b){ return a.time - b.time; });
                events.forEach(function(ev){
                    activityList.append(ev.html);
                });
            }
            if(page === 1 && !hasActivity && !hasVisits){
                wrap.append('<em>'+gm2AcActivityLog.empty+'</em>');
            }
            if((hasActivity && response.data.activity.length === gm2AcActivityLog.per_page) || (hasVisits && response.data.visits.length === gm2AcActivityLog.per_page)){
                wrap.data('page', page + 1);
                wrap.data('end', false);
                if(!wrap.find('.gm2-ac-load-more').length){
                    wrap.append('<button class="gm2-ac-load-more">'+gm2AcActivityLog.load_more+'</button>');
                }
            } else {
                wrap.find('.gm2-ac-load-more').remove();
                wrap.data('end', true);
            }
        }).fail(function(){
            if(page === 1){
                wrap.append('<em>'+gm2AcActivityLog.error+'</em>');
            }
            wrap.find('.gm2-ac-load-more').remove();
        }).always(function(){
            wrap.data('loading', false);
        });
    }
    $(document).on('click', '.gm2-ac-activity-log-button', function(e){
        e.preventDefault();
        var btn = $(this);
        var tr = btn.closest('tr');
        if(removeExisting(tr)){
            return;
        }
        btn.prop('disabled', true);
        var colspan = tr.children().length;
        var row = $('<tr class="gm2-ac-activity-row"><td colspan="'+colspan+'"><div class="gm2-ac-activity-wrap" style="max-height:300px;overflow:auto;"></div></td></tr>');
        tr.after(row);
        var wrap = row.find('.gm2-ac-activity-wrap');
        wrap.data({ ip: btn.data('ip'), page: 1, loading: false, end: false });
        loadPage(wrap);
        btn.prop('disabled', false);
    });
    $(document).on('click', '.gm2-ac-load-more', function(e){
        e.preventDefault();
        var wrap = $(this).closest('.gm2-ac-activity-wrap');
        wrap.data('end', false);
        loadPage(wrap);
    });
    $(document).on('scroll', '.gm2-ac-activity-wrap', function(){
        var wrap = $(this);
        if(wrap.data('loading') || wrap.data('end')){
            return;
        }
        if(this.scrollTop + wrap.innerHeight() + 20 >= this.scrollHeight){
            wrap.find('.gm2-ac-load-more').trigger('click');
        }
    });
});
