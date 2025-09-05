(function () {
    const dom = window.aePerf?.dom;
    const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
    const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

    const cfg = window.gm2GmcRealtime || {};
    if (!cfg.url) {
        return;
    }
    async function poll() {
        try {
            const resp = await fetch(cfg.url, {
                headers: { 'X-WP-Nonce': cfg.nonce }
            });
            const data = await resp.json();
            mutate(() => {
                document.dispatchEvent(
                    new CustomEvent('gm2GmcRealtimeUpdate', { detail: data })
                );
            });
        } catch (err) {
            // eslint-disable-next-line no-console
            console.error('GMC realtime fetch failed', err);
        }
    }
    poll();
    setInterval(poll, 5000);
})();
