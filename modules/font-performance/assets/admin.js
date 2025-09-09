(function($){
    function initRepeater(name){
        var textarea = $('[name="gm2seo_fonts['+name+']"]');
        if(!textarea.length){ return; }

        var wrap = $('<div class="gm2-fonts-repeater"></div>');
        var list = $('<div class="gm2-fonts-list"></div>').appendTo(wrap);
        var addBtn = $('<button type="button" class="button gm2-fonts-add">Add</button>').appendTo(wrap);

        function addRow(val){
            var row = $('<div class="gm2-fonts-row"><input type="text" value="'+(val||'')+'" /> <button type="button" class="button-link-delete gm2-fonts-remove">&times;</button></div>');
            list.append(row);
        }

        var initial = textarea.val().split(/\n/).filter(function(v){ return v.trim(); });
        if(initial.length){
            initial.forEach(function(v){ addRow(v); });
        }else{
            addRow('');
        }

        textarea.after(wrap).hide();

        addBtn.on('click', function(){ addRow(''); });
        list.on('click', '.gm2-fonts-remove', function(){ $(this).closest('.gm2-fonts-row').remove(); });

        textarea.closest('form').on('submit', function(){
            var values = [];
            list.find('input').each(function(){
                var v = $(this).val().trim();
                if(v){ values.push(v); }
            });
            textarea.val(values.join('\n'));
        });
    }

    $(function(){
        initRepeater('preload');
        initRepeater('families');

        function renderVariants(list){
            var wrap = $('#gm2-variant-suggestions');
            wrap.empty();
            if(!list || !list.length){ return; }
            list.forEach(function(v){
                var id = 'gm2-variant-' + v.replace(/[^a-z0-9]/gi, '-');
                var label = $('<label for="'+id+'"></label>');
                var chk = $('<input type="checkbox" id="'+id+'" name="gm2seo_fonts[variant_suggestions][]" value="'+v+'" />');
                if($.inArray(v, GM2FontPerf.selected) !== -1){ chk.prop('checked', true); }
                label.append(chk).append(' '+v);
                wrap.append($('<div></div>').append(label));
            });
        }

        function fetchVariants(){
            $.post(GM2FontPerf.ajax_url, {action: 'gm2_detect_font_variants', nonce: GM2FontPerf.nonce}, function(resp){
                if(resp && resp.success){
                    renderVariants(resp.data);
                }
            });
        }

        $('#gm2-detect-variants').on('click', function(e){
            e.preventDefault();
            fetchVariants();
        });

        fetchVariants();
    });
})(jQuery);
