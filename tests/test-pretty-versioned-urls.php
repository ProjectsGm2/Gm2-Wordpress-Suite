<?php
class PrettyVersionedUrlsTest extends WP_UnitTestCase {
    public function test_pretty_url_applied_when_enabled() {
        update_option('gm2_pretty_versioned_urls', '1');
        $src = home_url('/wp-includes/js/jquery/jquery.js?ver=1');
        $result = \Gm2\Versioning_MTime::update_src($src, 'jquery');
        $this->assertMatchesRegularExpression('/jquery\.v\d+\.js$/', $result);
    }

    public function test_query_arg_used_when_disabled() {
        update_option('gm2_pretty_versioned_urls', '0');
        $src = home_url('/wp-includes/js/jquery/jquery.js?ver=1');
        $result = \Gm2\Versioning_MTime::update_src($src, 'jquery');
        $this->assertStringContainsString('ver=', $result);
        $this->assertStringNotContainsString('.v', $result);
    }
}
