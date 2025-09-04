export default function recaptcha(config) {
  var siteKey = '';
  var checkbox = false;
  if (typeof config === 'object') {
    siteKey = config.siteKey || '';
    checkbox = !!config.checkbox;
  } else {
    siteKey = config;
  }
  if (!siteKey || document.getElementById('ae-recaptcha')) {
    return;
  }
  var params = 'render=' + encodeURIComponent(siteKey);
  if (checkbox) {
    params += '&recaptcha=checkbox';
  }
  const script = document.createElement('script');
  script.id = 'ae-recaptcha';
  script.src = 'https://www.google.com/recaptcha/api.js?' + params;
  document.head.appendChild(script);
}
