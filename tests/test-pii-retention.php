<?php
use Gm2\Gm2_Audit_Log;

class PiiRetentionTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        Gm2_Audit_Log::install();
    }

    public function test_purge_removes_old_pii_meta() {
        global $wpdb;
        $post_id = self::factory()->post->create();
        update_post_meta($post_id, 'secret', 'top');
        Gm2_Audit_Log::tag_field_as_pii('secret', 1);
        $table = $wpdb->prefix . 'gm2_audit_log';
        $wpdb->insert($table, [
            'object_id' => $post_id,
            'meta_key' => 'secret',
            'old_value' => '',
            'new_value' => 'top',
            'user_id' => get_current_user_id(),
            'changed_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
            'pii' => 1,
        ]);
        Gm2_Audit_Log::purge();
        $this->assertSame('', get_post_meta($post_id, 'secret', true));
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $this->assertSame(0, $count);
    }

    public function test_export_pii_returns_data() {
        $post_id = self::factory()->post->create();
        update_post_meta($post_id, 'secret', 'top');
        Gm2_Audit_Log::tag_field_as_pii('secret');
        $data = Gm2_Audit_Log::export_pii($post_id);
        $this->assertArrayHasKey('secret', $data);
        $this->assertSame('top', $data['secret']);
    }
}
