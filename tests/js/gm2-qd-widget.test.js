const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test.skip('clicking option activates it', async () => {
  const dom = new JSDOM(`
    <div class="gm2-qd-options">
      <button class="gm2-qd-option" data-qty="1">1</button>
      <button class="gm2-qd-option" data-qty="2">2</button>
    </div>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  jest.resetModules();
  require('../../public/js/gm2-qd-widget.js');
  await new Promise(r => setTimeout(r, 0));

  const first = $('.gm2-qd-option').eq(0);
  const second = $('.gm2-qd-option').eq(1);

  first.triggerHandler('click');
  expect(first.hasClass('active')).toBe(true);
  expect(second.hasClass('active')).toBe(false);

  second.triggerHandler('click');
  expect(second.hasClass('active')).toBe(true);
  expect(first.hasClass('active')).toBe(false);
});
