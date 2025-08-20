const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test('renders activity items returned from ajax', async () => {
  const dom = new JSDOM(`
    <table id="log">
      <tr id="row">
        <td><a href="#" class="gm2-ac-activity-log-button" data-ip="1.2.3.4">Log</a></td>
      </tr>
    </table>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2AcActivityLog = { ajax_url: '/ajax', nonce: 'nonce', empty: 'empty', error: 'error' };

  $.post = jest.fn(() => $.Deferred().resolve({
    success: true,
    data: {
      activity: [{ changed_at: '2024-01-01', action: 'add', sku: 'SKU1', quantity: 1 }]
    }
  }).promise());

  jest.resetModules();
  require('../../admin/js/gm2-ac-activity-log.js');

  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  $('.gm2-ac-activity-log-button').trigger('click');

  await new Promise(r => setTimeout(r, 0));

  const text = $('.gm2-ac-activity-log li').text();
  expect(text).toContain('2024-01-01');
  expect(text).toContain('add');
  expect(text).toContain('SKU1');
  expect(text).toContain('x1');
});

