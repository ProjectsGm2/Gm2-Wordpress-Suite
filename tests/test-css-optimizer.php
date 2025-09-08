<?php
use AE\CSS\AE_CSS_Optimizer;

class CssOptimizerTest extends WP_UnitTestCase {
    private function reset_optimizer(): void {
        $ref  = new ReflectionClass(AE_CSS_Optimizer::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->reset_optimizer();
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_print_styles', 8);
    }

    protected function tearDown(): void {
        remove_all_actions('wp_head');
        remove_all_filters('style_loader_tag');
        remove_all_actions('wp_enqueue_scripts');
        foreach ([ 'woocommerce-general', 'elementor-frontend', 'test' ] as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
        wp_styles()->queue = [];
        wp_styles()->done  = [];
        if (class_exists('Elementor\\Plugin')) {
            \Elementor\Plugin::$instance = null;
        }
        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     */
    public function test_activation_adds_defaults_and_uninstall_removes(): void {
        delete_option('ae_css_settings');
        gm2_activate_css_optimizer_defaults();
        $this->assertSame(
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ],
            get_option('ae_css_settings')
        );
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }
        require dirname(__DIR__) . '/uninstall.php';
        $this->assertFalse(get_option('ae_css_settings'));
    }

    public function test_has_node_capability_cache_and_fallback(): void {
        delete_transient('ae_css_has_node');
        set_transient('ae_css_has_node', '1');
        $this->assertTrue(AE_CSS_Optimizer::has_node_capability());
        set_transient('ae_css_has_node', '0');
        $this->assertFalse(AE_CSS_Optimizer::has_node_capability());
        delete_transient('ae_css_has_node');

        $old = getenv('PATH');
        $tmp = sys_get_temp_dir() . '/nonode' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/node', "#!/bin/sh\nexit 0");
        chmod($tmp . '/node', 0755);
        file_put_contents($tmp . '/npx', "#!/bin/sh\nexit 0");
        chmod($tmp . '/npx', 0755);
        putenv('PATH=' . $tmp);

        $this->assertFalse(AE_CSS_Optimizer::has_node_capability());
        $this->assertSame('0', get_transient('ae_css_has_node'));

        putenv('PATH=' . $old);
        unlink($tmp . '/node');
        unlink($tmp . '/npx');
        rmdir($tmp);
        delete_transient('ae_css_has_node');
    }

    public function test_enqueue_smart_keeps_styles_when_dependencies_inactive(): void {
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('woocommerce-general', 'https://example.com/woo.css');
        wp_enqueue_style('elementor-frontend', 'https://example.com/el.css');
        $optimizer->enqueue_smart();
        $this->assertContains('woocommerce-general', wp_styles()->queue);
        $this->assertContains('elementor-frontend', wp_styles()->queue);
        $this->assertTrue(wp_style_is('woocommerce-general', 'enqueued'));
        $this->assertTrue(wp_style_is('elementor-frontend', 'enqueued'));
    }

    /**
     * @runInSeparateProcess
     */
    public function test_enqueue_smart_keeps_styles_in_active_contexts(): void {
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        if (!function_exists('is_woocommerce')) {
            function is_woocommerce() { return true; }
        }
        do_action('elementor/loaded');
        eval('namespace Elementor; class DB { public function is_built_with_elementor($id){ return true; } } class Plugin { public static $instance; public $db; public function __construct(){ self::$instance=$this; $this->db=new DB(); } }');
        new \Elementor\Plugin();
        self::factory()->post->create();
        self::go_to('/?p=1');

        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('woocommerce-general', 'https://example.com/woo.css');
        wp_enqueue_style('elementor-frontend', 'https://example.com/el.css');
        $optimizer->enqueue_smart();
        $this->assertContains('woocommerce-general', wp_styles()->queue);
        $this->assertContains('elementor-frontend', wp_styles()->queue);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_enqueue_smart_flags_disable_dequeue(): void {
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [ 'woo' => '1' ],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('woocommerce-general', 'https://example.com/woo.css');
        $optimizer->enqueue_smart();
        $this->assertContains('woocommerce-general', wp_styles()->queue);
        $this->assertTrue(wp_style_is('woocommerce-general', 'enqueued'));
    }

    public function test_enqueue_smart_dequeues_woo_styles_on_non_woo_pages_when_enabled(): void {
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '1',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('woocommerce-general', 'https://example.com/woo.css');
        $this->assertTrue(wp_style_is('woocommerce-general', 'enqueued'));
        $optimizer->enqueue_smart();
        $this->assertFalse(wp_style_is('woocommerce-general', 'enqueued'));
    }

    public function test_enqueue_smart_force_keep_style_filter_preserves_handle(): void {
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '1',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        add_filter('ae/css/force_keep_style', function ($keep, $handle) {
            return $handle === 'woocommerce-general' ? true : $keep;
        }, 10, 2);
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('woocommerce-general', 'https://example.com/woo.css');
        $optimizer->enqueue_smart();
        $this->assertTrue(wp_style_is('woocommerce-general', 'enqueued'));
        remove_all_filters('ae/css/force_keep_style');
    }

    public function test_enqueue_smart_dequeues_elementor_styles_on_non_elementor_pages_when_enabled(): void {
        do_action('elementor/loaded');
        if (!class_exists('Elementor\\Plugin')) {
            eval('namespace Elementor; class DB { public function is_built_with_elementor($id){ return false; } } class Dummy { public function is_edit_mode(){ return false; } public function is_preview_mode(){ return false; } } class Plugin { public static $instance; public $db; public $editor; public $preview; public function __construct(){ self::$instance=$this; $this->db=new DB(); $this->editor=new Dummy(); $this->preview=new Dummy(); } }');
            new \Elementor\Plugin();
        }
        self::factory()->post->create();
        self::go_to('/?p=1');
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '1',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('elementor-frontend', 'https://example.com/el.css');
        $optimizer->enqueue_smart();
        $this->assertFalse(wp_style_is('elementor-frontend', 'enqueued'));
    }

    public function test_enqueue_smart_elementor_allow_filter_preserves_handle(): void {
        do_action('elementor/loaded');
        if (!class_exists('Elementor\\Plugin')) {
            eval('namespace Elementor; class DB { public function is_built_with_elementor($id){ return false; } } class Dummy { public function is_edit_mode(){ return false; } public function is_preview_mode(){ return false; } } class Plugin { public static $instance; public $db; public $editor; public $preview; public function __construct(){ self::$instance=$this; $this->db=new DB(); $this->editor=new Dummy(); $this->preview=new Dummy(); } }');
            new \Elementor\Plugin();
        }
        self::factory()->post->create();
        self::go_to('/?p=1');
        add_filter('ae/css/elementor_allow', function ($allow) {
            $allow[] = 'elementor-frontend';
            return $allow;
        });
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '1',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('elementor-frontend', 'https://example.com/el.css');
        $optimizer->enqueue_smart();
        $this->assertTrue(wp_style_is('elementor-frontend', 'enqueued'));
        remove_all_filters('ae/css/elementor_allow');
    }

    public function test_inject_critical_and_defer_inlines_and_defers_when_enabled(): void {
        $url = home_url(add_query_arg([], ''));
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [],
                'generate_critical'             => '1',
                'async_load_noncritical'        => '1',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [ $url => '.critical{color:red;}' ],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();

        wp_enqueue_style('test', 'https://example.com/style.css');
        $this->assertTrue(wp_style_is('test', 'enqueued'));

        ob_start();
        do_action('wp_head');
        $head = ob_get_clean();
        $this->assertStringContainsString('<style id="ae-critical-css">.critical{color:red;}</style>', $head);

        ob_start();
        wp_print_styles();
        $out = ob_get_clean();
        $this->assertStringContainsString('rel="preload"', $out);
    }

    public function test_inject_critical_and_defer_no_effect_when_disabled(): void {
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();
        wp_enqueue_style('test', 'https://example.com/style.css');

        ob_start();
        do_action('wp_head');
        $head = ob_get_clean();
        $this->assertStringNotContainsString('ae-critical-css', $head);

        ob_start();
        wp_print_styles();
        $out = ob_get_clean();
        $this->assertStringNotContainsString('rel="preload"', $out);
        $this->assertStringContainsString('rel="stylesheet"', $out);
    }

    public function test_purgecss_analyze_generates_and_caches_css(): void {
        delete_transient('ae_css_has_node');
        $old_path = getenv('PATH');
        $tmp      = sys_get_temp_dir() . '/npxstub' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/node', "#!/bin/sh\necho v18.0.0\n");
        chmod($tmp . '/node', 0755);
        file_put_contents($tmp . '/npx', "#!/bin/sh\nif [ \"$1\" = \"--version\" ]; then echo v9.0.0; exit 0; fi\nif [ \"$1\" = \"--yes\" ]; then shift; fi\necho 'body{color:green}'\n");
        chmod($tmp . '/npx', 0755);
        putenv('PATH=' . $tmp);

        $css_file = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($css_file, 'body{color:red}.unused{display:none}');

        $filter = function ($pre, $args, $url) {
            return [ 'body' => '<html><body class="current-menu-item is-active">x</body></html>' ];
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $result = AE_CSS_Optimizer::purgecss_analyze([$css_file], [ 123 => 'https://example.com/page' ]);
        $this->assertSame('body{color:green}', $result);

        $upload = wp_upload_dir();
        $this->assertFileExists($upload['basedir'] . '/ae-css/snapshots/123.html');

        file_put_contents($tmp . '/npx', "#!/bin/sh\nif [ \"$1\" = \"--version\" ]; then echo v9.0.0; exit 0; fi\nif [ \"$1\" = \"--yes\" ]; then shift; fi\necho 'body{color:blue}'\n");
        chmod($tmp . '/npx', 0755);

        $result2 = AE_CSS_Optimizer::purgecss_analyze([$css_file], [ 123 => 'https://example.com/page' ]);
        $this->assertSame('body{color:green}', $result2);

        remove_filter('pre_http_request', $filter, 10);
        putenv('PATH=' . $old_path);
        unlink($css_file);
        unlink($tmp . '/node');
        unlink($tmp . '/npx');
        rmdir($tmp);
        delete_transient('ae_css_has_node');
    }

    public function test_print_critical_css_php_fallback_filters_and_limits_output(): void {
        set_transient('ae_css_has_node', '0');
        update_option(
            'ae_css_settings',
            [
                'flags'                         => [],
                'safelist'                      => '',
                'exclude_handles'               => [],
                'include_above_the_fold_handles'=> [ 'test' ],
                'generate_critical'             => '0',
                'async_load_noncritical'        => '0',
                'woocommerce_smart_enqueue'     => '0',
                'elementor_smart_enqueue'       => '0',
                'critical'                      => [],
                'queue'                         => [],
            ]
        );
        $optimizer = AE_CSS_Optimizer::get_instance();
        $optimizer->init();

        wp_register_style('test', 'https://example.com/test.css');
        wp_enqueue_style('test');

        $css = str_repeat('.menu{color:red;}', 2000) . str_repeat('.foo{color:blue;}', 1000);
        $filter = static function ($pre, $args, $url) use ($css) {
            if ($url === 'https://example.com/test.css') {
                return [ 'body' => $css, 'response' => [ 'code' => 200 ] ];
            }
            return $pre;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        ob_start();
        $optimizer->print_critical_css();
        $output = ob_get_clean();
        remove_filter('pre_http_request', $filter, 10);

        $this->assertStringContainsString('<style id="ae-critical-css">', $output);
        $this->assertStringNotContainsString('.foo', $output);
        preg_match('/<style id="ae-critical-css">(.*)<\/style>/s', $output, $m);
        $critical = $m[1] ?? '';
        $this->assertSame(20000, strlen($critical));
        $this->assertStringContainsString('.menu{color:red', $critical);
    }
}

