const jquery = require('jquery');
const { JSDOM } = require('jsdom');

test('row select all toggles suggestion checkboxes', () => {
  const dom = new JSDOM(`
    <table id="gm2-bulk-list">
      <tr id="gm2-row-1">
        <td>
          <p><label><input type="checkbox" class="gm2-row-select-all"> Select all</label></p>
          <p><label><input type="checkbox" class="gm2-apply"> First</label></p>
          <p><label><input type="checkbox" class="gm2-apply"> Second</label></p>
        </td>
      </tr>
    </table>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  $('#gm2-bulk-list').on('change', '.gm2-row-select-all', function(){
    const checked = $(this).prop('checked');
    $(this).closest('td').find('.gm2-apply').prop('checked', checked);
  });

  const selectAll = $('.gm2-row-select-all');
  const boxes = $('.gm2-apply');

  selectAll.prop('checked', true).trigger('change');
  expect(boxes.filter(':checked').length).toBe(2);

  selectAll.prop('checked', false).trigger('change');
  expect(boxes.filter(':checked').length).toBe(0);
});
