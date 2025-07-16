jQuery(function($){
    var groups = gm2Qd.groups || [];
    var categories = gm2Qd.categories || [];
    function createRuleRow(rule){
        rule = rule || {min:'',type:'percent',amount:''};
        var row = $('<tr class="gm2-qd-rule">\
            <td><input type="number" class="gm2-qd-min" value="'+rule.min+'" min="1"></td>\
            <td><input type="number" step="0.01" class="gm2-qd-percent" '+(rule.type==='percent'?'' : 'disabled')+' value="'+(rule.type==='percent'?rule.amount:'')+'"></td>\
            <td><input type="number" step="0.01" class="gm2-qd-fixed" '+(rule.type==='fixed'?'' : 'disabled')+' value="'+(rule.type==='fixed'?rule.amount:'')+'"></td>\
            <td><button type="button" class="button gm2-qd-remove-rule">&times;</button></td></tr>');
        return row;
    }
    function createGroup(g){
        g = g || {name:'',products:[],rules:[]};
        var accordion = $('<div class="gm2-qd-accordion open"></div>');
        var header = $('<div class="gm2-qd-header"></div>');
        header.append('<input type="text" class="gm2-qd-name" placeholder="Group name" value="'+(g.name||'')+'"> <button type="button" class="button gm2-qd-remove-group">&times;</button><span class="gm2-qd-toggle">&#9650;</span>');
        accordion.append(header);
        var container = $('<div class="gm2-qd-group"></div>');
        var prodSection = $('<div class="gm2-qd-products"><select class="gm2-qd-cat"><option value="">All Categories</option></select> <input type="text" class="gm2-qd-search" placeholder="Search products"> <button type="button" class="button gm2-qd-search-btn">Search</button> <div class="gm2-qd-cat-products"></div><div class="gm2-qd-results"></div><ul class="gm2-qd-selected"></ul></div>');
        categories.forEach(function(c){prodSection.find('select').append('<option value="'+c.id+'">'+c.name+'</option>');});
        container.append(prodSection);
        var table = $('<table class="widefat gm2-qd-rules"><thead><tr><th>Min Qty</th><th>% Off</th><th>Fixed Off</th><th></th></tr></thead><tbody></tbody></table>');
        g.rules.forEach(function(r){table.find('tbody').append(createRuleRow(r));});
        container.append(table);
        container.append('<p><button type="button" class="button gm2-qd-add-rule">Add Rule</button></p>');
        g.products.forEach(function(id){
            addSelectedProduct(container, {id:id,text:id});
        });
        accordion.append(container);
        return accordion;
    }
    function addSelectedProduct(group, item){
        var ul = group.find('.gm2-qd-selected');
        if( ul.find('li[data-id="'+item.id+'"]').length ) return;
        ul.append('<li data-id="'+item.id+'">'+item.text+' <span class="remove">x</span></li>');
    }

    function loadCategoryProducts(group, cat){
        var box = group.find('.gm2-qd-cat-products').html('');
        if(!cat){ return; }
        $.get(gm2Qd.ajax_url,{action:'gm2_qd_get_category_products',nonce:gm2Qd.nonce,category:cat}).done(function(res){
            if(!res.success) return;
            var html = '<label><input type="checkbox" class="gm2-qd-select-all"> Select all</label><ul class="gm2-qd-checkboxes"></ul><p><button type="button" class="button gm2-qd-add-selected">Add selected products</button></p>';
            box.append(html);
            var list = box.find('.gm2-qd-checkboxes');
            res.data.forEach(function(p){
                var li = $('<li><label><input type="checkbox" class="gm2-qd-product-chk" value="'+p.id+'"> '+p.text+'</label></li>');
                if(group.find('.gm2-qd-selected li[data-id="'+p.id+'"]').length){
                    li.find('input').prop('checked',true);
                }
                list.append(li);
            });
        });
    }
    function renderGroups(){
        var holder = $('#gm2-qd-groups').empty();
        groups.forEach(function(g){holder.append(createGroup(g));});
    }
    renderGroups();
    $('#gm2-qd-add-group').on('click',function(){
        $('#gm2-qd-groups').append(createGroup());
    });
    $(document).on('click','.gm2-qd-remove-group',function(){
        $(this).closest('.gm2-qd-accordion').remove();
    });
    $(document).on('click','.gm2-qd-header',function(e){
        if($(e.target).closest('button').length) return;
        var acc=$(this).closest('.gm2-qd-accordion');
        acc.toggleClass('open');
        acc.find('> .gm2-qd-group').toggle();
        var icon=$(this).find('.gm2-qd-toggle');
        icon.html(acc.hasClass('open')?'&#9650;':'&#9660;');
    });
    $(document).on('click','.gm2-qd-add-rule',function(){
        var group=$(this).closest('.gm2-qd-group');
        group.find('.gm2-qd-rules tbody').append(createRuleRow());
    });
    $(document).on('click','.gm2-qd-remove-rule',function(){
        $(this).closest('tr').remove();
    });
    $(document).on('change','.gm2-qd-cat',function(){
        var group=$(this).closest('.gm2-qd-group');
        loadCategoryProducts(group,$(this).val());
    });
    $(document).on('change','.gm2-qd-select-all',function(){
        var container=$(this).closest('div');
        var c=$(this).is(':checked');
        container.find('.gm2-qd-product-chk').prop('checked',c);
    });
    $(document).on('click','.gm2-qd-add-selected',function(){
        var container=$(this).closest('div');
        var group=$(this).closest('.gm2-qd-group');
        container.find('.gm2-qd-product-chk:checked').each(function(){
            var id=$(this).val();
            var name=$(this).closest('label').text().trim();
            addSelectedProduct(group,{id:id,text:name});
        });
    });
    function searchProducts(group){
        var term=group.find('.gm2-qd-search').val();
        var cat=group.find('.gm2-qd-cat').val();
        if(term.length<2){group.find('.gm2-qd-results').empty();return;}
        $.get(gm2Qd.ajax_url,{action:'gm2_qd_search_products',nonce:gm2Qd.nonce,term:term,category:cat}).done(function(res){
            var box=group.find('.gm2-qd-results').empty();
            if(!res.success) return;
            var html='<label><input type="checkbox" class="gm2-qd-select-all"> Select all</label><ul class="gm2-qd-checkboxes"></ul><p><button type="button" class="button gm2-qd-add-selected">Add selected products</button></p>';
            box.append(html);
            var list=box.find('.gm2-qd-checkboxes');
            res.data.forEach(function(i){
                var li=$('<li><label><input type="checkbox" class="gm2-qd-product-chk" value="'+i.id+'"> '+i.text+'</label></li>');
                if(group.find('.gm2-qd-selected li[data-id="'+i.id+'"]').length){
                    li.find('input').prop('checked',true);
                }
                list.append(li);
            });
        });
    }
    $(document).on('click','.gm2-qd-search-btn',function(){
        var group=$(this).closest('.gm2-qd-group');
        searchProducts(group);
    });
    $(document).on('click','.gm2-qd-selected .remove',function(){
        $(this).parent().remove();
    });
    $('#gm2-qd-form').on('submit',function(e){
        e.preventDefault();
        var data=[];
        $('#gm2-qd-groups .gm2-qd-accordion').each(function(){
            var acc=$(this);var g=acc.find('> .gm2-qd-group');
            var obj={name:acc.find('.gm2-qd-name').val(),products:[],rules:[]};
            g.find('.gm2-qd-selected li').each(function(){obj.products.push($(this).data('id'));});
            g.find('.gm2-qd-rules tbody tr').each(function(){
                var min=parseInt($(this).find('.gm2-qd-min').val(),10)||0;
                var percent=$(this).find('.gm2-qd-percent').val();
                var fixed=$(this).find('.gm2-qd-fixed').val();
                var type='percent';var amount=parseFloat(percent)||0;
                if(fixed){type='fixed';amount=parseFloat(fixed)||0;}
                obj.rules.push({min:min,type:type,amount:amount});
            });
            data.push(obj);
        });
        var $msg=$('#gm2-qd-msg');
        $msg.removeClass('notice-success notice-error').addClass('hidden');
        $.post(gm2Qd.ajax_url,{action:'gm2_qd_save_groups',nonce:gm2Qd.nonce,groups:data}).done(function(res){
            if(res.success){$msg.text('Saved.').addClass('notice-success');}
            else{$msg.text(res.data&&res.data.message?res.data.message:'Error').addClass('notice-error');}
        }).fail(function(){
            $msg.text('Error').addClass('notice-error');
        }).always(function(){
            $msg.removeClass('hidden');
        });
    });
});
