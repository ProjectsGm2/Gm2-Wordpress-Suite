<?php

use Gm2\Gm2_Script_Attributes;

class ScriptAttributesTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('gm2-foo');
        wp_deregister_script('gm2-foo');
        wp_dequeue_script('gm2-bar');
        wp_deregister_script('gm2-bar');
        wp_dequeue_script('gm2-baz');
        wp_deregister_script('gm2-baz');
        wp_scripts()->done = [];
        delete_option('gm2_script_attributes');
        parent::tearDown();
    }

    private function get_output(string $handle): string {
        ob_start();
        wp_print_scripts($handle);
        return ob_get_clean();
    }

    private function extract_tag(string $html, string $handle): string {
        preg_match("/\<script[^>]*id='" . preg_quote($handle, '/') . "-js'[^>]*>\<\/script>/", $html, $m);
        return $m[0] ?? '';
    }

    public function test_unknown_handle_defaults_to_defer() {
        update_option('gm2_script_attributes', []);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('defer', $fooTag);
    }

    public function test_async_attribute_applied_with_deferred_dependencies() {
        update_option('gm2_script_attributes', [
            'gm2-foo' => 'async',
            'gm2-bar' => 'defer',
        ]);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('defer', $barTag);
        $this->assertStringContainsString('async', $fooTag);
    }

    public function test_blocking_handle_removes_attribute() {
        update_option('gm2_script_attributes', [ 'gm2-foo' => 'blocking' ]);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_blocking_dependency_removes_attribute() {
        update_option('gm2_script_attributes', [
            'gm2-foo' => 'async',
            'gm2-bar' => 'blocking',
        ]);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $barTag);
        $this->assertStringNotContainsString('defer', $barTag);
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_non_deferred_dependency_removes_attribute() {
        update_option('gm2_script_attributes', [
            'gm2-foo' => 'defer',
            'gm2-bar' => 'async',
        ]);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('async', $barTag);
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_blocking_dependency_propagates_through_chain() {
        update_option('gm2_script_attributes', [
            'gm2-foo' => 'async',
            'gm2-bar' => 'defer',
            'gm2-baz' => 'blocking',
        ]);
        wp_register_script('gm2-baz', 'https://example.com/baz.js', [], null);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', ['gm2-baz'], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $bazTag = $this->extract_tag($html, 'gm2-baz');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $bazTag);
        $this->assertStringNotContainsString('defer', $bazTag);
        $this->assertStringNotContainsString('async', $barTag);
        $this->assertStringNotContainsString('defer', $barTag);
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_dependency_without_attribute_omits_async() {
        update_option('gm2_script_attributes', [ 'gm2-foo' => 'async' ]);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $barTag);
        $this->assertStringNotContainsString('defer', $barTag);
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_markup_is_valid_and_no_document_write() {
        update_option('gm2_script_attributes', [ 'gm2-foo' => 'defer' ]);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html = $this->get_output('gm2-foo');
        $this->assertStringNotContainsString('document.write', $html);
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<html><head>' . $html . '</head></html>');
        $this->assertNotNull($dom->getElementById('gm2-foo-js'));
    }
}
