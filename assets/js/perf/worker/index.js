/**
 * Worker pool facade.
 */

import { WorkerPool } from './pool.js';
import { sha1, calcHistogram, compressStats } from './ae-worker.js';

let pool;
let warned = false;

export function init(size) {
    if (typeof Worker === 'undefined') {
        if (!warned) {
            // eslint-disable-next-line no-console
            console.warn('Web Workers are not supported');
            warned = true;
        }
        pool = null;
        return;
    }
    if (!pool) {
        pool = new WorkerPool(size);
    }
}

const fallbackTasks = { sha1, calcHistogram, compressStats };

export function runTask(name, payload) {
    if (pool) {
        return pool.runTask(name, payload);
    }
    if (typeof Worker !== 'undefined') {
        init();
        if (pool) {
            return pool.runTask(name, payload);
        }
    }
    if (!warned) {
        // eslint-disable-next-line no-console
        console.warn('Web Workers are not supported');
        warned = true;
    }
    return new Promise((resolve, reject) => {
        queueMicrotask(async () => {
            try {
                const fn = fallbackTasks[name];
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
