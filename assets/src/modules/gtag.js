export default function gtag(id, config = {}) {
  if (!id || window.gtag || document.getElementById('ae-gtag')) {
    return;
  }
  const script = document.createElement('script');
  script.async = true;
  script.id = 'ae-gtag';
  script.src = 'https://www.googletagmanager.com/gtag/js?id=' +
    encodeURIComponent(id);
  document.head.appendChild(script);
  window.dataLayer = window.dataLayer || [];
  function gtagFn() { window.dataLayer.push(arguments); }
  window.gtag = gtagFn;
  gtagFn('js', new Date());
  gtagFn('config', id, config);
}
