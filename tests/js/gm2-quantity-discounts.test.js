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
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [{ name: 'Group A', products: [{ id: 1, title: 'Prod', sku: 'P1' }], rules: [] }], categories: [], productTitles: { 1: 'Prod' } };

  jest.resetModules();
  require('../../admin/js/gm2-quantity-discounts.js');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  expect($('.gm2-qd-name').val()).toBe('Group A');
  expect($('.gm2-qd-accordion').length).toBe(1);
  expect($('.gm2-qd-selected-title').text()).toBe('Selected products');
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
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [], categories: [], productTitles: {} };
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
  global.gm2Qd = { nonce: 'n', ajax_url: '/fake', groups: [{ name: 'A', products: [], rules: [] }], categories: [], productTitles: {} };

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

test('search results use checkboxes and add selected button', async () => {
  const dom = new JSDOM(`
    <form id="gm2-qd-form">
      <div id="gm2-qd-groups"></div>
    </form>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2Qd = {
    nonce: 'n',
    ajax_url: '/fake',
    groups: [{ name: 'A', products: [], rules: [] }],
    categories: [],
    productTitles: {}
  };
  $.get = jest.fn(() => $.Deferred().resolve({
    success: true,
    data: [
      { id: 1, title: 'Prod1', sku: 'S1' },
      { id: 2, title: 'Prod2', sku: 'S2' }
    ]
  }));

  jest.resetModules();
  require('../../admin/js/gm2-quantity-discounts.js');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  const group = $('.gm2-qd-group');
  group.find('.gm2-qd-search').val('pr');
  group.find('.gm2-qd-search-btn').trigger('click');
  await new Promise(r => setTimeout(r, 0));

  const checkboxes = group.find('.gm2-qd-product-chk');
  expect(checkboxes.length).toBe(2);

  group.find('.gm2-qd-select-all').prop('checked', true).trigger('change');
  expect(checkboxes.filter(':checked').length).toBe(2);

  group.find('.gm2-qd-add-selected').trigger('click');
  await new Promise(r => setTimeout(r, 0));

  const selected = group.find('.gm2-qd-selected li');
  expect(selected.length).toBe(2);
  expect(selected.eq(0).text()).toContain('Prod1');
  expect(selected.eq(1).text()).toContain('Prod2');
});
