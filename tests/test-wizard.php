<?php
use Gm2\Gm2_SEO_Wizard;

class WizardTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        update_option('gm2_setup_complete', '0');
        $this->wizard = new Gm2_SEO_Wizard();
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function test_handle_post_executes_when_action_set() {
        $_POST['action'] = 'gm2_save_wizard';
        $_POST['gm2_wizard_step'] = 'chatgpt';
        $_POST['gm2_chatgpt_api_key'] = 'test-key';
        $_POST['gm2_next_step'] = 'oauth';
        $_POST['_wpnonce'] = wp_create_nonce('gm2_save_wizard');
        $_SERVER['SCRIPT_NAME'] = 'admin-post.php';

        $this->wizard->handle_redirect();
        $this->wizard->handle_post();

        $this->assertSame('test-key', get_option('gm2_chatgpt_api_key'));
        $this->assertSame('0', get_option('gm2_setup_complete'));
    }
}
