jQuery(function($){
    function fetchSchema(){
        var data = {
            action: 'gm2_schema_preview',
            nonce: gm2SchemaPreview.nonce,
            post_id: gm2SchemaPreview.post_id,
            schema_type: $('#gm2_schema_type').val(),
            schema_brand: $('#gm2_schema_brand').val(),
            schema_rating: $('#gm2_schema_rating').val()
        };
        $.post(gm2SchemaPreview.ajax_url, data, function(resp){
            if(resp && resp.success){
                $('#gm2-schema-preview').text(JSON.stringify(resp.data, null, 2));
            }
        });
    }
    $('#gm2_schema_type, #gm2_schema_brand, #gm2_schema_rating').on('change input', fetchSchema);
    fetchSchema();
});
