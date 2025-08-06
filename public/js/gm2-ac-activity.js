(function () {
    const KEY = 'gm2AcTabCount';
    const ajaxUrl = gm2AcActivity.ajax_url;
    const nonce = gm2AcActivity.nonce;
    const url = window.location.href;

    function send(action) {
        const data = new URLSearchParams({ action, nonce, url });

        if (action === 'gm2_ac_mark_abandoned') {
            if (navigator.sendBeacon) {
                const payload = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' });
                navigator.sendBeacon(ajaxUrl, payload);
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

    function incrementTabs() {
        const count = Math.max(parseInt(localStorage.getItem(KEY) || '0', 10), 0) + 1;
        localStorage.setItem(KEY, String(count));
    }

    function decrementTabs() {
        const count = Math.max(parseInt(localStorage.getItem(KEY) || '0', 10) - 1, 0);
        localStorage.setItem(KEY, String(count));
        if (count === 0) {
            send('gm2_ac_mark_abandoned');
        }
    }

    incrementTabs();
    send('gm2_ac_mark_active');

    window.addEventListener('pagehide', decrementTabs, { once: true });
})();
