<?php

use Gm2\Gm2_REST_Rate_Limiter;

class RestRateLimiterTest extends WP_UnitTestCase {

    private function prepare_request_for_ip(string $ip): array {
        $_SERVER['REMOTE_ADDR'] = $ip;
        $key = 'gm2_rl_' . md5($ip);
        delete_transient($key);
        $request = new WP_REST_Request('GET', '/gm2/v1/test');

        return [ $request, $key ];
    }

    protected function tearDown(): void {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function test_count_increments_without_resetting_window(): void {
        [ $request, $key ] = $this->prepare_request_for_ip('198.51.100.10');

        $this->assertNull(Gm2_REST_Rate_Limiter::maybe_limit(null, null, $request));
        $first_data = get_transient($key);
        $this->assertIsArray($first_data);
        $this->assertSame(1, $first_data['count']);
        $initial_start = $first_data['start'];

        $this->assertNull(Gm2_REST_Rate_Limiter::maybe_limit(null, null, $request));
        $second_data = get_transient($key);
        $this->assertIsArray($second_data);
        $this->assertSame(2, $second_data['count']);
        $this->assertSame($initial_start, $second_data['start']);

        delete_transient($key);
    }

    public function test_counts_reset_after_window(): void {
        [ $request, $key ] = $this->prepare_request_for_ip('198.51.100.11');

        $this->assertNull(Gm2_REST_Rate_Limiter::maybe_limit(null, null, $request));
        $data = get_transient($key);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('start', $data);

        $expired_start = $data['start'] - Gm2_REST_Rate_Limiter::WINDOW - 1;
        set_transient($key, [
            'count' => Gm2_REST_Rate_Limiter::LIMIT,
            'start' => $expired_start,
        ], 5);

        $this->assertNull(Gm2_REST_Rate_Limiter::maybe_limit(null, null, $request));
        $after_reset = get_transient($key);
        $this->assertIsArray($after_reset);
        $this->assertSame(1, $after_reset['count']);
        $this->assertGreaterThan($expired_start, $after_reset['start']);

        delete_transient($key);
    }
}

