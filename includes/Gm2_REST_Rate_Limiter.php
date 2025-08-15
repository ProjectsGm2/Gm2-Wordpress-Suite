<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_REST_Rate_Limiter {
    const LIMIT  = 100; // requests
    const WINDOW = MINUTE_IN_SECONDS; // per minute

    public static function init(): void {
        add_filter('rest_pre_dispatch', [ __CLASS__, 'maybe_limit' ], 10, 3);
    }

    public static function maybe_limit($result, $server, $request) {
        $route = $request->get_route();
        if (strpos($route, '/gm2/v1') !== 0) {
            return $result;
        }
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'gm2_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= self::LIMIT) {
            return new \WP_Error('gm2_rate_limited', __('Too many requests.', 'gm2-wordpress-suite'), [ 'status' => 429 ]);
        }
        set_transient($key, $count + 1, self::WINDOW);
        return $result;
    }
}
