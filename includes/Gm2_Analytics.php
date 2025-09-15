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
                'nonce'    => wp_create_nonce('gm2_analytics'),
            ]
        );
    }

    public function maybe_log_request() {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (wp_doing_cron()) {
            return;
        }
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        if ($this->should_skip_logging($user_agent)) {
            return;
        }

        $user_id    = $this->get_user_id();
        $session_id = $this->get_session_id();
        $url        = esc_url_raw(home_url($_SERVER['REQUEST_URI'] ?? ''));
        $referrer   = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $device     = wp_is_mobile() ? 'mobile' : 'desktop';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $ip         = $ip ? wp_privacy_anonymize_ip($ip) : '';

        $this->insert_log([
            'session_id' => $session_id,
            'user_id'    => $user_id,
            'url'        => $url,
            'referrer'   => $referrer,
            'timestamp'  => current_time('mysql'),
            'user_agent' => $user_agent,
            'device'     => $device,
            'ip'         => $ip,
            'event_type' => 'pageview',
            'duration'   => 0,
            'element'    => '',
        ]);
    }

    private function get_user_id() {
        $user_id = get_current_user_id();

        if ($user_id) {
            $id = (string) $user_id;
        } else {
            if (!isset($_COOKIE[self::COOKIE_NAME])) {
                $id = wp_generate_uuid4();
            } else {
                $id = $_COOKIE[self::COOKIE_NAME];
            }
        }

        if (!isset($_COOKIE[self::COOKIE_NAME]) || $_COOKIE[self::COOKIE_NAME] !== $id) {
            setcookie(self::COOKIE_NAME, $id, time() + YEAR_IN_SECONDS * 2, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE[self::COOKIE_NAME] = $id;
        }

        return sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
    }

    private function get_session_id() {
        if (!isset($_COOKIE[self::SESSION_COOKIE])) {
            $session = wp_generate_uuid4();
            setcookie(self::SESSION_COOKIE, $session, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE[self::SESSION_COOKIE] = $session;
        }
        return sanitize_text_field($_COOKIE[self::SESSION_COOKIE]);
    }

    public function ajax_track() {
        check_ajax_referer('gm2_analytics', 'nonce');
        $url        = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $referrer   = isset($_POST['referrer']) ? esc_url_raw(wp_unslash($_POST['referrer'])) : '';
        $event_type = isset($_POST['event_type']) ? sanitize_text_field(wp_unslash($_POST['event_type'])) : '';
        $duration   = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
        $element    = isset($_POST['element']) ? sanitize_text_field(wp_unslash($_POST['element'])) : '';
        // Pass event type through so special handling (e.g. 'duration') occurs in log_event.
        $this->log_event($url, $referrer, $event_type, $duration, $element);
        wp_send_json_success();
    }

    private function log_event($url, $referrer = '', $event_type = '', $duration = 0, $element = '') {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        if ($this->should_skip_logging($user_agent)) {
            return;
        }

        $user_id    = $this->get_user_id();
        $session_id = $this->get_session_id();
        $device     = wp_is_mobile() ? 'mobile' : 'desktop';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $ip         = $ip ? wp_privacy_anonymize_ip($ip) : '';

        if ($event_type === 'duration') {
            global $wpdb;
            $table = $wpdb->prefix . 'gm2_analytics_log';
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE session_id = %s AND url = %s AND event_type = 'pageview' ORDER BY `timestamp` DESC LIMIT 1",
                $session_id,
                $url
            ));
            if ($id) {
                $wpdb->update($table, [ 'duration' => $duration ], [ 'id' => $id ], [ '%d' ], [ '%d' ]);
            }
            return;
        }

        $this->insert_log([
            'session_id' => $session_id,
            'user_id'    => $user_id,
            'url'        => $url,
            'referrer'   => $referrer,
            'timestamp'  => current_time('mysql'),
            'user_agent' => $user_agent,
            'device'     => $device,
            'ip'         => $ip,
            'event_type' => $event_type,
            'duration'   => $duration,
            'element'    => $element,
        ]);
    }

    private function should_skip_logging($user_agent) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $bot_pattern = '/(bot|crawl|slurp|spider|facebookexternalhit|facebot|pingdom|crawler)/i';
        if ($user_agent && preg_match($bot_pattern, $user_agent)) {
            return true;
        }

        if (apply_filters('gm2_analytics_respect_dnt', true) && isset($_SERVER['HTTP_DNT']) && '1' === $_SERVER['HTTP_DNT']) {
            return true;
        }

        return false;
    }

    private function insert_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'gm2_analytics_log';
        $inserted = $wpdb->insert(
            $table,
            [
                'session_id' => $data['session_id'],
                'user_id'    => $data['user_id'],
                'url'        => $data['url'],
                'referrer'   => $data['referrer'],
                'timestamp'  => $data['timestamp'],
                'user_agent' => $data['user_agent'],
                'device'     => $data['device'],
                'ip'         => $data['ip'],
                'event_type' => $data['event_type'],
                'duration'   => $data['duration'],
                'element'    => $data['element'],
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        if (false === $inserted) {
            error_log($wpdb->last_error);
        }

        return $inserted;
    }
}
