/**
 * AE Performance bootstrap.
 *
 * Conditionally loads feature modules based on window.AE_PERF_FLAGS.
 *
 * @package Gm2
 */

/* global AE_PERF_FLAGS */
const flags = window.AE_PERF_FLAGS || {};
const imports = [];

if (flags.worker) {
    imports.push(import('./worker.js').then((m) => m.init()));
}
if (flags.long_tasks) {
    imports.push(import('./longtask.js').then((m) => m.init()));
}
if (flags.layout_thrash) {
    imports.push(import('./layout.js').then((m) => m.init()));
}
if (flags.passive_listeners) {
    imports.push(import('./passive.js').then((m) => m.init()));
}
if (flags.dom_audit) {
    imports.push(import('./dom-audit.js').then((m) => m.init()));
}

Promise.all(imports).catch((err) => {
    // eslint-disable-next-line no-console
    console.error('AE Performance error', err);
});

window.aePerf = {
    /**
     * Output current flag state.
     *
     * @return {void}
     */
    debug() {
        // eslint-disable-next-line no-console
        console.log(flags);
    },
};
