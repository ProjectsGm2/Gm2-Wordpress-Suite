const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test.skip('clicking option activates it', async () => {
  const dom = new JSDOM(`
    <div class="gm2-qd-options">
      <button class="gm2-qd-option" data-qty="1">1</button>
      <button class="gm2-qd-option" data-qty="2">2</button>
    </div>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });

  jest.resetModules();
  require('../../public/js/gm2-qd-widget.js');
  await new Promise(r => setTimeout(r, 0));

  const first = $('.gm2-qd-option').eq(0);
  const second = $('.gm2-qd-option').eq(1);

  first.triggerHandler('click');
  expect(first.hasClass('active')).toBe(true);
  expect(second.hasClass('active')).toBe(false);

  second.triggerHandler('click');
  expect(second.hasClass('active')).toBe(true);
  expect(first.hasClass('active')).toBe(false);
});

test('currency icon font size changes with active class', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-currency-icon{font-size:10px}
      .gm2-qd-option.active .gm2-qd-currency-icon{font-size:20px}
    </style>
    <div class="gm2-qd-option">
      <span class="gm2-qd-currency-icon"></span>
    </div>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);

  expect($('.gm2-qd-currency-icon').css('font-size')).toBe('10px');
  $('.gm2-qd-option').addClass('active');
  expect($('.gm2-qd-option.active .gm2-qd-currency-icon').css('font-size')).toBe('20px');
});

test('option background colors apply for normal and active states', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-option{background-color:red}
      .gm2-qd-option:hover{background-color:green}
      .gm2-qd-option.active{background-color:blue}
    </style>
    <button class="gm2-qd-option">Option</button>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);

  expect($('.gm2-qd-option').css('background-color')).toBe('rgb(255, 0, 0)');
  $('.gm2-qd-option').addClass('active');
  expect($('.gm2-qd-option.active').css('background-color')).toBe('rgb(0, 0, 255)');
});
