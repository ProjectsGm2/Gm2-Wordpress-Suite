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

    public function test_visit_old_url_triggers_redirect() {
        update_option('gm2_redirects', [
            [
                'source' => '/legacy',
                'target' => '/new-destination',
                'type'   => 302,
            ]
        ]);

        $seo = new Gm2_SEO_Public();
        $seo->run();

        $this->assertSame(0, has_action('template_redirect', [$seo, 'maybe_apply_redirects']));

        $captured = [];
        add_filter('wp_redirect', function($location, $status) use (&$captured) {
            $captured = [$location, $status];
            throw new Exception('redirect');
        }, 10, 2);

        $this->go_to(home_url('/legacy'));
        try {
            do_action('template_redirect');
        } catch (Exception $e) {
            // swallow redirect exception
        }
        remove_all_filters('wp_redirect');

        $this->assertSame('/new-destination', $captured[0]);
        $this->assertSame(302, $captured[1]);
    }
}

