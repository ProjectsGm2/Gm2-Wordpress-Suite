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

test('captures external link destination before navigation', () => {
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

  const abandonCalls = window.navigator.sendBeacon.mock.calls.filter((c) => {
    const params = new URLSearchParams(c[1].toString());
    return params.get('action') === 'gm2_ac_mark_abandoned';
  });

  expect(abandonCalls.length).toBe(1);
  const params = new URLSearchParams(abandonCalls[0][1].toString());
  expect(params.get('url')).toBe('https://external.com/path');
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('marks abandoned on visibilitychange hidden', () => {
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

  Object.defineProperty(window.document, 'visibilityState', { configurable: true, value: 'hidden' });
  window.document.dispatchEvent(new window.Event('visibilitychange'));

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

test('renders and toggles activity log rows', () => {
  jest.resetModules();
  const dom = new JSDOM(`<!DOCTYPE html><table><tbody><tr id="r"><td><a href="#" class="gm2-ac-activity-log-button" data-ip="1.1.1.1">log</a></td></tr></tbody></table>`);
  global.window = dom.window;
  global.document = dom.window.document;
  global.gm2AcActivityLog = { ajax_url: '/ajax', nonce: 'n', empty: 'Empty', error: 'Err' };
  const res = { success: true, data: [{ changed_at: 'now', action: 'add', sku: 'SKU', quantity: 2 }] };
  const post = jest.fn(() => ({ done(cb){ cb(res); return this; }, fail(){ return this; }, always(cb){ cb(); return this; } }));
  const $ = setupJQuery(post);
  global.jQuery = global.$ = $;

  require('../../admin/js/gm2-ac-activity-log.js');

  const btn = $('.gm2-ac-activity-log-button');
  btn.trigger('click');
  expect($.post).toHaveBeenCalledTimes(1);
  expect($('.gm2-ac-activity-row').length).toBe(1);
  expect($('.gm2-ac-activity-row li').elem.textContent).toContain('add SKU x2');
  expect(btn.prop('disabled')).toBe(false);

  btn.trigger('click');
  expect($('.gm2-ac-activity-row').length).toBe(0);
  expect($.post).toHaveBeenCalledTimes(1);
});

test('shows error message on failed activity request', () => {
  jest.resetModules();
  const dom = new JSDOM(`<!DOCTYPE html><table><tbody><tr id="r"><td><a href="#" class="gm2-ac-activity-log-button" data-ip="1.1.1.1">log</a></td></tr></tbody></table>`);
  global.window = dom.window;
  global.document = dom.window.document;
  global.gm2AcActivityLog = { ajax_url: '/ajax', nonce: 'n', empty: 'Empty', error: 'Err' };
  const post = jest.fn(() => ({ done(){ return this; }, fail(cb){ cb(); return this; }, always(cb){ cb(); return this; } }));
  const $ = setupJQuery(post);
  global.jQuery = global.$ = $;

  require('../../admin/js/gm2-ac-activity-log.js');

  $('.gm2-ac-activity-log-button').trigger('click');
  expect($('.gm2-ac-activity-row').elem.textContent).toBe('Err');
});
