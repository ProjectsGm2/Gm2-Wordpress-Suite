/**
 * Batch DOM reads and writes to avoid layout thrash.
 *
 * Exposes aePerf.measure() and aePerf.mutate().
 *
 * @return {void}
 */
export function init() {
    const reads = [];
    const writes = [];
    let scheduled = false;

    function flush() {
        const r = reads.splice(0, reads.length);
        r.forEach((fn) => fn());
        const w = writes.splice(0, writes.length);
        w.forEach((fn) => fn());
        scheduled = false;
    }

    window.aePerf = window.aePerf || {};
    window.aePerf.measure = (fn) => {
        reads.push(fn);
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(flush);
        }
    };
    window.aePerf.mutate = (fn) => {
        writes.push(fn);
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(flush);
        }
    };
}
