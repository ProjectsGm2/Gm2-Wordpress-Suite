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
        var targets = (gm2TaxArgs.fields || []).map(function(f){ return f.slug; });
        targets.push('page_id','post_id');
        gm2Conditions.init($('#gm2-tax-conditions'), { targets: targets, data: data ? data.conditions : [] });
        $('#gm2-tax-arg-form').show();
    }

    function saveAll(cb){
        var data = {
            action: 'gm2_save_tax_args',
            nonce: gm2TaxArgs.nonce,
            slug: gm2TaxArgs.slug,
            label: $('#gm2-tax-label').val(),
            post_types: $('#gm2-tax-post-types').val(),
            hierarchical: $('#gm2-tax-hierarchical').is(':checked') ? 1 : 0,
            public: $('#gm2-tax-public').is(':checked') ? 1 : 0,
            show_ui: $('#gm2-tax-show_ui').is(':checked') ? 1 : 0,
            show_in_nav_menus: $('#gm2-tax-show_in_nav_menus').is(':checked') ? 1 : 0,
            show_admin_column: $('#gm2-tax-show_admin_column').is(':checked') ? 1 : 0,
            show_tagcloud: $('#gm2-tax-show_tagcloud').is(':checked') ? 1 : 0,
            show_in_quick_edit: $('#gm2-tax-show_in_quick_edit').is(':checked') ? 1 : 0,
            show_in_rest: $('#gm2-tax-show-rest').is(':checked') ? 1 : 0,
            rewrite_slug: $('#gm2-tax-rewrite-slug').val(),
            default_terms: $('#gm2-tax-default-terms').val(),
            orderby: $('#gm2-tax-orderby').val(),
            order: $('#gm2-tax-order').val(),
            term_fields: $('#gm2-tax-term-fields').val(),
            args: args
        };
        $.post(gm2TaxArgs.ajax, data, function(resp){
            if(resp && resp.success){
                args = [];
                $.each(resp.data.args || {}, function(key, val){ args.push({ key:key, value: val.value !== undefined ? val.value : val, conditions: val.conditions || [] }); });
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
        var obj = { key:key, value:val, conditions: gm2Conditions.getData($('#gm2-tax-conditions')) };
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

