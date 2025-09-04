export default function tagManager(id, tags = {}) {
  if (!id || document.getElementById('ae-gtm')) {
    return;
  }
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
  if (tags && Object.keys(tags).length) {
    window.dataLayer.push({ event: 'ae.tags', tags: tags });
  }
  const script = document.createElement('script');
  script.async = true;
  script.id = 'ae-gtm';
  script.src = 'https://www.googletagmanager.com/gtm.js?id=' +
    encodeURIComponent(id);
  document.head.appendChild(script);
}
