/**
 * Report long tasks via PerformanceObserver.
 *
 * Buckets entries and logs per-second summaries.
 *
 * @return {void}
 */
import { chunk } from './yield.js';

/* global AE_PERF_FLAGS */

const summary = { events: 0, max: 0, total: 0, buckets: {} };
const recent = [];
const budgetWindow = [];
let budgetLimit;
let budgetExceeded = false;

function getKey(a = {}) {
    let key = a.name || 'unknown';
    if (a.containerType) {
        key += `:${a.containerType}`;
        if (a.containerId) {
            key += `#${a.containerId}`;
        } else if (a.containerSrc) {
            key += `@${a.containerSrc}`;
        }
    }
    return key;
}

function getInfo(a = {}) {
    const info = {};
    if (a.name) {
        info.name = a.name;
    }
    if (a.containerType) {
        info.containerType = a.containerType;
        if (a.containerId) {
            info.containerId = a.containerId;
        } else if (a.containerSrc) {
            info.containerSrc = a.containerSrc;
        }
    }
    return info;
}

function prune(queue, now, limitMs) {
    while (queue.length && now - queue[0].time > limitMs) {
        queue.shift();
    }
}

function handleEntry(entry) {
    const now = performance.now();
    recent.push({ time: now, duration: entry.duration });
    prune(recent, now, 1000);

    const attrib = entry.attribution && entry.attribution[0];
    const key = getKey(attrib);
    const bucket = (summary.buckets[key] = summary.buckets[key] || {
        events: 0,
        max: 0,
        total: 0,
        attribution: getInfo(attrib),
    });
    bucket.events++;
    bucket.total += entry.duration;
    if (entry.duration > bucket.max) {
        bucket.max = entry.duration;
    }

    summary.events++;
    summary.total += entry.duration;
    if (entry.duration > summary.max) {
        summary.max = entry.duration;
    }

    if (budgetLimit) {
        budgetWindow.push({ time: now, duration: entry.duration });
        prune(budgetWindow, now, 10000);
        const total = budgetWindow.reduce((t, e) => t + e.duration, 0);
        if (total > budgetLimit && !budgetExceeded) {
            // eslint-disable-next-line no-console
            console.warn('Long task budget exceeded', { total, budget: budgetLimit });
            budgetExceeded = true;
        } else if (total <= budgetLimit) {
            budgetExceeded = false;
        }
    }
}

function logRecent() {
    const now = performance.now();
    prune(recent, now, 1000);
    if (!recent.length) {
        return;
    }
    let total = 0;
    let max = 0;
    for (const e of recent) {
        total += e.duration;
        if (e.duration > max) {
            max = e.duration;
        }
    }
    // eslint-disable-next-line no-console
    console.log('Long tasks (last 1s)', {
        events: recent.length,
        max,
        total,
    });
}

/**
 * Start observing long tasks.
 *
 * @return {void}
 */
export function init() {
    const flags = window.AE_PERF_FLAGS || {};
    if (flags.longTasks !== true || typeof PerformanceObserver === 'undefined') {
        return;
    }
    budgetLimit = flags.longTaskBudgetMs;
    try {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            chunk(entries, 50, handleEntry);
        });
        observer.observe({ type: 'longtask', buffered: true });
        setInterval(logRecent, 1000);
    } catch (e) {
        // eslint-disable-next-line no-console
        console.debug('Longtask observer unsupported', e);
    }
}

/**
 * Retrieve lifetime long task totals.
 *
 * @return {Object}
 */
export function getLongTaskSummary() {
    return summary;
}

export const getSummary = getLongTaskSummary;
