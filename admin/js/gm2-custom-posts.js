(function($){
    function evaluate(groups){
        var result = null;
        var disabled = false;
        $.each(groups, function(i, group){
            var groupRes = null;
            $.each(group.conditions || [], function(j, cond){
                var $t = $('[name="'+cond.target+'"]');
                if(!$t.length){ return; }
                var v;
                if($t.attr('type') === 'checkbox'){ v = $t.is(':checked') ? '1' : '0'; } else { v = $t.val(); }
                var ok = false;
                switch(cond.operator){
                    case '!=': ok = String(v) !== String(cond.value); break;
                    case '>': ok = parseFloat(v) > parseFloat(cond.value); break;
                    case '<': ok = parseFloat(v) < parseFloat(cond.value); break;
                    case 'contains': ok = String(v).indexOf(String(cond.value)) !== -1; break;
                    default: ok = String(v) === String(cond.value); break;
                }
                if(groupRes === null){ groupRes = ok; }
                else{ groupRes = cond.relation === 'OR' ? (groupRes || ok) : (groupRes && ok); }
            });
            if(groupRes === null){ groupRes = false; }
            if(result === null){ result = groupRes; }
            else{ result = group.relation === 'AND' ? (result && groupRes) : (result || groupRes); }
            if(groupRes && group.action){
                switch(group.action){
                    case 'hide':
                        result = false;
                        break;
                    case 'show':
                        result = true;
                        break;
                    case 'disable':
                        disabled = true;
                        break;
                }
            }
        });
        var visible = (result === null) ? true : !!result;
        return { show: visible, disabled: disabled };
    }

    function setupConditional(){
        var items = [];
        $('.gm2-field[data-conditions]').each(function(){
            var $wrap = $(this);
            var conds = $wrap.data('conditions');
            items.push({el:$wrap, conds:conds});
            $.each(conds, function(i,g){
                $.each(g.conditions || [], function(j,c){
                    $('[name="'+c.target+'"]').on('change', run);
                });
            });
        });
        $('.gm2-field[data-conditional-field]').each(function(){
            var $wrap = $(this);
            var target = $wrap.data('conditional-field');
            var expected = $wrap.data('conditional-value');
            var conds = [{ relation:'AND', conditions:[{ relation:'AND', target:target, operator:'=', value:String(expected) }] }];
            items.push({el:$wrap, conds:conds});
            $('[name="'+target+'"]').on('change', run);
        });
        function run(){
            $.each(items, function(i,it){
                var state = evaluate(it.conds);
                it.el.toggle(state.show);
                it.el.find(':input').prop('disabled', state.disabled);
                if(state.disabled){
                    it.el.attr('data-disabled', '1');
                }else{
                    it.el.removeAttr('data-disabled');
                }
            });
        }
        run();
    }
    $(document).ready(function(){
        setupConditional();
    });

    $(document).on('click', '.gm2-media-upload', function(e){
        e.preventDefault();
        var target = $(this).data('target');
        var frame = wp.media({ multiple: false });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            var field = $('[name="'+target+'"]');
            field.val(attachment.id);
            var preview = field.closest('.gm2-media-field').find('.gm2-media-preview');
            if(attachment.type === 'image' && attachment.sizes && attachment.sizes.thumbnail){
                preview.html('<img src="'+attachment.sizes.thumbnail.url+'" alt="" />');
            }else{
                preview.html('<span>'+attachment.filename+'</span>');
            }
        });
        frame.open();
    });

    $(document).on('click', '.gm2-repeater-add', function(){
        var key = $(this).data('target');
        var wrap = $(this).closest('.gm2-repeater');
        var row = $('<div class="gm2-repeater-row"><input type="text" name="'+key+'[]" /> <button type="button" class="button gm2-repeater-remove">&times;</button></div>');
        row.insertBefore($(this).parent());
    });

    $(document).on('click', '.gm2-repeater-remove', function(){
        $(this).closest('.gm2-repeater-row').remove();
    });
})(jQuery);
