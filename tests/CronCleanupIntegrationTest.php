<?php
use WP_UnitTestCase;

class CronCleanupIntegrationTest extends WP_UnitTestCase {
    private string $table;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query("DROP TABLE IF EXISTS {$this->table}");
        $wpdb->query("CREATE TABLE {$this->table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, session_id varchar(64) NOT NULL, user_id varchar(64) NOT NULL, url text NOT NULL, referrer text, `timestamp` datetime NOT NULL, user_agent text NOT NULL, device varchar(20) NOT NULL, ip varchar(100) NOT NULL, event_type varchar(20) NOT NULL DEFAULT '', duration int NOT NULL DEFAULT 0, element text, PRIMARY KEY(id))");
    }

    public function test_cron_event_purges_old_logs_and_unschedules_on_deactivate() {
        global $wpdb;
        // Schedule the purge event and ensure it exists.
        wp_schedule_event(time() - HOUR_IN_SECONDS, 'daily', 'gm2_analytics_purge');
        $this->assertNotFalse(wp_next_scheduled('gm2_analytics_purge'));

        // Insert new and old rows.
        $wpdb->insert($this->table, [ 'session_id' => 'new', 'user_id' => 'u1', 'url' => '/', 'referrer' => '', 'timestamp' => gmdate('Y-m-d H:i:s'), 'user_agent' => 'UA', 'device' => 'desktop', 'ip' => '0.0.0.0', 'event_type' => 'pageview', 'duration' => 0, 'element' => '' ]);
        $wpdb->insert($this->table, [ 'session_id' => 'old', 'user_id' => 'u2', 'url' => '/old', 'referrer' => '', 'timestamp' => gmdate('Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS), 'user_agent' => 'UA', 'device' => 'desktop', 'ip' => '0.0.0.0', 'event_type' => 'pageview', 'duration' => 0, 'element' => '' ]);

        update_option('gm2_analytics_retention_days', 30);
        do_action('gm2_analytics_purge');

        $sessions = $wpdb->get_col("SELECT session_id FROM {$this->table}");
        sort($sessions);
        $this->assertSame(['new'], $sessions);

        // Deactivate plugin to unschedule the event.
        gm2_deactivate_plugin();
        $this->assertFalse(wp_next_scheduled('gm2_analytics_purge'));
    }
}
