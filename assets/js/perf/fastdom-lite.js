const reads = [];
const writes = [];
let scheduled = false;

const DEBUG = typeof window !== 'undefined' && /[?&]aePerfDebug=1\b/.test(window.location.search);

function schedule() {
    if (!scheduled) {
        scheduled = true;
        requestAnimationFrame(flush);
    }
}

function flush() {
    const r = reads.splice(0, reads.length);
    for (const task of r) {
        try {
            const result = runMeasure(task.fn);
            task.resolve(result);
        } catch (err) {
            task.reject(err);
        }
    }
    const w = writes.splice(0, writes.length);
    for (const task of w) {
        try {
            const result = runMutate(task.fn);
            task.resolve(result);
        } catch (err) {
            task.reject(err);
        }
    }
    scheduled = false;
}

function runMeasure(fn) {
    if (!DEBUG) {
        return fn();
    }
    let mutated = false;
    const observer = new MutationObserver(() => {
        mutated = true;
    });
    observer.observe(document.documentElement, {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true,
    });
    let result;
    try {
        result = fn();
    } finally {
        observer.disconnect();
        if (mutated) {
            // eslint-disable-next-line no-console
            console.warn('aePerf.measure callback mutated the DOM');
        }
    }
    return result;
}

function runMutate(fn) {
    if (!DEBUG) {
        return fn();
    }
    let accessed = false;
    const origGBCR = Element.prototype.getBoundingClientRect;
    Element.prototype.getBoundingClientRect = function(...args) {
        accessed = true;
        return origGBCR.apply(this, args);
    };
    const origGCS = window.getComputedStyle;
    window.getComputedStyle = function(...args) {
        accessed = true;
        return origGCS.apply(this, args);
    };
    const owDesc = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetWidth');
    const ohDesc = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetHeight');
    Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
        get() {
            accessed = true;
            return owDesc.get.call(this);
        },
    });
    Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
        get() {
            accessed = true;
            return ohDesc.get.call(this);
        },
    });
    let result;
    try {
        result = fn();
    } finally {
        Element.prototype.getBoundingClientRect = origGBCR;
        window.getComputedStyle = origGCS;
        Object.defineProperty(HTMLElement.prototype, 'offsetWidth', owDesc);
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', ohDesc);
        if (accessed) {
            // eslint-disable-next-line no-console
            console.warn('aePerf.mutate callback performed a layout read');
        }
    }
    return result;
}

export function measure(fn) {
    return new Promise((resolve, reject) => {
        reads.push({ fn, resolve, reject });
        schedule();
    });
}

export function mutate(fn) {
    return new Promise((resolve, reject) => {
        writes.push({ fn, resolve, reject });
        schedule();
    });
}

