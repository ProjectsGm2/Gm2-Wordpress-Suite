jQuery(function($){
    $('#gm2-start-thumb-regeneration').on('click', function(e){
        e.preventDefault();
        var $progress = $('#gm2-thumb-progress');
        var $bar = $progress.find('progress');
        var $percent = $progress.find('.percent');
        $progress.show();
        $bar.val(0);
        $percent.text('0%');
        $.post(ajaxurl, {
            action: 'gm2_regenerate_thumbnails',
            nonce: gm2Thumbs.nonce
        }, function(resp){
            if(resp && resp.success){
                $bar.attr('max', resp.data.total).val(resp.data.total);
                $percent.text('100%');
            } else {
                $percent.text('error');
            }
        });
    });
});
