jQuery(function($){
    var $form = $('#gm2-add-tariff-form');
    if (!$form.length) {
        return;
    }

    var $error = $('#gm2-tariff-error');
    if (!$error.length) {
        $error = $('<div id="gm2-tariff-error" class="notice notice-error" style="display:none;"></div>');
        $form.before($error);
    }

    $form.on('submit', function(e){
        e.preventDefault();
        var data = {
            action: 'gm2_add_tariff',
            _ajax_nonce: gm2Tariff.nonce,
            tariff_name: $('#tariff_name').val(),
            tariff_percentage: $('#tariff_percentage').val(),
            tariff_status: $('#tariff_status').is(':checked') ? 'enabled' : 'disabled'
        };
        $.post(gm2Tariff.ajax_url, data, function(response){
            if (response.success) {
                var t = response.data;
                var row = '<tr>'+
                    '<td>'+t.name+'</td>'+
                    '<td>'+t.percentage+'%</td>'+
                    '<td>'+t.status.charAt(0).toUpperCase()+t.status.slice(1)+'</td>'+
                    '<td><a href="'+t.edit_url+'">View</a> | <a href="'+t.edit_url+'">Edit</a> | <a href="'+t.delete_url+'" onclick="return confirm(\'Are you sure?\');">Delete</a></td>'+
                '</tr>';
                $('#gm2-tariff-table tbody').append(row);
                $form[0].reset();
                $error.hide().text('');
            } else {
                var msg = response.data && response.data.message ? response.data.message : response.data;
                $error.text(msg || 'Error').show();
            }
        });
    });
});
