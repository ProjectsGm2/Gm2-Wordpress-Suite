const reads = [];
const writes = [];
let scheduled = false;

const DEBUG = typeof window !== 'undefined' && /[?&]aePerfDebug=1\b/.test(window.location.search);

// Common layout read APIs that can trigger forced synchronous layouts when
// accessed. This list is not exhaustive but covers the most common cases we
// want to guard against when batching DOM reads and writes.
const LAYOUT_READ_PROPS = new Set([
    'offsetWidth',
    'offsetHeight',
    'offsetTop',
    'offsetLeft',
    'scrollTop',
    'scrollLeft',
    'scrollWidth',
    'scrollHeight',
    'clientTop',
    'clientLeft',
    'clientWidth',
    'clientHeight',
    'getBoundingClientRect',
    'getClientRects',
    'getComputedStyle',
]);

/**
 * Determine if accessing the given property is considered a layout read.
 *
 * @param {string} prop Property or method name being accessed.
 * @return {boolean} Whether the access is a layout read.
 */
function isLayoutRead(prop) {
    return LAYOUT_READ_PROPS.has(prop);
}

// Layout write operations. For simplicity we detect direct style mutations and
// common DOM APIs that mutate layout such as classList operations.
const CLASSLIST_METHODS = new Set(['add', 'remove', 'toggle', 'replace']);
const LAYOUT_WRITE_PROPS = new Set([
    'innerHTML',
    'outerHTML',
    'innerText',
    'textContent',
    'scrollTop',
    'scrollLeft',
    'appendChild',
    'insertBefore',
    'removeChild',
    'replaceChild',
    'insertAdjacentHTML',
    'insertAdjacentElement',
]);

/**
 * Determine if writing to the given property is considered a layout write.
 *
 * @param {string} prop Property or method name being set or invoked.
 * @return {boolean} Whether the access is a layout mutation.
 */
function isLayoutWrite(prop) {
    if (prop.startsWith('style.')) {
        return true;
    }
    if (prop.startsWith('classList.')) {
        const method = prop.split('.')[1];
        return CLASSLIST_METHODS.has(method);
    }
    return LAYOUT_WRITE_PROPS.has(prop);
}

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
            console.warn('aePerf.dom.measure callback mutated the DOM');
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
            console.warn('aePerf.dom.mutate callback performed a layout read');
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

export { isLayoutRead, isLayoutWrite };

