<?php
use WP_UnitTestCase;
use Gm2\Gm2_Webhooks;

class AsyncJobSchedulingTest extends WP_UnitTestCase {
    public function test_media_webhook_schedules_event() {
        $file = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($file);
        Gm2_Webhooks::init();
        do_action('add_attachment', $attachment_id);
        $this->assertNotFalse(wp_next_scheduled('gm2_webhook_media', [ $attachment_id ]));
    }

    public function test_thumbnail_regeneration_schedules_event() {
        $file = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($file);
        \gm2_queue_thumbnail_regeneration($attachment_id);
        $this->assertNotFalse(wp_next_scheduled('gm2_generate_thumbnails', [ $attachment_id ]));
    }
}
