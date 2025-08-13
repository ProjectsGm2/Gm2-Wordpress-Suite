<?php
use WP_UnitTestCase;

class AnalyticsRetentionTest extends WP_UnitTestCase {
    public function test_purge_removes_old_rows() {
        global $wpdb;
        $table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query("DROP TABLE IF EXISTS $table");
        $wpdb->query("CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, session_id varchar(64) NOT NULL, user_id varchar(64) NOT NULL, url text NOT NULL, referrer text, `timestamp` datetime NOT NULL, user_agent text NOT NULL, device varchar(20) NOT NULL, ip varchar(100) NOT NULL, PRIMARY KEY(id))");

        $wpdb->insert($table, [
            'session_id' => 'new',
            'user_id' => 'u1',
            'url' => '/',
            'referrer' => '',
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'user_agent' => 'UA',
            'device' => 'desktop',
            'ip' => '0.0.0.0',
        ]);
        $wpdb->insert($table, [
            'session_id' => 'old',
            'user_id' => 'u2',
            'url' => '/old',
            'referrer' => '',
            'timestamp' => gmdate('Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS),
            'user_agent' => 'UA',
            'device' => 'desktop',
            'ip' => '0.0.0.0',
        ]);

        update_option('gm2_analytics_retention_days', 30);

        do_action('gm2_analytics_purge');

        $sessions = $wpdb->get_col("SELECT session_id FROM $table");
        sort($sessions);
        $this->assertSame(['new'], $sessions);
    }
}
