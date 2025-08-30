jQuery(function($){
    if (typeof gm2CacheAudit === 'undefined') {
        return;
    }

    // Toggle all asset checkboxes.
    $(document).on('change', '#gm2-cache-select-all', function(){
        $('.gm2-cache-select').prop('checked', this.checked);
    });

    $(document).on('change', '.gm2-cache-select', function(){
        if (!this.checked) {
            $('#gm2-cache-select-all').prop('checked', false);
        } else if ($('.gm2-cache-select').length === $('.gm2-cache-select:checked').length) {
            $('#gm2-cache-select-all').prop('checked', true);
        }
    });

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

    // Bulk fix selected assets sequentially.
    $(document).on('click', '.gm2-cache-bulk-fix', function(e){
        e.preventDefault();
        var $bulkBtn = $(this);
        var items = [];
        $('.gm2-cache-select:checked').each(function(){
            items.push({
                url: $(this).data('url'),
                type: $(this).data('type'),
                $row: $(this).closest('tr'),
                $checkbox: $(this)
            });
        });
        if (!items.length) {
            return;
        }
        $bulkBtn.prop('disabled', true);
        function processNext(){
            if (!items.length) {
                $bulkBtn.prop('disabled', false);
                $('#gm2-cache-select-all').prop('checked', false);
                return;
            }
            var item = items.shift();
            var data = {
                nonce: gm2CacheAudit.fix_nonce,
                url: item.url,
                asset_type: item.type
            };
            $.post(gm2CacheAudit.fix_url, data).done(function(resp){
                if (resp && resp.success) {
                    item.$row.find('.gm2-cache-status').text(resp.data.status);
                    item.$row.find('.gm2-cache-fix').text(resp.data.fix);
                    item.$row.find('.gm2-cache-fix-now').remove();
                }
            }).always(function(){
                item.$checkbox.prop('checked', false);
                processNext();
            });
        }
        processNext();
    });
});
