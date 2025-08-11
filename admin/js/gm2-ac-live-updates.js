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
    setInterval(fetchCarts, 30000);
});
