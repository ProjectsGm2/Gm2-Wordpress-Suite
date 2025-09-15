jQuery(function($){
    'use strict';

    var $document = $(document);
    var $body = $(document.body);
    var overlayClass = 'gm2-open-in-code__overlay';
    var overlayOpenClass = 'is-open';
    var styleId = 'gm2-open-in-code-inline-styles';
    var $overlay = null;
    var $frame = null;
    var $activeModal = null;
    var $lastTrigger = null;

    function injectStyles(){
        if($('#' + styleId).length){
            return;
        }
        var css = ''
            + '.gm2-open-in-code__overlay{position:fixed;top:0;left:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;background:rgba(0,0,0,0.6);z-index:100000;}' + '\n'
            + '.gm2-open-in-code__overlay.is-open{display:flex;}' + '\n'
            + '.gm2-open-in-code__frame{position:relative;width:100%;max-width:960px;max-height:100%;overflow:auto;background:#fff;border-radius:4px;box-shadow:0 20px 40px rgba(0,0,0,0.3);padding:24px;}' + '\n'
            + '.gm2-open-in-code__frame > .gm2-open-in-code__close{position:absolute;top:12px;right:12px;background:transparent;border:0;color:#1d2327;font-size:24px;line-height:1;cursor:pointer;}' + '\n'
            + '.gm2-open-in-code__frame > .gm2-open-in-code__close:focus{outline:2px solid #2271b1;}' + '\n'
            + '.gm2-open-in-code__modal{max-height:100%;overflow:auto;}' + '\n'
            + '.gm2-open-in-code__modal textarea{width:100%;min-height:160px;margin:0 0 16px 0;font-family:Menlo,Monaco,monospace;font-size:13px;line-height:1.5;}' + '\n'
            + '.gm2-open-in-code__modal textarea:focus{outline:2px solid #2271b1;}' + '\n'
            + '.gm2-open-in-code__actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}' + '\n'
            + '.gm2-open-in-code__modal .gm2-open-in-code__copy,.gm2-open-in-code__modal .gm2-open-in-code__download{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;text-decoration:none;}' + '\n'
            + '.gm2-open-in-code__modal .gm2-open-in-code__copy.button,.gm2-open-in-code__modal .gm2-open-in-code__download.button{height:auto;}' + '\n'
            + '.gm2-open-in-code--modal-open{overflow:hidden;}' + '\n'
            + '.gm2-open-in-code__copy--success{pointer-events:none;}\n';
        var $style = $('<style type="text/css" id="' + styleId + '"></style>');
        if($style[0].styleSheet){
            $style[0].styleSheet.cssText = css;
        } else {
            $style.append(document.createTextNode(css));
        }
        $('head').append($style);
    }

    function ensureOverlay(){
        if($overlay){
            return;
        }
        injectStyles();
        $overlay = $('<div>', {'class': overlayClass, 'aria-hidden': 'true'});
        $frame = $('<div>', {'class': 'gm2-open-in-code__frame', role: 'dialog', 'aria-modal': 'true'});
        var $close = $('<button>', {
            type: 'button',
            'class': 'gm2-open-in-code__close',
            'aria-label': getLocalizedString('close', 'Close')
        }).text('×');
        $frame.append($close);
        $overlay.append($frame);
        $body.append($overlay);

        $overlay.on('click', function(event){
            if(event.target === $overlay[0]){
                closeModal();
            }
        });

        $close.on('click', function(event){
            event.preventDefault();
            closeModal();
        });
    }

    function prepareModalElement($modal){
        if($modal.data('gm2OpenInCodePrepared')){
            return;
        }
        $modal.attr('aria-hidden', 'true');
        if(!$modal.find('.gm2-open-in-code__actions').length){
            var $buttons = $modal.find('.gm2-open-in-code__copy, .gm2-open-in-code__download');
            if($buttons.length){
                var $actions = $('<div class="gm2-open-in-code__actions"></div>');
                $buttons.each(function(){
                    var $btn = $(this);
                    if($btn.is('button') && !$btn.hasClass('button')){
                        $btn.addClass('button button-secondary');
                    }
                    if($btn.is('a') && !$btn.hasClass('button')){
                        $btn.addClass('button button-secondary');
                    }
                    $actions.append($btn);
                });
                $modal.append($actions);
            }
        }
        $modal.data('gm2OpenInCodePrepared', true);
    }

    function openModal($sourceModal, $trigger){
        ensureOverlay();
        prepareModalElement($sourceModal);

        if($overlay.hasClass(overlayOpenClass) && $lastTrigger && $trigger.is($lastTrigger)){
            closeModal();
            return;
        }

        closeModal(false);

        $lastTrigger = $trigger;
        $activeModal = $sourceModal.clone(true, true);
        $activeModal.removeAttr('style');
        $activeModal.attr({'aria-hidden': 'false'});
        $activeModal.addClass('gm2-open-in-code__modal--active');

        $frame.children('.gm2-open-in-code__modal--active').remove();
        $frame.append($activeModal);

        $overlay.addClass(overlayOpenClass).attr('aria-hidden', 'false');
        $body.addClass('gm2-open-in-code--modal-open');

        setTimeout(function(){
            var $firstField = $activeModal.find('textarea').first();
            if($firstField.length){
                $firstField.focus().select();
            } else {
                var $close = $frame.find('.gm2-open-in-code__close');
                if($close.length){
                    $close.focus();
                }
            }
        }, 10);
    }

    function closeModal(restoreFocus){
        if(typeof restoreFocus === 'undefined'){
            restoreFocus = true;
        }

        if($activeModal){
            $activeModal.remove();
            $activeModal = null;
        }

        if($overlay){
            $overlay.removeClass(overlayOpenClass).attr('aria-hidden', 'true');
        }

        $body.removeClass('gm2-open-in-code--modal-open');

        if(restoreFocus && $lastTrigger && $lastTrigger.length){
            $lastTrigger.focus();
            $lastTrigger = null;
        } else if(restoreFocus){
            $lastTrigger = null;
        }
    }

    function getLocalizedString(key, fallback){
        if(window.gm2OpenInCodeL10n && window.gm2OpenInCodeL10n[key]){
            return window.gm2OpenInCodeL10n[key];
        }
        return fallback;
    }

    function storeOriginalButtonText($button){
        if(!$button.data('gm2OriginalText')){
            $button.data('gm2OriginalText', $.trim($button.text()));
        }
        return $button.data('gm2OriginalText');
    }

    function showCopySuccess($button, originalText){
        var successText = getLocalizedString('copied', 'Copied!');
        $button.text(successText).prop('disabled', true).addClass('gm2-open-in-code__copy--success');
        setTimeout(function(){
            $button.text(originalText).prop('disabled', false).removeClass('gm2-open-in-code__copy--success');
        }, 2000);
    }

    function showCopyFailure($button, originalText){
        var failureText = getLocalizedString('copyError', 'Copy failed');
        $button.text(failureText);
        setTimeout(function(){
            $button.text(originalText);
        }, 2000);
    }

    function fallbackCopy(text, callback){
        var activeElement = document.activeElement;
        var $temp = $('<textarea>').val(text).css({position:'absolute', left:'-9999px', top:'0'});
        $body.append($temp);
        var tempEl = $temp[0];
        tempEl.focus();
        tempEl.select();
        var succeeded = false;
        try {
            succeeded = document.execCommand('copy');
        } catch(err){
            succeeded = false;
        }
        $temp.remove();
        if(activeElement && typeof activeElement.focus === 'function'){
            activeElement.focus();
        }
        if(callback){
            callback(succeeded);
        }
    }

    function copyToClipboard(text, $button){
        if(typeof text !== 'string'){
            text = '';
        }
        var trimmed = text.replace(/\s+$/, '');
        var originalText = storeOriginalButtonText($button);
        if(!trimmed.length){
            showCopyFailure($button, originalText);
            return;
        }

        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(text).then(function(){
                showCopySuccess($button, originalText);
            }).catch(function(){
                fallbackCopy(text, function(success){
                    if(success){
                        showCopySuccess($button, originalText);
                    } else {
                        showCopyFailure($button, originalText);
                    }
                });
            });
            return;
        }

        fallbackCopy(text, function(success){
            if(success){
                showCopySuccess($button, originalText);
            } else {
                showCopyFailure($button, originalText);
            }
        });
    }

    ensureOverlay();
    $('.gm2-open-in-code__modal').each(function(){
        prepareModalElement($(this));
    });

    $document.on('click', '.gm2-open-in-code__trigger', function(event){
        event.preventDefault();
        var $trigger = $(this);
        var $modal = $trigger.closest('.gm2-open-in-code').find('.gm2-open-in-code__modal').first();
        if(!$modal.length){
            return;
        }
        openModal($modal, $trigger);
    });

    $document.on('click', '.gm2-open-in-code__copy', function(event){
        event.preventDefault();
        var $button = $(this);
        var target = $button.data('target');
        if(!target){
            return;
        }
        var $modal = $button.closest('.gm2-open-in-code__modal');
        var $field = $modal.find('.gm2-open-in-code__' + target);
        if(!$field.length){
            return;
        }
        copyToClipboard($field.val(), $button);
    });

    $document.on('click', '.gm2-open-in-code__download', function(){
        if(typeof window.Blob === 'undefined' || typeof window.URL === 'undefined' || typeof window.URL.createObjectURL !== 'function'){
            return;
        }
        var $link = $(this);
        var downloadName = ($link.attr('download') || '').toLowerCase();
        var type = downloadName.indexOf('json') !== -1 ? 'json' : 'php';
        var $modal = $link.closest('.gm2-open-in-code__modal');
        var $field = $modal.find('.gm2-open-in-code__' + type);
        if(!$field.length){
            return;
        }
        var mime = type === 'json' ? 'application/json' : 'text/plain';
        try {
            var blob = new Blob([$field.val()], {type: mime});
            var url = window.URL.createObjectURL(blob);
            $link.attr('href', url);
            setTimeout(function(){
                try {
                    window.URL.revokeObjectURL(url);
                } catch(err){
                    // ignore
                }
            }, 5000);
        } catch(error){
            // Fallback to default href
        }
    });

    $document.on('focus', '.gm2-open-in-code__modal textarea', function(){
        var textarea = this;
        setTimeout(function(){
            if(textarea && textarea.select){
                textarea.select();
            }
        }, 0);
    });

    $document.on('keydown', function(event){
        if(event.key === 'Escape' || event.keyCode === 27){
            if($overlay && $overlay.hasClass(overlayOpenClass)){
                event.preventDefault();
                closeModal();
            }
        }
    });
});
