(function(){
    document.addEventListener('DOMContentLoaded', function(){
        if (typeof gm2Analytics === 'undefined' || !gm2Analytics.ajax_url) {
            return;
        }

        var startTime = Date.now();

        function send(data) {
            var params = new URLSearchParams({
                action: 'gm2_analytics_track',
                url: window.location.href,
                referrer: document.referrer
            });
            if (gm2Analytics.nonce) {
                params.append('nonce', gm2Analytics.nonce);
            }
            for (var key in data) {
                if (Object.prototype.hasOwnProperty.call(data, key) && data[key] !== undefined && data[key] !== null) {
                    params.append(key, data[key]);
                }
            }
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
        }

        function handleVisibility() {
            if (document.visibilityState === 'hidden') {
                var duration = Math.round((Date.now() - startTime) / 1000);
                send({ event_type: 'pageview', duration: duration });
            }
        }

        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('pagehide', handleVisibility);

        document.addEventListener('click', function(e){
            var el = e.target;
            while (el && el.tagName !== 'A') {
                el = el.parentElement;
            }
            if (!el || el.tagName !== 'A') {
                return;
            }
            var identifier = el.getAttribute('href') || el.getAttribute('id') || el.getAttribute('class') || '';
            send({ event_type: 'click', element: identifier });
        }, true);
    });
})();
