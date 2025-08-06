jQuery(function($){
    var cardTmpl = wp.template('gm2-schema-card');

    function fetchSchema(){
        var data = {
            action: 'gm2_schema_preview',
            nonce: gm2SchemaPreview.nonce,
            post_id: gm2SchemaPreview.post_id,
            term_id: gm2SchemaPreview.term_id,
            taxonomy: gm2SchemaPreview.taxonomy,
            schema_type: $('#gm2_schema_type').val(),
            schema_brand: $('#gm2_schema_brand').val(),
            schema_rating: $('#gm2_schema_rating').val()
        };
        $.post(gm2SchemaPreview.ajax_url, data, function(resp){
            if(resp && resp.success){
                var schemaObj = $.isArray(resp.data) ? resp.data[0] : resp.data;
                $('#gm2-schema-preview').html(cardTmpl(schemaObj));
            } else {
                $('#gm2-schema-preview').empty();
            }
        });
    }

    $('#gm2_schema_type, #gm2_schema_brand, #gm2_schema_rating').on('change input', fetchSchema);
    fetchSchema();
});
