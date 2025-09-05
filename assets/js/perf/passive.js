/**
 * Passive event listener helpers and patches.
 *
 * @package Gm2
 */

/* global AE_PERF_FLAGS, AE_PERF_DISABLE_PASSIVE */

const defaultOptions = { passive: true, capture: false };
const patchEvents = new Set(['scroll', 'touchstart', 'touchmove', 'wheel']);
let warned = false;

/**
 * Add an event listener defaulting to passive true/capture false.
 *
 * @param {EventTarget} el      Target element.
 * @param {string}      type    Event type.
 * @param {Function|Object} handler  Listener or object with handleEvent.
 * @param {Object}      [options={}] Listener options.
 * @return {void}
 */
export function addPassive(el, type, handler, options = {}) {
    if (window.AE_PERF_DISABLE_PASSIVE) {
        el.addEventListener(type, handler, options);
        return;
    }

    const opts = options && options.passive === false
        ? options
        : { ...defaultOptions, ...options };

    el.addEventListener(type, handler, opts);
}

// Expose helper for other scripts.
window.aePerf = window.aePerf || {};
window.aePerf.addPassive = addPassive;

// Patch addEventListener to default certain events to passive.
if (!window.AE_PERF_DISABLE_PASSIVE && window.AE_PERF_FLAGS?.passivePatch === true) {
    try {
        const desc = Object.getOwnPropertyDescriptor(EventTarget.prototype, 'addEventListener');
        if (desc && desc.configurable) {
            const original = EventTarget.prototype.addEventListener;

            EventTarget.prototype.addEventListener = function patched(type, listener, options) {
                const view = this && this.ownerDocument && this.ownerDocument.defaultView;
                if (view && view !== window) {
                    return original.call(this, type, listener, options);
                }

                if (patchEvents.has(type) && options === undefined) {
                    const wrapped = wrapListener(listener);
                    return original.call(this, type, wrapped, { passive: true });
                }

                return original.call(this, type, listener, options);
            };
        }
    } catch (err) {
        // Swallow patch errors quietly.
    }
}

/**
 * Wrap listener to warn if preventDefault is called on a passive listener.
 *
 * @param {Function|Object} listener Listener to wrap.
 * @return {Function|Object}
 */
function wrapListener(listener) {
    if (
        typeof listener !== 'function' &&
        (!listener || typeof listener.handleEvent !== 'function')
    ) {
        return listener;
    }

    return function wrapped(event) {
        if (event.view && event.view !== window) {
            return callListener(listener, this, event);
        }

        const originalPrevent = event.preventDefault;
        event.preventDefault = function patchedPreventDefault() {
            if (!warned) {
                // eslint-disable-next-line no-console
                console.warn('preventDefault() was called from a passive listener');
                warned = true;
            }
            return originalPrevent.call(this);
        };

        try {
            return callListener(listener, this, event);
        } finally {
            event.preventDefault = originalPrevent;
        }
    };
}

/**
 * Call a listener whether it's a function or an object with handleEvent.
 *
 * @param {Function|Object} listener Listener to invoke.
 * @param {Object} context           Context for the call.
 * @param {Event} event              Event object.
 * @return {*}
 */
function callListener(listener, context, event) {
    if (typeof listener === 'function') {
        return listener.call(context, event);
    }
    return listener.handleEvent.call(listener, event);
}

