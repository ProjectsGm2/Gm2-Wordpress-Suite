<?php
use Gm2\Perf\Settings;

class PerfFlagsTest extends WP_UnitTestCase {
    public function test_defaults_and_filter_override() {
        Settings::register();
        $this->assertSame('0', get_option('ae_perf_passive_patch'));
        $this->assertFalse( (bool) apply_filters('ae/perf/flag', get_option('ae_perf_passive_patch'), 'passivePatch') );
        add_filter('ae/perf/flag', function($on, $feature) {
            return $feature === 'passivePatch' ? true : $on;
        }, 10, 2);
        $this->assertTrue( (bool) apply_filters('ae/perf/flag', get_option('ae_perf_passive_patch'), 'passivePatch') );
        remove_all_filters('ae/perf/flag');
    }
}
