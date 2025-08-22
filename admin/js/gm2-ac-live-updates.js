jQuery(function($){
    function showError(message){
        var $notice = $('<div class="notice notice-error is-dismissible gm2-ac-error"><p>'+message+'</p></div>');
        $('.gm2-ac-error').remove();
        $('.wrap').prepend($notice);
    }
    function fetchCarts(){
        var $list = $('#the-list');
        $.post(gm2AcLive.ajax_url, {
            action: 'gm2_ac_get_carts',
            nonce: gm2AcLive.nonce,
            paged: gm2AcLive.paged,
            s: gm2AcLive.s
        }).done(function(response){
            if(response && response.success && response.data && response.data.rows){
                $('#the-list').html(response.data.rows);
            }
        }).fail(function(){
            showError('Failed to load carts.');
        }).always(function(){
            $list.removeClass('loading');
        });
    }
    fetchCarts();
    setInterval(fetchCarts, 30000);

    $('#gm2-ac-process').on('click', function(e){
        e.preventDefault();
        $.post(gm2AcLive.ajax_url, {
            action: 'gm2_ac_process',
            nonce: gm2AcLive.process_nonce
        }).done(function(){
            fetchCarts();
            refreshSummary();
        });
    });

    function refreshSummary(){
        var summary = document.getElementById('gm2-ac-summary');
        $.post(gm2AcLive.ajax_url, {
            action: 'gm2_ac_refresh_summary',
            nonce: gm2AcLive.summary_nonce
        }).done(function(response){
            if(response && response.success && response.data){
                document.querySelector('#gm2-ac-total .count').textContent = response.data.total;
                document.querySelector('#gm2-ac-pending .count').textContent = response.data.pending;
                document.querySelector('#gm2-ac-abandoned .count').textContent = response.data.abandoned;
                document.querySelector('#gm2-ac-recovered .count').textContent = response.data.recovered;
                document.querySelector('#gm2-ac-potential .count').textContent = response.data.potential_revenue;
                document.querySelector('#gm2-ac-recovered-revenue .count').textContent = response.data.recovered_revenue;
            }
        }).fail(function(){
            showError('Failed to refresh summary.');
        }).always(function(){
            summary.classList.remove('loading');
        });
    }

    $('#gm2-ac-refresh-summary').on('click', function(e){
        e.preventDefault();
        refreshSummary();
    });
});
