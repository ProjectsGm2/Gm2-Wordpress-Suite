<?php
use Gm2\Gm2_Phone_Auth;

if (!function_exists('wc_clean')) {
    function wc_clean($var) {
        return is_array($var) ? array_map('wc_clean', $var) : sanitize_text_field($var);
    }
}

class PhoneAuthRegistrationTest extends WP_UnitTestCase {
    protected $auth;

    public function setUp(): void {
        parent::setUp();
        $this->auth = new Gm2_Phone_Auth();
    }

    public function test_registration_with_email() {
        $_POST['contact'] = 'user@example.com';
        $errors = new \WP_Error();
        $errors = $this->auth->validate_contact_field('', '', $errors);
        $this->assertFalse($errors->has_errors());
        $this->assertSame('user@example.com', $_POST['email']);
        $data = $this->auth->assign_contact_field([]);
        $this->assertSame('user@example.com', $data['user_email']);
        $this->assertArrayNotHasKey('meta_input', $data);
    }

    public function test_registration_with_phone_and_login() {
        $_POST['contact'] = '+1234567890';
        $errors = new \WP_Error();
        $errors = $this->auth->validate_contact_field('', '', $errors);
        $this->assertFalse($errors->has_errors());
        $this->assertSame('+1234567890', $_POST['billing_phone']);
        $this->assertSame('+1234567890@example.com', $_POST['email']);
        $data = $this->auth->assign_contact_field([]);
        $this->assertSame('+1234567890@example.com', $data['user_email']);
        $this->assertSame('+1234567890', $data['meta_input']['billing_phone']);
        $user_id = wp_insert_user([
            'user_login' => 'phoneuser',
            'user_pass'  => 'pass',
            'user_email' => $data['user_email'],
        ]);
        update_user_meta($user_id, 'billing_phone', '+1234567890');
        $user = $this->auth->authenticate(null, '+1234567890', 'pass');
        $this->assertInstanceOf(\WP_User::class, $user);
        $this->assertSame($user_id, $user->ID);
    }
}
