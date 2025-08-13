<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Phone_Auth {
    public function run() {
        add_action('woocommerce_register_form_start', [$this, 'render_registration_contact_field']);
        add_filter('woocommerce_register_post', [$this, 'validate_contact_field'], 10, 3);
        add_filter('woocommerce_new_customer_data', [$this, 'assign_contact_field']);
        add_filter('authenticate', [$this, 'authenticate'], 10, 3);
        add_action('woocommerce_edit_account_form', [$this, 'render_account_phone_field']);
        add_action('woocommerce_save_account_details', [$this, 'save_account_phone']);
        add_action('woocommerce_save_account_details_errors', [$this, 'require_account_phone'], 10, 2);
        add_filter('woocommerce_login_username_label', function ($label) {
            return __('Phone or Email or Username', 'gm2-wordpress-suite');
        }, 20);
        add_filter('gettext', function ($translated, $original, $domain) {
            if ('Username or email address' === $original && 'woocommerce' === $domain) {
                return __('Phone or Email or Username', 'gm2-wordpress-suite');
            }
            return $translated;
        }, 20, 3);
    }

    public function render_registration_contact_field() {
        $contact = isset($_POST['contact']) ? wc_clean(wp_unslash($_POST['contact'])) : '';
        woocommerce_form_field('contact', [
            'type'     => 'text',
            'label'    => __('Phone or Email', 'gm2-wordpress-suite'),
            'required' => true,
        ], $contact);
    }

    public function validate_contact_field($username, $email, $errors) {
        $contact = isset($_POST['contact']) ? trim(wp_unslash($_POST['contact'])) : '';
        if (empty($contact)) {
            $errors->add('registration-error-contact', __('Please enter a phone number or email address.', 'gm2-wordpress-suite'));
            return $errors;
        }

        if (false !== strpos($contact, '@')) {
            if (!is_email($contact)) {
                $errors->add('registration-error-contact', __('Please enter a valid email address.', 'gm2-wordpress-suite'));
            } else {
                $_POST['email'] = $contact;
            }
        } else {
            if (!preg_match('/^\+?\d+$/', $contact)) {
                $errors->add('registration-error-contact', __('Please enter a valid phone number.', 'gm2-wordpress-suite'));
            } else {
                $_POST['billing_phone'] = $contact;
                $_POST['email']         = $contact . '@example.com';
            }
        }

        return $errors;
    }

    public function assign_contact_field($data) {
        $contact = isset($_POST['contact']) ? wc_clean(wp_unslash($_POST['contact'])) : '';
        if (false !== strpos($contact, '@') && is_email($contact)) {
            $data['user_email'] = $contact;
        } else {
            $data['user_email']        = $contact . '@example.com';
            $data['meta_input']        = isset($data['meta_input']) ? $data['meta_input'] : [];
            $data['meta_input']['billing_phone'] = $contact;
        }

        return $data;
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
