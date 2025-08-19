<?php
use WP_UnitTestCase;
use Gm2\Gm2_Loader;
use Gm2\Gm2_Abandoned_Carts;

class AbandonedCartsCronIntegrationTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        Gm2_Abandoned_Carts::clear_scheduled_event();
        parent::tearDown();
    }

    public function test_event_scheduled_when_module_enabled() {
        update_option('gm2_enable_abandoned_carts', '1');
        Gm2_Abandoned_Carts::clear_scheduled_event();
        $this->assertFalse(wp_next_scheduled('gm2_ac_mark_abandoned_cron'));

        $loader = new Gm2_Loader();
        $loader->run();

        $this->assertNotFalse(wp_next_scheduled('gm2_ac_mark_abandoned_cron'));
    }

    public function test_event_present_after_interval_change() {
        update_option('gm2_enable_abandoned_carts', '1');
        Gm2_Abandoned_Carts::clear_scheduled_event();
        Gm2_Abandoned_Carts::schedule_event();
        $this->assertNotFalse(wp_next_scheduled('gm2_ac_mark_abandoned_cron'));

        update_option('gm2_ac_mark_abandoned_interval', 10);
        $this->assertNotFalse(wp_next_scheduled('gm2_ac_mark_abandoned_cron'));
    }
}
