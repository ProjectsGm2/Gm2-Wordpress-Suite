export default function fbq(id) {
  if (!id || window.fbq) {
    return;
  }
  const n = function() {
    n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
  };
  window.fbq = n;
  if (!window._fbq) {
    window._fbq = n;
  }
  n.push = n;
  n.loaded = true;
  n.version = '2.0';
  n.queue = [];

  const script = document.createElement('script');
  script.async = true;
  script.id = 'ae-fbq';
  script.src = 'https://connect.facebook.net/en_US/fbevents.js';
  document.head.appendChild(script);

  n('init', id);
  n('track', 'PageView');
}
