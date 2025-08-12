(function(){
    document.addEventListener('DOMContentLoaded', function(){
        if (typeof gm2Analytics === 'undefined' || !gm2Analytics.ajax_url) {
            return;
        }
        var params = new URLSearchParams({
            action: 'gm2_analytics_track',
            url: window.location.href,
            referrer: document.referrer
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(gm2Analytics.ajax_url, params);
        } else {
            fetch(gm2Analytics.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            });
        }
    });
})();
