jQuery(function($){
    $('#gm2-ai-seo').on('click', '.gm2-ai-research', function(e){
        e.preventDefault();
        $('#gm2-ai-results').text('Researching...');
        // TODO: implement AI research AJAX call
    });
    $('#gm2-ai-seo').on('click', '.gm2-ai-implement', function(e){
        e.preventDefault();
        // TODO: implement applying selected results
        alert('Implement selected results');
    });
});
