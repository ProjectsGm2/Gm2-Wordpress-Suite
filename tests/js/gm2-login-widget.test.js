const { JSDOM } = require('jsdom');
const jquery = require('jquery');

test.skip('placeholders and remember toggle', () => {
  const dom = new JSDOM(`
    <div class="gm2-login-widget" data-show-remember="no" data-login-placeholder="User" data-pass-placeholder="Pass">
      <p class="gm2-login-form"><input type="text" name="username"></p>
      <p class="gm2-register-form"><input type="password" name="password"></p>
      <p class="remember"><input type="checkbox" name="rememberme"></p>
    </div>
  `, { url: 'http://localhost' });
  const $ = jquery(dom.window);
  Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
  require('../../public/js/gm2-login-widget.js');
  expect($('input[name="username"]').attr('placeholder')).toBe('User');
  expect($('input[type="password"]').attr('placeholder')).toBe('Pass');
  expect($('input[name="rememberme"]').closest('p').css('display')).toBe('none');
});
