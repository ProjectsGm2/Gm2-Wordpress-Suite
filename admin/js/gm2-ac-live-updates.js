jQuery(function($){
    function fetchCarts(){
        $.post(gm2AcLive.ajax_url, {
            action: 'gm2_ac_get_carts',
            nonce: gm2AcLive.nonce,
            paged: gm2AcLive.paged,
            s: gm2AcLive.s
        }).done(function(response){
            if(response && response.success && response.data && response.data.rows){
                $('#the-list').html(response.data.rows);
            }
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
        });
    });
});
