(function($){
    function evaluate(groups){
        var result = null;
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
        });
        return result;
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
                it.el.toggle( evaluate(it.conds) );
            });
        }
        run();
    }
    $(document).ready(setupConditional);
})(jQuery);
