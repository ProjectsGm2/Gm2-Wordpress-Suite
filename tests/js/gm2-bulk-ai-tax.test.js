const jquery = require('jquery');
const { JSDOM } = require('jsdom');

test('taxonomy rows with suggestions get analyzed status on load', () => {
  const dom = new JSDOM(`
    <table id="gm2-bulk-term-list">
      <tr id="gm2-term-1">
        <td>
          <div class="gm2-result">
            <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
          </div>
        </td>
      </tr>
      <tr id="gm2-term-2">
        <td><div class="gm2-result"></div></td>
      </tr>
    </table>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  var $rows = $('#gm2-bulk-term-list tr').addClass('gm2-status-new');
  $rows.each(function(){
    var $row = $(this);
    var $res = $row.find('.gm2-result');
    if($res.find('.gm2-apply').length || $.trim($res.text()).length){
      $row.removeClass('gm2-status-new').addClass('gm2-status-analyzed');
    }
  });

  expect($('#gm2-term-1').hasClass('gm2-status-analyzed')).toBe(true);
  expect($('#gm2-term-2').hasClass('gm2-status-new')).toBe(true);
});

test('taxonomy select analyzed checks row checkbox and suggestions', () => {
  const dom = new JSDOM(`
    <div id="gm2-bulk-ai-tax">
      <button id="gm2-bulk-term-select-analyzed">Select analyzed</button>
      <table id="gm2-bulk-term-list">
        <tr id="gm2-term-cat-1" class="gm2-status-analyzed">
          <td>
            <input type="checkbox" class="gm2-select">
            <div class="gm2-result">
              <p><label><input type="checkbox" class="gm2-row-select-all"> Select all</label></p>
              <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
            </div>
          </td>
        </tr>
        <tr id="gm2-term-cat-2" class="gm2-status-new">
          <td>
            <input type="checkbox" class="gm2-select">
            <div class="gm2-result">
              <p><label><input type="checkbox" class="gm2-row-select-all"> Select all</label></p>
              <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
            </div>
          </td>
        </tr>
      </table>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  $('#gm2-bulk-term-list').on('change', '.gm2-row-select-all', function(){
    const checked = $(this).prop('checked');
    $(this).closest('td').find('.gm2-apply').prop('checked', checked);
  });

  $('#gm2-bulk-ai-tax').on('click', '#gm2-bulk-term-select-analyzed', function(e){
    e.preventDefault();
    $('#gm2-bulk-term-list tr.gm2-status-analyzed').each(function(){
      $(this).find('.gm2-select').prop('checked', true);
      $(this).find('.gm2-row-select-all').prop('checked', true).trigger('change');
    });
  });

  $('#gm2-bulk-term-select-analyzed').trigger('click');

  expect($('#gm2-term-cat-1 .gm2-select').prop('checked')).toBe(true);
  expect($('#gm2-term-cat-1 .gm2-apply').prop('checked')).toBe(true);
  expect($('#gm2-term-cat-2 .gm2-select').prop('checked')).toBe(false);
  expect($('#gm2-term-cat-2 .gm2-apply').prop('checked')).toBe(false);
});
