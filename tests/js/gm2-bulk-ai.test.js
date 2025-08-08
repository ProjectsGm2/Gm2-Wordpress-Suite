const jquery = require('jquery');
const { JSDOM } = require('jsdom');

test('row select all toggles suggestion checkboxes', () => {
    const dom = new JSDOM(`
      <table id="gm2-bulk-list">
        <tr id="gm2-row-1">
          <td>
            <div class="gm2-result">
              <p><label><input type="checkbox" class="gm2-row-select-all"> Select all</label></p>
              <p><label><input type="checkbox" class="gm2-apply"> First</label></p>
              <p><label><input type="checkbox" class="gm2-apply"> Second</label></p>
            </div>
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
          <td>
            <div class="gm2-result">
              <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
            </div>
          </td>
        </tr>
        <tr id="gm2-row-2">
          <td><div class="gm2-result"></div></td>
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

test('select analyzed checks row checkbox and suggestions', () => {
    const dom = new JSDOM(`
      <div id="gm2-bulk-ai">
        <button class="gm2-bulk-select-analyzed">Select analyzed</button>
        <table id="gm2-bulk-list">
          <tr id="gm2-row-1" class="gm2-status-analyzed">
            <td>
              <input type="checkbox" class="gm2-select">
              <div class="gm2-result">
                <p><label><input type="checkbox" class="gm2-row-select-all"> Select all</label></p>
                <p><label><input type="checkbox" class="gm2-apply"> Suggestion</label></p>
              </div>
            </td>
          </tr>
          <tr id="gm2-row-2" class="gm2-status-new">
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

  $('#gm2-bulk-list').on('change', '.gm2-row-select-all', function(){
    const checked = $(this).prop('checked');
    $(this).closest('td').find('.gm2-apply').prop('checked', checked);
  });

  $('#gm2-bulk-ai').on('click', '.gm2-bulk-select-analyzed', function(e){
    e.preventDefault();
    $('#gm2-bulk-list tr.gm2-status-analyzed').each(function(){
      $(this).find('.gm2-select').prop('checked', true);
      $(this).find('.gm2-row-select-all').prop('checked', true).trigger('change');
    });
  });

  $('.gm2-bulk-select-analyzed').trigger('click');

  expect($('#gm2-row-1 .gm2-select').prop('checked')).toBe(true);
  expect($('#gm2-row-1 .gm2-apply').prop('checked')).toBe(true);
  expect($('#gm2-row-2 .gm2-select').prop('checked')).toBe(false);
  expect($('#gm2-row-2 .gm2-apply').prop('checked')).toBe(false);
});

test('select filtered toggles between select and unselect all', () => {
    const dom = new JSDOM(`
      <div id="gm2-bulk-ai">
        <button class="gm2-bulk-select-filtered">Select All</button>
        <table id="gm2-bulk-list">
          <tr id="gm2-row-1"><td><input type="checkbox" class="gm2-select" value="1"></td></tr>
          <tr id="gm2-row-2"><td><input type="checkbox" class="gm2-select" value="2"></td></tr>
        </table>
      </div>
    `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2BulkAi = {
    ajax_url: '/ajax',
    fetch_nonce: 'nonce',
    i18n: { selectAllPosts: 'Select All', unselectAllPosts: 'Un-Select All' }
  };
  let selectedIds = [];
  function getSelectedIds(){
    if(selectedIds.length){ return selectedIds.slice(); }
    const ids=[]; $('#gm2-bulk-list .gm2-select:checked').each(function(){ ids.push($(this).val()); });
    return ids;
  }
  $('#gm2-bulk-list').on('change','.gm2-select',function(){
    if(!selectedIds.length) return;
    const id=$(this).val();
    if($(this).is(':checked')){
      if($.inArray(id, selectedIds) === -1){ selectedIds.push(id); }
    } else {
      selectedIds=$.grep(selectedIds,function(v){ return v!=id; });
    }
  });
  $.post = jest.fn(() => ({
    done(cb){ cb({ success:true, data:{ ids:['1','2','3'] } }); return this; },
    fail(){ return this; }
  }));
  $('#gm2-bulk-ai').on('click','.gm2-bulk-select-filtered',function(e){
    e.preventDefault();
    const $btn=$(this);
    if($btn.data('selected')){
      selectedIds=[];
      $('#gm2-bulk-list .gm2-select').prop('checked',false);
      $btn.data('selected',false).text(gm2BulkAi.i18n.selectAllPosts);
      return;
    }
    const data={ action:'gm2_bulk_ai_fetch_ids', status:'publish', post_type:'all', seo_status:'all', terms:[], missing_title:0, missing_desc:0, search:'', _ajax_nonce:gm2BulkAi.fetch_nonce };
    $btn.prop('disabled',true);
    $.post(gm2BulkAi.ajax_url,data).done(function(resp){
      $btn.prop('disabled',false);
      if(resp&&resp.success){
        selectedIds=resp.data.ids||[];
        $('#gm2-bulk-list .gm2-select').prop('checked',true);
        $btn.data('selected',true).text(gm2BulkAi.i18n.unselectAllPosts);
      }
    }).fail(function(){ $btn.prop('disabled',false); });
  });
  const $btn=$('.gm2-bulk-select-filtered');
  $btn.trigger('click');
  expect(selectedIds).toEqual(['1','2','3']);
  expect($('#gm2-bulk-list .gm2-select:checked').length).toBe(2);
  expect($btn.text()).toBe('Un-Select All');
  $('#gm2-row-1 .gm2-select').prop('checked',false).trigger('change');
  expect(selectedIds).toEqual(['2','3']);
  $btn.trigger('click');
  expect(selectedIds).toEqual([]);
  expect($('#gm2-bulk-list .gm2-select:checked').length).toBe(0);
  expect($btn.text()).toBe('Select All');
});

test('getSelectedIds returns stored ids when DOM is empty', () => {
    const dom = new JSDOM(`<table id="gm2-bulk-list"></table>`, { url: 'http://localhost' });
    const $ = jquery(dom.window);
    Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
    let selectedIds = ['10','20'];
    function getSelectedIds(){
      if(selectedIds.length){ return selectedIds.slice(); }
      const ids=[]; $('#gm2-bulk-list .gm2-select:checked').each(function(){ ids.push($(this).val()); });
      return ids;
    }
    const ids=getSelectedIds();
    expect(ids).toEqual(['10','20']);
    ids.push('30');
    expect(selectedIds).toEqual(['10','20']);
});
