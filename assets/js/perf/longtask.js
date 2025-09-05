/**
 * Report long tasks via PerformanceObserver.
 *
 * @return {void}
 */
export function init() {
    if (typeof PerformanceObserver === 'undefined') {
        return;
    }
    try {
        const observer = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                // eslint-disable-next-line no-console
                console.log('Long task:', entry);
            }
        });
        observer.observe({ type: 'longtask', buffered: true });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.debug('Longtask observer unsupported', e);
    }
}
