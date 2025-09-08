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

class AeCssAdminHelpTabsTest extends WP_UnitTestCase {
    public function test_run_hooks_help_tabs(): void {
        $admin = new AE_CSS_Admin();
        $admin->run();
        $this->assertSame(10, has_action('load-gm2-css-optimization', [ $admin, 'add_help_tabs' ]));
    }

    public function test_add_help_tabs_adds_tab(): void {
        set_current_screen('gm2-css-optimization');
        $admin = new AE_CSS_Admin();
        $admin->add_help_tabs();
        $screen = get_current_screen();
        $tabs   = $screen->get_help_tabs();

        $this->assertArrayHasKey('gm2-css-hooks', $tabs);
        $content = $tabs['gm2-css-hooks']['content'] ?? '';
        $this->assertStringContainsString('ae/css/safelist', $content);
        $this->assertStringContainsString('ae/css/exclude_handles', $content);
        $this->assertStringContainsString('ae/css/force_keep_style', $content);
        $this->assertStringContainsString('ae/css/elementor_allow', $content);
        $this->assertStringContainsString('ae_css_settings', $content);
    }
}

class AeCssAdminSanitizeSettingsTest extends WP_UnitTestCase {
    public function test_exclude_handles_persist_after_sanitize(): void {
        wp_register_style('alpha', 'https://example.com/alpha.css');
        wp_register_style('beta', 'https://example.com/beta.css');

        $input     = ['exclude_handles' => ['alpha', 'beta']];
        $sanitized = AE_CSS_Admin::sanitize_settings($input);
        update_option('ae_css_settings', $sanitized);

        $saved = get_option('ae_css_settings');
        $this->assertSame(['alpha', 'beta'], $saved['exclude_handles']);

        wp_deregister_style('alpha');
        wp_deregister_style('beta');
    }
}
