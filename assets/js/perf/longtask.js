/**
 * Report long tasks via PerformanceObserver.
 *
 * @return {void}
 */
import { chunk } from './yield.js';
export function init() {
    if (typeof PerformanceObserver === 'undefined') {
        return;
    }
    try {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            chunk(entries, 50, (entry) => {
                // eslint-disable-next-line no-console
                console.log('Long task:', entry);
            });
        });
        observer.observe({ type: 'longtask', buffered: true });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.debug('Longtask observer unsupported', e);
    }
}
