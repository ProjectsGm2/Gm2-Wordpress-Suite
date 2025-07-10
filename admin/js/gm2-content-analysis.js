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
        return {wordCount, topWord, density, readability, words: words.map(function(w){return w.toLowerCase();}), text};
    }

    function analyzeFocusKeywords(text, wordCount, keywords){
        const lower = text.toLowerCase();
        const result = {};
        keywords.forEach(function(k){
            const key = k.trim().toLowerCase();
            if(!key) return;
            const wordLen = key.split(/\s+/).filter(Boolean).length;
            const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            const matches = lower.match(regex);
            const count = matches ? matches.length : 0;
            const density = wordCount ? ((count * wordLen) / wordCount * 100).toFixed(2) : '0';
            result[key] = density;
        });
        return result;
    }

    function applyRuleResults(results){
        const list = $('.gm2-analysis-rules');
        Object.keys(results).forEach(function(key){
            let $li = list.find('li[data-key="' + key + '"]');
            if(!$li.length){
                $li = $('<li>').attr('data-key', key)
                    .append('<span class="dashicons"></span> ' + key.replace(/-/g,' '))
                    .appendTo(list);
            }
            const pass = results[key];
            $li.toggleClass('pass', pass).toggleClass('fail', !pass)
                .find('.dashicons').removeClass('dashicons-no dashicons-yes')
                .addClass(pass ? 'dashicons-yes' : 'dashicons-no');
        });
    }

    function checkRules(content, title, description, focus, postType){
        $.post(ajaxurl, {
            action: 'gm2_check_rules',
            content: content,
            title: title,
            description: description,
            focus: focus,
            post_type: postType,
            _ajax_nonce: window.gm2ContentAnalysisData ? window.gm2ContentAnalysisData.nonce : ''
        }, function(resp){
            if(resp && resp.success){
                applyRuleResults(resp.data);
            }
        });
    }

    function getElementorContent(){
        if(window.elementor){
            if(elementor.channels && elementor.channels.editor && elementor.channels.editor.request){
                var model = elementor.channels.editor.request('document:model');
                if(model && model.get){
                    return model.get('content') || model.get('post_content') || '';
                }
            }
            if(elementor.settings && elementor.settings.page && elementor.settings.page.model){
                return elementor.settings.page.model.get('content') || elementor.settings.page.model.get('post_content') || '';
            }
        }
        return '';
    }

    function update(){
        var content = '';
        if(typeof wp !== 'undefined' && wp.data){
            content = wp.data.select('core/editor').getEditedPostContent();
        }
        if(!content){
            content = getElementorContent();
        }
        if(!content) return;
        const data = analyzeContent(content);
        const kwInput = $('#gm2_focus_keywords').val() || '';
        const keywords = kwInput.split(',');
        const densities = analyzeFocusKeywords(data.text, data.wordCount, keywords);
        $('#gm2-content-analysis-word-count').text(data.wordCount);
        $('#gm2-content-analysis-keyword').text(data.topWord);
        $('#gm2-content-analysis-density').text(data.density);
        $('#gm2-content-analysis-readability').text(data.readability.toFixed(2));
        const kwList = $('#gm2-focus-keyword-density').empty();
        Object.keys(densities).forEach(function(k){
            $('<li>').text(k + ': ' + densities[k] + '%').appendTo(kwList);
        });
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

        const ptype = window.gm2ContentAnalysisData ? window.gm2ContentAnalysisData.postType : '';
        checkRules(content, $('#gm2_seo_title').val() || '', $('#gm2_seo_description').val() || '', kwInput, ptype);
    }

    $(document).ready(function(){
        update();
        if(typeof wp !== 'undefined' && wp.data){
            wp.data.subscribe(update);
        }

        function initElementorPanel(){
            var $panel = jQuery('#gm2-elementor-seo-panel');
            if($panel.length){
                jQuery('#elementor-panel-footer').append($panel);
            }
            if(elementor.hooks && elementor.hooks.addAction){
                elementor.hooks.addAction('document:save', update);
            }
            if(elementor.channels && elementor.channels.editor){
                elementor.channels.editor.on('change', update);
            }
            update();
        }

        if(window.elementor){
            if(elementor.on){
                elementor.on('init', initElementorPanel);
            } else {
                initElementorPanel();
            }
        }

        $(document).on('input', '#gm2_focus_keywords', update);
    });
})(jQuery);
