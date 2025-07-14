<?php
use PHPUnit\Framework\TestCase;

class ContentAnalysisTest extends TestCase {
    public function test_analyze_content_js() {
        $content = '<p>Hello world.</p> Hello again!';
        $js = <<<'JS'
const fs = require('fs');
const filePath = process.argv[3];
let code = fs.readFileSync(filePath, 'utf8');
const start = code.indexOf('function countSyllables');
const end = code.indexOf('function analyzeFocusKeywords');
let snippet = code.substring(start, end);
function jQuery(){
    return {
        _html:'',
        html:function(c){this._html=c;return this;},
        text:function(){return this._html.replace(/<[^>]*>/g,'');},
        find:function(){return {each:function(){}};},
        attr:function(){return '';}
    };
}
const vm = require('vm');
const context = {jQuery:jQuery, $: jQuery, location:{hostname:'example.com'}};
vm.createContext(context);
vm.runInContext(snippet + '; exports={analyzeContent};', context);
const result = context.exports.analyzeContent(process.argv[2]);
console.log(JSON.stringify({topWord: result.topWord, readability: result.readability, internal: result.internalLinks, external: result.externalLinks}));
JS;
        $tmp = tempnam(sys_get_temp_dir(), 'js');
        file_put_contents($tmp, $js);
        $filePath = realpath(__DIR__ . '/../admin/js/gm2-content-analysis.js');
        $output = shell_exec('node ' . escapeshellarg($tmp) . ' ' . escapeshellarg($content) . ' ' . escapeshellarg($filePath));
        unlink($tmp);
        $data = json_decode(trim($output), true);
        $this->assertSame('hello', $data['topWord']);
        $this->assertGreaterThan(0, $data['readability']);
        $this->assertSame(0, $data['internal']);
        $this->assertSame(0, $data['external']);
    }
}

class ContentRulesNewTest extends WP_Ajax_UnitTestCase {
    private function run_check($content, $description = 'desc keyword', $focus = 'keyword') {
        $this->_setRole('administrator');
        $_POST['title'] = str_repeat('T', 35);
        $_POST['description'] = $description;
        $_POST['focus'] = $focus;
        $_POST['content'] = $content;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_check_rules'); } catch (WPAjaxDieContinueException $e) {}
        return json_decode($this->_last_response, true);
    }

    public function test_new_rules_pass() {
        $content = '<p>keyword first</p><h1>Heading</h1>' .
            '<img src="img.jpg" alt="keyword image" />' .
            '<a href="' . home_url('/') . '">int</a>' .
            '<a href="https://example.com">ext</a> ' . str_repeat('word ', 300);
        $resp = $this->run_check($content, 'meta with keyword', 'keyword');
        $data = $resp['data'];
        $this->assertTrue($data['focus-keyword-appears-in-first-paragraph']);
        $this->assertTrue($data['only-one-h1-tag-present']);
        $this->assertTrue($data['at-least-one-internal-link']);
        $this->assertTrue($data['at-least-one-external-link']);
        $this->assertTrue($data['focus-keyword-included-in-meta-description']);
        $this->assertTrue($data['image-alt-text-contains-focus-keyword']);
    }

    public function test_new_rules_fail() {
        $content = '<p>no key</p><img src="img.jpg" alt="no match" />' .
            '<h1>h1</h1><h1>h2</h1>' . str_repeat('word ', 10);
        $resp = $this->run_check($content, 'no match', 'keyword');
        $data = $resp['data'];
        $this->assertFalse($data['focus-keyword-appears-in-first-paragraph']);
        $this->assertFalse($data['only-one-h1-tag-present']);
        $this->assertFalse($data['at-least-one-internal-link']);
        $this->assertFalse($data['at-least-one-external-link']);
        $this->assertFalse($data['focus-keyword-included-in-meta-description']);
        $this->assertFalse($data['image-alt-text-contains-focus-keyword']);
    }

    public function test_alt_text_rule_passes() {
        $content = '<p>text</p><img src="img.jpg" alt="keyword here" />';
        $resp = $this->run_check($content, 'desc keyword', 'keyword');
        $data = $resp['data'];
        $this->assertTrue($data['image-alt-text-contains-focus-keyword']);
    }

    public function test_alt_text_rule_fails() {
        $content = '<p>text</p><img src="img.jpg" alt="something else" />';
        $resp = $this->run_check($content, 'desc keyword', 'keyword');
        $data = $resp['data'];
        $this->assertFalse($data['image-alt-text-contains-focus-keyword']);
    }

    public function test_min_link_rules_pass() {
        update_option('gm2_content_rules', ['post_post' => ['content' => "Minimum X internal links\nMinimum X external links"]]);
        update_option('gm2_min_internal_links', 2);
        update_option('gm2_min_external_links', 1);
        $content = '<a href="' . home_url('/a') . '">1</a>' .
            '<a href="' . home_url('/b') . '">2</a>' .
            '<a href="https://ext.com">e</a>';
        $resp = $this->run_check($content);
        $data = $resp['data'];
        $this->assertTrue($data['minimum-x-internal-links']);
        $this->assertTrue($data['minimum-x-external-links']);
    }

    public function test_min_link_rules_fail() {
        update_option('gm2_content_rules', ['post_post' => ['content' => "Minimum X internal links\nMinimum X external links"]]);
        update_option('gm2_min_internal_links', 2);
        update_option('gm2_min_external_links', 1);
        $content = '<a href="' . home_url('/a') . '">1</a>';
        $resp = $this->run_check($content);
        $data = $resp['data'];
        $this->assertFalse($data['minimum-x-internal-links']);
        $this->assertFalse($data['minimum-x-external-links']);
    }
}

