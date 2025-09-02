function needPolyfills() {
  return !('IntersectionObserver' in window) ||
    !('fetch' in window) ||
    !('Promise' in window) ||
    !('classList' in document.createElement('div'));
}

function loadPolyfills() {
  var script = document.createElement('script');
  script.src = 'polyfills.js';
  document.head.appendChild(script);
}

if (needPolyfills()) {
  loadPolyfills();
}

export { needPolyfills };
