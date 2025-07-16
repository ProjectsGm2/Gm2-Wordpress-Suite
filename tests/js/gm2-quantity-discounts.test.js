const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test('renders groups from gm2Qd', async () => {
  const dom = new JSDOM(`
    <form id="gm2-qd-form">
      <div id="gm2-qd-groups"></div>
      <button id="gm2-qd-add-group"></button>
    </form>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [{ name: 'Group A', products: [{ id: 1, title: 'Prod', sku: 'P1' }], rules: [] }], categories: [] };

  jest.resetModules();
  require('../../admin/js/gm2-quantity-discounts.js');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  expect($('.gm2-qd-name').val()).toBe('Group A');
  expect($('.gm2-qd-accordion').length).toBe(1);
  const text = $('.gm2-qd-selected li').text();
  expect(text).toContain('Prod');
  expect(text).toContain('P1');
});

test('submits group data via ajax', async () => {
  const dom = new JSDOM(`
    <form id="gm2-qd-form">
      <div id="gm2-qd-groups"></div>
      <button id="gm2-qd-add-group" type="button"></button>
      <div id="gm2-qd-msg" class="hidden"></div>
    </form>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [], categories: [] };
  $.post = jest.fn(() => $.Deferred().resolve({ success: true }));

  jest.resetModules();
  require('../../admin/js/gm2-quantity-discounts.js');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  $('#gm2-qd-add-group').trigger('click');
  $('.gm2-qd-add-rule').trigger('click');
  $('.gm2-qd-name').val('Test');
  $('.gm2-qd-min').val('2');
  $('.gm2-qd-label').val('First');
  $('.gm2-qd-percent').val('10');
  $('.gm2-qd-selected').append('<li data-id="55"><label><input type="checkbox" class="gm2-qd-selected-chk"> Prod</label></li>');

  $('#gm2-qd-form').trigger('submit');
  await new Promise(r => setTimeout(r, 0));

  expect($.post).toHaveBeenCalled();
  const data = $.post.mock.calls[0][1].groups[0];
  expect(data.name).toBe('Test');
  expect(data.products[0]).toBe(55);
  expect(data.rules[0]).toEqual({ min: 2, label: 'First', type: 'percent', amount: 10 });
});

test('accordion toggles visibility', async () => {
  const dom = new JSDOM(`
    <form id="gm2-qd-form">
      <div id="gm2-qd-groups"></div>
    </form>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [{ name: 'A', products: [], rules: [] }], categories: [] };

  jest.resetModules();
  require('../../admin/js/gm2-quantity-discounts.js');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  const accordion = $('.gm2-qd-accordion');
  const body = accordion.find('.gm2-qd-group');
  expect(body.css('display')).not.toBe('none');

  accordion.find('.gm2-qd-header').trigger('click');
  await new Promise(r => setTimeout(r, 0));
  expect(body.css('display')).toBe('none');

  accordion.find('.gm2-qd-header').trigger('click');
  await new Promise(r => setTimeout(r, 0));
  expect(body.css('display')).not.toBe('none');
});
