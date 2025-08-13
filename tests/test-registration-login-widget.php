<?php
use Gm2\Gm2_Elementor_Registration_Login;
use Gm2\GM2_Registration_Login_Widget;

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($GLOBALS['gm2_tests_is_user_logged_in']);
    }
}
if (!function_exists('woocommerce_login_form')) {
    function woocommerce_login_form($args = []) { echo '<form class="login"></form>'; }
}
if (!class_exists('Elementor\\Widget_Base')) {
    eval('namespace Elementor; class Widget_Base {}');
}

class RegistrationLoginWidgetTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        if (!class_exists('Elementor\\Plugin')) {
            eval('namespace Elementor; class Plugin { public static $instance; public $widgets_manager; public function __construct(){ self::$instance=$this; $this->widgets_manager=new class{ public $registered=[]; public function register($w){ $this->registered[]=$w; } public function register_widget_type($w){ $this->registered[]=$w; } }; } }');
            new \Elementor\Plugin();
        } else {
            \Elementor\Plugin::$instance = new class { public $widgets_manager; public function __construct(){ $this->widgets_manager=new class{ public $registered=[]; public function register($w){ $this->registered[]=$w; } public function register_widget_type($w){ $this->registered[]=$w; } }; } };
        }
    }

    public function test_widget_registers_only_with_elementor_and_wc() {
        $loader = new Gm2_Elementor_Registration_Login();
        $loader->run();
        do_action('elementor/init');
        do_action('elementor/widgets/register', \Elementor\Plugin::$instance->widgets_manager);
        $this->assertEmpty(\Elementor\Plugin::$instance->widgets_manager->registered);

        if (!class_exists('WooCommerce')) { class WooCommerce {} }
        \Elementor\Plugin::$instance->widgets_manager->registered = [];
        $loader = new Gm2_Elementor_Registration_Login();
        $loader->run();
        do_action('elementor/init');
        do_action('elementor/widgets/register', \Elementor\Plugin::$instance->widgets_manager);
        $found = false;
        foreach (\Elementor\Plugin::$instance->widgets_manager->registered as $w) {
            if ($w instanceof GM2_Registration_Login_Widget) { $found = true; }
        }
        $this->assertTrue($found);
    }

    public function test_render_outputs_forms_and_restricts_role() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-registration-login-widget.php';
        add_action('woocommerce_register_form', function(){ echo '<form class="register"></form>'; });
        ob_start();
        do_action('woocommerce_register_form'); // simulate hook output
        $registration_html = ob_get_clean();

        $widget = new GM2_Registration_Login_Widget();
        ob_start();
        $widget->render();
        $html = ob_get_clean();
        $this->assertStringContainsString('class="login"', $html);
        $this->assertStringContainsString('class="register"', $registration_html);
        $this->assertStringContainsString('class="register"', $html);

        $user = new \WP_User(1, 'test');
        $user->add_role('editor');
        $this->assertInstanceOf(\WP_Error::class, $widget->restrict_auth_roles($user));
        $cust = new \WP_User(2, 'cust');
        $cust->add_role('customer');
        $this->assertSame($cust, $widget->restrict_auth_roles($cust));
    }

    public function test_google_button_only_when_enabled() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-registration-login-widget.php';
        eval('namespace Google\\Site_Kit; class Plugin { public static function get(){ return new self; } public function get_authentication(){ return new class { public function get_google_login_url(){ return "https://example.com"; } }; } }');
        $widget = new GM2_Registration_Login_Widget();
        add_filter('gm2_sitekit_login_enabled', '__return_true');
        ob_start();
        $widget->render();
        $html = ob_get_clean();
        $this->assertStringContainsString('Continue with Google', $html);
        add_filter('gm2_sitekit_login_enabled', '__return_false');
        ob_start();
        $widget->render();
        $html2 = ob_get_clean();
        $this->assertStringNotContainsString('Continue with Google', $html2);
    }

    public function test_edit_mode_renders_forms_for_logged_in_user() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-registration-login-widget.php';
        $GLOBALS['gm2_tests_is_user_logged_in'] = true;
        $_GET['elementor-preview'] = 1;
        $widget = new GM2_Registration_Login_Widget();
        ob_start();
        $widget->render();
        $html = ob_get_clean();
        unset($_GET['elementor-preview'], $GLOBALS['gm2_tests_is_user_logged_in']);
        $this->assertStringContainsString('class="login"', $html);
        $this->assertStringNotContainsString('already logged in', strtolower($html));
    }

    public function test_editor_bypasses_logged_in_guard() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-registration-login-widget.php';
        $editor_id = self::factory()->user->create([ 'role' => 'editor' ]);
        wp_set_current_user($editor_id);
        $GLOBALS['gm2_tests_is_user_logged_in'] = true;
        $widget = new GM2_Registration_Login_Widget();
        ob_start();
        $widget->render();
        $html = ob_get_clean();
        unset($GLOBALS['gm2_tests_is_user_logged_in']);
        wp_set_current_user(0);
        $this->assertStringContainsString('class="login"', $html);
        $this->assertStringNotContainsString('already logged in', strtolower($html));
    }
}
