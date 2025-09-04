if (document.documentElement.hasAttribute('data-aejs-off')) {
  // Bail out early if AE scripts are disabled
} else {
  var cfg = window.aeLazy || {};
  var modules = cfg.modules || {};
  var ids = cfg.ids || {};
  var consent = cfg.consent || { key: 'aeConsent', value: 'allow_analytics' };

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
    if (analytics.gtag || modules.gtag) {
      import('./modules/gtag.js').then(function (m) {
        m.default(analytics.gtag || ids.gtag);
      });
    }
    if (analytics.gtm || modules.gtm) {
      import('./modules/gtm.js').then(function (m) {
        m.default(analytics.gtm || ids.gtm);
      });
    }
    if (analytics.fbq || modules.fbq) {
      import('./modules/fbq.js').then(function (m) {
        m.default(analytics.fbq || ids.fbq);
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

  window.addEventListener('ae:engaged', function () {
    loadAnalytics();
    loadRecaptcha();
    loadHCaptcha();
  });

  document.addEventListener('aeConsentChanged', loadAnalytics);

  var fired = false;
  function go() {
    if (fired) {
      return;
    }
    fired = true;
    window.dispatchEvent(new Event('ae:engaged'));
  }

  window.addEventListener('click', go, { once: true });
  window.addEventListener('scroll', go, { once: true });
  window.addEventListener('keydown', go, { once: true });
  window.addEventListener('pointerdown', go, { once: true, passive: true });
  setTimeout(go, 3000);
}
