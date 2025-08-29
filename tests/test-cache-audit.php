<?php
use Gm2\Gm2_Cache_Audit;

class CacheAuditMock extends Gm2_Cache_Audit {
    public static $mock_results = [];
    public static function scan() {
        return self::$mock_results;
    }
}

class CacheAuditTest extends WP_UnitTestCase {
    public function test_save_and_get_results() {
        $results = [
            'scanned_at' => '2024-01-01 00:00:00',
            'handles' => ['scripts' => [], 'styles' => []],
            'assets' => [],
        ];
        Gm2_Cache_Audit::clear_results();
        Gm2_Cache_Audit::save_results($results);
        $this->assertSame($results, Gm2_Cache_Audit::get_results());
    }

    public function test_clear_results() {
        $results = [
            'scanned_at' => '2024-01-01 00:00:00',
            'handles' => ['scripts' => [], 'styles' => []],
            'assets' => [],
        ];
        Gm2_Cache_Audit::save_results($results);
        Gm2_Cache_Audit::clear_results();
        $this->assertSame([], Gm2_Cache_Audit::get_results());
    }

    public function test_rescan_updates_results() {
        $mock_results = [
            'scanned_at' => '2024-02-02 12:00:00',
            'handles' => ['scripts' => [], 'styles' => []],
            'assets' => [],
        ];
        CacheAuditMock::$mock_results = $mock_results;
        CacheAuditMock::rescan();
        $this->assertSame($mock_results, Gm2_Cache_Audit::get_results());
    }
}
