jQuery(function($){
    if (typeof gm2CacheAudit === 'undefined') {
        return;
    }

    function showError($row, msg){
        if (typeof wp !== 'undefined' && wp.a11y && wp.a11y.speak) {
            wp.a11y.speak(msg);
        }
        var $cell = $row.find('.gm2-cache-fix');
        var $notice = $('<div class="gm2-cache-error notice notice-error inline"><p></p></div>');
        $notice.find('p').text(msg);
        $cell.append($notice);
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
            asset_type: $btn.data('type'),
            handle: $btn.data('handle')
        };
        $btn.prop('disabled', true);
        $.post(gm2CacheAudit.fix_url, data).done(function(resp){
            var $row = $btn.closest('tr');
            if (resp && resp.success && resp.data) {
                $row.find('.gm2-cache-status').text(resp.data.status);
                $row.find('.gm2-cache-fix').text(resp.data.fix);
                if (typeof resp.data.ttl !== 'undefined') {
                    $row.find('td').eq(3).text(resp.data.ttl);
                }
                var $checkbox = $row.find('.gm2-cache-select');
                if (resp.data.needs_attention) {
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true);
                    }
                    $btn.prop('disabled', false);
                } else {
                    $checkbox.remove();
                    $btn.remove();
                    $('#gm2-cache-select-all').prop('checked', false);
                }
            } else {
                var msg = resp && resp.data && resp.data.message ? resp.data.message : gm2CacheAudit.generic_error;
                showError($row, msg);
                $btn.prop('disabled', false);
            }
        }).fail(function(){
            showError($btn.closest('tr'), gm2CacheAudit.generic_error);
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
                handle: $(this).data('handle'),
                $row: $(this).closest('tr'),
                $checkbox: $(this)
            });
        });
        if (!items.length) {
            return;
        }
        var total = items.length,
            processed = 0,
            stopOnError = false,
            $progress = $('#gm2-fix-progress').show().removeClass('complete'),
            $bar = $progress.find('.gm2-progress-bar').width('0%'),
            $text = $progress.find('.gm2-progress-text').text('0%');
        $bulkBtn.prop('disabled', true);
        function processNext(){
            if (!items.length || stopOnError) {
                return;
            }
            var item = items.shift();
            var data = {
                nonce: gm2CacheAudit.fix_nonce,
                url: item.url,
                asset_type: item.type,
                handle: item.handle
            };
            $.post(gm2CacheAudit.fix_url, data).done(function(resp){
                if (resp && resp.success && resp.data) {
                    item.$row.find('.gm2-cache-status').text(resp.data.status);
                    item.$row.find('.gm2-cache-fix').text(resp.data.fix);
                    if (typeof resp.data.ttl !== 'undefined') {
                        item.$row.find('td').eq(3).text(resp.data.ttl);
                    }
                    if (resp.data.needs_attention) {
                        item.$checkbox.prop('checked', true);
                    } else {
                        item.$row.find('.gm2-cache-fix-now').remove();
                        item.$checkbox.remove();
                    }
                } else {
                    var msg = resp && resp.data && resp.data.message ? resp.data.message : gm2CacheAudit.generic_error;
                    showError(item.$row, msg);
                    if (typeof wp !== 'undefined' && wp.a11y && wp.a11y.speak) {
                        wp.a11y.speak(gm2CacheAudit.bulk_halted.replace('%s', msg));
                    }
                    stopOnError = true;
                }
            }).fail(function(){
                var msg = gm2CacheAudit.generic_error;
                showError(item.$row, msg);
                if (typeof wp !== 'undefined' && wp.a11y && wp.a11y.speak) {
                    wp.a11y.speak(gm2CacheAudit.bulk_halted.replace('%s', msg));
                }
                stopOnError = true;
            }).always(function(){
                processed++;
                var percent = Math.round((processed / total) * 100);
                $bar.css('width', percent + '%');
                $text.text(percent + '%');
                if (processed >= total || stopOnError) {
                    $bulkBtn.prop('disabled', false);
                    $('#gm2-cache-select-all').prop('checked', false);
                    $progress.addClass('complete');
                    setTimeout(function(){ $progress.fadeOut(); }, 1000);
                } else {
                    processNext();
                }
            });
        }
        processNext();
    });
});
