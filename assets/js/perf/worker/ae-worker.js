/**
 * AE Web Worker tasks.
 *
 * Accepts messages of the form { id, name, payload } and posts back
 * { id, result } or { id, error }.
 */

export async function sha1(text) {
    const data = new TextEncoder().encode(text);
    const digest = await crypto.subtle.digest('SHA-1', data);
    const bytes = new Uint8Array(digest);
    return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
}

export function calcHistogram(arr) {
    const hist = new Array(256).fill(0);
    if (arr instanceof Uint8Array) {
        for (let i = 0; i < arr.length; i += 1) {
            hist[arr[i]] += 1;
        }
    }
    return hist;
}

export function compressStats(arr) {
    if (!arr || arr.length === 0) {
        return [];
    }
    const out = [];
    let prev = arr[0];
    let count = 1;
    for (let i = 1; i < arr.length; i += 1) {
        const val = arr[i];
        if (val === prev) {
            count += 1;
        } else {
            out.push([prev, count]);
            prev = val;
            count = 1;
        }
    }
    out.push([prev, count]);
    return out;
}

const tasks = { sha1, calcHistogram, compressStats };

self.onmessage = async (e) => {
    const { id, name, payload } = e.data || {};
    const fn = tasks[name];
    if (!fn) {
        self.postMessage({ id, error: 'Unknown task' });
        return;
    }
    try {
        const result = await fn(payload);
        self.postMessage({ id, result });
    } catch (err) {
        self.postMessage({ id, error: err && err.message ? err.message : String(err) });
    }
};
