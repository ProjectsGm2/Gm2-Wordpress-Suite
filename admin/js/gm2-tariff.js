jQuery(function($){
    $('#gm2-add-tariff-form').on('submit', function(e){
        e.preventDefault();
        var data = {
            action: 'gm2_add_tariff',
            _ajax_nonce: $('#gm2_add_tariff_nonce').val(),
            tariff_name: $('#tariff_name').val(),
            tariff_percentage: $('#tariff_percentage').val(),
            tariff_status: $('#tariff_status').is(':checked') ? 'enabled' : 'disabled'
        };
        $.post(gm2Tariff.ajax_url, data, function(response){
            if(response.success){
                var t = response.data;
                var row = '<tr>'+
                    '<td>'+t.name+'</td>'+
                    '<td>'+t.percentage+'%</td>'+
                    '<td>'+t.status.charAt(0).toUpperCase()+t.status.slice(1)+'</td>'+
                    '<td><a href="'+t.edit_url+'">View</a> | <a href="'+t.edit_url+'">Edit</a> | <a href="'+t.delete_url+'" onclick="return confirm(\'Are you sure?\');">Delete</a></td>'+
                '</tr>';
                $('#gm2-tariff-table tbody').append(row);
                $('#gm2-add-tariff-form')[0].reset();
            } else {
                alert(response.data || 'Error');
            }
        });
    });
});
