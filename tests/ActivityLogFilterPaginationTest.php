<?php
use WP_UnitTestCase;
use Gm2\Gm2_Analytics_Admin;

require_once dirname(__DIR__) . '/admin/Gm2_Analytics_Admin.php';

class ActivityLogFilterPaginationTest extends WP_UnitTestCase {
    private string $table;
    private array $orig_get;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->orig_get = $_GET;
        $this->table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query("DROP TABLE IF EXISTS {$this->table}");
        $wpdb->query("CREATE TABLE {$this->table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, session_id varchar(64) NOT NULL, user_id varchar(64) NOT NULL, url text NOT NULL, referrer text, `timestamp` datetime NOT NULL, user_agent text NOT NULL, device varchar(20) NOT NULL, ip varchar(100) NOT NULL, PRIMARY KEY(id))");
    }

    protected function tearDown(): void {
        $_GET = $this->orig_get;
        parent::tearDown();
    }

    public function test_filters_by_user_and_device() {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->insert($this->table, [ 'session_id' => 's1', 'user_id' => 'u1', 'url' => '/', 'referrer' => '', 'timestamp' => $now, 'user_agent' => 'UA', 'device' => 'desktop', 'ip' => '0.0.0.0' ]);
        $wpdb->insert($this->table, [ 'session_id' => 's2', 'user_id' => 'u2', 'url' => '/two', 'referrer' => '', 'timestamp' => $now, 'user_agent' => 'UA', 'device' => 'mobile', 'ip' => '0.0.0.0' ]);

        $_GET['log_user'] = 'u2';
        $_GET['log_device'] = 'mobile';
        $_GET['gm2_activity_log_nonce'] = wp_create_nonce('gm2_activity_log_filter');

        $admin = new Gm2_Analytics_Admin();
        $ref = new ReflectionClass(Gm2_Analytics_Admin::class);
        $method = $ref->getMethod('render_activity_log');
        $method->setAccessible(true);
        ob_start();
        $method->invoke($admin, ['start' => gmdate('Y-m-d', strtotime('-1 day')), 'end' => gmdate('Y-m-d')]);
        $output = ob_get_clean();

        $this->assertStringContainsString('u2', $output);
        $this->assertStringNotContainsString('u1', $output);
    }

    public function test_pagination_outputs_links() {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        for ($i = 1; $i <= 25; $i++) {
            $wpdb->insert($this->table, [ 'session_id' => "s{$i}", 'user_id' => "u{$i}", 'url' => '/p', 'referrer' => '', 'timestamp' => $now, 'user_agent' => 'UA', 'device' => 'desktop', 'ip' => '0.0.0.0' ]);
        }
        $_GET['paged'] = '2';
        $_GET['gm2_activity_log_nonce'] = wp_create_nonce('gm2_activity_log_filter');

        $admin = new Gm2_Analytics_Admin();
        $ref = new ReflectionClass(Gm2_Analytics_Admin::class);
        $method = $ref->getMethod('render_activity_log');
        $method->setAccessible(true);
        ob_start();
        $method->invoke($admin, ['start' => gmdate('Y-m-d', strtotime('-1 day')), 'end' => gmdate('Y-m-d')]);
        $output = ob_get_clean();

        $this->assertStringContainsString('class="tablenav-page current">2</span>', $output);
        $this->assertStringContainsString('paged=1', $output);
    }
}
