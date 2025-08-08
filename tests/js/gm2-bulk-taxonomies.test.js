const assert = require('assert');
const {JSDOM} = require('jsdom');
const init = require('../../admin/js/gm2-bulk-taxonomies.js');

(async () => {
    const dom = new JSDOM(`<!DOCTYPE html><form id="posts-filter">
        <button id="gm2-tax-select-all">Select All</button>
        <input type="hidden" id="gm2-tax-selected-ids" />
        <input type="checkbox" id="cb-select-1" />
        <input type="checkbox" id="cb-select-2" />
    </form>`, {url: 'http://example.com/wp-admin/edit-tags.php?taxonomy=category'});

    global.window = dom.window;
    global.document = dom.window.document;
    global.gm2BulkTaxData = {ajax_url: '/ajax', nonce: 'nonce'};
    global.fetch = () => Promise.resolve({json: () => Promise.resolve({success: true, data: [1,2,3]})});

    init();
    const button = document.getElementById('gm2-tax-select-all');
    const form = document.getElementById('posts-filter');

    // First select all
    button.dispatchEvent(new window.Event('click'));
    await new Promise(resolve => setTimeout(resolve, 0));
    const cb1 = document.getElementById('cb-select-1');
    const cb2 = document.getElementById('cb-select-2');
    const hidden = document.getElementById('gm2-tax-selected-ids');
    assert.strictEqual(cb1.checked && cb2.checked, true, 'checkboxes checked');
    assert.strictEqual(hidden.value, '1,2,3', 'hidden has ids');
    assert.strictEqual(button.textContent, 'Un-Select All', 'button toggled');

    // Toggle unselect
    button.dispatchEvent(new window.Event('click'));
    assert.strictEqual(cb1.checked || cb2.checked, false, 'checkboxes cleared');
    assert.strictEqual(hidden.value, '', 'hidden cleared');
    assert.strictEqual(button.textContent, 'Select All', 'button reset');

    // Select again and submit to verify hidden inputs
    button.dispatchEvent(new window.Event('click'));
    await new Promise(resolve => setTimeout(resolve, 0));
    form.dispatchEvent(new window.Event('submit'));
    const extra = [...form.querySelectorAll('input[name="delete_tags[]"]')].find(el => el.value === '3');
    assert.ok(extra, 'hidden input for missing id added');
    console.log('Taxonomy JS test passed');
})();
