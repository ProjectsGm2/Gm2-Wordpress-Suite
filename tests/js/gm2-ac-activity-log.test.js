const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test('paginates activity items', async () => {
  const dom = new JSDOM(`
    <table id="log">
      <tr id="row">
        <td><a href="#" class="gm2-ac-activity-log-button" data-ip="1.2.3.4">Log</a></td>
      </tr>
    </table>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2AcActivityLog = { ajax_url: '/ajax', nonce: 'nonce', empty: 'empty', error: 'error', load_more: 'more', per_page: 1 };

  $.post = jest.fn()
    .mockImplementationOnce(() => $.Deferred().resolve({
      success: true,
      data: {
        activity: [{ changed_at: '2024-01-01', action: 'add', sku: 'SKU1', quantity: 1 }]
      }
    }).promise())
    .mockImplementationOnce(() => $.Deferred().resolve({
      success: true,
      data: {
        activity: [{ changed_at: '2024-01-02', action: 'add', sku: 'SKU2', quantity: 1 }]
      }
    }).promise());

  jest.resetModules();
  require('../../admin/js/gm2-ac-activity-log.js');

  const flush = () => new Promise(r => setTimeout(r, 0));
  await flush();
  await flush();

  $('.gm2-ac-activity-log-button').trigger('click');

  await flush();

  expect($.post).toHaveBeenCalledWith('/ajax', expect.objectContaining({ page: 1, per_page: 1, ip: '1.2.3.4' }));

  $('.gm2-ac-load-more').trigger('click');

  await flush();

  expect($.post).toHaveBeenLastCalledWith('/ajax', expect.objectContaining({ page: 2, per_page: 1, ip: '1.2.3.4' }));

  const items = $('.gm2-ac-activity-log li');
  expect(items.length).toBe(2);
  expect(items.eq(1).text()).toContain('SKU2');
});

test('stops auto-loading when no more results', async () => {
  const dom = new JSDOM(`
    <table id="log">
      <tr id="row">
        <td><a href="#" class="gm2-ac-activity-log-button" data-ip="1.2.3.4">Log</a></td>
      </tr>
    </table>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2AcActivityLog = { ajax_url: '/ajax', nonce: 'nonce', empty: 'empty', error: 'error', load_more: 'more', per_page: 1 };

  $.post = jest.fn()
    .mockImplementationOnce(() => $.Deferred().resolve({
      success: true,
      data: {
        activity: [{ changed_at: '2024-01-01', action: 'add', sku: 'SKU1', quantity: 1 }]
      }
    }).promise())
    .mockImplementationOnce(() => $.Deferred().resolve({
      success: true,
      data: {
        activity: []
      }
    }).promise())
    .mockImplementationOnce(() => $.Deferred().resolve({
      success: true,
      data: {
        activity: []
      }
    }).promise());

  jest.resetModules();
  require('../../admin/js/gm2-ac-activity-log.js');

  const flush = () => new Promise(r => setTimeout(r, 0));
  await flush();
  await flush();

  $('.gm2-ac-activity-log-button').trigger('click');

  await flush();

  // simulate scroll to load second page
  const wrap = $('.gm2-ac-activity-wrap');
  wrap[0].scrollTop = 100;
  wrap[0].scrollHeight = 100;
  wrap.trigger('scroll');

  await flush();

  expect($.post).toHaveBeenCalledTimes(2);

  // append a manual load more button and trigger scroll again; should not load more due to end flag
  wrap.append('<button class="gm2-ac-load-more">more</button>');
  wrap[0].scrollTop = 100;
  wrap.trigger('scroll');

  await flush();

  expect($.post).toHaveBeenCalledTimes(2);

  // manual click resets end and allows another request
  wrap.find('.gm2-ac-load-more').trigger('click');

  await flush();

  expect($.post).toHaveBeenCalledTimes(3);
});

