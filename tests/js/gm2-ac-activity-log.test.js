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

