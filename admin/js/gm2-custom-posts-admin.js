jQuery(function($){
    var table = $('#gm2-fields-table tbody');

    function addRow(data){
        data = data || {};
        var row = $('<tr>\
<td class="gm2-move-field"><span class="dashicons dashicons-move"></span></td>\
<td><input type="text" class="gm2-field-label" /></td>\
<td><input type="text" class="gm2-field-slug" /></td>\
<td><select class="gm2-field-type">\
<option value="text">Text</option>\
<option value="number">Number</option>\
<option value="checkbox">Checkbox</option>\
<option value="select">Dropdown</option>\
<option value="radio">Radio</option>\
</select></td>\
<td><input type="text" class="gm2-field-default" /></td>\
<td><input type="text" class="gm2-field-options" placeholder="value:Label,value2:Label2" /></td>\
<td><input type="text" class="gm2-cond-field" /></td>\
<td><input type="text" class="gm2-cond-value" /></td>\
<td><button type="button" class="button gm2-remove-field">Remove</button></td>\
</tr>');
        table.append(row);
        if(data.label){ row.find('.gm2-field-label').val(data.label); }
        if(data.slug){ row.find('.gm2-field-slug').val(data.slug); }
        if(data.type){ row.find('.gm2-field-type').val(data.type); }
        if(data.default){ row.find('.gm2-field-default').val(data.default); }
        if(data.options){ row.find('.gm2-field-options').val(data.options); }
        if(data.conditional){
            row.find('.gm2-cond-field').val(data.conditional.field || '');
            row.find('.gm2-cond-value').val(data.conditional.value || '');
        }
    }

    $('#gm2-add-field').on('click', function(){
        addRow();
    });

    table.on('click', '.gm2-remove-field', function(){
        $(this).closest('tr').remove();
    });

    table.sortable({
        handle: '.gm2-move-field',
        helper: function(e, ui){
            ui.children().each(function(){
                $(this).width($(this).width());
            });
            return ui;
        }
    });

    $('#gm2-fields-form').on('submit', function(e){
        e.preventDefault();
        var fields = [];
        table.find('tr').each(function(){
            var row = $(this);
            fields.push({
                label: row.find('.gm2-field-label').val(),
                slug: row.find('.gm2-field-slug').val(),
                type: row.find('.gm2-field-type').val(),
                default: row.find('.gm2-field-default').val(),
                options: row.find('.gm2-field-options').val(),
                conditional: {
                    field: row.find('.gm2-cond-field').val(),
                    value: row.find('.gm2-cond-value').val()
                }
            });
        });
        var data = {
            action: 'gm2_save_cpt_fields',
            nonce: gm2CPTFields.nonce,
            slug: $('#gm2-fields-form input[name="pt_slug"]').val(),
            fields: fields
        };
        $.post(gm2CPTFields.ajax, data, function(resp){
            if(resp && resp.success){
                alert('Fields saved');
            }else{
                alert('Error saving fields');
            }
        });
    });
});
