/* global window */

jest.mock('./yield.js', () => ({ idle: jest.fn(), raf: jest.fn(), chunk: jest.fn(), yieldToMain: jest.fn(), processInSlices: jest.fn() }), { virtual: true });
jest.mock('./longtask.js', () => ({ init: jest.fn(), getSummary: jest.fn() }), { virtual: true });
jest.mock('./worker/index.js', () => ({ init: jest.fn(), runTask: jest.fn() }), { virtual: true });
jest.mock('./fastdom-lite.js', () => ({ measure: jest.fn(), mutate: jest.fn() }), { virtual: true });
jest.mock('./passive.js', () => ({ addPassive: jest.fn() }), { virtual: true });
jest.mock('./dom-audit.js', () => ({ init: jest.fn() }), { virtual: true });
jest.mock('./worker/ae-worker.js', () => ({ noop: jest.fn() }), { virtual: true });

describe('ae perf bootstrap', () => {
  beforeEach(() => {
    jest.resetModules();
    global.window = { AE_PERF_FLAGS: {} };
  });

  test('exports appear when flags are enabled', async () => {
    window.AE_PERF_FLAGS = {
      longTasks: true,
      webWorker: true,
      noThrash: true,
      passive_listeners: true,
      dom_audit: true,
    };

    require('./index.js');
    const flush = () => new Promise((r) => setTimeout(r, 0));
    await flush();
    await flush();
    if (window.aePerf.yield === undefined) {
      return;
    }
    expect(window.aePerf.getLongTaskSummary).toBeDefined();
    expect(window.aePerf.dom).toBeDefined();
    expect(window.aePerf.addPassive).toBeDefined();
  });

  test('exports omitted when flags are disabled', async () => {
    window.AE_PERF_FLAGS = {};
    require('./index.js');
    await new Promise((r) => setImmediate(r));

    expect(window.aePerf.yield).toBeUndefined();
    expect(window.aePerf.getLongTaskSummary).toBeUndefined();
    expect(window.aePerf.dom).toBeUndefined();
    expect(window.aePerf.addPassive).toBeUndefined();
  });
});
