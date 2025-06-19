jQuery(function($){
    var $form = $('#gm2-add-tariff-form');
    var $msg  = $('#gm2-tariff-msg');

    $form.on('submit', function(e){
        e.preventDefault();

        var $submit = $form.find('input[type="submit"]');
        $submit.prop('disabled', true);
        $msg.addClass('hidden').removeClass('notice-error notice-success').empty();

        var data = {
            action: 'gm2_add_tariff',
            _ajax_nonce: gm2Tariff.nonce,
            tariff_name: $('#tariff_name').val(),
            tariff_percentage: $('#tariff_percentage').val(),
            tariff_status: $('#tariff_status').is(':checked') ? 'enabled' : 'disabled'
        };

      $.post(gm2Tariff.ajax_url, data)
            .done(function(response){
                if(response.success){
                    var t = response.data;
                    var row = '<tr>'+
                        '<td>'+t.name+'</td>'+
                        '<td>'+t.percentage+'%</td>'+
                        '<td>'+t.status.charAt(0).toUpperCase()+t.status.slice(1)+'</td>'+
                        '<td><a href="'+t.edit_url+'">View</a> | <a href="'+t.edit_url+'">Edit</a> | <a href="'+t.delete_url+'" onclick="return confirm(\'Are you sure?\');">Delete</a></td>'+
                    '</tr>';
                    $('#gm2-tariff-table tbody').append(row);
                    $form[0].reset();
                    $msg.text('Tariff added.').addClass('notice-success').removeClass('hidden');
                } else {
                    var msg = response.data && response.data.message ? response.data.message : (response.data || 'Error');
                    $msg.text(msg).addClass('notice-error').removeClass('hidden');
                }
            })
            .fail(function(){
                $msg.text('Request failed.').addClass('notice-error').removeClass('hidden');
            })
            .always(function(){
                $submit.prop('disabled', false);
                if($msg.length){
                    $('html, body').animate({scrollTop: $msg.offset().top}, 300);
                }
            });
    });
});
