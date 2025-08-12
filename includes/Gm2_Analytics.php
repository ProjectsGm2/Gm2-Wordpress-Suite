<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Analytics {
    const COOKIE_NAME = 'gm2_analytics_id';
    const SESSION_COOKIE = 'gm2_analytics_session';

    public function run() {
        add_action('init', [ $this, 'maybe_log_request' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_tracker' ]);
        add_action('wp_ajax_nopriv_gm2_analytics_track', [ $this, 'ajax_track' ]);
        add_action('wp_ajax_gm2_analytics_track', [ $this, 'ajax_track' ]);
    }

    public function enqueue_tracker() {
        wp_enqueue_script(
            'gm2-analytics-tracker',
            GM2_PLUGIN_URL . 'public/js/gm2-analytics-tracker.js',
            [],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-analytics-tracker',
            'gm2Analytics',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
            ]
        );
    }

    public function maybe_log_request() {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $user_id    = $this->get_user_id();
        $session_id = $this->get_session_id();
        $url        = esc_url_raw(home_url($_SERVER['REQUEST_URI'] ?? ''));
        $referrer   = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $device     = wp_is_mobile() ? 'mobile' : 'desktop';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $this->insert_log([
            'session_id' => $session_id,
            'user_id'    => $user_id,
            'url'        => $url,
            'referrer'   => $referrer,
            'timestamp'  => current_time('mysql'),
            'user_agent' => $user_agent,
            'device'     => $device,
            'ip'         => $ip,
        ]);
    }

    private function get_user_id() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            $id = wp_generate_uuid4();
            setcookie(self::COOKIE_NAME, $id, time() + YEAR_IN_SECONDS * 2, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE[self::COOKIE_NAME] = $id;
        }
        return sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
    }

    private function get_session_id() {
        if (!isset($_COOKIE[self::SESSION_COOKIE])) {
            $session = wp_generate_uuid4();
            setcookie(self::SESSION_COOKIE, $session, 0, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE[self::SESSION_COOKIE] = $session;
        }
        return sanitize_text_field($_COOKIE[self::SESSION_COOKIE]);
    }

    public function ajax_track() {
        $url      = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $referrer = isset($_POST['referrer']) ? esc_url_raw(wp_unslash($_POST['referrer'])) : '';
        $this->log_event($url, $referrer);
        wp_send_json_success();
    }

    private function log_event($url, $referrer = '') {
        $user_id    = $this->get_user_id();
        $session_id = $this->get_session_id();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $device     = wp_is_mobile() ? 'mobile' : 'desktop';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $this->insert_log([
            'session_id' => $session_id,
            'user_id'    => $user_id,
            'url'        => $url,
            'referrer'   => $referrer,
            'timestamp'  => current_time('mysql'),
            'user_agent' => $user_agent,
            'device'     => $device,
            'ip'         => $ip,
        ]);
    }

    private function insert_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table (`session_id`,`user_id`,`url`,`referrer`,`timestamp`,`user_agent`,`device`,`ip`) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
                $data['session_id'],
                $data['user_id'],
                $data['url'],
                $data['referrer'],
                $data['timestamp'],
                $data['user_agent'],
                $data['device'],
                $data['ip']
            )
        );
    }
}
