export function on(el, evt, fn) {
  el.addEventListener(evt, fn);
}

export function closest(el, selector) {
  return el.closest(selector);
}

export function ajax(url, options = {}) {
  return fetch(url, options);
}

export function slideToggle(el) {
  el.classList.toggle('is-open');
}
