jQuery(function($){
    var groups = gm2Qd.groups || [];
    var categories = gm2Qd.categories || [];
    let activeSearch = null;

    function decodeEntities(str){
        if(!str) return '';
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    function labelFor(item){
        var l = item.title || item.text || item.id;
        if(typeof l === 'string'){
            l = decodeEntities(l);
        }
        if(item.sku){ l += ' ('+item.sku+')'; }
        return l;
    }
    function createRuleRow(rule){
        rule = rule || {min:'',label:'',type:'percent',amount:''};
        var row = $('<tr class="gm2-qd-rule">\
            <td><input type="number" class="gm2-qd-min" value="'+rule.min+'" min="1"></td>\
            <td><input type="text" class="gm2-qd-label" value="'+(rule.label||'')+'"></td>\
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
        var prodSection = $('<div class="gm2-qd-products"><select class="gm2-qd-cat" multiple><option value="">All Categories</option></select> <input type="text" class="gm2-qd-search" placeholder="Search products"> <button type="button" class="button gm2-qd-search-btn">Search</button> <div class="gm2-qd-cat-products"></div><ul class="gm2-qd-results"></ul><h4 class="gm2-qd-selected-title">Selected products</h4><ul class="gm2-qd-selected"></ul><p class="gm2-qd-remove-actions"><button type="button" class="button gm2-qd-remove-selected">Remove selected products</button> <button type="button" class="button gm2-qd-remove-all">Remove all</button></p></div>');
        categories.forEach(function(c){prodSection.find('select').append('<option value="'+c.id+'">'+c.name+'</option>');});
        container.append(prodSection);
        var table = $('<table class="widefat gm2-qd-rules"><thead><tr><th>Min Qty</th><th>Label</th><th>% Off</th><th>Fixed Off</th><th></th></tr></thead><tbody></tbody></table>');
        g.rules.forEach(function(r){table.find('tbody').append(createRuleRow(r));});
        container.append(table);
        container.append('<p><button type="button" class="button gm2-qd-add-rule">Add Rule</button></p>');
        g.products.forEach(function(p){
            if(typeof p!=='object'){
                var t=null;
                if(gm2Qd && gm2Qd.productTitles && gm2Qd.productTitles[p]){
                    t=gm2Qd.productTitles[p];
                }
                p={id:p};
                if(t){p.title=t;}
            }else if(!p.title && gm2Qd && gm2Qd.productTitles && gm2Qd.productTitles[p.id]){
                p.title=gm2Qd.productTitles[p.id];
            }
            addSelectedProduct(container, p);
        });
        accordion.append(container);
        return accordion;
    }
    function addSelectedProduct(group, item){
        var ul = group.find('.gm2-qd-selected');
        if( ul.find('li[data-id="'+item.id+'"]').length ) return;
        var input = $('<input>', { type:'checkbox', class:'gm2-qd-selected-chk' });
        var span = $('<span>', { 'class':'remove' }).text('x');
        var label = $('<label>').append(input).append(' ').append($('<span>').text(labelFor(item))).append(' ').append(span);
        var li = $('<li>').attr('data-id', item.id).append(label);
        ul.append(li);
    }

    function loadCategoryProducts(group, cats){
        var box = group.find('.gm2-qd-cat-products').empty().hide();
        if(!cats || !cats.length){ return; }
        var spinner = $('<span class="loading-spinner"></span>');
        box.append(spinner).addClass('loading').show();
        $.get(gm2Qd.ajax_url,{action:'gm2_qd_get_category_products',nonce:gm2Qd.nonce,'categories[]':cats}).done(function(res){
            box.removeClass('loading').empty();
            if(!res.success || !res.data || !res.data.length) return;
            var selectAll = $('<label>').append(
                $('<input>', { type:'checkbox', class:'gm2-qd-select-all' })
            ).append(' Select all');
            var list = $('<ul>', { class:'gm2-qd-checkboxes' });
            var addBtn = $('<p>').append(
                $('<button>', { type:'button', class:'button gm2-qd-add-selected' }).text('Add selected products')
            );
            box.append(selectAll, list, addBtn).show();
            res.data.forEach(function(p){
                var input = $('<input>', {
                    type:'checkbox',
                    class:'gm2-qd-product-chk',
                    value:p.id,
                    'data-title':p.title,
                    'data-sku':p.sku
                });
                var li = $('<li>').append(
                    $('<label>').append(input).append(' ').append($('<span>').text(labelFor(p)))
                );
                if(group.find('.gm2-qd-selected li[data-id="'+p.id+'"]').length){
                    li.find('input').prop('checked',true);
                }
                list.append(li);
            });
        }).fail(function(){
            box.removeClass('loading').empty();
        });
    }
    function renderGroups(){
        var holder = $('#gm2-qd-groups').empty();
        groups.forEach(function(g){holder.append(createGroup(g));});
        $('.gm2-qd-cat').selectWoo({ width: '200px' });
    }
    renderGroups();
    $(document).on('select2:unselect','.gm2-qd-cat',function(){
        var $select = $(this);
        setTimeout(function(){
            $select.selectWoo('close');
        });
    });
    $('#gm2-qd-add-group').on('click',function(){
        var group = createGroup();
        $('#gm2-qd-groups').append(group);
        group.find('.gm2-qd-cat').selectWoo({ width: '200px' });
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
        var group=$(this).closest('.gm2-qd-group');
        var c=$(this).is(':checked');
        group.find('.gm2-qd-product-chk').prop('checked',c);
    });
    $(document).on('click','.gm2-qd-add-selected',function(){
        var group=$(this).closest('.gm2-qd-group');
        group.find('.gm2-qd-product-chk:checked').each(function(){
            var id=$(this).val();
            addSelectedProduct(group,{id:id,title:$(this).data('title'),sku:$(this).data('sku')});
        });
    });
    function searchProducts(group){
        var term=group.find('.gm2-qd-search').val();
        var cats=group.find('.gm2-qd-cat').val();
        var box=group.find('.gm2-qd-results').empty();
        if(term.length<2){return;}
        if(activeSearch){
            activeSearch.abort();
        }
        activeSearch=$.get(gm2Qd.ajax_url,{action:'gm2_qd_search_products',nonce:gm2Qd.nonce,term:term,'categories[]':cats}).done(function(res){
            if(!res.success) return;
            var selectAll = $('<label>').append(
                $('<input>', { type:'checkbox', class:'gm2-qd-select-all' })
            ).append(' Select all');
            var list = $('<ul>', { class:'gm2-qd-checkboxes' });
            var addBtn = $('<p>').append(
                $('<button>', { type:'button', class:'button gm2-qd-add-selected' }).text('Add selected products')
            );
            box.append(selectAll, list, addBtn);
            res.data.forEach(function(i){
                var input = $('<input>', {
                    type:'checkbox',
                    class:'gm2-qd-product-chk',
                    value:i.id,
                    'data-title':i.title,
                    'data-sku':i.sku
                });
                var li = $('<li>').append(
                    $('<label>').append(input).append(' ').append($('<span>').text(labelFor(i)))
                );
                if(group.find('.gm2-qd-selected li[data-id="'+i.id+'"]').length){
                    li.find('input').prop('checked',true);
                }
                list.append(li);
            });
        }).always(function(){
            activeSearch=null;
        });
    }
    $(document).on('click','.gm2-qd-search-btn',function(){
        var group=$(this).closest('.gm2-qd-group');
        searchProducts(group);
    });
    $(document).on('click','.gm2-qd-remove-selected',function(){
        var group=$(this).closest('.gm2-qd-products');
        group.find('.gm2-qd-selected-chk:checked').each(function(){
            $(this).closest('li').remove();
        });
    });
    $(document).on('click','.gm2-qd-remove-all',function(){
        var group=$(this).closest('.gm2-qd-products');
        group.find('.gm2-qd-selected').empty();
    });
    $(document).on('click','.gm2-qd-selected .remove',function(){
        $(this).closest('li').remove();
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
                var label=$(this).find('.gm2-qd-label').val();
                var percent=$(this).find('.gm2-qd-percent').val();
                var fixed=$(this).find('.gm2-qd-fixed').val();
                var type='percent';var amount=parseFloat(percent)||0;
                if(fixed){type='fixed';amount=parseFloat(fixed)||0;}
                obj.rules.push({min:min,label:label,type:type,amount:amount});
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
