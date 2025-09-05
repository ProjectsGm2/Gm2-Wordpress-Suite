/**
 * Default scroll and touch listeners to passive.
 *
 * @return {void}
 */
export function init() {
    const original = EventTarget.prototype.addEventListener;
    EventTarget.prototype.addEventListener = function (type, listener, options) {
        if (type === 'scroll' || type === 'touchstart' || type === 'touchmove') {
            if (typeof options === 'object') {
                options = { passive: true, ...options };
            } else if (options === undefined) {
                options = { passive: true };
            }
        }
        return original.call(this, type, listener, options);
    };
}
