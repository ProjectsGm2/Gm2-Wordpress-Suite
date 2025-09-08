<?php
use AE\CSS\AE_CSS_Queue;

class AeCssQueuePersistenceTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        update_option('ae_css_queue', []);
    }

    public function test_queue_survives_delete_transient() {
        $queue = AE_CSS_Queue::get_instance();
        $queue->enqueue('snapshot', 'https://example.com');
        $before = get_option('ae_css_queue');
        $this->assertCount(1, $before);
        delete_transient('ae_css_queue');
        $after = get_option('ae_css_queue');
        $this->assertCount(1, $after);
        $this->assertSame($before, $after);
    }
}
