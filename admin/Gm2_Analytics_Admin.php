<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Analytics_Admin {
    private $data = [];
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
    }

    public function add_menu() {
        add_menu_page(
            esc_html__( 'Analytics', 'gm2-wordpress-suite' ),
            esc_html__( 'Analytics', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-analytics',
            [ $this, 'display_page' ],
            'dashicons-chart-area'
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_gm2-analytics' ) {
            return;
        }
        $this->data = $this->get_analytics_data();
        wp_enqueue_script(
            'gm2-analytics',
            GM2_PLUGIN_URL . 'admin/js/gm2-analytics.js',
            [ 'jquery' ],
            file_exists( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) ? filemtime( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) : GM2_VERSION,
            true
        );
        wp_localize_script('gm2-analytics', 'gm2SiteAnalytics', $this->data);
    }

    public function display_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        if (empty($this->data)) {
            $this->data = $this->get_analytics_data();
        }
        $data = $this->data;
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<p>' . sprintf(esc_html__( 'Live users (last 5 minutes): %d', 'gm2-wordpress-suite' ), intval($data['live'])) . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="gm2-analytics" />';
        echo '<label>' . esc_html__('Start', 'gm2-wordpress-suite') . ': <input type="date" name="start" value="' . esc_attr($data['start']) . '" /></label> ';
        echo '<label>' . esc_html__('End', 'gm2-wordpress-suite') . ': <input type="date" name="end" value="' . esc_attr($data['end']) . '" /></label> ';
        submit_button(esc_html__('Filter', 'gm2-wordpress-suite'), 'secondary', '', false);
        echo '</form>';
        echo '<p>' . sprintf(esc_html__( 'Sessions: %d', 'gm2-wordpress-suite' ), intval($data['total_sessions'])) . ' | ';
        echo sprintf(esc_html__( 'Users: %d', 'gm2-wordpress-suite' ), intval($data['total_users'])) . ' | ';
        echo sprintf(esc_html__( 'Bounce Rate: %s%%', 'gm2-wordpress-suite' ), esc_html($data['bounce_rate'])) . ' | ';
        echo sprintf(esc_html__( 'Avg Session Duration: %ss', 'gm2-wordpress-suite' ), esc_html($data['avg_duration'])) . '</p>';
        echo '<canvas id="gm2-sessions-chart" width="400" height="200" aria-hidden="true"></canvas>';
        echo '<canvas id="gm2-device-chart" width="400" height="200" aria-hidden="true"></canvas>';
        if (!empty($data['locations'])) {
            echo '<h2>' . esc_html__('Top Locations', 'gm2-wordpress-suite') . '</h2><ul>';
            foreach ($data['locations'] as $loc => $count) {
                echo '<li>' . esc_html($loc) . ' - ' . intval($count) . '</li>';
            }
            echo '</ul>';
        }
        $this->render_activity_log($data);
        echo '</div>';
    }

    private function render_activity_log($data) {
        global $wpdb;

        // Verify nonce before processing any filter parameters.
        if ( isset($_GET['log_user']) || isset($_GET['log_device']) || isset($_GET['paged']) ) {
            $nonce = isset($_GET['gm2_activity_log_nonce']) ? sanitize_text_field( wp_unslash($_GET['gm2_activity_log_nonce']) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'gm2_activity_log_filter' ) ) {
                return;
            }
        }

        $table = $wpdb->prefix . 'gm2_analytics_log';
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;
        $where  = $wpdb->prepare("`timestamp` BETWEEN %s AND %s", $data['start'] . ' 00:00:00', $data['end'] . ' 23:59:59');
        $user_filter = isset($_GET['log_user']) ? sanitize_text_field(wp_unslash($_GET['log_user'])) : '';
        $device_filter = isset($_GET['log_device']) ? sanitize_text_field(wp_unslash($_GET['log_device'])) : '';
        if ($user_filter !== '') {
            $where .= $wpdb->prepare(" AND user_id = %s", $user_filter);
        }
        if ($device_filter !== '') {
            $where .= $wpdb->prepare(" AND device = %s", $device_filter);
        }
        $logs = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY `timestamp` DESC LIMIT $per_page OFFSET $offset");
        echo '<h2>' . esc_html__('Activity Log', 'gm2-wordpress-suite') . '</h2>';
        echo '<form method="get"><input type="hidden" name="page" value="gm2-analytics" />';
        wp_nonce_field('gm2_activity_log_filter', 'gm2_activity_log_nonce');
        echo '<input type="hidden" name="start" value="' . esc_attr($data['start']) . '" />';
        echo '<input type="hidden" name="end" value="' . esc_attr($data['end']) . '" />';
        echo '<label>' . esc_html__('User', 'gm2-wordpress-suite') . ': <input type="text" name="log_user" value="' . esc_attr($user_filter) . '" /></label> ';
        echo '<label>' . esc_html__('Device', 'gm2-wordpress-suite') . ': <select name="log_device"><option value="">' . esc_html__('All', 'gm2-wordpress-suite') . '</option><option value="desktop"' . selected($device_filter, 'desktop', false) . '>' . esc_html__('Desktop', 'gm2-wordpress-suite') . '</option><option value="mobile"' . selected($device_filter, 'mobile', false) . '>' . esc_html__('Mobile', 'gm2-wordpress-suite') . '</option></select></label> ';
        submit_button(esc_html__('Apply', 'gm2-wordpress-suite'), 'secondary', '', false);
        echo '</form>';
        echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__('Time', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Session', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('User', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Device', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('URL', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Referrer', 'gm2-wordpress-suite') . '</th></tr></thead><tbody>';
        if ($logs) {
            foreach ($logs as $log) {
                echo '<tr><td>' . esc_html($log->timestamp) . '</td><td>' . esc_html($log->session_id) . '</td><td>' . esc_html($log->user_id) . '</td><td>' . esc_html($log->device) . '</td><td>' . esc_html($log->url) . '</td><td>' . esc_html($log->referrer) . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="6">' . esc_html__('No activity found.', 'gm2-wordpress-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            $base_url = remove_query_arg('paged');
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = esc_url(add_query_arg('paged', $i, $base_url));
                if ($i === $paged) {
                    echo '<span class="tablenav-page current">' . $i . '</span> ';
                } else {
                    echo '<a class="tablenav-page" href="' . $url . '">' . $i . '</a> ';
                }
            }
            echo '</div></div>';
        }
    }

    private function get_analytics_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'gm2_analytics_log';
        $start = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : gmdate('Y-m-d', strtotime('-7 days'));
        $end   = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : gmdate('Y-m-d');
        $start_time = $start . ' 00:00:00';
        $end_time   = $end . ' 23:59:59';
        $now = gmdate('Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS);
        $live = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM $table WHERE `timestamp` >= %s", $now));
        $total_sessions = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM $table WHERE `timestamp` BETWEEN %s AND %s", $start_time, $end_time));
        $total_users = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM $table WHERE `timestamp` BETWEEN %s AND %s", $start_time, $end_time));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(`timestamp`) as day, COUNT(DISTINCT session_id) as sessions FROM $table WHERE `timestamp` BETWEEN %s AND %s GROUP BY day ORDER BY day ASC", $start_time, $end_time), ARRAY_A);
        $dates = $sessions = [];
        foreach ($rows as $r) {
            $dates[] = $r['day'];
            $sessions[] = (int) $r['sessions'];
        }
        $device_rows = $wpdb->get_results($wpdb->prepare("SELECT device, COUNT(DISTINCT session_id) as sessions FROM $table WHERE `timestamp` BETWEEN %s AND %s GROUP BY device", $start_time, $end_time), ARRAY_A);
        $device_labels = $device_counts = [];
        foreach ($device_rows as $r) {
            $device_labels[] = $r['device'];
            $device_counts[] = (int) $r['sessions'];
        }
        $session_rows = $wpdb->get_results($wpdb->prepare("SELECT session_id, COUNT(*) as hits, MIN(`timestamp`) as start_time, MAX(`timestamp`) as end_time FROM $table WHERE `timestamp` BETWEEN %s AND %s GROUP BY session_id", $start_time, $end_time), ARRAY_A);
        $bounce = 0;
        $durations = [];
        foreach ($session_rows as $s) {
            if ((int) $s['hits'] === 1) {
                $bounce++;
            }
            $durations[] = strtotime($s['end_time']) - strtotime($s['start_time']);
        }
        $bounce_rate = $total_sessions > 0 ? round($bounce / $total_sessions * 100, 2) : 0;
        $avg_duration = !empty($durations) ? round(array_sum($durations) / count($durations)) : 0;
        $ips = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT ip FROM $table WHERE `timestamp` BETWEEN %s AND %s", $start_time, $end_time));
        $locations = [];
        foreach ($ips as $ip) {
            $country = $this->get_ip_country($ip);
            if ('' === $country) {
                $country = esc_html__('Unknown', 'gm2-wordpress-suite');
            }
            if (!isset($locations[$country])) {
                $locations[$country] = 0;
            }
            $locations[$country]++;
        }
        return [
            'live' => $live,
            'total_sessions' => $total_sessions,
            'total_users' => $total_users,
            'dates' => $dates,
            'sessions' => $sessions,
            'device_labels' => $device_labels,
            'device_counts' => $device_counts,
            'bounce_rate' => $bounce_rate,
            'avg_duration' => $avg_duration,
            'locations' => $locations,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function get_ip_country($ip) {
        $key = 'gm2_geo_' . md5($ip);
        $country = get_transient($key);
        if (false !== $country) {
            return $country;
        }
        $response = wp_remote_get('https://ipapi.co/' . rawurlencode($ip) . '/country_name/');
        if (is_wp_error($response)) {
            return '';
        }
        $country = sanitize_text_field(wp_remote_retrieve_body($response));
        set_transient($key, $country, DAY_IN_SECONDS);
        return $country;
    }
}
