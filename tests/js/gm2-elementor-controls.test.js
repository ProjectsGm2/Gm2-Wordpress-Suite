const { JSDOM } = require('jsdom');
const jqueryFactory = require('jquery');

describe('gm2-elementor-controls script', () => {
  beforeEach(() => {
    jest.resetModules();
  });

  afterEach(() => {
    delete global.window;
    delete global.document;
    delete global.jQuery;
    delete global.$;
    delete global.MutationObserver;
  });

  test('populates a select with AJAX data', async () => {
    const dom = new JSDOM('<div class="elementor-controls-stack"></div>', { url: 'http://localhost' });
    const $ = jqueryFactory(dom.window);
    Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
    global.MutationObserver = dom.window.MutationObserver;

    window.gm2ElementorControls = { ajaxUrl: '/ajax', nonce: 'abc' };

    const postMock = jest.fn(() =>
      Promise.resolve({ success: true, data: [{ value: 'book', label: 'Book' }] })
    );
    $.post = postMock;

    require('../../public/js/gm2-elementor-controls.js');

    const control = dom.window.document.createElement('div');
    control.className = 'elementor-control';
    const select = dom.window.document.createElement('select');
    select.className = 'gm2-ajax-select';
    select.setAttribute('data-action', 'gm2_elementor_post_types');
    select.setAttribute('data-setting', 'gm2_cp_post_type');
    select.setAttribute('data-selected', '[]');
    select.setAttribute('multiple', 'multiple');
    control.appendChild(select);
    dom.window.document.querySelector('.elementor-controls-stack').appendChild(control);

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(postMock).toHaveBeenCalledWith('/ajax', expect.objectContaining({ action: 'gm2_elementor_post_types', nonce: 'abc' }));
    const options = dom.window.document.querySelectorAll('option');
    expect(options).toHaveLength(1);
    expect(options[0].value).toBe('book');
  });

  test('terms control requests terms for selected taxonomy', async () => {
    const dom = new JSDOM('<div class="elementor-controls-stack"></div>', { url: 'http://localhost' });
    const $ = jqueryFactory(dom.window);
    Object.assign(global, { window: dom.window, document: dom.window.document, jQuery: $, $ });
    global.MutationObserver = dom.window.MutationObserver;

    window.gm2ElementorControls = { ajaxUrl: '/ajax', nonce: 'nonce' };

    const responses = [
      Promise.resolve({ success: true, data: [{ value: 'genre', label: 'Genre' }] }),
      Promise.resolve({ success: true, data: [{ value: '12', label: 'Mystery' }] }),
      Promise.resolve({ success: true, data: [{ value: '12', label: 'Mystery' }] }),
    ];
    const postMock = jest.fn(() => responses.shift() || Promise.resolve({ success: true, data: [] }));
    $.post = postMock;

    require('../../public/js/gm2-elementor-controls.js');

    const controlsStack = dom.window.document.querySelector('.elementor-controls-stack');
    const taxonomyWrapper = dom.window.document.createElement('div');
    taxonomyWrapper.className = 'elementor-control';
    const taxonomySelect = dom.window.document.createElement('select');
    taxonomySelect.className = 'gm2-ajax-select';
    taxonomySelect.setAttribute('data-action', 'gm2_elementor_taxonomy_terms');
    taxonomySelect.setAttribute('data-setting', 'gm2_cp_taxonomy');
    taxonomySelect.setAttribute('data-mode', 'taxonomy');
    taxonomySelect.setAttribute('data-selected', '"genre"');
    taxonomySelect.value = 'genre';
    taxonomyWrapper.appendChild(taxonomySelect);
    controlsStack.appendChild(taxonomyWrapper);

    const termsWrapper = dom.window.document.createElement('div');
    termsWrapper.className = 'elementor-control';
    const termsSelect = dom.window.document.createElement('select');
    termsSelect.className = 'gm2-ajax-select';
    termsSelect.setAttribute('data-action', 'gm2_elementor_taxonomy_terms');
    termsSelect.setAttribute('data-setting', 'gm2_cp_terms');
    termsSelect.setAttribute('data-mode', 'terms');
    termsSelect.setAttribute('data-taxonomy-control', 'gm2_cp_taxonomy');
    termsSelect.setAttribute('data-selected', '[]');
    termsWrapper.appendChild(termsSelect);
    controlsStack.appendChild(termsWrapper);

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(postMock).toHaveBeenCalled();
    const termCalls = postMock.mock.calls.filter((call) => call[1] && call[1].mode === 'terms');
    expect(termCalls.length).toBeGreaterThan(0);
    expect(termCalls[termCalls.length - 1][1].taxonomy).toBe('genre');

    const termOptions = dom.window.document.querySelectorAll('[data-setting="gm2_cp_terms"] option');
    expect(termOptions.length).toBeGreaterThan(0);
  });
});
