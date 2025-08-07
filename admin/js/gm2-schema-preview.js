jQuery(function($){
    var cardTmpl = wp.template('gm2-schema-card');

    function fetchSchema(){
        if (typeof gm2SchemaPreview === 'undefined') {
            return;
        }
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

    if (typeof gm2SchemaPreview !== 'undefined') {
        $('#gm2_schema_type, #gm2_schema_brand, #gm2_schema_rating').on('change input', fetchSchema);
        fetchSchema();
    }

    var $custom = $('#gm2_custom_schema_json');
    if ($custom.length) {
        function renderCustom(){
            var val = $custom.val();
            try {
                var obj = JSON.parse(val);
                $('#gm2-custom-schema-preview').html(cardTmpl(obj));
            } catch(e) {
                $('#gm2-custom-schema-preview').empty();
            }
        }
        $custom.on('input', renderCustom);
        renderCustom();
    }
});
