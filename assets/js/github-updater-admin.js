(function ($) {
    'use strict';

    var settingsForm = $('#gm2-github-updater-form');
    if (!settingsForm.length || typeof gm2GitHubUpdaterAdmin === 'undefined') {
        return;
    }

    var tokenToggle = $('#gm2-github-token-toggle');
    var tokenFieldWrapper = $('#gm2-github-token-field');
    var tokenInput = $('#gm2-github-token');
    var tokenKeep = $('#gm2-github-token-keep');
    var branchRow = $('#gm2-github-branch-row');
    var channelRadios = $('input[name="' + gm2GitHubUpdaterAdmin.optionKey + '[channel]"]');
    var feedback = $('#gm2-github-updater-feedback');
    var testButton = $('#gm2-github-test');
    var checkButton = $('#gm2-github-check');

    function renderMessage(type, message) {
        var notice = $('<div/>', {
            'class': 'notice notice-' + type + ' is-dismissible'
        });
        $('<p/>').text(message).appendTo(notice);
        $('<button/>', {
            'type': 'button',
            'class': 'notice-dismiss'
        }).append(
            $('<span/>', {
                'class': 'screen-reader-text',
                'text': gm2GitHubUpdaterAdmin.i18n.dismiss
            })
        ).appendTo(notice);
        feedback.empty().append(notice);
    }

    function toggleTokenField(show) {
        if (show) {
            tokenFieldWrapper.show().attr('aria-hidden', 'false');
            tokenInput.prop('disabled', false).focus();
            tokenKeep.val('0');
            tokenToggle.text(gm2GitHubUpdaterAdmin.i18n.hideToken).attr('aria-expanded', 'true');
        } else {
            tokenFieldWrapper.hide().attr('aria-hidden', 'true');
            tokenInput.prop('disabled', true).val('');
            tokenKeep.val('1');
            tokenToggle.text(gm2GitHubUpdaterAdmin.i18n.showToken).attr('aria-expanded', 'false');
        }
    }

    tokenToggle.on('click', function (event) {
        event.preventDefault();
        var shouldShow = tokenFieldWrapper.is(':hidden');
        toggleTokenField(shouldShow);
    });

    if (tokenKeep.val() === '0') {
        toggleTokenField(true);
    } else if (tokenFieldWrapper.is(':hidden')) {
        tokenToggle.text(gm2GitHubUpdaterAdmin.i18n.showToken);
    }

    function updateBranchVisibility() {
        var selected = channelRadios.filter(':checked').val();
        if (selected === 'branch') {
            branchRow.show();
        } else {
            branchRow.hide();
        }
    }

    channelRadios.on('change', updateBranchVisibility);
    updateBranchVisibility();

    function setButtonsDisabled(disabled) {
        testButton.prop('disabled', disabled);
        checkButton.prop('disabled', disabled);
    }

    function handleAjax(button, action, pendingMessage, successHandler) {
        setButtonsDisabled(true);
        renderMessage('info', pendingMessage);

        $.ajax({
            method: 'POST',
            url: gm2GitHubUpdaterAdmin.ajaxUrl,
            dataType: 'json',
            data: {
                action: action,
                nonce: gm2GitHubUpdaterAdmin.nonce
            }
        }).done(function (response) {
            if (response && response.success && typeof successHandler === 'function') {
                successHandler(response.data || {});
            } else if (response && response.data && response.data.message) {
                renderMessage('error', response.data.message);
            } else {
                renderMessage('error', gm2GitHubUpdaterAdmin.i18n.unknownError);
            }
        }).fail(function (jqXHR) {
            var message = gm2GitHubUpdaterAdmin.i18n.unknownError;
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                message = jqXHR.responseJSON.data.message;
            }
            renderMessage('error', message);
        }).always(function () {
            setButtonsDisabled(false);
        });
    }

    testButton.on('click', function (event) {
        event.preventDefault();
        handleAjax(testButton, testButton.data('action'), gm2GitHubUpdaterAdmin.i18n.testing, function (data) {
            var message = gm2GitHubUpdaterAdmin.i18n.testSuccess;
            if (data.repository) {
                var details = [];
                if (data.repository.full_name) {
                    details.push(data.repository.full_name);
                }
                if (data.repository.default_branch) {
                    details.push(gm2GitHubUpdaterAdmin.i18n.defaultBranch.replace('%s', data.repository.default_branch));
                }
                if (data.repository.private) {
                    details.push(gm2GitHubUpdaterAdmin.i18n.privateRepo);
                }
                if (details.length) {
                    message += ' ' + details.join(' • ');
                }
            }
            renderMessage('success', message);
        });
    });

    checkButton.on('click', function (event) {
        event.preventDefault();
        handleAjax(checkButton, checkButton.data('action'), gm2GitHubUpdaterAdmin.i18n.checking, function (data) {
            var message = gm2GitHubUpdaterAdmin.i18n.checkSuccess;
            if (data.metadata) {
                var parts = [];
                if (data.metadata.version) {
                    parts.push(gm2GitHubUpdaterAdmin.i18n.versionLabel.replace('%s', data.metadata.version));
                }
                if (data.metadata.last_updated) {
                    parts.push(gm2GitHubUpdaterAdmin.i18n.updatedLabel.replace('%s', data.metadata.last_updated));
                }
                if (parts.length) {
                    message += ' ' + parts.join(' • ');
                }
            }
            renderMessage('success', message);
        });
    });
})(jQuery);
