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
let warned = false;

async function fallbackRunTask(name, payload) {
    if (!warned) {
        // eslint-disable-next-line no-console
        console.warn('Web Workers are not supported');
        warned = true;
    }
    return new Promise((resolve, reject) => {
        queueMicrotask(async () => {
            try {
                const m = await import('./worker/ae-worker.js');
                const fn = m[name];
                if (!fn) {
                    throw new Error('Unknown task');
                }
                const result = await fn(payload);
                resolve(result);
            } catch (err) {
                reject(err);
            }
        });
    });
}

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
    runTask: fallbackRunTask,
};

if (flags.longTasks === true) {
    imports.push(import('./yield.js').then((m) => { window.aePerf.yield = m; }));
}

if (flags.webWorker && typeof Worker !== 'undefined') {
    imports.push(
        import('./worker/index.js').then((m) => {
            m.init();
            window.aePerf.runTask = m.runTask;
        })
    );
}
if (flags.long_tasks) {
    imports.push(import('./longtask.js').then((m) => m.init()));
}
if (flags.noThrash === true) {
    imports.push(
        import('./fastdom-lite.js').then((m) => {
            window.aePerf.dom = {
                measure: m.measure,
                mutate: m.mutate,
            };
        })
    );
}
if (flags.passive_listeners) {
    imports.push(import('./passive.js'));
}
if (flags.dom_audit) {
    imports.push(import('./dom-audit.js').then((m) => m.init()));
}

Promise.all(imports).catch((err) => {
    // eslint-disable-next-line no-console
    console.error('AE Performance error', err);
});
