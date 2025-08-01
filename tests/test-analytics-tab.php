<?php
use Gm2\Gm2_SEO_Admin;

class AnalyticsTabTest extends WP_UnitTestCase {
    public function tearDown(): void {
        remove_all_filters('gm2_google_oauth_instance');
        delete_option('gm2_sc_query_limit');
        delete_option('gm2_analytics_days');
        delete_option('gm2_ga_measurement_id');
        parent::tearDown();
    }

    public function test_metrics_table_rendered() {
        $_GET['tab'] = 'analytics';
        update_option('gm2_google_refresh_token', 'tok');
        update_option('gm2_ga_measurement_id', 'G-123');
        update_option('gm2_sc_query_limit', 2);
        update_option('gm2_analytics_days', 7);

        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_search_console_metrics($site, $limit) {
                    return [
                        ['query' => 'alpha', 'clicks' => 5, 'impressions' => 10],
                        ['query' => 'beta', 'clicks' => 3, 'impressions' => 8],
                    ];
                }
                public function get_search_console_queries($site, $limit) { return ['alpha', 'beta']; }
                public function get_analytics_metrics($prop, $days) { return ['sessions' => 10, 'bounce_rate' => 20]; }
                public function get_analytics_trends($prop, $days) {
                    return [
                        'dates'       => ['2024-01-01'],
                        'sessions'    => [1],
                        'bounce_rate' => [50],
                    ];
                }
            };
        });

        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_dashboard();
        $out = ob_get_clean();

        $this->assertStringContainsString('alpha', $out);
        $this->assertStringContainsString('5', $out);
        $this->assertStringContainsString('Sessions', $out);
        $this->assertStringContainsString('10', $out);
        $this->assertStringContainsString('gm2-analytics-trend', $out);
        $this->assertStringContainsString('gm2-query-chart', $out);
    }
}
?>
