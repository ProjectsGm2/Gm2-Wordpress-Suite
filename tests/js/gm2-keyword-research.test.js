const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test('renders complex keyword objects without [object Object]', async () => {
  const dom = new JSDOM(`
    <form id="gm2-keyword-research-form">
      <input id="gm2_seed_keyword" />
    </form>
    <ul id="gm2-keyword-results"></ul>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2KeywordResearch = { enabled: true, ajax_url: '/fake', nonce: 'nonce' };

  $.post = jest.fn(() => $.Deferred().resolve({
    success: true,
    data: [
      { text: { value: 'hello' } },
      { text: { value: 'world' }, metrics: { competition: { value: 'low' } } }
    ]
  }));

  jest.resetModules();
  require('../../admin/js/gm2-keyword-research.js');

  // wait for jQuery ready callbacks to run
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  $('#gm2-keyword-research-form').triggerHandler('submit');

  await new Promise(r => setTimeout(r, 0));

  const text = $('#gm2-keyword-results').text();
  expect(text).toContain('hello');
  expect(text).toContain('world');
  expect(text).not.toContain('[object Object]');
});

test('uses translation for metrics unavailable notice', async () => {
  const dom = new JSDOM(`
    <form id="gm2-keyword-research-form">
      <input id="gm2_seed_keyword" />
    </form>
    <ul id="gm2-keyword-results"></ul>
    <div id="gm2-keyword-msg"></div>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2KeywordResearch = {
    enabled: true,
    ajax_url: '/fake',
    nonce: 'nonce',
    i18n: { metricsUnavailable: 'translated text' }
  };
  dom.window.gm2KeywordResearch = global.gm2KeywordResearch;

  $.post = jest.fn(() => $.Deferred().resolve({
    success: true,
    data: { ai_only: true, ideas: [] }
  }));

  jest.resetModules();
  require('../../admin/js/gm2-keyword-research.js');

  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  $('#gm2-keyword-research-form').triggerHandler('submit');

  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  const msg = $('#gm2-keyword-msg').text();
  expect(msg).toBe('translated text');
});
