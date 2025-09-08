<?php
use Gm2\AE_CSS_Admin;

class AeCssAdminNoticesTest extends WP_UnitTestCase {
    public function test_show_queue_notices_includes_job_types() {
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        update_option('ae_css_queue', [
            ['type' => 'snapshot', 'payload' => 'https://example.com'],
            ['type' => 'purge', 'payload' => '/theme'],
        ]);
        update_option('ae_css_job_status', [
            'purge'    => ['status' => 'running', 'message' => ''],
            'critical' => ['status' => 'done', 'message' => 'finished'],
        ]);

        $admin = new AE_CSS_Admin();
        ob_start();
        $admin->show_queue_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('Snapshot: queued', $output);
        $this->assertStringContainsString('Purge: running', $output);
        $this->assertStringContainsString('Critical: done', $output);
    }
}
