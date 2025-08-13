<?php
use Gm2\Gm2_Abandoned_Carts;
use Gm2\Gm2_Analytics_Admin;

class IpLocationTest extends WP_UnitTestCase {
    public function test_abandoned_carts_handles_non_200() {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $filter = function($pre, $args, $url) {
            if (false !== strpos($url, 'ipapi.co')) {
                return [ 'response' => ['code' => 500], 'body' => '' ];
            }
            return $pre;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $result = Gm2_Abandoned_Carts::get_ip_and_location();
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('Unknown', $result['location']);
    }

    public function test_abandoned_carts_handles_timeout() {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $filter = function($pre, $args, $url) {
            if (false !== strpos($url, 'ipapi.co')) {
                return new WP_Error('http_request_timeout', 'timeout');
            }
            return $pre;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $result = Gm2_Abandoned_Carts::get_ip_and_location();
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('Unknown', $result['location']);
    }

    public function test_analytics_handles_non_200() {
        $admin = new Gm2_Analytics_Admin();
        $ip = '5.6.7.8';
        delete_transient('gm2_geo_' . md5($ip));
        $filter = function($pre, $args, $url) {
            if (false !== strpos($url, 'ipapi.co')) {
                return [ 'response' => ['code' => 404], 'body' => '' ];
            }
            return $pre;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $ref = new ReflectionMethod(Gm2_Analytics_Admin::class, 'get_ip_country');
        $ref->setAccessible(true);
        $country = $ref->invoke($admin, $ip);
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('Unknown', $country);
    }

    public function test_analytics_handles_timeout() {
        $admin = new Gm2_Analytics_Admin();
        $ip = '8.7.6.5';
        delete_transient('gm2_geo_' . md5($ip));
        $filter = function($pre, $args, $url) {
            if (false !== strpos($url, 'ipapi.co')) {
                return new WP_Error('http_request_timeout', 'timeout');
            }
            return $pre;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $ref = new ReflectionMethod(Gm2_Analytics_Admin::class, 'get_ip_country');
        $ref->setAccessible(true);
        $country = $ref->invoke($admin, $ip);
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('Unknown', $country);
    }
}
