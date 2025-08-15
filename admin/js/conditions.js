jQuery(function($){
    window.gm2Conditions = {
        init: function($wrap, opts){
            opts = opts || {};
            var targets = opts.targets || [];
            var data = opts.data || [];
            $wrap.off('.gm2cond');
            $wrap.html('<div class="gm2-condition-groups"></div><p><button type="button" class="button gm2-add-condition-group">'+(opts.addGroupText||'Add Condition Group')+'</button></p>');
            function addGroup(g){
                var group = $('<div class="gm2-condition-group">\n<select class="gm2-group-relation"><option value="AND">AND</option><option value="OR">OR</option></select>\n<div class="gm2-condition-list"></div>\n<p><button type="button" class="button gm2-add-condition">'+(opts.addConditionText||'Add Condition')+'</button> <button type="button" class="button gm2-remove-condition-group">'+(opts.removeGroupText||'Remove Group')+'</button></p></div>');
                if(g && g.relation){ group.find('.gm2-group-relation').val(g.relation); }
                $wrap.find('.gm2-condition-groups').append(group);
                $.each((g && g.conditions)||[], function(i,c){ addCondition(group, c); });
            }
            function addCondition($group, c){
                var row = $('<div class="gm2-condition-row">\n<select class="gm2-condition-relation"><option value="AND">AND</option><option value="OR">OR</option></select>\n<select class="gm2-target"></select>\n<select class="gm2-operator"><option value="=">=</option><option value="!=">!=</option><option value=">">&gt;</option><option value="<">&lt;</option><option value="contains">contains</option></select>\n<input type="text" class="gm2-value" /> <button type="button" class="button gm2-remove-condition">'+(opts.removeConditionText||'Remove')+'</button></div>');
                var tSel = row.find('.gm2-target');
                $.each(targets, function(i,t){ tSel.append('<option value="'+t+'">'+t+'</option>'); });
                if(c){
                    row.find('.gm2-condition-relation').val(c.relation || 'AND');
                    row.find('.gm2-target').val(c.target || '');
                    row.find('.gm2-operator').val(c.operator || '=');
                    row.find('.gm2-value').val(c.value || '');
                }
                $group.find('.gm2-condition-list').append(row);
            }
            $wrap.on('click.gm2cond', '.gm2-add-condition-group', function(){ addGroup(); });
            $wrap.on('click.gm2cond', '.gm2-add-condition', function(){ addCondition($(this).closest('.gm2-condition-group')); });
            $wrap.on('click.gm2cond', '.gm2-remove-condition', function(){ $(this).closest('.gm2-condition-row').remove(); });
            $wrap.on('click.gm2cond', '.gm2-remove-condition-group', function(){ $(this).closest('.gm2-condition-group').remove(); });
            $.each(data, function(i,g){ addGroup(g); });
        },
        getData: function($wrap){
            var groups = [];
            $wrap.find('.gm2-condition-group').each(function(){
                var $g = $(this);
                var g = { relation: $g.find('.gm2-group-relation').val(), conditions: [] };
                $g.find('.gm2-condition-row').each(function(){
                    var $r = $(this);
                    g.conditions.push({
                        relation: $r.find('.gm2-condition-relation').val(),
                        target: $r.find('.gm2-target').val(),
                        operator: $r.find('.gm2-operator').val(),
                        value: $r.find('.gm2-value').val()
                    });
                });
                groups.push(g);
            });
            return groups;
        }
    };
});
