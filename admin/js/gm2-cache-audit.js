jQuery(function($){
    if (typeof gm2CacheAudit === 'undefined') {
        return;
    }

    $(document).on('click', '.gm2-cache-fix-now', function(e){
        e.preventDefault();
        var $btn = $(this);
        var data = {
            nonce: gm2CacheAudit.fix_nonce,
            url: $btn.data('url'),
            asset_type: $btn.data('type')
        };
        $btn.prop('disabled', true);
        $.post(gm2CacheAudit.fix_url, data).done(function(resp){
            if (resp && resp.success) {
                var $row = $btn.closest('tr');
                $row.find('.gm2-cache-status').text(resp.data.status);
                $row.find('.gm2-cache-fix').text(resp.data.fix);
                $btn.remove();
            } else {
                $btn.prop('disabled', false);
            }
        }).fail(function(){
            $btn.prop('disabled', false);
        });
    });
});
