jQuery(function($){
    var fields = gm2CPTFields.fields || [];
    var args   = gm2CPTFields.args || [];

    function esc(str){ return $('<div>').text(str).html(); }

    function renderFields(){
        var tbody = $('#gm2-fields-table tbody').empty();
        if(!fields.length){
            tbody.append('<tr><td colspan="6">'+esc(gm2CPTFields.noFields || 'No fields')+'</td></tr>');
            return;
        }
        $.each(fields, function(i, f){
            var row = $('<tr>\
<td>'+esc(f.label)+'</td>\
<td>'+esc(f.slug)+'</td>\
<td>'+esc(f.type)+'</td>\
<td>'+esc(f.default || '')+'</td>\
<td>'+esc(f.description || '')+'</td>\
<td><button class="button gm2-edit-field" data-index="'+i+'">Edit</button> <button class="button gm2-delete-field" data-index="'+i+'">Delete</button></td>\
</tr>');
            tbody.append(row);
        });
    }

    function renderArgs(){
        var tbody = $('#gm2-args-table tbody').empty();
        if(!args.length){
            tbody.append('<tr><td colspan="3">'+esc(gm2CPTFields.noArgs || 'No arguments')+'</td></tr>');
            return;
        }
        $.each(args, function(i, a){
            var val = a.value;
            if($.isArray(val)){
                val = val.join(', ');
            } else if(val === true){
                val = 'true';
            } else if(val === false){
                val = 'false';
            } else if(typeof val === 'object'){
                val = JSON.stringify(val);
            }
            var row = $('<tr>\
<td>'+esc(a.key)+'</td>\
<td>'+esc(val)+'</td>\
<td><button class="button gm2-edit-arg" data-index="'+i+'">Edit</button> <button class="button gm2-delete-arg" data-index="'+i+'">Delete</button></td>\
</tr>');
            tbody.append(row);
        });
    }

    function toggleFieldOptions(type){
        $('#gm2-field-date-options').toggle(type === 'date');
        $('#gm2-field-wysiwyg-options').toggle(type === 'wysiwyg');
        $('#gm2-field-repeater-options').toggle(type === 'repeater');
    }

    function showFieldForm(data, index){
        $('#gm2-field-index').val(index !== undefined ? index : '');
        $('#gm2-field-label').val(data ? data.label : '');
        $('#gm2-field-slug').val(data ? data.slug : '');
        $('#gm2-field-type').val(data ? data.type : 'text');
        $('#gm2-field-default').val(data ? data.default : '');
        $('#gm2-field-description').val(data ? data.description : '');
        $('#gm2-field-cap').val(data ? data.capability : '');
        $('#gm2-field-edit-cap').val(data ? data.edit_capability : '');
        $('#gm2-field-help').val(data ? data.help : '');
        $('#gm2-field-date-min').val(data ? data.date_min || '' : '');
        $('#gm2-field-date-max').val(data ? data.date_max || '' : '');
        $('#gm2-field-wysiwyg-media').prop('checked', data ? !!data.wysiwyg_media : false);
        $('#gm2-field-wysiwyg-rows').val(data ? data.wysiwyg_rows || '' : '');
        $('#gm2-field-repeater-min').val(data ? data.min_rows || '' : '');
        $('#gm2-field-repeater-max').val(data ? data.max_rows || '' : '');
        var targets = fields.map(function(f){ return f.slug; });
        targets.push('page_id','post_id');
        gm2Conditions.init($('#gm2-field-conditions'), { targets: targets, data: data ? data.conditions : [] });
        toggleFieldOptions($('#gm2-field-type').val());
        $('#gm2-field-form').show();
    }

    function showArgControl(key, value){
        var wrap = $('#gm2-arg-value-wrap').empty();
        var boolKeys = ['public','hierarchical','publicly_queryable','show_ui','show_in_menu','show_in_nav_menus','show_in_admin_bar','exclude_from_search','has_archive','show_in_rest','map_meta_cap'];
        if(boolKeys.indexOf(key) !== -1){
            var chk = $('<label><input type="checkbox" id="gm2-arg-value" value="1"/> '+key+'</label>');
            if(value){ chk.find('input').prop('checked', true); }
            wrap.append(chk);
        }else if(key === 'supports'){
            wrap.append('<input type="text" id="gm2-arg-value" class="regular-text" />');
            $('#gm2-arg-value').val($.isArray(value) ? value.join(',') : value);
        }else{
            wrap.append('<input type="text" id="gm2-arg-value" class="regular-text" />');
            if(typeof value === 'object'){
                value = JSON.stringify(value);
            }
            $('#gm2-arg-value').val(value);
        }
    }

    function showArgForm(data, index){
        $('#gm2-arg-index').val(index !== undefined ? index : '');
        $('#gm2-arg-key').prop('disabled', index !== undefined).val(data ? data.key : '');
        showArgControl(data ? data.key : '', data ? data.value : '');
        var targets = fields.map(function(f){ return f.slug; });
        targets.push('page_id','post_id');
        gm2Conditions.init($('#gm2-arg-conditions'), { targets: targets, data: data ? data.conditions : [] });
        $('#gm2-arg-form').show();
    }

    function saveAll(cb){
        var data = {
            action: 'gm2_save_cpt_fields',
            nonce: gm2CPTFields.nonce,
            slug: gm2CPTFields.slug,
            fields: fields,
            args: args
        };
        $.post(gm2CPTFields.ajax, data, function(resp){
            if(resp && resp.success){
                fields = [];
                $.each(resp.data.fields || {}, function(slug, f){
                    fields.push({
                        label: f.label || '',
                        slug: slug,
                        type: f.type || 'text',
                        default: f.default || '',
                        description: f.description || '',
                        conditions: f.conditions || []
                    });
                });
                args = [];
                $.each(resp.data.args || {}, function(key, val){
                    args.push({ key: key, value: val.value !== undefined ? val.value : val, conditions: val.conditions || [] });
                });
                renderFields();
                renderArgs();
                if(cb) cb(true);
            }else{
                alert('Error saving');
                if(cb) cb(false);
            }
        });
    }

    // Field handlers
    $('#gm2-add-field').on('click', function(){ showFieldForm(); });
    $('#gm2-field-type').on('change', function(){ toggleFieldOptions($(this).val()); });
    $('#gm2-field-cancel').on('click', function(){ $('#gm2-field-form').hide(); });
    $('#gm2-field-save').on('click', function(){
        var idx = $('#gm2-field-index').val();
        var obj = {
            label: $('#gm2-field-label').val(),
            slug: $('#gm2-field-slug').val(),
            type: $('#gm2-field-type').val(),
            default: $('#gm2-field-default').val(),
            description: $('#gm2-field-description').val(),
            capability: $('#gm2-field-cap').val(),
            edit_capability: $('#gm2-field-edit-cap').val(),
            help: $('#gm2-field-help').val(),
            conditions: gm2Conditions.getData($('#gm2-field-conditions'))
        };
        if(obj.type === 'date'){
            obj.date_min = $('#gm2-field-date-min').val();
            obj.date_max = $('#gm2-field-date-max').val();
        } else if(obj.type === 'wysiwyg'){
            obj.wysiwyg_media = $('#gm2-field-wysiwyg-media').is(':checked');
            obj.wysiwyg_rows = $('#gm2-field-wysiwyg-rows').val();
        } else if(obj.type === 'repeater'){
            obj.min_rows = $('#gm2-field-repeater-min').val();
            obj.max_rows = $('#gm2-field-repeater-max').val();
        }
        if(idx === ''){ fields.push(obj); } else { fields[idx] = obj; }
        saveAll(function(){ $('#gm2-field-form').hide(); });
    });
    $('#gm2-fields-table').on('click', '.gm2-edit-field', function(){
        var idx = $(this).data('index');
        showFieldForm(fields[idx], idx);
    });
    $('#gm2-fields-table').on('click', '.gm2-delete-field', function(){
        var idx = $(this).data('index');
        fields.splice(idx,1);
        saveAll();
    });

    // Arg handlers
    $('#gm2-add-arg').on('click', function(){ showArgForm(); });
    $('#gm2-arg-cancel').on('click', function(){ $('#gm2-arg-form').hide(); });
    $('#gm2-arg-save').on('click', function(){
        var idx = $('#gm2-arg-index').val();
        var key = $('#gm2-arg-key').val();
        var val;
        var boolKeys = ['public','hierarchical','publicly_queryable','show_ui','show_in_menu','show_in_nav_menus','show_in_admin_bar','exclude_from_search','has_archive','show_in_rest','map_meta_cap'];
        if(boolKeys.indexOf(key) !== -1){
            val = $('#gm2-arg-value').is(':checked');
        }else if(key === 'supports'){
            val = $('#gm2-arg-value').val();
        }else{
            val = $('#gm2-arg-value').val();
        }
        var obj = { key: key, value: val, conditions: gm2Conditions.getData($('#gm2-arg-conditions')) };
        if(idx === ''){ args.push(obj); } else { args[idx] = obj; }
        saveAll(function(){ $('#gm2-arg-form').hide(); });
    });
    $('#gm2-args-table').on('click', '.gm2-edit-arg', function(){
        var idx = $(this).data('index');
        showArgForm(args[idx], idx);
    });
    $('#gm2-args-table').on('click', '.gm2-delete-arg', function(){
        var idx = $(this).data('index');
        args.splice(idx,1);
        saveAll();
    });

    // Media uploader for media field type
    $(document).on('click', '.gm2-media-upload', function(e){
        e.preventDefault();
        var target = $(this).data('target');
        var frame = wp.media({ multiple: false });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $('[name="'+target+'"]').val(attachment.id);
        });
        frame.open();
    });

    // Basic sanitization for relationship fields
    $(document).on('input', '.gm2-relationship', function(){
        var val = $(this).val();
        $(this).val(val.replace(/[^0-9,]/g,''));
    });

    renderFields();
    renderArgs();
});

