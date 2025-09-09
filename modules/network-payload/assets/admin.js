document.addEventListener('DOMContentLoaded', function () {
    if (typeof gm2Netpayload === 'undefined' || !window.performance || !window.fetch) {
        return;
    }
    var entries = performance.getEntriesByType('resource') || [];
    var total = 0;
    for (var i = 0; i < entries.length; i++) {
        if (entries[i].transferSize) {
            total += entries[i].transferSize;
        }
    }
    try {
        fetch(gm2Netpayload.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': gm2Netpayload.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ payload: Math.round(total / 1024) })
        });
    } catch (e) {}
});
