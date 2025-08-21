const { JSDOM } = require('jsdom');

test('records exit URL when localStorage is disabled', () => {
  jest.useFakeTimers();
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

  const abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].toString());
  expect(params.get('url')).toBe('https://example.com/page');
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('defaults to current URL when navigating to an external link', () => {
  jest.useFakeTimers();
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
  link.addEventListener('click', (e) => e.preventDefault());
  link.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));

  expect(window.navigator.sendBeacon).not.toHaveBeenCalled();

  window.dispatchEvent(new window.Event('beforeunload'));

  const abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].toString());
  expect(params.get('url')).toBe('https://example.com/page');
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('revives cart on visibilitychange visible', () => {
  jest.useFakeTimers();
  const dom = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  fetchMock.mockClear();
  Object.defineProperty(window.document, 'visibilityState', { configurable: true, value: 'visible' });
  window.document.dispatchEvent(new window.Event('visibilitychange'));

  const activeCalls = fetchMock.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].body.toString());
    return params.get('action') === 'gm2_ac_mark_active';
  });

  expect(activeCalls.length).toBe(1);
  expect(window.navigator.sendBeacon).not.toHaveBeenCalled();
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('marks abandoned on beforeunload', () => {
  jest.useFakeTimers();
  const dom = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  window.dispatchEvent(new window.Event('beforeunload'));

  const abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].toString());
  expect(params.get('url')).toBe('https://example.com/page');
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('marks abandoned after inactivity threshold', () => {
  jest.useFakeTimers();
  const dom = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce', inactivity_ms: 50 };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  jest.advanceTimersByTime(60);

  const abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('resets inactivity timer on interaction', () => {
  jest.useFakeTimers();
  const dom = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/page' });
  const { window } = dom;

  const fetchMock = jest.fn().mockResolvedValue({ ok: true });
  window.fetch = fetchMock;
  global.fetch = fetchMock;
  window.navigator.sendBeacon = jest.fn().mockReturnValue(true);

  Object.assign(global, { window, document: window.document, navigator: window.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce', inactivity_ms: 50 };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  window.document.dispatchEvent(new window.Event('mousemove'));
  jest.advanceTimersByTime(40);
  let abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });
  expect(abandonCalls.length).toBe(0);

  jest.advanceTimersByTime(20);
  abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });
  expect(abandonCalls.length).toBe(1);
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('records entry and exit on revisit with timestamps', () => {
  jest.useFakeTimers();
  jest.setSystemTime(new Date('2024-01-01T00:00:00Z'));
  const dom1 = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/first' });
  const { window: win1 } = dom1;

  const activeTimes = [];
  const abandonTimes = [];
  const fetchMock = jest.fn((url, opts) => {
    const params = new URLSearchParams(opts.body.toString());
    if (params.get('action') === 'gm2_ac_mark_active') {
      activeTimes.push(Date.now());
    }
    return Promise.resolve({ ok: true });
  });
  win1.fetch = fetchMock;
  global.fetch = fetchMock;
  win1.navigator.sendBeacon = jest.fn((url, data) => {
    abandonTimes.push(Date.now());
    return true;
  });

  Object.assign(global, { window: win1, document: win1.document, navigator: win1.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  expect(activeTimes.length).toBeGreaterThan(0);
  const entry1 = activeTimes.shift();
  activeTimes.length = 0;
  expect(abandonTimes.length).toBe(0);

  jest.setSystemTime(new Date('2024-01-01T00:05:00Z'));
  win1.dispatchEvent(new win1.Event('pagehide'));
  expect(abandonTimes.length).toBe(1);
  const exit1 = abandonTimes.shift();

  jest.setSystemTime(new Date('2024-01-01T00:06:00Z'));
  const dom2 = new JSDOM(`<!DOCTYPE html><body></body>`, { url: 'https://example.com/second' });
  const { window: win2 } = dom2;
  win2.fetch = fetchMock;
  win2.navigator.sendBeacon = win1.navigator.sendBeacon;
  Object.assign(global, { window: win2, document: win2.document, navigator: win2.navigator });
  global.gm2AcActivity = { ajax_url: '/ajax', nonce: 'nonce' };

  jest.resetModules();
  require('../../public/js/gm2-ac-activity.js');

  expect(activeTimes.length).toBeGreaterThan(0);
  const entry2 = activeTimes.shift();

  jest.setSystemTime(new Date('2024-01-01T00:10:00Z'));
  win2.dispatchEvent(new win2.Event('pagehide'));
  expect(abandonTimes.length).toBe(1);
  const exit2 = abandonTimes.shift();

  expect(entry1).toBeLessThan(exit1);
  expect(exit1).toBeLessThan(entry2);
  expect(entry2).toBeLessThan(exit2);

  jest.useRealTimers();
});

function setupJQuery(postImpl) {
  function createWrapper(elem) {
    return {
      elem,
      on(ev, fn) { elem.addEventListener(ev, fn); return this; },
      closest(sel) { return createWrapper(elem.closest(sel)); },
      next() { return createWrapper(elem.nextElementSibling); },
      hasClass(cls) { return elem && elem.classList.contains(cls); },
      remove() { if (elem) elem.remove(); },
      prop(name, val) { if (val === undefined) return elem[name]; elem[name] = val; return this; },
      data(name) { return elem.getAttribute('data-' + name); },
      children() { return { length: elem.children.length }; },
      after(html) { elem.insertAdjacentHTML('afterend', html); return this; },
      trigger(ev) { elem.dispatchEvent(new window.Event(ev, { bubbles: true })); return this; },
      length: elem ? 1 : 0,
    };
  }
  function $(arg) {
    if (typeof arg === 'function') { arg($); return; }
    const el = typeof arg === 'string' ? document.querySelector(arg) : arg;
    return createWrapper(el);
  }
  $.post = postImpl;
  return $;
}

