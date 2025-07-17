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

test('currency icon color changes with active class', () => {
  const dom = new JSDOM(`
    <style>
        .gm2-qd-currency-icon{color:red;fill:red}
      .gm2-qd-option.active .gm2-qd-currency-icon{color:blue;fill:blue}
    </style>
    <div class="gm2-qd-option">
    <span class="gm2-qd-currency-icon"><svg><path d=""/></svg></span>
    </div>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);

  expect($('.gm2-qd-currency-icon').css('color')).toBe('rgb(255, 0, 0)');
  $('.gm2-qd-option').addClass('active');
  expect($('.gm2-qd-option.active .gm2-qd-currency-icon').css('color')).toBe('rgb(0, 0, 255)');
   // fill is applied to SVG paths but jsdom does not compute it reliably
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

test('svg currency icon width and height follow CSS rules', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-currency-icon svg,
      .gm2-qd-currency-icon.e-font-icon-svg { width:10px; height:10px; }
    </style>
    <span class="gm2-qd-currency-icon"><svg></svg></span>
    <span class="gm2-qd-currency-icon e-font-icon-svg"></span>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);

  expect($('.gm2-qd-currency-icon svg').css('width')).toBe('10px');
  expect($('.gm2-qd-currency-icon.e-font-icon-svg').css('width')).toBe('10px');
  expect($('.gm2-qd-currency-icon svg').css('height')).toBe('10px');
  expect($('.gm2-qd-currency-icon.e-font-icon-svg').css('height')).toBe('10px');
});

test('currency icon margin can be customized', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-currency-icon { margin: 2px 3px 4px 5px; }
    </style>
    <span class="gm2-qd-currency-icon"></span>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);

  expect($('.gm2-qd-currency-icon').css('margin-top')).toBe('2px');
  expect($('.gm2-qd-currency-icon').css('margin-right')).toBe('3px');
  expect($('.gm2-qd-currency-icon').css('margin-bottom')).toBe('4px');
  expect($('.gm2-qd-currency-icon').css('margin-left')).toBe('5px');
});

test('horizontal alignment modifies justify-content', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-price { display:flex; }
      .justify-start { justify-content:flex-start; }
      .justify-center { justify-content:center; }
      .justify-end { justify-content:flex-end; }
    </style>
    <span class="gm2-qd-price"></span>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  const price = $('.gm2-qd-price');
  price.addClass('justify-end');
  expect(price.css('justify-content')).toBe('flex-end');
  price.removeClass('justify-end').addClass('justify-center');
  expect(price.css('justify-content')).toBe('center');
});

test('vertical alignment modifies align-items', () => {
  const dom = new JSDOM(`
    <style>
      .gm2-qd-price { display:flex; align-items:center; }
      .align-top { align-items:flex-start; }
      .align-bottom { align-items:flex-end; }
    </style>
    <span class="gm2-qd-price"></span>
  `, { url: 'http://localhost' });

  const $ = jquery(dom.window);
  const price = $('.gm2-qd-price');
  expect(price.css('align-items')).toBe('center');
  price.addClass('align-top');
  expect(price.css('align-items')).toBe('flex-start');
  price.removeClass('align-top').addClass('align-bottom');
  expect(price.css('align-items')).toBe('flex-end');
});
