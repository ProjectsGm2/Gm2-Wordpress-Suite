(function () {
    const KEY = 'gm2AcTabCount';
    const ENTRY_KEY = 'gm2_entry_url';
    const CLIENT_KEY = 'gm2_ac_client_id';
    const ajaxUrl = gm2AcActivity.ajax_url;
    const nonce = gm2AcActivity.nonce;
    const rawInactivity = gm2AcActivity.inactivity_ms;
    const parsedInactivity = rawInactivity === null ? 0 : parseInt(rawInactivity, 10);
    const inactivityMs = Number.isNaN(parsedInactivity) ? 5 * 60 * 1000 : parsedInactivity;
    const url = window.location.href;
    let pendingTargetUrl;

    function getClientId() {
        let id;
        try {
            id = localStorage.getItem(CLIENT_KEY);
            if (!id && typeof crypto !== 'undefined' && crypto.randomUUID) {
                id = crypto.randomUUID();
                localStorage.setItem(CLIENT_KEY, id);
            }
        } catch (e) {
            if (!id && typeof crypto !== 'undefined' && crypto.randomUUID) {
                id = crypto.randomUUID();
            }
        }
        if (!id) {
            id = String(Math.random());
        }
        document.cookie = CLIENT_KEY + '=' + id + '; path=/';
        return id;
    }

    const clientId = getClientId();

    function send(action, targetUrl) {
        const data = new URLSearchParams({ action, nonce, url: targetUrl || window.location.href, client_id: clientId });

        if (action === 'gm2_ac_mark_abandoned') {
            let beaconSent = false;
            if (navigator.sendBeacon) {
                try {
                    beaconSent = navigator.sendBeacon(ajaxUrl, data);
                    if (!beaconSent) {
                        const payload = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' });
                        beaconSent = navigator.sendBeacon(ajaxUrl, payload);
                    }
                } catch (err) {
                    console.debug('sendBeacon URLSearchParams failed', err);
                    try {
                        const payload = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' });
                        beaconSent = navigator.sendBeacon(ajaxUrl, payload);
                    } catch (error) {
                        console.error('sendBeacon failed', error);
                    }
                }
            }

            if (!beaconSent) {
                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data,
                    keepalive: true,
                })
                    .then((response) => {
                        if (!response.ok) {
                            console.error('gm2_ac_mark_abandoned request failed', response.status, response.statusText);
                        }
                    })
                    .catch((error) => {
                        console.error('gm2_ac_mark_abandoned request error', error);
                    });
            }
        } else {
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            })
                .then((response) => {
                    if (!response.ok) {
                        console.error(`${action} request failed`, response.status, response.statusText);
                    }
                })
                .catch((error) => {
                    console.error(`${action} request error`, error);
                });
        }
    }

    function getEntryUrl() {
        try {
            return localStorage.getItem(ENTRY_KEY);
        } catch (e) {
            const match = document.cookie.match(new RegExp('(?:^|; )' + ENTRY_KEY + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : null;
        }
    }

    function setEntryUrl(value) {
        try {
            localStorage.setItem(ENTRY_KEY, value);
        } catch (e) {
            // localStorage might be unavailable; ignore errors.
        }
        document.cookie = `${ENTRY_KEY}=${encodeURIComponent(value)}; path=/`;
    }

    function clearEntryUrl() {
        try {
            localStorage.removeItem(ENTRY_KEY);
        } catch (e) {
            // localStorage might be unavailable; ignore errors.
        }
        document.cookie = `${ENTRY_KEY}=; path=/; expires=${new Date(0).toUTCString()}`;
    }

    let memoryTabCount = 0;

    function incrementTabs() {
        let count;
        try {
            count = Math.max(parseInt(localStorage.getItem(KEY) || '0', 10), 0) + 1;
            localStorage.setItem(KEY, String(count));
        } catch (e) {
            try {
                const match = document.cookie.match(new RegExp('(?:^|; )' + KEY + '=([^;]*)'));
                count = Math.max(parseInt(match ? decodeURIComponent(match[1]) : '0', 10), 0) + 1;
                document.cookie = `${KEY}=${count}; path=/`;
            } catch (err) {
                count = ++memoryTabCount;
            }
        }
    }

    function decrementTabs() {
        let count;
        try {
            count = Math.max(parseInt(localStorage.getItem(KEY) || '0', 10) - 1, 0);
            localStorage.setItem(KEY, String(count));
        } catch (e) {
            try {
                const match = document.cookie.match(new RegExp('(?:^|; )' + KEY + '=([^;]*)'));
                count = Math.max(parseInt(match ? decodeURIComponent(match[1]) : '0', 10) - 1, 0);
                document.cookie = `${KEY}=${count}; path=/`;
            } catch (err) {
                count = Math.max(memoryTabCount - 1, 0);
                memoryTabCount = count;
            }
        }

        return typeof count === 'undefined' || count === 0;
    }

    incrementTabs();
    if (!getEntryUrl()) {
        setEntryUrl(url);
    }
    send('gm2_ac_mark_active');

    let inactivityTimer;
    function resetInactivityTimer(shouldSend = true) {
        if (inactivityMs <= 0) {
            return;
        }
        clearTimeout(inactivityTimer);
        if (shouldSend) {
            send('gm2_ac_mark_active');
        }
        inactivityTimer = setTimeout(() => {
            send('gm2_ac_mark_abandoned');
        }, inactivityMs);
        if (inactivityTimer && typeof inactivityTimer.unref === 'function') {
            inactivityTimer.unref();
        }
    }
    if (inactivityMs > 0) {
        resetInactivityTimer();
    }

    document.body.addEventListener('added_to_cart', () => {
        resetInactivityTimer();
    });

    document.addEventListener('click', (e) => {
        resetInactivityTimer();
        const anchor = e.target.closest('a');
        if (!anchor) {
            return;
        }
        const href = anchor.href;
        if (href && anchor.origin !== window.location.origin) {
            pendingTargetUrl = href;
        }
    });

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach((ev) => {
        document.addEventListener(ev, resetInactivityTimer, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            send('gm2_ac_mark_active');
            resetInactivityTimer(false);
        }
    });
    window.addEventListener('beforeunload', () => {
        if (decrementTabs()) {
            send('gm2_ac_mark_abandoned', pendingTargetUrl);
            clearEntryUrl();
        }
    });
    window.addEventListener('pagehide', () => {
        if (decrementTabs()) {
            send('gm2_ac_mark_abandoned', pendingTargetUrl);
            clearEntryUrl();
        }
    }, { once: true });
})();
