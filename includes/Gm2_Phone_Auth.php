<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Phone_Auth {
    public function run() {
        add_action('woocommerce_register_form_start', [$this, 'render_registration_phone_field']);
        add_filter('woocommerce_register_post', [$this, 'require_phone'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'save_phone_on_register']);
        add_filter('authenticate', [$this, 'authenticate'], 10, 3);
        add_action('woocommerce_edit_account_form', [$this, 'render_account_phone_field']);
        add_action('woocommerce_save_account_details', [$this, 'save_account_phone']);
        add_action('woocommerce_save_account_details_errors', [$this, 'require_account_phone'], 10, 2);
        add_filter('woocommerce_login_username_label', function ($label) {
            return __('Phone or Email or Username', 'gm2-wordpress-suite');
        }, 20);
    }

    public function render_registration_phone_field() {
        $phone = isset($_POST['billing_phone']) ? wc_clean(wp_unslash($_POST['billing_phone'])) : '';
        woocommerce_form_field('billing_phone', [
            'type'     => 'tel',
            'label'    => __('Phone', 'gm2-wordpress-suite'),
            'required' => true,
        ], $phone);
    }

    public function require_phone($username, $email, $errors) {
        $phone = isset($_POST['billing_phone']) ? trim(wp_unslash($_POST['billing_phone'])) : '';
        if (empty($phone)) {
            $errors->add('registration-error-phone', __('Please enter a phone number.', 'gm2-wordpress-suite'));
        }
        return $errors;
    }

    public function save_phone_on_register($customer_id) {
        if (isset($_POST['billing_phone'])) {
            update_user_meta($customer_id, 'billing_phone', wc_clean(wp_unslash($_POST['billing_phone'])));
        }
    }

    public function authenticate($user, $username, $password) {
        if (!empty($user) || empty($username) || empty($password) || false !== strpos($username, '@')) {
            return $user;
        }

        $users = get_users([
            'meta_key'   => 'billing_phone',
            'meta_value' => $username,
            'number'     => 1,
            'fields'     => 'all',
        ]);

        if (!empty($users)) {
            $login = $users[0]->user_login;
            $user  = wp_authenticate_username_password(null, $login, $password);
        }

        return $user;
    }

    public function render_account_phone_field() {
        $user  = wp_get_current_user();
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        woocommerce_form_field('billing_phone', [
            'type'     => 'tel',
            'label'    => __('Phone', 'gm2-wordpress-suite'),
            'required' => true,
        ], $phone);
    }

    public function save_account_phone($user_id) {
        if (isset($_POST['billing_phone'])) {
            update_user_meta($user_id, 'billing_phone', wc_clean(wp_unslash($_POST['billing_phone'])));
        }
    }

    public function require_account_phone($errors, $user) {
        $phone = isset($_POST['billing_phone']) ? trim(wp_unslash($_POST['billing_phone'])) : '';
        if (empty($phone)) {
            $errors->add('account-error-phone', __('Please enter a phone number.', 'gm2-wordpress-suite'));
        }
    }
}
