export default function recaptcha(siteKey) {
  if (!siteKey || document.getElementById('ae-recaptcha')) {
    return;
  }
  const script = document.createElement('script');
  script.id = 'ae-recaptcha';
  script.src = 'https://www.google.com/recaptcha/api.js?render=' +
    encodeURIComponent(siteKey);
  document.head.appendChild(script);
}
