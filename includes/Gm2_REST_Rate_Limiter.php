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
        $ip = '';
        if (\function_exists('rest_get_ip_address')) {
            $ip = (string) \rest_get_ip_address();
        }
        if ($ip === '') {
            $raw_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!\is_string($raw_ip)) {
                $raw_ip = ''; // Ensure the value is always a string.
            }
            $ip = \sanitize_text_field(\wp_unslash($raw_ip));
        }
        if ($ip === '') {
            $ip = 'unknown';
        }
        $key = 'gm2_rl_' . md5($ip);
        $now = time();
        $data = get_transient($key);
        $window_remaining = self::WINDOW;
        if (!\is_array($data) || !isset($data['count'], $data['start'])) {
            $data = [
                'count' => 0,
                'start' => $now,
            ];
        } else {
            $data['count'] = (int) $data['count'];
            $data['start'] = (int) $data['start'];
            $elapsed = max(0, $now - $data['start']);
            if ($elapsed >= self::WINDOW) {
                $data['count'] = 0;
                $data['start'] = $now;
                $window_remaining = self::WINDOW;
            } else {
                $window_remaining = self::WINDOW - $elapsed;
            }
        }
        if ($data['count'] >= self::LIMIT) {
            return new \WP_Error('gm2_rate_limited', __('Too many requests.', 'gm2-wordpress-suite'), [ 'status' => 429 ]);
        }
        $data['count']++;
        set_transient($key, $data, max(1, (int) $window_remaining));
        return $result;
    }
}
