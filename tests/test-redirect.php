<?php
class RedirectTest extends WP_UnitTestCase {
    public function test_maybe_apply_redirects_when_source_exists() {
        update_option('gm2_redirects', [
            [
                'source' => '/old-page',
                'target' => '/new-page',
                'type'   => 301,
            ]
        ]);
        $seo = new Gm2_SEO_Public();
        $_SERVER['REQUEST_URI'] = '/old-page';
        add_filter('wp_redirect', function($location, $status) {
            $this->assertSame('/new-page', $location);
            $this->assertSame(301, $status);
            return $location;
        }, 10, 2);
        try {
            $seo->maybe_apply_redirects();
        } catch (Exception $e) {
            // ignore
        }
        remove_all_filters('wp_redirect');
    }
}

