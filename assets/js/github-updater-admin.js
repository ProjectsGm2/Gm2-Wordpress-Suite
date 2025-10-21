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
    var updateButton = $('#gm2-github-update');
    var oauthConfig = gm2GitHubUpdaterAdmin.oauth || {};
    var loginButton = $('#gm2-github-login-button');
    var disconnectButton = $('#gm2-github-disconnect-button');
    var loginInstructions = $('#gm2-github-login-instructions');
    var loginCode = $('#gm2-github-login-code');
    var loginUrl = $('#gm2-github-login-url');
    var accountStatus = $('#gm2-github-account-status');
    var oauthState = {
        timer: null,
        interval: 5,
        expiresAt: 0
    };

    function clearOAuthTimer() {
        if (oauthState.timer) {
            window.clearTimeout(oauthState.timer);
            oauthState.timer = null;
        }
    }

    function isAccountConnected() {
        if (!accountStatus.length) {
            return false;
        }
        return accountStatus.attr('data-connected') === '1';
    }

    function setOauthBusy(isBusy) {
        if (loginButton.length) {
            loginButton.prop('disabled', !!isBusy || !oauthConfig.enabled);
        }
        if (disconnectButton.length) {
            disconnectButton.prop('disabled', !!isBusy ? true : !isAccountConnected());
        }
    }

    function updateAccountStatus(login) {
        if (!accountStatus.length) {
            return;
        }

        var hasLogin = typeof login === 'string' && login !== '';
        accountStatus.attr('data-connected', hasLogin ? '1' : '0');

        if (hasLogin) {
            accountStatus.html($('<span/>', {
                'class': 'gm2-github-account-connected',
                'text': gm2GitHubUpdaterAdmin.i18n.oauthConnectedAs.replace('%s', login)
            }));
        } else {
            accountStatus.html($('<span/>', {
                'class': 'gm2-github-account-disconnected',
                'text': gm2GitHubUpdaterAdmin.i18n.oauthNotConnected
            }));
        }

        if (disconnectButton.length) {
            disconnectButton.prop('disabled', !hasLogin);
        }
    }

    function showOAuthInstructions(url, code) {
        if (!loginInstructions.length) {
            return;
        }

        var targetUrl = typeof url === 'string' && url !== '' ? url : '#';
        loginUrl.attr('href', targetUrl);
        loginUrl.text(gm2GitHubUpdaterAdmin.i18n.oauthOpenLink);
        loginCode.text(typeof code === 'string' ? code : '');
        loginInstructions.show().attr('aria-hidden', 'false');
    }

    function hideOAuthInstructions() {
        if (!loginInstructions.length) {
            return;
        }

        loginInstructions.hide().attr('aria-hidden', 'true');
        loginUrl.attr('href', '#');
        loginCode.text('');
    }

    function scheduleOAuthPoll(seconds) {
        clearOAuthTimer();

        if (typeof seconds === 'number' && !isNaN(seconds) && seconds > 0) {
            oauthState.interval = seconds;
        }

        if (oauthState.expiresAt && Date.now() >= oauthState.expiresAt) {
            handleOAuthError(gm2GitHubUpdaterAdmin.i18n.oauthExpired);
            return;
        }

        oauthState.timer = window.setTimeout(pollOAuth, oauthState.interval * 1000);
    }

    function handleOAuthError(message) {
        clearOAuthTimer();
        setOauthBusy(false);
        hideOAuthInstructions();
        renderMessage('error', typeof message === 'string' && message ? message : gm2GitHubUpdaterAdmin.i18n.unknownError);
    }

    function handleOAuthSuccess(data) {
        clearOAuthTimer();
        setOauthBusy(false);
        hideOAuthInstructions();

        var message = gm2GitHubUpdaterAdmin.i18n.oauthSuccess;
        var login = '';

        if (data && data.user && typeof data.user.login === 'string' && data.user.login !== '') {
            login = data.user.login;
            message += ' ' + gm2GitHubUpdaterAdmin.i18n.oauthConnectedAs.replace('%s', login);
        }

        updateAccountStatus(login);
        oauthConfig.connectedUser = login;

        toggleTokenField(false);
        renderMessage('success', message);
    }

    function startOAuth() {
        if (!oauthConfig.startAction) {
            renderMessage('error', gm2GitHubUpdaterAdmin.i18n.unknownError);
            return;
        }

        clearOAuthTimer();
        setOauthBusy(true);
        renderMessage('info', gm2GitHubUpdaterAdmin.i18n.oauthPrompt);

        $.ajax({
            method: 'POST',
            url: gm2GitHubUpdaterAdmin.ajaxUrl,
            dataType: 'json',
            data: {
                action: oauthConfig.startAction,
                nonce: gm2GitHubUpdaterAdmin.nonce
            }
        }).done(function (response) {
            if (response && response.success && response.data) {
                var data = response.data;
                var verificationUrl = data.verification_uri_complete || data.verification_uri || '';
                oauthState.interval = typeof data.interval === 'number' ? data.interval : parseInt(data.interval, 10) || 5;
                oauthState.expiresAt = Date.now() + ((typeof data.expires_in === 'number' ? data.expires_in : parseInt(data.expires_in, 10) || 600) * 1000);
                showOAuthInstructions(verificationUrl, data.user_code || '');
                scheduleOAuthPoll(oauthState.interval);
            } else if (response && response.data && response.data.message) {
                handleOAuthError(response.data.message);
            } else {
                handleOAuthError(gm2GitHubUpdaterAdmin.i18n.unknownError);
            }
        }).fail(function (jqXHR) {
            var message = gm2GitHubUpdaterAdmin.i18n.unknownError;
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                message = jqXHR.responseJSON.data.message;
            }
            handleOAuthError(message);
        });
    }

    function pollOAuth() {
        if (!oauthConfig.pollAction) {
            handleOAuthError(gm2GitHubUpdaterAdmin.i18n.unknownError);
            return;
        }

        $.ajax({
            method: 'POST',
            url: gm2GitHubUpdaterAdmin.ajaxUrl,
            dataType: 'json',
            data: {
                action: oauthConfig.pollAction,
                nonce: gm2GitHubUpdaterAdmin.nonce
            }
        }).done(function (response) {
            if (response && response.success && response.data) {
                var data = response.data;
                if (data.status === 'pending') {
                    renderMessage('info', gm2GitHubUpdaterAdmin.i18n.oauthPending);
                    scheduleOAuthPoll(typeof data.interval === 'number' ? data.interval : parseInt(data.interval, 10) || oauthState.interval);
                    return;
                }
                if (data.status === 'slow_down') {
                    renderMessage('info', gm2GitHubUpdaterAdmin.i18n.oauthSlowDown);
                    scheduleOAuthPoll(typeof data.interval === 'number' ? data.interval : parseInt(data.interval, 10) || oauthState.interval + 5);
                    return;
                }
                if (data.status === 'success') {
                    handleOAuthSuccess(data);
                    return;
                }
            }

            if (response && response.data && response.data.message) {
                handleOAuthError(response.data.message);
            } else {
                handleOAuthError(gm2GitHubUpdaterAdmin.i18n.unknownError);
            }
        }).fail(function (jqXHR) {
            var message = gm2GitHubUpdaterAdmin.i18n.unknownError;
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                message = jqXHR.responseJSON.data.message;
            }
            handleOAuthError(message);
        });
    }

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

    updateAccountStatus(oauthConfig.connectedUser || '');
    hideOAuthInstructions();
    setOauthBusy(false);

    if (loginButton.length) {
        loginButton.on('click', function (event) {
            event.preventDefault();
            if (!oauthConfig.enabled) {
                renderMessage('error', gm2GitHubUpdaterAdmin.i18n.oauthMissingClientId);
                return;
            }
            startOAuth();
        });
    }

    if (disconnectButton.length) {
        disconnectButton.on('click', function (event) {
            event.preventDefault();

            if (!isAccountConnected()) {
                return;
            }

            if (!window.confirm(gm2GitHubUpdaterAdmin.i18n.oauthDisconnectConfirm)) {
                return;
            }

            setOauthBusy(true);

            if (!oauthConfig.disconnectAction) {
                setOauthBusy(false);
                renderMessage('error', gm2GitHubUpdaterAdmin.i18n.unknownError);
                return;
            }

            $.ajax({
                method: 'POST',
                url: gm2GitHubUpdaterAdmin.ajaxUrl,
                dataType: 'json',
                data: {
                    action: oauthConfig.disconnectAction,
                    nonce: gm2GitHubUpdaterAdmin.nonce
                }
            }).done(function (response) {
                if (response && response.success) {
                    renderMessage('success', gm2GitHubUpdaterAdmin.i18n.oauthDisconnected);
                    oauthConfig.connectedUser = '';
                    updateAccountStatus('');
                    hideOAuthInstructions();
                    tokenFieldWrapper.hide().attr('aria-hidden', 'true');
                    tokenInput.prop('disabled', true).val('');
                    tokenKeep.val('0');
                    tokenToggle.text(gm2GitHubUpdaterAdmin.i18n.addToken || gm2GitHubUpdaterAdmin.i18n.showToken).attr('aria-expanded', 'false');
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
                setOauthBusy(false);
            });
        });
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
        if (updateButton.length) {
            updateButton.prop('disabled', disabled);
        }
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

    if (updateButton.length) {
        updateButton.on('click', function (event) {
            event.preventDefault();
            handleAjax(updateButton, updateButton.data('action'), gm2GitHubUpdaterAdmin.i18n.updating, function (data) {
                var message = gm2GitHubUpdaterAdmin.i18n.updateSuccess;
                if (data.update && data.update.version) {
                    message += ' ' + gm2GitHubUpdaterAdmin.i18n.versionLabel.replace('%s', data.update.version);
                }
                renderMessage('success', message);
            });
        });
    }
})(jQuery);
