const { JSDOM } = require('jsdom');

test('records exit URL when localStorage is disabled', () => {
  const dom = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.defineProperty(window, 'localStorage', {
    configurable: true,
    get() {
      throw new Error('access denied');
    },
  });

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  window.dispatchEvent(new window.Event('pagehide'));

  const abandonCalls = fetchMock.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].body);
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].body);
  expect(params.get('url')).toBe('https://example.com/page');
});

test('captures external link destination before navigation', () => {
  const dom = new JSDOM(`<!DOCTYPE html><body><a id="out" href="https://external.com/path">go</a></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  const link = window.document.getElementById('out');
  link.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));

  const abandonCalls = fetchMock.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].body);
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].body);
  expect(params.get('url')).toBe('https://external.com/path');
});
