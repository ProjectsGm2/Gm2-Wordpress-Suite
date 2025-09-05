(function(){
    const dom = window.aePerf?.dom;
    const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
    const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();
    const addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf?.addPassive
        ? window.aePerf.addPassive
        : (el, type, handler, options) => el.addEventListener(type, handler, options);

    mutate(() => {
        addPassive(document, 'DOMContentLoaded', function(){
            if (typeof gm2Analytics === 'undefined' || !gm2Analytics.ajax_url) {
                return;
            }

            var startTime = Date.now();
            var referrer;
            measure(() => {
                referrer = document.referrer;
            });

            function send(data) {
                var params = new URLSearchParams({
                    action: 'gm2_analytics_track',
                    url: window.location.href,
                    referrer: referrer
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
                var state;
                measure(() => {
                    state = document.visibilityState;
                });
                if (state === 'hidden') {
                    var duration = Math.round((Date.now() - startTime) / 1000);
                    send({ event_type: 'duration', duration: duration });
                }
            }

            mutate(() => {
                addPassive(document, 'visibilitychange', handleVisibility);
                addPassive(window, 'pagehide', handleVisibility);
            });

            mutate(() => {
                addPassive(document, 'click', function(e){
                    var el;
                    measure(() => {
                        el = e.target;
                        while (el && el.tagName !== 'A') {
                            el = el.parentElement;
                        }
                    });
                    if (!el || el.tagName !== 'A') {
                        return;
                    }
                    var identifier;
                    measure(() => {
                        identifier = el.getAttribute('href') || el.getAttribute('id') || el.getAttribute('class') || '';
                    });
                    send({ event_type: 'click', element: identifier });
                }, { capture: true });
            });
        });
    });
})();
