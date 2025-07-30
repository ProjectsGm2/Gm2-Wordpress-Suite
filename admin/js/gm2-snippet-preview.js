jQuery(function($){
    function getSlug(){
        return $('#post_name').val() || $('#tag-slug').val() || $('#slug').val() || $('#editable-post-name-full').text() || '';
    }
    function updatePreview(){
        var title = $('#gm2_seo_title').val() || '';
        var desc  = $('#gm2_seo_description').val() || '';
        var slug  = getSlug();
        var url   = window.location.origin.replace(/\/$/,'') + '/' + slug;
        $('#gm2-snippet-preview .gm2-snippet-title').text(title);
        $('#gm2-snippet-preview .gm2-snippet-url').text(url);
        $('#gm2-snippet-preview .gm2-snippet-description').text(desc);
    }
    function init(){
        var $desc = $('#gm2_seo_description');
        if(!$desc.length || $('#gm2-snippet-preview').length){
            return;
        }
        var box = $('<div id="gm2-snippet-preview" class="gm2-snippet-preview">'+
            '<div class="gm2-snippet-title"></div>'+
            '<div class="gm2-snippet-url"></div>'+
            '<div class="gm2-snippet-description"></div>'+
        '</div>');
        $desc.closest('p, .form-field, tr').after(box);
        updatePreview();
        $('#gm2_seo_title, #gm2_seo_description, #post_name, #tag-slug, #slug').on('input change', updatePreview);
        $(document).on('input', '#editable-post-name', updatePreview);
    }
    $(init);
});
