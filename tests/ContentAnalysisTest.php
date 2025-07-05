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
function jQuery(){return {_html:'',html:function(c){this._html=c;return this;},text:function(){return this._html.replace(/<[^>]*>/g,'');}};}
const vm = require('vm');
const context = {jQuery:jQuery, $: jQuery};
vm.createContext(context);
vm.runInContext(snippet + '; exports={analyzeContent};', context);
const result = context.exports.analyzeContent(process.argv[2]);
console.log(JSON.stringify({topWord: result.topWord, readability: result.readability}));
JS;
        $tmp = tempnam(sys_get_temp_dir(), 'js');
        file_put_contents($tmp, $js);
        $filePath = realpath(__DIR__ . '/../admin/js/gm2-content-analysis.js');
        $output = shell_exec('node ' . escapeshellarg($tmp) . ' ' . escapeshellarg($content) . ' ' . escapeshellarg($filePath));
        unlink($tmp);
        $data = json_decode(trim($output), true);
        $this->assertSame('hello', $data['topWord']);
        $this->assertGreaterThan(0, $data['readability']);
    }
}
