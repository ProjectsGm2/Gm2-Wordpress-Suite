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

test('rows with suggestions get analyzed status on load', () => {
  const dom = new JSDOM(`
    <table id="gm2-bulk-list">
      <tr id="gm2-row-1">
        <td class="gm2-result">
          <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
        </td>
      </tr>
      <tr id="gm2-row-2">
        <td class="gm2-result"></td>
      </tr>
    </table>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  var $rows = $('#gm2-bulk-list tr[id^="gm2-row-"]').addClass('gm2-status-new');
  $rows.each(function(){
    var $row = $(this);
    var $res = $row.find('.gm2-result');
    if($res.find('.gm2-apply').length || $.trim($res.text()).length){
      $row.removeClass('gm2-status-new').addClass('gm2-status-analyzed');
    }
  });

  expect($('#gm2-row-1').hasClass('gm2-status-analyzed')).toBe(true);
  expect($('#gm2-row-2').hasClass('gm2-status-new')).toBe(true);
});
