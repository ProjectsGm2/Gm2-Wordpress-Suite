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

test('clearing taxonomy suggestions removes analyzed status', () => {
  const dom = new JSDOM(`
    <div id="gm2-bulk-ai-tax">
      <button id="gm2-bulk-term-reset-ai">Reset AI Suggestion</button>
      <p id="gm2-bulk-term-msg"></p>
      <progress class="gm2-bulk-term-progress-bar" value="0" max="100"></progress>
      <table id="gm2-bulk-term-list">
        <tr id="gm2-term-cat-1" class="gm2-status-analyzed">
          <td>
            <input type="checkbox" class="gm2-select" value="cat:1">
            <div class="gm2-result">Suggestion</div>
          </td>
        </tr>
      </table>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  $('#gm2-bulk-ai-tax').on('click', '#gm2-bulk-term-reset-ai', function(e){
    e.preventDefault();
    $('#gm2-bulk-term-list .gm2-select:checked').each(function(){
      const row = $(this).closest('tr');
      row.find('.gm2-result').html('✓');
      row.removeClass('gm2-status-analyzed').addClass('gm2-status-new');
    });
    $('#gm2-bulk-term-msg').text('Cleared AI suggestions for 1 terms');
  });

  $('#gm2-term-cat-1 .gm2-select').prop('checked', true);
  $('#gm2-bulk-term-reset-ai').trigger('click');

  expect($('#gm2-term-cat-1').hasClass('gm2-status-new')).toBe(true);
  expect($('#gm2-term-cat-1 .gm2-result').text()).toBe('✓');
  expect($('#gm2-bulk-term-msg').text()).toBe('Cleared AI suggestions for 1 terms');
});

test('taxonomy select filtered toggles between select and unselect all', () => {
  const dom = new JSDOM(`
    <div id="gm2-bulk-ai-tax">
      <button id="gm2-bulk-term-select-filtered">Select All</button>
      <table id="gm2-bulk-term-list">
        <tr id="gm2-term-cat-1"><td><input type="checkbox" class="gm2-select" value="cat:1"></td></tr>
        <tr id="gm2-term-cat-2"><td><input type="checkbox" class="gm2-select" value="cat:2"></td></tr>
      </table>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2BulkAiTax = {
    ajax_url: '/ajax',
    fetch_nonce: 'nonce',
    i18n: { selectAllTerms: 'Select All', unselectAllTerms: 'Un-Select All' }
  };

  const storageKeys='gm2BulkAiTaxSelectedKeys';
  const storageFlag='gm2BulkAiTaxSelected';
  window.sessionStorage.clear();
  let selectedKeys = [];

  $('#gm2-bulk-term-list').on('change','.gm2-select',function(){
    if(!selectedKeys.length) return;
    const key=String($(this).val());
    if($(this).is(':checked')){
      if($.inArray(key, selectedKeys) === -1){ selectedKeys.push(key); }
    } else {
      selectedKeys=$.grep(selectedKeys,function(v){ return v!==key; });
    }
    window.sessionStorage.setItem(storageKeys, JSON.stringify(selectedKeys));
  });

  $.post = jest.fn(() => ({
    done(cb){ cb({ success:true, data:{ ids:['cat:1','cat:2','tag:3'] } }); return this; },
    fail(){ return this; }
  }));

  function clearStored(){
    window.sessionStorage.removeItem(storageKeys);
    window.sessionStorage.removeItem(storageFlag);
  }

  $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-select-filtered',function(e){
    e.preventDefault();
    const $btn=$(this);
    if($btn.data('selected')){
      selectedKeys=[];
      $('#gm2-bulk-term-list .gm2-select').prop('checked',false);
      $btn.data('selected',false).text(gm2BulkAiTax.i18n.selectAllTerms);
      clearStored();
      return;
    }
    const data={
      action:'gm2_bulk_ai_tax_fetch_ids',
      taxonomy:'all',
      status:'publish',
      search:'',
      seo_status:'all',
      missing_title:0,
      missing_desc:0,
      _ajax_nonce:gm2BulkAiTax.fetch_nonce
    };
    $btn.prop('disabled',true);
    $.post(gm2BulkAiTax.ajax_url,data).done(function(resp){
      $btn.prop('disabled',false);
      if(resp&&resp.success){
        selectedKeys=(resp.data.ids||[]).map(String);
        $('#gm2-bulk-term-list .gm2-select').each(function(){
          $(this).prop('checked',$.inArray($(this).val(),selectedKeys)!==-1);
        });
        if(selectedKeys.length){
          $btn.data('selected',true).text(gm2BulkAiTax.i18n.unselectAllTerms);
          window.sessionStorage.setItem(storageKeys, JSON.stringify(selectedKeys));
          window.sessionStorage.setItem(storageFlag, '1');
        } else {
          $btn.data('selected',false).text(gm2BulkAiTax.i18n.selectAllTerms);
          clearStored();
        }
      }
    }).fail(function(){ $btn.prop('disabled',false); });
  });

  const $btn=$('#gm2-bulk-term-select-filtered');
  $btn.trigger('click');
  expect(selectedKeys).toEqual(['cat:1','cat:2','tag:3']);
  expect($('#gm2-bulk-term-list .gm2-select:checked').length).toBe(2);
  expect($btn.text()).toBe('Un-Select All');
  $('#gm2-term-cat-1 .gm2-select').prop('checked',false).trigger('change');
  expect(selectedKeys).toEqual(['cat:2','tag:3']);
  $btn.trigger('click');
  expect(selectedKeys).toEqual([]);
  expect(window.sessionStorage.getItem(storageFlag)).toBeNull();
  expect($('#gm2-bulk-term-list .gm2-select:checked').length).toBe(0);
  expect($btn.text()).toBe('Select All');
});

