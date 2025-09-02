<?php
class RenderOptimizerConflictsTest extends WP_UnitTestCase {
    public function test_missing_is_plugin_active_returns_false() {
        $classFile = realpath(dirname(__DIR__) . '/includes/render-optimizer/class-ae-seo-render-optimizer.php');

        $tempDir = sys_get_temp_dir() . '/gm2_fake_wp_' . uniqid();
        mkdir($tempDir . '/wp-admin/includes', 0777, true);
        file_put_contents($tempDir . '/wp-admin/includes/plugin.php', "<?php\n");

        $script = <<<'PHP'
        define('ABSPATH', '%s/');
        require '%s';
        class Test_RO extends AE_SEO_Render_Optimizer {
            public function __construct() {}
            public function run() { return $this->has_conflicts(); }
        }
        $t = new Test_RO();
        var_export($t->run());
        PHP;
        $script = sprintf($script, $tempDir, $classFile);

        $output = trim(shell_exec('php -r ' . escapeshellarg($script)));
        $this->assertSame('false', $output);

        unlink($tempDir . '/wp-admin/includes/plugin.php');
        rmdir($tempDir . '/wp-admin/includes');
        rmdir($tempDir . '/wp-admin');
        rmdir($tempDir);
    }

    public function test_login_url_skips_loading_features() {
        $originalPagenow = $GLOBALS['pagenow'] ?? null;
        $GLOBALS['pagenow'] = 'wp-login.php';

        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '1');

        $optimizer = new class extends AE_SEO_Render_Optimizer {
            public $loaded = false;
            public function __construct() {}
            protected function load_features() {
                $this->loaded = true;
            }
        };

        $optimizer->maybe_bootstrap();

        $this->assertFalse($optimizer->loaded);

        AE_SEO_Render_Optimizer::delete_option(AE_SEO_Critical_CSS::OPTION_ENABLE);

        if ($originalPagenow === null) {
            unset($GLOBALS['pagenow']);
        } else {
            $GLOBALS['pagenow'] = $originalPagenow;
        }
    }
}

