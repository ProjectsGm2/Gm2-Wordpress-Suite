(function($){
    function countSyllables(word) {
        word = word.toLowerCase();
        if(word.length <= 3) return 1;
        word = word.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '');
        word = word.replace(/^y/, '');
        const matches = word.match(/[aeiouy]{1,2}/g);
        return matches ? matches.length : 1;
    }

    function analyzeContent(content) {
        const text = $('<div>').html(content).text();
        const words = text.match(/\b\w+\b/g) || [];
        const wordCount = words.length;
        const freq = {};
        words.forEach(function(w){
            w = w.toLowerCase();
            freq[w] = (freq[w] || 0) + 1;
        });
        let topWord = '';
        let topCount = 0;
        for(const w in freq){
            if(freq[w] > topCount){
                topCount = freq[w];
                topWord = w;
            }
        }
        const density = wordCount ? (topCount / wordCount * 100).toFixed(2) : '0';
        const sentences = text.split(/[.!?]+/).filter(function(s){return s.trim().length > 0;});
        const syllables = words.reduce(function(t,w){return t + countSyllables(w);},0);
        const readability = (sentences.length && wordCount) ?
            206.835 - 1.015*(wordCount/sentences.length) - 84.6*(syllables/wordCount)
            : 0;
        return {wordCount, topWord, density, readability, words: words.map(function(w){return w.toLowerCase();})};
    }

    function update(){
        if(typeof wp === 'undefined' || !wp.data) return;
        const content = wp.data.select('core/editor').getEditedPostContent();
        const data = analyzeContent(content);
        $('#gm2-content-analysis-word-count').text(data.wordCount);
        $('#gm2-content-analysis-keyword').text(data.topWord);
        $('#gm2-content-analysis-density').text(data.density);
        $('#gm2-content-analysis-readability').text(data.readability.toFixed(2));
        const used = new Set(data.words);
        const suggestions = [];
        if(window.gm2ContentAnalysisData){
            window.gm2ContentAnalysisData.posts.forEach(function(p){
                const match = p.title.toLowerCase().split(/\W+/).some(function(w){
                    return used.has(w);
                });
                if(match){
                    suggestions.push(p);
                }
            });
        }
        const list = $('#gm2-content-analysis-links').empty();
        suggestions.slice(0,5).forEach(function(p){
            $('<li>').append($('<a>').attr('href', p.link).text(p.title)).appendTo(list);
        });
    }

    $(document).ready(function(){
        update();
        if(typeof wp !== 'undefined' && wp.data){
            wp.data.subscribe(update);
        }
    });
})(jQuery);
