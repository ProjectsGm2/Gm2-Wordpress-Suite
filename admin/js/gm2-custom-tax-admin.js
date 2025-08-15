jQuery(function($){
    var args = gm2TaxArgs.args || [];

    function esc(str){ return $('<div>').text(str).html(); }

    function renderArgs(){
        var tbody = $('#gm2-tax-args-table tbody').empty();
        if(!args.length){
            tbody.append('<tr><td colspan="3">'+esc(gm2TaxArgs.noArgs || 'No arguments')+'</td></tr>');
            return;
        }
        $.each(args, function(i, a){
            var val = a.value;
            if(val === true){ val = 'true'; }
            if(val === false){ val = 'false'; }
            tbody.append('<tr>\
<td>'+esc(a.key)+'</td>\
<td>'+esc($.isArray(val)? val.join(', ') : val)+'</td>\
<td><button class="button gm2-tax-edit-arg" data-index="'+i+'">Edit</button> <button class="button gm2-tax-delete-arg" data-index="'+i+'">Delete</button></td>\
</tr>');
        });
    }

    function showArgControl(key, value){
        var wrap = $('#gm2-tax-arg-value-wrap').empty();
        if(key === 'public' || key === 'hierarchical'){
            var chk = $('<label><input type="checkbox" id="gm2-tax-arg-value" value="1"/> '+key+'</label>');
            if(value){ chk.find('input').prop('checked', true); }
            wrap.append(chk);
        }else{
            wrap.append('<input type="text" id="gm2-tax-arg-value" class="regular-text" />');
            $('#gm2-tax-arg-value').val(value);
        }
    }

    function showArgForm(data, index){
        $('#gm2-tax-arg-index').val(index !== undefined ? index : '');
        $('#gm2-tax-arg-key').prop('disabled', index !== undefined).val(data ? data.key : '');
        showArgControl(data ? data.key : '', data ? data.value : '');
        $('#gm2-tax-arg-form').show();
    }

    function saveAll(cb){
        var data = {
            action: 'gm2_save_tax_args',
            nonce: gm2TaxArgs.nonce,
            slug: gm2TaxArgs.slug,
            label: $('#gm2-tax-label').val(),
            post_types: $('#gm2-tax-post-types').val(),
            args: args
        };
        $.post(gm2TaxArgs.ajax, data, function(resp){
            if(resp && resp.success){
                args = [];
                $.each(resp.data.args || {}, function(key, val){ args.push({ key:key, value:val }); });
                renderArgs();
                if(cb) cb(true);
            }else{
                alert('Error saving');
                if(cb) cb(false);
            }
        });
    }

    $('#gm2-add-tax-arg').on('click', function(){ showArgForm(); });
    $('#gm2-tax-arg-cancel').on('click', function(){ $('#gm2-tax-arg-form').hide(); });
    $('#gm2-tax-arg-save').on('click', function(){
        var idx = $('#gm2-tax-arg-index').val();
        var key = $('#gm2-tax-arg-key').val();
        var val = (key === 'public' || key === 'hierarchical') ? $('#gm2-tax-arg-value').is(':checked') : $('#gm2-tax-arg-value').val();
        var obj = { key:key, value:val };
        if(idx === ''){ args.push(obj); } else { args[idx] = obj; }
        saveAll(function(){ $('#gm2-tax-arg-form').hide(); });
    });
    $('#gm2-tax-args-table').on('click', '.gm2-tax-edit-arg', function(){
        var idx = $(this).data('index');
        showArgForm(args[idx], idx);
    });
    $('#gm2-tax-args-table').on('click', '.gm2-tax-delete-arg', function(){
        var idx = $(this).data('index');
        args.splice(idx,1);
        saveAll();
    });

    $('#gm2-tax-save').on('click', function(){ saveAll(); });

    renderArgs();
});

