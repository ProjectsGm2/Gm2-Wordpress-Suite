export default function hcaptcha(siteKey) {
  if (!siteKey || document.getElementById('ae-hcaptcha')) {
    return;
  }
  const script = document.createElement('script');
  script.id = 'ae-hcaptcha';
  script.src = 'https://hcaptcha.com/1/api.js?render=' + encodeURIComponent(siteKey);
  document.head.appendChild(script);
}
