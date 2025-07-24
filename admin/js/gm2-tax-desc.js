jQuery(function($){
    function insertButton($ta){
        if(!$ta.length || $ta.data('gm2-enhanced')) return;
        var $btn = $('<p><button class="button gm2-generate-desc">Generate Description</button></p>');
        $btn.insertAfter($ta);
        $ta.data('gm2-enhanced', true);
    }
    insertButton($('#tag-description'));
    insertButton($('#description'));

    $(document).on('click', '.gm2-generate-desc', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $ta = $btn.closest('p').prev('textarea');
        var name = $('#tag-name').val() || $('#name').val() || '';
        var loadingText = (window.gm2TaxDesc && gm2TaxDesc.loading) ? gm2TaxDesc.loading : 'Researching...';
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(loadingText);
        $.post(gm2TaxDesc.ajax_url, {
            action: 'gm2_ai_generate_tax_description',
            term_id: gm2TaxDesc.term_id,
            taxonomy: gm2TaxDesc.taxonomy,
            name: name,
            _ajax_nonce: gm2TaxDesc.nonce
        }).done(function(resp){
            if(resp && resp.success){
                $ta.val(resp.data);
            } else if(resp && resp.data){
                alert(resp.data);
            } else {
                alert('Error');
            }
        }).fail(function(){
            alert('Request failed');
        }).always(function(){
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
