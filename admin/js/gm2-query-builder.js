jQuery(function($){
    $('#gm2-save-query').on('click', function(e){
        e.preventDefault();
        var data = {
            action: 'gm2_save_query',
            nonce: gm2QB.nonce,
            id: $('#gm2-qb-id').val(),
            args: {
                post_type: $('#gm2-qb-post-type').val(),
                tax_query: [],
                meta_query: [],
                date_query: []
            }
        };
        var tax = $('#gm2-qb-taxonomy').val();
        var term = $('#gm2-qb-term').val();
        if(tax && term){
            data.args.tax_query.push({taxonomy: tax, terms: [term]});
        }
        var mk = $('#gm2-qb-meta-key').val();
        var mv = $('#gm2-qb-meta-value').val();
        if(mk){
            data.args.meta_query.push({key: mk, value: mv});
        }
        var after = $('#gm2-qb-after').val();
        var before = $('#gm2-qb-before').val();
        if(after || before){
            data.args.date_query.push({after: after, before: before});
        }
        $.post(gm2QB.ajax, data, function(resp){
            if(resp && resp.success){
                window.location.reload();
            }else{
                alert('Error saving query');
            }
        });
    });
});
