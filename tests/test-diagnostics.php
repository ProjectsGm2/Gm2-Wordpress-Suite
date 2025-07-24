<?php
use Gm2\Gm2_Diagnostics;
class DiagnosticsTest extends WP_UnitTestCase {
    public function test_conflicts_hidden_when_seo_disabled() {
        update_option('gm2_enable_seo', '0');
        update_option('active_plugins', ['wordpress-seo/wp-seo.php']);
        $diag = new Gm2_Diagnostics();
        $diag->diagnose();
        ob_start();
        $diag->display_notice();
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function test_conflicts_shown_when_seo_enabled() {
        update_option('gm2_enable_seo', '1');
        update_option('active_plugins', ['wordpress-seo/wp-seo.php']);
        $diag = new Gm2_Diagnostics();
        $diag->diagnose();
        ob_start();
        $diag->display_notice();
        $output = ob_get_clean();
        $this->assertStringContainsString('Conflicting SEO plugins', $output);
    }

    public function test_check_plugins_returns_early_when_seo_disabled() {
        update_option('gm2_enable_seo', '0');
        update_option('active_plugins', ['wordpress-seo/wp-seo.php']);
        $diag = new Gm2_Diagnostics();

        $reflect   = new ReflectionMethod($diag, 'check_plugins');
        $reflect->setAccessible(true);
        $reflect->invoke($diag);

        $prop = new ReflectionProperty($diag, 'conflicts');
        $prop->setAccessible(true);
        $this->assertSame([], $prop->getValue($diag));
    }
}
