if (document.documentElement.hasAttribute('data-aejs-off')) {
  // Bail out early if AE scripts are disabled
} else {
  var cfg = window.aeLazy || {};
  var modules = cfg.modules || {};
  var ids = cfg.ids || {};
  var consent = cfg.consent || { key: 'aeConsent', value: 'allow_analytics' };
  var addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf && window.aePerf.addPassive
    ? window.aePerf.addPassive
    : function (el, type, handler, options) {
        el.addEventListener(type, handler, options);
      };

  function cookie(name) {
    var match = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
    return match ? match[2] : null;
  }

  function hasConsent() {
    return cookie(consent.key) === consent.value;
  }

  var analyticsLoaded = false;
  function loadAnalytics() {
    if (analyticsLoaded || !hasConsent()) {
      return;
    }
    analyticsLoaded = true;
    var analytics = window.aeLoadAnalyticsAllowed || {};
    if (analytics.tagManager || modules.tagManager) {
      import('./modules/tag-manager.js').then(function (m) {
        var cfg = analytics.tagManager || ids.tagManager || {};
        m.default(cfg.id, { gtag: cfg.gtag, fbq: cfg.fbq });
      });
    }
  }

  var recaptchaLoaded = false;
  function loadRecaptcha() {
    if (recaptchaLoaded) {
      return;
    }
    var key = window.aeLoadReCaptcha || (modules.recaptcha && ids.recaptcha);
    if (!key) {
      return;
    }
    recaptchaLoaded = true;
    import('./modules/recaptcha.js').then(function (m) {
      m.default(key);
    });
  }

  var hcaptchaLoaded = false;
  function loadHCaptcha() {
    if (hcaptchaLoaded) {
      return;
    }
    var key = window.aeLoadHCaptcha || (modules.hcaptcha && ids.hcaptcha);
    if (!key) {
      return;
    }
    hcaptchaLoaded = true;
    import('./modules/hcaptcha.js').then(function (m) {
      m.default(key);
    });
  }

  var builtin = { tagManager: 1, recaptcha: 1, hcaptcha: 1 };
  function setupCustomModules() {
    for (var name in modules) {
      if (!modules[name] || builtin[name]) {
        continue;
      }
      (function (moduleName) {
        var loaded = false;
        function load(event) {
          if (loaded) {
            return;
          }
          loaded = true;
          import('./modules/' + moduleName + '.js').then(function (m) {
            if (typeof m.default === 'function') {
              m.default(event && event.currentTarget);
            }
          });
        }
        var els = document.querySelectorAll('[data-ae-module="' + moduleName + '"]');
        for (var i = 0; i < els.length; i++) {
          var el = els[i];
          addPassive(el, 'mouseenter', load, { once: true });
          addPassive(el, 'focus', load, { once: true });
        }
      })(name);
    }
  }

  if (document.readyState === 'loading') {
    addPassive(document, 'DOMContentLoaded', setupCustomModules);
  } else {
    setupCustomModules();
  }

  addPassive(window, 'ae:engaged', function () {
    loadAnalytics();
    loadRecaptcha();
    loadHCaptcha();
  });

  addPassive(document, 'aeConsentChanged', loadAnalytics);

  var fired = false;
  function go() {
    if (fired) {
      return;
    }
    fired = true;
    window.dispatchEvent(new Event('ae:engaged'));
  }

  addPassive(window, 'click', go, { once: true });
  addPassive(window, 'scroll', go, { once: true });
  addPassive(window, 'keydown', go, { once: true });
  addPassive(window, 'pointerdown', go, { once: true });
  setTimeout(go, 3000);
}
