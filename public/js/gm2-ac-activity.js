(function () {
    const KEY = 'gm2AcTabCount';
    const ajaxUrl = gm2AcActivity.ajax_url;
    const nonce = gm2AcActivity.nonce;
    const url = window.location.href;

    function send(action) {
        const data = new URLSearchParams({ action, nonce, url });

        if (action === 'gm2_ac_mark_abandoned') {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(ajaxUrl, data);
            }

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
                keepalive: true,
            });
        } else {
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            });
        }
    }

    function incrementTabs() {
        const count = parseInt(localStorage.getItem(KEY) || '0', 10) + 1;
        localStorage.setItem(KEY, String(count));
    }

    function decrementTabs() {
        let count = parseInt(localStorage.getItem(KEY) || '0', 10) - 1;
        if (count < 0) {
            count = 0;
        }
        localStorage.setItem(KEY, String(count));
        if (count === 0) {
            send('gm2_ac_mark_abandoned');
        }
    }

    incrementTabs();
    send('gm2_ac_mark_active');

    window.addEventListener('beforeunload', decrementTabs);
    window.addEventListener('pagehide', decrementTabs);
    window.addEventListener('unload', decrementTabs);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            decrementTabs();
        }
    });
})();
