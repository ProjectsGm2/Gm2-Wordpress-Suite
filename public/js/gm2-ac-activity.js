(function () {
    const KEY = 'gm2AcTabCount';
    const ENTRY_KEY = 'gm2_entry_url';
    const ajaxUrl = gm2AcActivity.ajax_url;
    const nonce = gm2AcActivity.nonce;
    const url = window.location.href;

    function send(action, targetUrl) {
        const data = new URLSearchParams({ action, nonce, url: targetUrl || window.location.href });

        if (action === 'gm2_ac_mark_abandoned') {
            if (navigator.sendBeacon) {
                try {
                    const sent = navigator.sendBeacon(ajaxUrl, data);

                    if (!sent) {
                        const payload = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' });
                        navigator.sendBeacon(ajaxUrl, payload);
                    }
                } catch (err) {
                    console.debug('sendBeacon URLSearchParams failed', err);

                    try {
                        const payload = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' });
                        navigator.sendBeacon(ajaxUrl, payload);
                    } catch (error) {
                        console.error('sendBeacon failed', error);
                    }
                }
            }

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

        if (typeof count === 'undefined' || count === 0) {
            send('gm2_ac_mark_abandoned');
        }
    }

    incrementTabs();
    if (!getEntryUrl()) {
        setEntryUrl(url);
    }
    send('gm2_ac_mark_active');

    document.body.addEventListener('added_to_cart', () => {
        send('gm2_ac_mark_active');
    });

    document.addEventListener('click', (e) => {
        const anchor = e.target.closest('a');
        if (!anchor) {
            return;
        }
        const href = anchor.href;
        if (href && anchor.origin !== window.location.origin) {
            send('gm2_ac_mark_abandoned', href);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            decrementTabs();
        }
    });

    window.addEventListener('beforeunload', decrementTabs);
    window.addEventListener('pagehide', decrementTabs, { once: true });
})();
