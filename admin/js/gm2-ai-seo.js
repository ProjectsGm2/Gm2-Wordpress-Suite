jQuery(function($){
    $('#gm2-ai-seo').on('click', '.gm2-ai-research', function(e){
        e.preventDefault();
        var $out = $('#gm2-ai-results').text('Researching...');
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
            if (!confirm('Use existing SEO values for AI research?')) {
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
            var extra = prompt('Describe the page or its target audience:');
            if(extra){
                data.extra_context = extra;
            }
        }
        $.post((window.gm2AiSeo ? gm2AiSeo.ajax_url : ajaxurl), data)
        .done(function(resp){
            $out.empty();
            if(resp && resp.success && resp.data){
                if(typeof resp.data === 'object' && !resp.data.response){
                    buildResults(resp.data, $out);
                } else if(resp.data.response){
                    try {
                        var parsed = JSON.parse(resp.data.response);
                        if(parsed && typeof parsed === 'object'){
                            if(resp.data.html_issues){
                                parsed.html_issues = resp.data.html_issues;
                            }
                            buildResults(parsed, $out);
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
        var $selectAll = $('<p>').append(
            $('<label>').append(
                $('<input>', {type:'checkbox', id:'gm2-ai-select-all'})
            ).append(' Select all')
        );
        var $list = $('<div>', {id:'gm2-ai-suggestions'});
        var fields = {
            seo_title: 'SEO Title',
            description: 'SEO Description',
            focus_keywords: 'Focus Keywords',
            canonical: 'Canonical URL',
            page_name: 'Page Name'
        };
        if(typeof data.slug !== 'undefined'){
            fields.slug = 'Slug';
        }
        Object.keys(fields).forEach(function(key){
            if(typeof data[key] === 'undefined') return;
            var label = fields[key];
            var val = Array.isArray(data[key]) ? data[key].join(', ') : data[key];
            var $lbl = $('<label>');
            $('<input>', {type:'checkbox','class':'gm2-ai-select', 'data-field':key, 'data-value':val}).appendTo($lbl);
            $lbl.append(' '+label+': '+val);
            $list.append($('<p>').append($lbl));
        });
        $wrap.append($selectAll).append($list);

        if(data.long_tail_keywords){
            var $kw = $('<ul>');
            [].concat(data.long_tail_keywords).forEach(function(k){
                $('<li>').text(k).appendTo($kw);
            });
            $wrap.append('<h4>Long Tail Keywords</h4>').append($kw);
        }
        if(data.content_suggestions){
            var $cs = $('<ul>');
            [].concat(data.content_suggestions).forEach(function(c){
                $('<li>').text(c).appendTo($cs);
            });
            $wrap.append('<h4>Content Suggestions</h4>').append($cs);
        }
        if(data.html_issues){
            var $issues = $('<ul>', {id:'gm2-html-issues'});
            [].concat(data.html_issues).forEach(function(issue){
                var text = issue.issue || issue;
                var fix = issue.fix || '';
                var $li = $('<li>').addClass('gm2-html-issue').text(text);
                if(fix){
                    $('<button>', {text:'Apply fix', 'class':'button gm2-html-fix', 'data-fix':fix}).appendTo($li);
                }
                $issues.append($li);
            });
            $wrap.append('<h4>HTML Issues</h4>').append($issues);
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
});
