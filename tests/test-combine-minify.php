<?php

class CombineMinifyTest extends WP_UnitTestCase {
    private $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $handles = ['a','b','c','remote','ae-seo-combined-js'];
        foreach ($handles as $h) {
            wp_dequeue_script($h);
            wp_deregister_script($h);
        }
        remove_all_filters('print_scripts_array');
        delete_option('ae_seo_ro_enable_combine_js');
        delete_option('ae_seo_ro_combine_js_kb');
        delete_option('ae_seo_ro_combine_exclude_handles');
        delete_option('ae_seo_ro_combine_exclude_domains');
        AE_SEO_Combine_Minify::purge_cache();
        parent::tearDown();
    }

    private function make_script(string $handle, string $filename, ?string $domain = null): void {
        if ($domain) {
            $url = $domain . '/' . $filename;
            wp_register_script($handle, $url, [], null, false);
        } else {
            $path = WP_CONTENT_DIR . '/' . $filename;
            file_put_contents($path, str_repeat('a', 100));
            $this->files[] = $path;
            $url = content_url($filename);
            wp_register_script($handle, $url, [], null, false);
        }
        wp_enqueue_script($handle);
    }

    public function test_combines_only_when_enabled_and_within_limits(): void {
        $this->make_script('b', 'b.js');
        $this->make_script('c', 'c.js');
        $handles = ['b','c'];

        update_option('ae_seo_ro_enable_combine_js', '0');
        $combiner = new AE_SEO_Combine_Minify();
        $combiner->setup();
        $result = apply_filters('print_scripts_array', $handles);
        $this->assertSame($handles, $result, 'Scripts should remain separate when disabled');

        remove_all_filters('print_scripts_array');
        update_option('ae_seo_ro_enable_combine_js', '1');
        update_option('ae_seo_ro_combine_js_kb', 1000);
        $combiner = new AE_SEO_Combine_Minify();
        $combiner->setup();
        $result = apply_filters('print_scripts_array', $handles);
        $this->assertContains('ae-seo-combined-js', $result);
        $this->assertNotContains('b', $result);
        $this->assertNotContains('c', $result);

        remove_all_filters('print_scripts_array');
        update_option('ae_seo_ro_combine_js_kb', 0);
        $combiner = new AE_SEO_Combine_Minify();
        $combiner->setup();
        $result = apply_filters('print_scripts_array', $handles);
        $this->assertNotContains('ae-seo-combined-js', $result);
    }

    public function test_excluded_handles_and_domains_remain_separate(): void {
        $this->make_script('a', 'a.js');
        $this->make_script('b', 'b.js');
        $this->make_script('c', 'c.js');
        $this->make_script('remote', 'remote.js', 'https://cdn.example.com');
        $handles = ['a','b','c','remote'];

        update_option('ae_seo_ro_enable_combine_js', '1');
        update_option('ae_seo_ro_combine_js_kb', 1000);
        update_option('ae_seo_ro_combine_exclude_handles', 'a');
        update_option('ae_seo_ro_combine_exclude_domains', 'cdn.example.com');
        $combiner = new AE_SEO_Combine_Minify();
        $combiner->setup();
        $result = apply_filters('print_scripts_array', $handles);
        $this->assertContains('ae-seo-combined-js', $result);
        $this->assertContains('a', $result);
        $this->assertContains('remote', $result);
        $this->assertNotContains('b', $result);
        $this->assertNotContains('c', $result);
    }

    public function test_purge_action_removes_generated_files(): void {
        $this->make_script('b', 'b.js');
        $this->make_script('c', 'c.js');
        $handles = ['b','c'];
        update_option('ae_seo_ro_enable_combine_js', '1');
        update_option('ae_seo_ro_combine_js_kb', 1000);
        $combiner = new AE_SEO_Combine_Minify();
        $combiner->setup();
        $result = apply_filters('print_scripts_array', $handles);
        $this->assertContains('ae-seo-combined-js', $result);
        global $wp_scripts;
        $src = $wp_scripts->registered['ae-seo-combined-js']->src;
        $upload = wp_upload_dir();
        $path = str_replace($upload['baseurl'], $upload['basedir'], $src);
        $this->assertFileExists($path);
        AE_SEO_Combine_Minify::purge_cache();
        $this->assertFileDoesNotExist($path);
    }
}
