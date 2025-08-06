jQuery(function($){
    function updateLink(){
        var $link = $('#gm2-rich-results-preview');
        if(!$link.length){
            return;
        }
        var permalink = '';
        if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor')) {
            var p = wp.data.select('core/editor').getPermalink();
            if (p) {
                permalink = p;
            }
        }
        if(!permalink){
            var slug = $('#post_name').val() || $('#editable-post-name-full').text() || $('#slug').val() || $('#tag-slug').val() || '';
            if(slug){
                permalink = window.location.origin.replace(/\/$/,'') + '/' + slug;
            }
        }
        if(permalink){
            $link.attr('href', 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(permalink));
        }
    }
    updateLink();
    $(document).on('input change', '#post_name, #slug, #editable-post-name, #tag-slug', updateLink);
    if (typeof wp !== 'undefined' && wp.data && typeof wp.data.subscribe === 'function') {
        wp.data.subscribe(updateLink);
    }
});
