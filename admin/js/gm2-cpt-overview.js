jQuery(function($){
    if(typeof gm2CPTEdit === 'undefined'){ return; }
    function fillPostType(slug){
        $.get(gm2CPTEdit.ptUrl, { slug: slug, _wpnonce: gm2CPTEdit.nonce }, function(resp){
            if(!resp || !resp.success){ return; }
            var data = resp.data || {};
            var form = $('#gm2-post-type-form');
            form.find('input[name="pt_original"]').val(slug);
            form.find('input[name="pt_slug"]').val(slug);
            form.find('input[name="pt_label"]').val(data.label || '');
            form.find('textarea[name="pt_fields"]').val(JSON.stringify(data.fields || {}, null, 2));
            var args = data.args || {};
            form.find('textarea[name="pt_labels"]').val(args.labels ? JSON.stringify(args.labels.value || {}, null, 2) : '');
            form.find('input[name="pt_menu_icon"]').val(args.menu_icon ? args.menu_icon.value : '');
            form.find('input[name="pt_menu_position"]').val(args.menu_position ? args.menu_position.value : '');
            form.find('input[name="pt_hierarchical"]').prop('checked', args.hierarchical ? !!args.hierarchical.value : false);
            var supports = args.supports ? args.supports.value || [] : [];
            form.find('input[name="pt_supports[]"]').prop('checked', false);
            $.each(supports, function(i, s){
                form.find('input[name="pt_supports[]"][value="'+s+'"]').prop('checked', true);
            });
            var vis = ['public','publicly_queryable','show_ui','show_in_menu','show_in_nav_menus','show_in_admin_bar','exclude_from_search','has_archive'];
            $.each(vis, function(i,k){
                form.find('input[name="pt_'+k+'"]').prop('checked', args[k] ? !!args[k].value : false);
            });
            form.find('input[name="pt_show_in_rest"]').prop('checked', args.show_in_rest ? !!args.show_in_rest.value : false);
            form.find('input[name="pt_rest_base"]').val(args.rest_base ? args.rest_base.value : '');
            form.find('input[name="pt_rest_controller_class"]').val(args.rest_controller_class ? args.rest_controller_class.value : '');
            var rw = args.rewrite ? args.rewrite.value || {} : {};
            form.find('input[name="pt_rewrite_slug"]').val(rw.slug || '');
            ['with_front','hierarchical','feeds','pages'].forEach(function(k){
                form.find('input[name="pt_rewrite_'+k+'"]').prop('checked', rw[k] ? true : false);
            });
            form.find('input[name="pt_map_meta_cap"]').prop('checked', args.map_meta_cap ? !!args.map_meta_cap.value : false);
            var capType = args.capability_type ? args.capability_type.value : '';
            if($.isArray(capType)) capType = capType.join(',');
            form.find('input[name="pt_capability_type"]').val(capType);
            form.find('textarea[name="pt_capabilities"]').val(args.capabilities ? JSON.stringify(args.capabilities.value || {}, null, 2) : '');
            form.find('textarea[name="pt_template"]').val(args.template ? JSON.stringify(args.template.value || [], null, 2) : '');
            form.find('select[name="pt_template_lock"]').val(args.template_lock ? args.template_lock.value : '');
        });
    }
    function fillTaxonomy(slug){
        $.get(gm2CPTEdit.taxUrl, { slug: slug, _wpnonce: gm2CPTEdit.taxNonce }, function(resp){
            if(!resp || !resp.success){ return; }
            var data = resp.data || {};
            var form = $('#gm2-tax-form');
            form.find('input[name="tax_original"]').val(slug);
            form.find('input[name="tax_slug"]').val(slug);
            form.find('input[name="tax_label"]').val(data.label || '');
            form.find('input[name="tax_post_types"]').val((data.post_types || []).join(','));
            form.find('textarea[name="tax_args"]').val(JSON.stringify(data.args || {}, null, 2));
        });
    }
    $('.gm2-edit-pt').on('click', function(e){
        e.preventDefault();
        fillPostType($(this).data('slug'));
        $('html, body').animate({ scrollTop: $('#gm2-post-type-form').offset().top }, 200);
    });
    $('.gm2-edit-tax').on('click', function(e){
        e.preventDefault();
        fillTaxonomy($(this).data('slug'));
        $('html, body').animate({ scrollTop: $('#gm2-tax-form').offset().top }, 200);
    });
});
