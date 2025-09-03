import { on } from './vanilla-helpers.js';

export function initReplacements(map) {
  if (!map || typeof map !== 'object') {
    return;
  }

  function run() {
    Object.keys(map).forEach(function (selector) {
      var callbackName = map[selector];
      if (typeof callbackName !== 'string') {
        return;
      }
      var cb = window[callbackName];
      if (typeof cb !== 'function') {
        return;
      }
      var elements = document.querySelectorAll(selector);
      for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        try {
          cb(el);
        } catch (e) {
          console.error('aeSEO replacement failed', selector, e);
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    on(document, 'DOMContentLoaded', run);
  } else {
    run();
  }
}
