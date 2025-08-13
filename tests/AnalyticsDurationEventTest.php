<?php
use WP_UnitTestCase;
use Gm2\Gm2_Analytics;

require_once dirname(__DIR__) . '/includes/Gm2_Analytics.php';

class AnalyticsDurationEventTest extends WP_UnitTestCase {
    private string $table;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query("DROP TABLE IF EXISTS {$this->table}");
        $wpdb->query("CREATE TABLE {$this->table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, session_id varchar(64) NOT NULL, user_id varchar(64) NOT NULL, url text NOT NULL, referrer text, `timestamp` datetime NOT NULL, user_agent text NOT NULL, device varchar(20) NOT NULL, ip varchar(100) NOT NULL, event_type varchar(20) NOT NULL DEFAULT '', duration int NOT NULL DEFAULT 0, element text, PRIMARY KEY(id))");
        $_SERVER['HTTP_USER_AGENT'] = 'UA';
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $_COOKIE[Gm2_Analytics::COOKIE_NAME] = 'user';
        $_COOKIE[Gm2_Analytics::SESSION_COOKIE] = 'sess';
    }

    public function test_duration_updates_latest_pageview() {
        global $wpdb;
        $analytics = new Gm2_Analytics();
        $ref = new ReflectionClass(Gm2_Analytics::class);
        $method = $ref->getMethod('log_event');
        $method->setAccessible(true);
        // Insert initial pageview row
        $method->invoke($analytics, '/', '', 'pageview', 0, '');
        // Send duration event which should update existing row
        $method->invoke($analytics, '/', '', 'duration', 5, '');

        $rows = $wpdb->get_results("SELECT event_type, duration FROM {$this->table}");
        $this->assertCount(1, $rows);
        $this->assertSame('pageview', $rows[0]->event_type);
        $this->assertSame(5, (int) $rows[0]->duration);
    }
}