test('taxonomy selections persist across pagination and bulk actions', () => {
  const dom = new JSDOM(`
    <div id="gm2-bulk-ai-tax">
      <button id="gm2-bulk-term-select-filtered">Select All</button>
      <button id="gm2-bulk-term-reset-selected">Reset Selected</button>
      <p id="gm2-bulk-term-msg"></p>
      <progress class="gm2-bulk-term-progress-bar" value="0" max="100"></progress>
      <table id="gm2-bulk-term-list">
        <tr id="gm2-term-cat-1"><td><input type="checkbox" class="gm2-select" value="cat:1"></td></tr>
        <tr id="gm2-term-cat-2"><td><input type="checkbox" class="gm2-select" value="cat:2"></td></tr>
      </table>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2BulkAiTax = {
    ajax_url: '/ajax',
    fetch_nonce: 'nonce',
    reset_nonce: 'nonce',
    i18n: { selectAllTerms: 'Select All', unselectAllTerms: 'Un-Select All', resetting:'Resetting', resetDone:'Reset %s' }
  };

  const storageKeys='gm2BulkAiTaxSelectedKeys';
  const storageFlag='gm2BulkAiTaxSelected';
  window.sessionStorage.clear();
  let selectedKeys=[];

  $('#gm2-bulk-term-list').on('change','.gm2-select',function(){
    if(!selectedKeys.length) return;
    const key=String($(this).val());
    if($(this).is(':checked')){
      if($.inArray(key,selectedKeys)===-1){selectedKeys.push(key);}
    }else{
      selectedKeys=$.grep(selectedKeys,function(v){return v!==key;});
    }
    window.sessionStorage.setItem(storageKeys, JSON.stringify(selectedKeys));
  });

  function clearStored(){
    window.sessionStorage.removeItem(storageKeys);
    window.sessionStorage.removeItem(storageFlag);
  }

  $.post = jest.fn(() => ({
    done(cb){ cb({ success:true, data:{ ids:['cat:1','cat:2','cat:3'] } }); return this; },
    fail(){ return this; }
  }));

  $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-select-filtered',function(e){
    e.preventDefault();
    const $btn=$(this);
    if($btn.data('selected')){
      selectedKeys=[];
      $('#gm2-bulk-term-list .gm2-select').prop('checked',false);
      $btn.data('selected',false).text(gm2BulkAiTax.i18n.selectAllTerms);
      clearStored();
      return;
    }
    const data={
      action:'gm2_bulk_ai_tax_fetch_ids',
      taxonomy:'all',
      status:'publish',
      search:'',
      seo_status:'all',
      missing_title:0,
      missing_desc:0,
      _ajax_nonce:gm2BulkAiTax.fetch_nonce
    };
    $btn.prop('disabled',true);
    $.post(gm2BulkAiTax.ajax_url,data).done(function(resp){
      $btn.prop('disabled',false);
      if(resp&&resp.success){
        selectedKeys=(resp.data.ids||[]).map(String);
        $('#gm2-bulk-term-list .gm2-select').each(function(){
          $(this).prop('checked',$.inArray($(this).val(),selectedKeys)!==-1);
        });
        if(selectedKeys.length){
          $btn.data('selected',true).text(gm2BulkAiTax.i18n.unselectAllTerms);
          window.sessionStorage.setItem(storageKeys, JSON.stringify(selectedKeys));
          window.sessionStorage.setItem(storageFlag, '1');
        }else{
          $btn.data('selected',false).text(gm2BulkAiTax.i18n.selectAllTerms);
          clearStored();
        }
      }
    }).fail(function(){ $btn.prop('disabled',false); });
  });

  function getSelectedKeys(){
    if(selectedKeys.length){ return selectedKeys.slice(); }
    const ids=[]; $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ ids.push(String($(this).val())); });
    return ids;
  }

  $.ajax = jest.fn(() => ({
    done(cb){ cb({ success:true, data:{ reset:selectedKeys.length } }); return this; },
    fail(){ return this; },
    always(cb){ cb(); return this; }
  }));

  $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-reset-selected',function(e){
    e.preventDefault();
    var ids=getSelectedKeys();
    if(!ids.length) return;
    var $msg=$('#gm2-bulk-term-msg');
    var total=ids.length, processed=0;
    $('.gm2-bulk-term-progress-bar').attr('max',total).val(0).show();
    $msg.text(gm2BulkAiTax.i18n.resetting);
    function updateProgress(){
      $('.gm2-bulk-term-progress-bar').val(processed);
    }
    updateProgress();
    $.ajax({
      url: gm2BulkAiTax.ajax_url,
      method:'POST',
      data:{action:'gm2_bulk_ai_tax_reset',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAiTax.reset_nonce},
      dataType:'json'
    }).done(function(resp){
      $msg.text(gm2BulkAiTax.i18n.resetDone.replace('%s', resp.data.reset));
    }).always(function(){
      $('.gm2-bulk-term-progress-bar').hide();
    });
  });

  // Select all
  $('#gm2-bulk-term-select-filtered').trigger('click');

  // Simulate pagination: new page rows
  $('#gm2-bulk-term-list').html(`
    <tr id="gm2-term-cat-2"><td><input type="checkbox" class="gm2-select" value="cat:2"></td></tr>
    <tr id="gm2-term-cat-3"><td><input type="checkbox" class="gm2-select" value="cat:3"></td></tr>
    <tr id="gm2-term-cat-4"><td><input type="checkbox" class="gm2-select" value="cat:4"></td></tr>
  `);

  // Reapply selections on new page
  selectedKeys=(JSON.parse(window.sessionStorage.getItem(storageKeys)||'[]')||[]).map(String);
  if(selectedKeys.length){
    $('#gm2-bulk-term-list .gm2-select').each(function(){
      $(this).prop('checked',$.inArray($(this).val(),selectedKeys)!==-1);
    });
    $('#gm2-bulk-term-select-filtered').data('selected',true).text(gm2BulkAiTax.i18n.unselectAllTerms);
  }else{
    $('#gm2-bulk-term-select-filtered').data('selected',false).text(gm2BulkAiTax.i18n.selectAllTerms);
  }

  expect($('#gm2-term-cat-2 .gm2-select').prop('checked')).toBe(true);
  expect($('#gm2-term-cat-3 .gm2-select').prop('checked')).toBe(true);
  expect($('#gm2-term-cat-4 .gm2-select').prop('checked')).toBe(false);

  $('#gm2-bulk-term-reset-selected').trigger('click');
  expect($.ajax).toHaveBeenCalledWith(expect.objectContaining({
    data: expect.objectContaining({ ids: JSON.stringify(['cat:1','cat:2','cat:3']) })
  }));
});

test('taxonomy reset selected uses stored keys', () => {
  const dom = new JSDOM(`
    <div id="gm2-bulk-ai-tax">
      <button id="gm2-bulk-term-reset-selected">Reset Selected</button>
      <p id="gm2-bulk-term-msg"></p>
      <progress class="gm2-bulk-term-progress-bar" value="0" max="100"></progress>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2BulkAiTax = {
    ajax_url: '/ajax',
    reset_nonce: 'nonce',
    i18n: { resetting: 'Resetting', resetDone: 'Reset %s', error: 'Error' }
  };

  let selectedKeys=['cat:1','tag:3'];
  function getSelectedKeys(){
    if(selectedKeys.length){ return selectedKeys.slice(); }
    const ids=[]; $('#gm2-bulk-term-list .gm2-select:checked').each(function(){ ids.push(String($(this).val())); });
    return ids;
  }

  $.ajax = jest.fn(() => ({
    done(cb){ cb({ success:true, data:{ reset:selectedKeys.length } }); return this; },
    fail(){ return this; },
    always(cb){ cb(); return this; }
  }));

  $('#gm2-bulk-ai-tax').on('click','#gm2-bulk-term-reset-selected',function(e){
    e.preventDefault();
    var ids=getSelectedKeys();
    if(!ids.length) return;
    var $msg=$('#gm2-bulk-term-msg');
    var total=ids.length, processed=0;
    $('.gm2-bulk-term-progress-bar').attr('max',total).val(0).show();
    $msg.text(gm2BulkAiTax.i18n.resetting);
    function updateProgress(){
      $('.gm2-bulk-term-progress-bar').val(processed);
    }
    updateProgress();
    $.ajax({
      url: gm2BulkAiTax.ajax_url,
      method:'POST',
      data:{action:'gm2_bulk_ai_tax_reset',ids:JSON.stringify(ids),_ajax_nonce:gm2BulkAiTax.reset_nonce},
      dataType:'json'
    }).done(function(resp){
      $msg.text(gm2BulkAiTax.i18n.resetDone.replace('%s', resp.data.reset));
    }).fail(function(){
      $msg.text(gm2BulkAiTax.i18n.error);
    }).always(function(){
      $('.gm2-bulk-term-progress-bar').hide();
    });
  });

  $('#gm2-bulk-term-reset-selected').trigger('click');
  expect($.ajax).toHaveBeenCalledWith(expect.objectContaining({
    data: expect.objectContaining({ ids: JSON.stringify(['cat:1','tag:3']) })
  }));
});
