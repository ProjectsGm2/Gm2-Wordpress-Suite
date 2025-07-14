jQuery(function($){
    var LS_KEY = 'gm2_ai_seo_results';
    $('#gm2-ai-seo').on('click', '.gm2-ai-research', function(e){
        e.preventDefault();
        var researchingText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.researching : 'Researching...';
        var $out = $('#gm2-ai-results').text(researchingText);
        var data = {
            action: 'gm2_ai_research',
            _ajax_nonce: (window.gm2AiSeo && gm2AiSeo.nonce) ? gm2AiSeo.nonce : ''
        };
        // collect current field values so unsaved changes are included
        data.seo_title       = $('#gm2_seo_title').val() || '';
        data.seo_description = $('#gm2_seo_description').val() || '';
        data.focus_keywords  = $('#gm2_focus_keywords').val() || '';
        data.canonical       = $('#gm2_canonical_url').val() || '';
        if (data.seo_title || data.seo_description || data.focus_keywords || data.canonical) {
            var confirmText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.useExisting : 'Use existing SEO values for AI research?';
            if (!confirm(confirmText)) {
                data.seo_title = '';
                data.seo_description = '';
                data.focus_keywords = '';
                data.canonical = '';
            }
        }
        if(window.gm2AiSeo){
            if(gm2AiSeo.post_id){
                data.post_id = gm2AiSeo.post_id;
            }
            if(gm2AiSeo.term_id){
                data.term_id = gm2AiSeo.term_id;
                data.taxonomy = gm2AiSeo.taxonomy;
            }
        }

        if(!data.seo_title && !data.seo_description && !data.focus_keywords && !data.canonical){
            var promptText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.promptExtra : 'Describe the page or its target audience:';
            var extra = prompt(promptText);
            if(extra){
                data.extra_context = extra;
            }
        }
        $.post((window.gm2AiSeo ? gm2AiSeo.ajax_url : ajaxurl), data)
        .done(function(resp){
            $out.empty();
            if(resp && resp.success && resp.data){
                if(window.gm2AiSeo){
                    gm2AiSeo.results = resp.data;
                }
                if(typeof resp.data === 'object' && !resp.data.response){
                    buildResults(resp.data, $out);
                    if(window.gm2AiSeo && parseInt(gm2AiSeo.post_id, 10) === 0){
                        try{ localStorage.setItem(LS_KEY, JSON.stringify(resp.data)); }catch(e){}
                    }
                } else if(resp.data.response){
                    try {
                        var parsed = JSON.parse(resp.data.response);
                        if(parsed && typeof parsed === 'object'){
                            if(resp.data.html_issues){
                                parsed.html_issues = resp.data.html_issues;
                            }
                            buildResults(parsed, $out);
                            if(window.gm2AiSeo && parseInt(gm2AiSeo.post_id, 10) === 0){
                                try{ localStorage.setItem(LS_KEY, JSON.stringify(parsed)); }catch(e){}
                            }
                            return;
                        }
                    } catch(e) {}
                    $out.text(resp.data.response);
                } else {
                    $out.text(typeof resp.data === 'string' ? resp.data : 'Error');
                }
            } else {
                var msg = resp && resp.data ? resp.data : 'Error';
                $('<div>', {'class':'notice notice-error gm2-ai-error'}).text(msg).appendTo($out);
            }
        })
        .fail(function(jqXHR, textStatus){
            var msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data)
                ? jqXHR.responseJSON.data
                : (jqXHR && jqXHR.responseText ? jqXHR.responseText : textStatus);
            $('<div>', {'class':'notice notice-error gm2-ai-error'})
                .text(msg || 'Request failed').appendTo($out.empty());
        });
    });
    $('#gm2-ai-seo').on('click', '.gm2-ai-implement', function(e){
        e.preventDefault();
        applySelected();
    });

    $('#gm2-ai-seo').on('click', '#gm2-ai-select-all', function(){
        var checked = $(this).prop('checked');
        $('#gm2-ai-suggestions input[type="checkbox"]').prop('checked', checked);
    });

    $('#gm2-ai-seo').on('click', '.gm2-html-fix', function(e){
        e.preventDefault();
        var fix = $(this).data('fix');
        if(!fix) return;
        if(typeof wp !== 'undefined' && wp.data){
            var content = wp.data.select('core/editor').getEditedPostContent();
            wp.data.dispatch('core/editor').editPost({content: content + "\n" + fix});
        }
        $(this).closest('li').fadeOut(300);
    });

    function buildResults(data, $out){
        var $wrap = $('<div>');
        var $list = $('<div>', {id:'gm2-ai-suggestions'});
        var labels = window.gm2AiSeo && gm2AiSeo.i18n && gm2AiSeo.i18n.labels ? gm2AiSeo.i18n.labels : {};
        var fields = {
            seo_title: labels.seoTitle || 'SEO Title',
            description: labels.description || 'SEO Description',
            focus_keywords: labels.focusKeywords || 'Focus Keywords',
            long_tail_keywords: labels.longTailKeywords || 'Long Tail Keywords',
            canonical: labels.canonical || 'Canonical URL',
            page_name: labels.pageName || 'Page Name'
        };
        if(typeof data.slug !== 'undefined'){
            fields.slug = labels.slug || 'Slug';
        }
        var added = 0;
        Object.keys(fields).forEach(function(key){
            if(typeof data[key] === 'undefined') return;
            added++;
            var label = fields[key];
            var val = Array.isArray(data[key]) ? data[key].join(', ') : data[key];
            var $lbl = $('<label>');
            $('<input>', {type:'checkbox','class':'gm2-ai-select', 'data-field':key, 'data-value':val}).appendTo($lbl);
            $lbl.append(document.createTextNode(' ' + label + ': ' + val));
            $list.append($('<p>').append($lbl));
        });
        if(added){
            var selectAllText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.selectAll : 'Select all';
            var $selectAll = $('<p>').append(
                $('<label>').append(
                    $('<input>', {type:'checkbox', id:'gm2-ai-select-all'})
                ).append(' ' + selectAllText)
            );
            $wrap.append($selectAll).append($list);
        } else {
            var parseErrorText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.parseError : 'Unable to parse AI response—please try again';
            $('<div>', {'class':'notice notice-warning gm2-ai-warning'})
                .text(parseErrorText).appendTo($wrap);
        }

        if(data.long_tail_keywords){
            var valLt = [].concat(data.long_tail_keywords).join(', ');
            var ltText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.longTailKeywords : 'Long Tail Keywords';
            var $lblLt = $('<label>');
            $('<input>', {type:'checkbox','class':'gm2-ai-select','data-field':'long_tail_keywords','data-value':valLt}).appendTo($lblLt);
            $lblLt.append(document.createTextNode(' ' + ltText + ': ' + valLt));
            $list.append($('<p>').append($lblLt));
        }
        if(data.content_suggestions){
            var $cs = $('<ul>');
            [].concat(data.content_suggestions).forEach(function(c){
                $('<li>').text(c).appendTo($cs);
            });
            var csText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.contentSuggestions : 'Content Suggestions';
            $wrap.append('<h4>' + csText + '</h4>').append($cs);
        }
        if(data.html_issues){
            var $issues = $('<ul>', {id:'gm2-html-issues'});
            [].concat(data.html_issues).forEach(function(issue){
                var text = issue.issue || issue;
                var fix = issue.fix || '';
                var $li = $('<li>').addClass('gm2-html-issue').text(text);
                if(fix){
                    var applyFixText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.applyFix : 'Apply fix';
                    $('<button>', {text:applyFixText, 'class':'button gm2-html-fix', 'data-fix':fix}).appendTo($li);
                }
                $issues.append($li);
            });
            var htmlIssuesText = window.gm2AiSeo && gm2AiSeo.i18n ? gm2AiSeo.i18n.htmlIssues : 'HTML Issues';
            $wrap.append('<h4>' + htmlIssuesText + '</h4>').append($issues);
        }
        $out.append($wrap);
    }

    function applySelected(){
        $('#gm2-ai-suggestions .gm2-ai-select:checked').each(function(){
            var field = $(this).data('field');
            var val = $(this).data('value');
            switch(field){
                case 'seo_title':
                    $('#gm2_seo_title').val(val); break;
                case 'description':
                    $('#gm2_seo_description').val(val); break;
                case 'focus_keywords':
                    $('#gm2_focus_keywords').val(val); break;
                case 'long_tail_keywords':
                    $('#gm2_long_tail_keywords').val(val); break;
                case 'canonical':
                    $('#gm2_canonical_url').val(val); break;
                case 'page_name':
                    if(window.gm2AiSeo && gm2AiSeo.post_id){
                        $('#title').val(val);
                    } else if(window.gm2AiSeo && gm2AiSeo.term_id){
                        $('#tag-name').val(val);
                    }
                    break;
                case 'slug':
                    if(window.gm2AiSeo && gm2AiSeo.post_id){
                        $('#post_name').val(val);
                    } else if(window.gm2AiSeo && gm2AiSeo.term_id){
                        $('#tag-slug').val(val);
                    }
                    break;
            }
        });
    }

    if(window.gm2AiSeo){
        if(gm2AiSeo.results){
            var results = gm2AiSeo.results;
            if(typeof results === 'string'){
                try {
                    results = JSON.parse(results);
                } catch(err){
                    if(window.console && console.error){
                        console.error('Invalid gm2AiSeo.results JSON', err);
                    }
                    results = null;
                }
            }
            if(results && typeof results === 'object'){
                buildResults(results, $('#gm2-ai-results'));
                if(parseInt(gm2AiSeo.post_id, 10) === 0){
                    try{ localStorage.setItem(LS_KEY, JSON.stringify(results)); }catch(e){}
                } else {
                    localStorage.removeItem(LS_KEY);
                }
            }
        } else if(parseInt(gm2AiSeo.post_id, 10) === 0){
            var stored = localStorage.getItem(LS_KEY);
            if(stored){
                try{ stored = JSON.parse(stored); }catch(e){ stored = null; }
                if(stored && typeof stored === 'object'){
                    buildResults(stored, $('#gm2-ai-results'));
                }
            }
        } else {
            localStorage.removeItem(LS_KEY);
        }
    }
});
