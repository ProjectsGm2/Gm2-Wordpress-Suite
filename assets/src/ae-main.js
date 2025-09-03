function needPolyfills() {
  return !('IntersectionObserver' in window) ||
    !('fetch' in window) ||
    !('Promise' in window) ||
    !('classList' in document.createElement('div'));
}

function loadPolyfills() {
  return new Promise(function (resolve, reject) {
    var script = document.createElement('script');
    script.src = 'polyfills.js';
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

import { initReplacements } from './replacements.js';

function main() {
  initReplacements(window.aeSEO && window.aeSEO.replacements);
}

if (needPolyfills()) {
  loadPolyfills().then(main);
} else {
  main();
}

export { needPolyfills };
