const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test('displays server error message and re-enables button', async () => {
  const dom = new JSDOM(`
    <textarea id="gm2_context_ai_prompt"></textarea>
    <button class="gm2-build-ai-prompt">Build</button>
    <input id="gm2_context_business_model" value="m" />
    <input id="gm2_context_industry_category" value="i" />
    <input id="gm2_context_target_audience" value="a" />
    <input id="gm2_context_unique_selling_points" value="u" />
    <input id="gm2_context_revenue_streams" value="r" />
    <input id="gm2_context_primary_goal" value="p" />
    <input id="gm2_context_brand_voice" value="b" />
    <input id="gm2_context_competitors" value="c" />
    <input id="gm2_context_core_offerings" value="co" />
    <input id="gm2_context_geographic_focus" value="g" />
    <input id="gm2_context_keyword_data" value="k" />
    <input id="gm2_context_competitor_landscape" value="cl" />
    <input id="gm2_context_success_metrics" value="s" />
    <input id="gm2_context_buyer_personas" value="bp" />
    <input id="gm2_context_project_description" value="pd" />
    <input id="gm2_context_custom_prompts" value="cp" />
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2ChatGPT = { ajax_url: '/fake', nonce: 'n', error: 'Error' };
  dom.window.gm2ChatGPT = global.gm2ChatGPT;

  $.post = jest.fn(() => $.Deferred().resolve({ success: false, data: 'bad input' }));

  jest.resetModules();
  require('../../admin/js/gm2-context-prompt.js');

  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));
  $('.gm2-build-ai-prompt').trigger('click');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));
  expect($('#gm2_context_ai_prompt').val()).toBe('bad input');
  expect($('.gm2-build-ai-prompt').prop('disabled')).toBe(false);
});

test('shows message when response is 0', async () => {
  const dom = new JSDOM(`
    <textarea id="gm2_context_ai_prompt"></textarea>
    <button class="gm2-build-ai-prompt">Build</button>
    <input id="gm2_context_business_model" />
    <input id="gm2_context_industry_category" />
    <input id="gm2_context_target_audience" />
    <input id="gm2_context_unique_selling_points" />
    <input id="gm2_context_revenue_streams" />
    <input id="gm2_context_primary_goal" />
    <input id="gm2_context_brand_voice" />
    <input id="gm2_context_competitors" />
    <input id="gm2_context_core_offerings" />
    <input id="gm2_context_geographic_focus" />
    <input id="gm2_context_keyword_data" />
    <input id="gm2_context_competitor_landscape" />
    <input id="gm2_context_success_metrics" />
    <input id="gm2_context_buyer_personas" />
    <input id="gm2_context_project_description" />
    <input id="gm2_context_custom_prompts" />
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  global.gm2ChatGPT = { ajax_url: '/fake', nonce: 'n', error: 'Error' };
  dom.window.gm2ChatGPT = global.gm2ChatGPT;

  $.post = jest.fn(() => $.Deferred().resolve('0'));

  jest.resetModules();
  require('../../admin/js/gm2-context-prompt.js');

  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));
  $('.gm2-build-ai-prompt').trigger('click');
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));
  expect($('#gm2_context_ai_prompt').val()).toBe('ChatGPT disabled or endpoint missing');
});
