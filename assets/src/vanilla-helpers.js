const addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf && window.aePerf.addPassive
  ? window.aePerf.addPassive
  : function (el, type, handler, options) {
      el.addEventListener(type, handler, options);
    };

export function on(el, evt, fn, options) {
  addPassive(el, evt, fn, options);
}

export function closest(el, selector) {
  return el.closest(selector);
}

export function ajax(url, options) {
  if (options === undefined) {
    options = {};
  }
  return fetch(url, options);
}

export function slideToggle(el) {
  el.classList.toggle('is-open');
}
