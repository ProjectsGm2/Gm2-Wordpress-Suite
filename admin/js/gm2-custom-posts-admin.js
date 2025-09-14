jQuery(function($){
    var fields = gm2CPTFields.fields || [];
    var args   = gm2CPTFields.args || [];
    var flexTypes = [];

    // Ensure various field types are available in the selector.
    var typeSelect = $('#gm2-field-type');
    var opts = [
        {val:'textarea', label:'Textarea'},
        {val:'toggle',   label:'Toggle'},
        {val:'file',     label:'File'},
        {val:'audio',    label:'Audio'},
        {val:'video',    label:'Video'},
        {val:'gallery',  label:'Gallery'},
        {val:'relationship', label:'Relationship'}
    ];
    $.each(opts, function(i, o){
        if(!typeSelect.find('option[value="'+o.val+'"]').length){
            typeSelect.append('<option value="'+o.val+'">'+o.label+'</option>');
        }
    });

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

    function renderFlexTypes(){
        var wrap = $('#gm2-flexible-types').empty();
        if(!flexTypes.length){
            wrap.append('<p>'+esc(gm2CPTFields.noFlex || 'No row types')+'</p>');
            return;
        }
        $.each(flexTypes, function(i, ft){
            var row = $('<div class="gm2-flex-type" data-index="'+i+'">\
<input type="text" class="gm2-flex-type-name" placeholder="Slug" value="'+esc(ft.name || '')+'" /> \
<input type="text" class="gm2-flex-type-label" placeholder="Label" value="'+esc(ft.label || '')+'" /> \
<button type="button" class="button gm2-flex-type-up">&#8593;</button> \
<button type="button" class="button gm2-flex-type-down">&#8595;</button> \
<button type="button" class="button gm2-flex-type-remove">&times;</button></div>');
            wrap.append(row);
        });
    }

    $('#gm2-import-preset').on('click', function(){
        var file = $('#gm2-preset-select').val();
        if(!file){ return; }
        $.post(gm2CPTImport.ajax, {
            action: 'gm2_import_preset',
            file: file,
            nonce: gm2CPTImport.nonce
        }, function(resp){
            $('.gm2-import-notice').remove();
            if(resp && resp.success){
                $('<div class="notice notice-success gm2-import-notice"><p>'+esc(gm2CPTImport.success)+'</p></div>').insertBefore('#gm2-post-type-form');
                window.scrollTo(0,0);
            }else{
                var msg = resp && resp.data && resp.data.message ? resp.data.message : gm2CPTImport.error;
                $('<div class="notice notice-error gm2-import-notice"><p>'+esc(msg)+'</p></div>').insertBefore('#gm2-post-type-form');
                window.scrollTo(0,0);
            }
        });
    });

    function toggleFieldOptions(type){
        $('#gm2-field-date-options').toggle(type === 'date');
        $('#gm2-field-wysiwyg-options').toggle(type === 'wysiwyg');
        $('#gm2-field-repeater-options').toggle(type === 'repeater');
        $('#gm2-field-flexible-options').toggle(type === 'flexible');
        $('#gm2-field-select-options').toggle(type === 'select');
        $('#gm2-field-relationship-options').toggle(type === 'relationship');
    }

    function showFieldForm(data, index){
        $('#gm2-field-index').val(index !== undefined ? index : '');
        $('#gm2-field-label').val(data ? data.label : '');
        $('#gm2-field-slug').val(data ? data.slug : '');
        $('#gm2-field-type').val(data ? data.type : 'text');
        $('#gm2-field-default').val(data ? data.default : '');
        $('#gm2-field-description').val(data ? data.description : '');
        $('#gm2-field-order').val(data ? data.order || '' : '');
        $('#gm2-field-container').val(data ? data.container || '' : '');
        $('#gm2-field-instructions').val(data ? data.instructions || '' : '');
        $('#gm2-field-placeholder').val(data ? data.placeholder || '' : '');
        $('#gm2-field-class').val(data ? data.class || '' : '');
        $('#gm2-field-cap').val(data ? data.capability : '');
        $('#gm2-field-edit-cap').val(data ? data.edit_capability : '');
        $('#gm2-field-help').val(data ? data.help : '');
        $('#gm2-field-rel-type').val(data ? data.relationship_type || 'post' : 'post');
        $('#gm2-field-sync').val(data ? data.sync || 'two-way' : 'two-way');
        $('#gm2-field-column').prop('checked', data ? !!data.column : false);
        $('#gm2-field-sortable').prop('checked', data ? !!data.sortable : false);
        $('#gm2-field-quick-edit').prop('checked', data ? !!data.quick_edit : false);
        $('#gm2-field-bulk-edit').prop('checked', data ? !!data.bulk_edit : false);
        $('#gm2-field-filter').prop('checked', data ? !!data.filter : false);
        $('#gm2-field-multiple').prop('checked', data ? !!data.multiple : false);
        $('#gm2-field-date-min').val(data ? data.date_min || '' : '');
        $('#gm2-field-date-max').val(data ? data.date_max || '' : '');
        $('#gm2-field-wysiwyg-media').prop('checked', data ? !!data.wysiwyg_media : false);
        $('#gm2-field-wysiwyg-rows').val(data ? data.wysiwyg_rows || '' : '');
        $('#gm2-field-repeater-min').val(data ? data.min_rows || '' : '');
        $('#gm2-field-repeater-max').val(data ? data.max_rows || '' : '');
        flexTypes = data ? (data.layouts || []) : [];
        renderFlexTypes();
        var targets = fields.map(function(f){ return f.slug; });
        targets.push('page_id','post_id');
        gm2Conditions.init($('#gm2-field-conditions'), { targets: targets, data: data ? data.conditions : [] });

        var locTargets = ['post_type','taxonomy','user','comment','media','options_page','term','site','network'];
        var locData = [];
        if(data && data.location){
            $.each(data.location, function(i,g){
                var cg = { relation: g.relation || 'AND', conditions: [] };
                $.each(g.rules || [], function(j,r){
                    cg.conditions.push({ relation: 'AND', target: r.param, operator: r.operator === '!=' ? '!=' : '=', value: r.value || '' });
                });
                locData.push(cg);
            });
        }
        gm2Conditions.init($('#gm2-field-location'), { targets: locTargets, data: locData, addGroupText: 'Add Location Group', addConditionText: 'Add Rule' });
        toggleFieldOptions($('#gm2-field-type').val());
        $('#gm2-field-form').show();
    }

    function showArgControl(key, value){
        var wrap = $('#gm2-arg-value-wrap').empty();
        var boolKeys = ['public','hierarchical','publicly_queryable','show_ui','show_in_menu','show_in_nav_menus','show_in_admin_bar','exclude_from_search','has_archive','show_in_rest','map_meta_cap','delete_with_user','can_export'];
        if(boolKeys.indexOf(key) !== -1){
            var chk = $('<label><input type="checkbox" id="gm2-arg-value" value="1"/> '+key+'</label>');
            if(value){ chk.find('input').prop('checked', true); }
            wrap.append(chk);
        }else if(key === 'supports'){
            var opts = ['title','editor','excerpt','author','thumbnail','page-attributes','custom-fields','revisions'];
            $.each(opts, function(i, sup){
                var id = 'gm2-support-'+sup;
                var chk = $('<label><input type="checkbox" class="gm2-support-item" value="'+sup+'" id="'+id+'"/> '+sup.replace('-', ' ')+'</label><br/>');
                wrap.append(chk);
            });
            if($.isArray(value)){
                $.each(value, function(i,v){ wrap.find('input[value="'+v+'"]').prop('checked', true); });
            }
        }else if(key === 'rewrite'){
            var html = '<p><label>Slug<br/><input type="text" id="gm2-rewrite-slug" class="regular-text" /></label></p>';
            $.each(['with_front','hierarchical','feeds','pages'], function(i, opt){
                html += '<label><input type="checkbox" class="gm2-rewrite-flag" id="gm2-rewrite-'+opt+'" value="1"/> '+opt.replace('_',' ')+'</label><br/>';
            });
            html += '<p><label>ep_mask<br/><input type="text" id="gm2-rewrite-ep_mask" class="regular-text" /></label></p>';
            wrap.append(html);
            if(value && typeof value === 'object'){
                $('#gm2-rewrite-slug').val(value.slug || '');
                $.each(['with_front','hierarchical','feeds','pages'], function(i,opt){ if(value[opt]) $('#gm2-rewrite-'+opt).prop('checked', true); });
                if(value.ep_mask) $('#gm2-rewrite-ep_mask').val(value.ep_mask);
            }
        }else if(key === 'taxonomies'){
            wrap.append('<input type="text" id="gm2-arg-value" class="regular-text" />');
            if($.isArray(value)){
                $('#gm2-arg-value').val(value.join(', '));
            }else{
                $('#gm2-arg-value').val(value);
            }
        }else if(key === 'description'){
            wrap.append('<textarea id="gm2-arg-value" class="large-text"></textarea>');
            $('#gm2-arg-value').val(value || '');
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
        $('#gm2-arg-key').off('input.gm2').on('input.gm2', function(){
            showArgControl($(this).val(), null);
        });
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
                        order: f.order || 0,
                        container: f.container || '',
                        instructions: f.instructions || '',
                        placeholder: f.placeholder || '',
                        class: f.class || '',
                        capability: f.capability || '',
                        edit_capability: f.edit_capability || '',
                        help: f.help || '',
                        location: f.location || [],
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
        var locRaw = gm2Conditions.getData($('#gm2-field-location'));
        var locGroups = [];
        $.each(locRaw, function(i,g){
            var rules = [];
            $.each(g.conditions || [], function(j,c){
                var op = c.operator === '!=' ? '!=' : '==';
                rules.push({ param: c.target, operator: op, value: c.value });
            });
            if(rules.length){ locGroups.push({ relation: g.relation || 'AND', rules: rules }); }
        });
        var obj = {
            label: $('#gm2-field-label').val(),
            slug: $('#gm2-field-slug').val(),
            type: $('#gm2-field-type').val(),
            default: $('#gm2-field-default').val(),
            description: $('#gm2-field-description').val(),
            order: $('#gm2-field-order').val(),
            container: $('#gm2-field-container').val(),
            instructions: $('#gm2-field-instructions').val(),
            placeholder: $('#gm2-field-placeholder').val(),
            class: $('#gm2-field-class').val(),
            capability: $('#gm2-field-cap').val(),
            edit_capability: $('#gm2-field-edit-cap').val(),
            help: $('#gm2-field-help').val(),
            column: $('#gm2-field-column').is(':checked'),
            sortable: $('#gm2-field-sortable').is(':checked'),
            quick_edit: $('#gm2-field-quick-edit').is(':checked'),
            bulk_edit: $('#gm2-field-bulk-edit').is(':checked'),
            filter: $('#gm2-field-filter').is(':checked'),
            location: locGroups,
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
        } else if(obj.type === 'flexible'){
            obj.layouts = flexTypes;
        } else if(obj.type === 'select'){
            obj.multiple = $('#gm2-field-multiple').is(':checked');
        } else if(obj.type === 'relationship'){
            obj.relationship_type = $('#gm2-field-rel-type').val();
            obj.sync = $('#gm2-field-sync').val();
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
        var boolKeys = ['public','hierarchical','publicly_queryable','show_ui','show_in_menu','show_in_nav_menus','show_in_admin_bar','exclude_from_search','has_archive','show_in_rest','map_meta_cap','delete_with_user','can_export'];
        if(boolKeys.indexOf(key) !== -1){
            val = $('#gm2-arg-value').is(':checked');
        }else if(key === 'supports'){
            val = [];
            $('.gm2-support-item:checked').each(function(){ val.push($(this).val()); });
        }else if(key === 'rewrite'){
            val = {
                slug: $('#gm2-rewrite-slug').val(),
                with_front: $('#gm2-rewrite-with_front').is(':checked'),
                hierarchical: $('#gm2-rewrite-hierarchical').is(':checked'),
                feeds: $('#gm2-rewrite-feeds').is(':checked'),
                pages: $('#gm2-rewrite-pages').is(':checked')
            };
            var ep = $('#gm2-rewrite-ep_mask').val();
            if(ep){ val.ep_mask = ep; }
        }else if(key === 'taxonomies'){
            var rawTax = $('#gm2-arg-value').val();
            val = rawTax.split(',').map(function(s){ return $.trim(s); }).filter(function(s){ return s.length; });
        }else if(key === 'capability_type'){
            var raw = $('#gm2-arg-value').val();
            var parts = raw.split(',').map(function(s){ return $.trim(s); }).filter(function(s){ return s.length; });
            val = parts.length > 1 ? parts : parts[0];
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

    $('#gm2-field-flexible-options').on('click', '.gm2-flex-type-add', function(){
        flexTypes.push({ name:'', label:'' });
        renderFlexTypes();
    });
    $('#gm2-flexible-types').on('click', '.gm2-flex-type-remove', function(){
        var idx = $(this).closest('.gm2-flex-type').data('index');
        flexTypes.splice(idx,1);
        renderFlexTypes();
    });
    $('#gm2-flexible-types').on('click', '.gm2-flex-type-up', function(){
        var idx = $(this).closest('.gm2-flex-type').data('index');
        if(idx > 0){
            var tmp = flexTypes[idx-1];
            flexTypes[idx-1] = flexTypes[idx];
            flexTypes[idx] = tmp;
            renderFlexTypes();
        }
    });
    $('#gm2-flexible-types').on('click', '.gm2-flex-type-down', function(){
        var idx = $(this).closest('.gm2-flex-type').data('index');
        if(idx < flexTypes.length-1){
            var tmp = flexTypes[idx+1];
            flexTypes[idx+1] = flexTypes[idx];
            flexTypes[idx] = tmp;
            renderFlexTypes();
        }
    });
    $('#gm2-flexible-types').on('input', '.gm2-flex-type-name, .gm2-flex-type-label', function(){
        var row = $(this).closest('.gm2-flex-type');
        var idx = row.data('index');
        flexTypes[idx].name = row.find('.gm2-flex-type-name').val();
        flexTypes[idx].label = row.find('.gm2-flex-type-label').val();
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

    $(document).on('click', '.gm2-gallery-upload', function(e){
        e.preventDefault();
        var target = $(this).data('target');
        var frame = wp.media({ multiple: true });
        frame.on('select', function(){
            var ids = [];
            frame.state().get('selection').each(function(att){
                ids.push(att.toJSON().id);
            });
            $('[name="'+target+'"]').val(ids.join(','));
        });
        frame.open();
    });

    // Basic sanitization for relationship fields
    $(document).on('input', '.gm2-relationship', function(){
        var val = $(this).val();
        $(this).val(val.replace(/[^0-9a-z_,]/gi,''));
    });

    function applyEnhancements(){
        $('.gm2-field[data-placeholder]').each(function(){
            var ph = $(this).data('placeholder');
            $(this).find('input, textarea, select').first().attr('placeholder', ph);
        });
        $('.gm2-field[data-admin-class]').each(function(){
            var cls = $(this).data('admin-class');
            $(this).find(':input').addClass(cls);
        });
        var tabs = [];
        $('.gm2-field[data-tab]').each(function(){
            var t = $(this).data('tab');
            if(tabs.indexOf(t) === -1){ tabs.push(t); }
        });
        if(tabs.length){
            var nav = $('<ul class="gm2-tab-nav"></ul>');
            $.each(tabs, function(i, t){ nav.append('<li data-tab="'+t+'">'+t+'</li>'); });
            var firstField = $('.gm2-field[data-tab]').first();
            firstField.before(nav);
            function showTab(tab){
                $('.gm2-field[data-tab]').hide();
                $('.gm2-field[data-tab="'+tab+'"]').show();
                nav.find('li').removeClass('active');
                nav.find('li[data-tab="'+tab+'"]').addClass('active');
            }
            nav.on('click', 'li', function(){
                showTab($(this).data('tab'));
            });
            showTab(tabs[0]);
        }
        var accGroups = {};
        $('.gm2-field[data-accordion]').each(function(){
            var name = $(this).data('accordion');
            if(!accGroups[name]){ accGroups[name] = []; }
            accGroups[name].push(this);
        });
        $.each(accGroups, function(name, fields){
            var first = $(fields[0]);
            var wrap = $('<div class="gm2-accordion-group"></div>');
            var header = $('<h3 class="gm2-accordion-header">'+name+'</h3>');
            var content = $('<div class="gm2-accordion-content"></div>');
            $(fields).each(function(){ content.append(this); });
            wrap.append(header).append(content);
            first.before(wrap);
        });
        $(document).on('click', '.gm2-accordion-header', function(){
            $(this).next('.gm2-accordion-content').slideToggle();
        });
    }

    renderFields();
    renderArgs();
    applyEnhancements();
});

