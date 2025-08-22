<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts {
    /**
     * Logger instance.
     *
     * @var object|null
     */
    private static $logger = null;

    /**
     * Optionally inject a PSR-3 compatible logger.
     * Falls back to WooCommerce's logger when available.
     *
     * @param object|null $logger Logger implementing a log( level, message, context ) method.
     */
    public function __construct($logger = null) {
        if ($logger && method_exists($logger, 'log')) {
            self::$logger = $logger;
        } elseif (function_exists('wc_get_logger')) {
            self::$logger = wc_get_logger();
        }
    }

    public function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $carts = $wpdb->prefix . 'wc_ac_carts';
        $queue = $wpdb->prefix . 'wc_ac_email_queue';
        $recovered = $wpdb->prefix . 'wc_ac_recovered';
        $activity = $wpdb->prefix . 'wc_ac_cart_activity';
        $visit_log = $wpdb->prefix . 'wc_ac_visit_log';

        $carts_sql = "CREATE TABLE $carts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_token varchar(100) NOT NULL,
            client_id varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            session_start datetime DEFAULT NULL,
            abandoned_at datetime DEFAULT NULL,
            browsing_time bigint(20) unsigned DEFAULT 0,
            revisit_count int DEFAULT 0,
            recovered_order_id bigint(20) unsigned DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            location varchar(100) DEFAULT NULL,
            device varchar(20) DEFAULT NULL,
            entry_url text DEFAULT NULL,
            exit_url text DEFAULT NULL,
            cart_total decimal(10,2) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY cart_token (cart_token),
            KEY client_id (client_id),
            KEY ip_address (ip_address)
        ) $charset_collate;";

        $queue_sql = "CREATE TABLE $queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            send_at datetime NOT NULL,
            sent tinyint(1) NOT NULL DEFAULT 0,
            attempts int NOT NULL DEFAULT 0,
            message_type varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";

        $recovered_sql = "CREATE TABLE $recovered (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_token varchar(100) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            session_start datetime DEFAULT NULL,
            abandoned_at datetime DEFAULT NULL,
            browsing_time bigint(20) unsigned DEFAULT 0,
            revisit_count int DEFAULT 0,
            recovered_order_id bigint(20) unsigned DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            location varchar(100) DEFAULT NULL,
            device varchar(20) DEFAULT NULL,
            entry_url text DEFAULT NULL,
            exit_url text DEFAULT NULL,
            cart_total decimal(10,2) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY cart_token (cart_token)
        ) $charset_collate;";

        $activity_sql = "CREATE TABLE $activity (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            action varchar(20) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            sku varchar(100) DEFAULT NULL,
            quantity int NOT NULL DEFAULT 0,
            changed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";

        $visit_sql = "CREATE TABLE $visit_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            entry_url text NOT NULL,
            exit_url text DEFAULT NULL,
            visit_start datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            visit_end datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($carts_sql);
        dbDelta($queue_sql);
        dbDelta($recovered_sql);
        dbDelta($activity_sql);
        dbDelta($visit_sql);
    }

    public function run() {
        $this->maybe_install();
        add_action('template_redirect', [$this, 'maybe_set_entry_url']);
        add_action('shutdown', [$this, 'store_last_seen_url']);

        add_action('woocommerce_add_to_cart', [$this, 'capture_cart'], 10, 6);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'capture_cart']);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'capture_cart']);
        add_action('woocommerce_thankyou', [$this, 'mark_cart_recovered']);
        add_action('woocommerce_checkout_order_processed', [$this, 'mark_cart_recovered'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'mark_cart_recovered'], 10, 1);
        if (is_admin()) {
            add_action('wp_ajax_gm2_ac_get_activity', [ __CLASS__, 'gm2_ac_get_activity' ]);
        }
    }

    public function store_last_seen_url() {
        if (wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
            return;
        }
        $skip_admin = apply_filters('gm2_ac_skip_admin', true);
        if (
            $skip_admin &&
            function_exists('current_user_can') &&
            current_user_can('manage_options')
        ) {
            return;
        }
        if (!class_exists('WC') || !WC()->session) {
            return;
        }
        $current_url = esc_url_raw(
            home_url(
                wp_unslash($_SERVER['REQUEST_URI'] ?? '/')
            )
        );

        WC()->session->set('gm2_ac_last_seen_url', $current_url);
    }

    public function maybe_set_entry_url() {
        $skip_admin = apply_filters('gm2_ac_skip_admin', true);
        if (
            $skip_admin &&
            function_exists('current_user_can') &&
            current_user_can('manage_options')
        ) {
            return;
        }
        if (!class_exists('WC') || !WC()->session) {
            return;
        }
        $session_entry = WC()->session->get('gm2_entry_url');
        if (!empty($session_entry)) {
            return;
        }
        $current_url = esc_url_raw(
            home_url(
                wp_unslash($_SERVER['REQUEST_URI'] ?? '/')
            )
        );

        WC()->session->set('gm2_entry_url', $current_url);
        if (isset($_COOKIE['gm2_entry_url'])) {
            setcookie('gm2_entry_url', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            unset($_COOKIE['gm2_entry_url']);
        }

        $token = WC()->session->get_customer_id();
        if (empty($token)) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE cart_token = %s", $token));
        if ($row && empty($row->entry_url)) {
            $wpdb->update(
                $table,
                [ 'entry_url' => $current_url ],
                [ 'id' => $row->id ],
                [ '%s' ],
                [ '%d' ]
            );
        }
    }

    public function capture_cart() {
        // Developers can return false to include admin sessions for testing.
        $skip_admin = apply_filters('gm2_ac_skip_admin', true);
        if (
            $skip_admin &&
            function_exists('current_user_can') &&
            current_user_can('manage_options')
        ) {
            return;
        }
        if (!class_exists('WC_Cart')) {
            return;
        }
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        if ($cart->is_empty()) {
            return;
        }
        $agent      = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $snapshot   = self::build_cart_snapshot($agent);
        $cart_items = $snapshot['items'];
        if (empty($cart_items)) {
            return;
        }
        $device   = $snapshot['device'];
        $total    = $snapshot['total'];
        $cart_map = [];
        foreach ($cart_items as $item) {
            $cart_map[$item['id']] = [
                'qty' => $item['qty'],
                'sku' => $item['sku'] ?? '',
            ];
        }
        $contents   = wp_json_encode($cart_items);

        $wc = WC();
        if (!is_object($wc->session)) {
            return;
        }
        $token      = $wc->session->get_customer_id();
        $client_id  = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : (isset($_COOKIE['gm2_ac_client_id']) ? sanitize_text_field(wp_unslash($_COOKIE['gm2_ac_client_id'])) : '');
        $browser    = self::get_browser($agent);
        $ip_info    = self::get_ip_and_location();
        $ip         = $ip_info['ip'];
        $location   = $ip_info['location'];
        $current_url = esc_url_raw(
            home_url(
                wp_unslash($_SERVER['REQUEST_URI'] ?? '/')
            )
        );

        $stored_entry  = '';
        $session_entry = $wc->session->get('gm2_entry_url');
        if (!empty($session_entry)) {
            $stored_entry = esc_url_raw($session_entry);
            $wc->session->set('gm2_entry_url', null);
        } elseif (isset($_COOKIE['gm2_entry_url'])) {
            $stored_entry = esc_url_raw(wp_unslash($_COOKIE['gm2_entry_url']));
        }
        if (!empty($stored_entry)) {
            $current_url = $stored_entry;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        if (!empty($client_id)) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, entry_url, exit_url, abandoned_at, revisit_count, session_start, location, cart_contents FROM $table WHERE client_id = %s",
                    $client_id
                )
            );
            if (!$row) {
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, entry_url, exit_url, abandoned_at, revisit_count, session_start, location, cart_contents FROM $table WHERE cart_token = %s",
                        $token
                    )
                );
            }
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, entry_url, exit_url, abandoned_at, revisit_count, session_start, location, cart_contents FROM $table WHERE cart_token = %s",
                    $token
                )
            );
        }

        // Determine previous cart state
        $prev_map = [];
        if (method_exists($wc->session, 'get')) {
            $session_prev = $wc->session->get('gm2_ac_cart_snapshot');
            if (is_array($session_prev)) {
                $prev_map = $session_prev;
            }
        }
        if (empty($prev_map) && $row && !empty($row->cart_contents)) {
            $decoded = json_decode($row->cart_contents, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $pid = isset($item['id']) ? (int) $item['id'] : 0;
                    if ($pid) {
                        $prev_map[$pid] = [
                            'qty' => isset($item['qty']) ? (int) $item['qty'] : 0,
                            'sku' => $item['sku'] ?? '',
                        ];
                    }
                }
            }
        }

        // Detect changes between previous and current cart
        $changes = [];
        foreach ($cart_map as $pid => $data) {
            if (!isset($prev_map[$pid])) {
                $changes[] = [
                    'action'     => 'add',
                    'product_id' => $pid,
                    'sku'        => $data['sku'],
                    'quantity'   => $data['qty'],
                ];
            } elseif ((int) $data['qty'] !== (int) $prev_map[$pid]['qty']) {
                $changes[] = [
                    'action'     => 'quantity',
                    'product_id' => $pid,
                    'sku'        => $data['sku'],
                    'quantity'   => $data['qty'],
                ];
            }
        }
        foreach ($prev_map as $pid => $data) {
            if (!isset($cart_map[$pid])) {
                $changes[] = [
                    'action'     => 'remove',
                    'product_id' => $pid,
                    'sku'        => $data['sku'],
                    'quantity'   => 0,
                ];
            }
        }

        if (method_exists($wc->session, 'set')) {
            $wc->session->set('gm2_ac_cart_snapshot', $cart_map);
        }

        if ($row) {
            $update = [];
            $update_format = [];
            $update['cart_contents'] = $contents;
            $update_format[] = '%s';
            $update['created_at'] = current_time('mysql');
            $update_format[] = '%s';
            $update['ip_address'] = $ip;
            $update_format[] = '%s';
            $update['user_agent'] = $agent;
            $update_format[] = '%s';
            $update['browser'] = $browser;
            $update_format[] = '%s';
            $update['device'] = $device;
            $update_format[] = '%s';
            $update['cart_total'] = $total;
            $update_format[] = '%f';
            if (empty($row->location) && !empty($location)) {
                $update['location'] = $location;
                $update_format[] = '%s';
            }
            if ($row->abandoned_at) {
                $update['abandoned_at']  = null;
                $update_format[] = '%s';
                $update['session_start'] = current_time('mysql');
                $update_format[] = '%s';
                $update['revisit_count'] = (int) $row->revisit_count + 1;
                $update_format[] = '%d';
            } elseif (!$row->session_start) {
                $update['session_start'] = current_time('mysql');
                $update_format[] = '%s';
            }
            if (!empty($client_id)) {
                $update['client_id'] = $client_id;
                $update_format[] = '%s';
            }
            if (!empty($update)) {
                $wpdb->update($table, $update, ['id' => $row->id], $update_format, ['%d']);
            }
            $url_update = [];
            $url_format = [];
            if (empty($row->entry_url)) {
                $url_update['entry_url'] = $current_url;
                $url_format[] = '%s';
            }
            // Do not set exit_url here; it will be populated when the cart is abandoned.
            if (!empty($url_update)) {
                $wpdb->update($table, $url_update, ['id' => $row->id], $url_format, ['%d']);
            }
            $cart_id = $row->id;
        } else {
            $insert = [
                'cart_token'    => $token,
                'user_id'       => get_current_user_id(),
                'cart_contents' => $contents,
                'created_at'    => current_time('mysql'),
                'session_start' => current_time('mysql'),
                'ip_address'    => $ip,
                'user_agent'    => $agent,
                'browser'       => $browser,
                'location'      => $location,
                'device'        => $device,
                'entry_url'     => $current_url,
                // exit_url will be set when the cart is abandoned.
                'exit_url'      => '',
                'cart_total'    => $total,
                'browsing_time' => 0,
                'revisit_count' => 0,
            ];
            if (!empty($client_id)) {
                $insert['client_id'] = $client_id;
            }
            $format = [
                '%s', // cart_token
                '%d', // user_id
                '%s', // cart_contents
                '%s', // created_at
                '%s', // session_start
                '%s', // ip_address
                '%s', // user_agent
                '%s', // browser
                '%s', // location
                '%s', // device
                '%s', // entry_url
                '%s', // exit_url
                '%f', // cart_total
                '%d', // browsing_time
                '%d', // revisit_count
            ];
            if (!empty($client_id)) {
                $format[] = '%s';
            }
            $wpdb->insert($table, $insert, $format);
            $cart_id = $wpdb->insert_id;
        }

        // Log cart activity
        if (!empty($changes) && !empty($cart_id)) {
            $activity_table = $wpdb->prefix . 'wc_ac_cart_activity';
            foreach ($changes as $change) {
                $wpdb->insert(
                    $activity_table,
                    [
                        'cart_id'    => $cart_id,
                        'action'     => $change['action'],
                        'product_id' => $change['product_id'],
                        'sku'        => $change['sku'],
                        'quantity'   => $change['quantity'],
                        'changed_at' => current_time('mysql'),
                    ],
                    [ '%d', '%s', '%d', '%s', '%d', '%s' ]
                );
            }
        }
    }

    private static function log_no_cart($action) {
        $message = sprintf('Gm2 Abandoned Carts: %s called without cart token.', $action);
        self::log_failure('token', $message, 'warning', [ 'action' => $action ]);
    }
    private static function log_failure($type, $message, $level = 'error', array $context = []) {
        if ($level === 'debug' && get_option('gm2_ac_enable_logging', '0') !== '1') {
            return;
        }
        $logger = self::$logger;
        if ($logger && method_exists($logger, 'log')) {
            try {
                $logger->log($level, $message, array_merge(['type' => $type], $context));
            } catch (\Throwable $e) {
                error_log($message);
            }
        } else {
            error_log($message);
        }
        do_action('gm2_ac_debug', $type, $message);
        $counts = get_option('gm2_ac_failure_count', []);
        if (!is_array($counts)) {
            $counts = [];
        }
        $counts[$type] = isset($counts[$type]) ? (int) $counts[$type] + 1 : 1;
        update_option('gm2_ac_failure_count', $counts);
    }

    /**
     * Build a snapshot of the current cart.
     *
     * Returns cart line items, total, and detected device type. The snapshot
     * is safe to call even when WooCommerce or MobileDetect are unavailable,
     * and it gracefully handles empty carts.
     *
     * @param string $agent Optional user agent string.
     *
     * @return array{items:array,total:float,device:string}
     */
    private static function build_cart_snapshot($agent = '') {
        $cart_items = [];
        $total      = 0.0;
        $device     = 'Desktop';

        // Sanitize the provided user agent or pull from the current request.
        if ($agent === '' && isset($_SERVER['HTTP_USER_AGENT'])) {
            $agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        } else {
            $agent = sanitize_text_field($agent);
        }

        // Gather cart items and totals if WooCommerce is available.
        if (function_exists('WC')) {
            $wc   = WC();
            $cart = ($wc && isset($wc->cart)) ? $wc->cart : null;
            if ($cart && !$cart->is_empty()) {
                foreach ($cart->get_cart() as $item) {
                    $prod_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                    $qty     = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    $product = isset($item['data']) && is_object($item['data'])
                        ? $item['data']
                        : (function_exists('wc_get_product') ? wc_get_product($prod_id) : null);
                    $name  = $product ? $product->get_name() : 'Product #' . $prod_id;
                    $price = $product ? (float) $product->get_price() : 0;
                    $sku   = $product ? $product->get_sku() : '';
                    $cart_items[] = [
                        'id'    => $prod_id,
                        'name'  => $name,
                        'qty'   => $qty,
                        'price' => $price,
                        'sku'   => $sku,
                    ];
                }
                $total = (float) $cart->get_cart_contents_total();
            }
        }

        // Determine device type if MobileDetect is available.
        if (file_exists(GM2_PLUGIN_DIR . 'includes/MobileDetect.php')) {
            require_once GM2_PLUGIN_DIR . 'includes/MobileDetect.php';
            if (class_exists('Detection\\MobileDetect')) {
                $detect = new \Detection\MobileDetect();
            } elseif (class_exists('Mobile_Detect')) {
                $detect = new \Mobile_Detect();
            } else {
                $detect = null;
            }
            if ($detect) {
                $detect->setUserAgent($agent);
                if ($detect->isTablet()) {
                    $device = 'Tablet';
                } elseif ($detect->isMobile()) {
                    $device = 'Mobile';
                }
            }
        }

        return [
            'items'  => $cart_items,
            'total'  => $total,
            'device' => $device,
        ];
    }

    /**
     * Public wrapper for cart snapshots.
     *
     * @param string $agent Optional user agent string.
     * @return array{items:array,total:float,device:string}
     */
    public static function get_cart_snapshot($agent = '') {
        return self::build_cart_snapshot($agent);
    }

    public static function gm2_ac_mark_active() {
        // Developers can return false to include admin sessions for testing.
        $skip_admin = apply_filters('gm2_ac_skip_admin', true);
        if (
            $skip_admin &&
            function_exists('current_user_can') &&
            current_user_can('manage_options')
        ) {
            return wp_send_json_success();
        }
        if (!check_ajax_referer('gm2_ac_activity', 'nonce', false)) {
            self::log_failure('nonce', __FUNCTION__ . ': invalid nonce', 'warning', [ 'action' => __FUNCTION__ ]);
            return wp_send_json_error('invalid_nonce');
        }

        $url       = esc_url_raw($_POST['url'] ?? '');
        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $token     = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
            if (method_exists(WC()->session, 'set')) {
                // store the most recent URL in the session for later retrieval
                WC()->session->set('gm2_ac_last_seen_url', $url);
                $session_entry = method_exists(WC()->session, 'get') ? WC()->session->get('gm2_entry_url') : '';
                if (empty($session_entry) && !empty($url)) {
                    WC()->session->set('gm2_entry_url', $url);
                    if (isset($_COOKIE['gm2_entry_url'])) {
                        setcookie('gm2_entry_url', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                        unset($_COOKIE['gm2_entry_url']);
                    }
                }
            }
        }
        if (empty($token) && empty($client_id)) {
            self::log_no_cart(__FUNCTION__);
            return wp_send_json_error('no_cart');
        }

        // Build a snapshot of the current cart and visitor details.
        $agent     = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $snapshot  = self::build_cart_snapshot($agent);
        $cart_items = $snapshot['items'];
        $total      = $snapshot['total'];
        $device     = $snapshot['device'];
        $contents   = wp_json_encode($cart_items);

        $browser  = self::get_browser($agent);
        $ip_info  = self::get_ip_and_location();
        $ip       = $ip_info['ip'];
        $location = $ip_info['location'];

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        if (!empty($client_id)) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, abandoned_at, revisit_count, session_start, ip_address, location FROM $table WHERE client_id = %s", $client_id));
            if (!$row && !empty($token)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, abandoned_at, revisit_count, session_start, ip_address, location FROM $table WHERE cart_token = %s", $token));
            }
        } else {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, abandoned_at, revisit_count, session_start, ip_address, location FROM $table WHERE cart_token = %s", $token));
        }
        $cart_id = $row ? (int) $row->id : 0;
        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }
        if ($row) {
            $update = [];
            $update_format = [];
            $update['cart_contents'] = $contents; $update_format[] = '%s';
            $update['cart_total'] = $total; $update_format[] = '%f';
            $update['user_agent'] = $agent; $update_format[] = '%s';
            $update['browser'] = $browser; $update_format[] = '%s';
            $update['device'] = $device; $update_format[] = '%s';
            if (!empty($ip)) { $update['ip_address'] = $ip; $update_format[] = '%s'; }
            if (!empty($location)) { $update['location'] = $location; $update_format[] = '%s'; }

            $new_session = false;
            if ($row->abandoned_at) {
                $update['abandoned_at'] = null; $update_format[] = '%s';
                $update['session_start'] = current_time('mysql'); $update_format[] = '%s';
                $update['revisit_count'] = (int) $row->revisit_count + 1; $update_format[] = '%d';
                $new_session = true;
            } else {
                $session_time = $row->session_start ? strtotime($row->session_start) : 0;
                $threshold    = current_time('timestamp') - $minutes * MINUTE_IN_SECONDS;
                if (!$row->session_start || $session_time <= $threshold) {
                    $update['session_start'] = current_time('mysql'); $update_format[] = '%s';
                    if ($row->session_start) {
                        $update['revisit_count'] = (int) $row->revisit_count + 1; $update_format[] = '%d';
                    }
                    $new_session = true;
                }
            }

            if ($new_session) {
                $update['entry_url'] = $url; $update_format[] = '%s';
                // exit_url will be populated upon abandonment of this new session.
                $update['exit_url'] = ''; $update_format[] = '%s';
            }

            if (!empty($client_id)) { $update['client_id'] = $client_id; $update_format[] = '%s'; }
            if (!empty($update)) {
                $wpdb->update($table, $update, ['id' => $row->id], $update_format, ['%d']);
            }
        } else {
            $insert = [
                'cart_token'    => $token,
                'user_id'       => get_current_user_id(),
                'cart_contents' => $contents,
                'created_at'    => current_time('mysql'),
                'session_start' => current_time('mysql'),
                'ip_address'    => $ip,
                'user_agent'    => $agent,
                'browser'       => $browser,
                'location'      => $location,
                'device'        => $device,
                'entry_url'     => $url,
                // exit_url will be populated when the cart is actually abandoned.
                'exit_url'      => '',
                'cart_total'    => $total,
                'browsing_time' => 0,
                'revisit_count' => 0,
            ];
            if (!empty($client_id)) { $insert['client_id'] = $client_id; }
            $format = [
                '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%d','%d'
            ];
            if (!empty($client_id)) { $format[] = '%s'; }
            $wpdb->insert($table, $insert, $format);
            $cart_id     = $wpdb->insert_id;
            $new_session = true;
        }

        if ($new_session && $cart_id) {
            $visit_table = $wpdb->prefix . 'wc_ac_visit_log';
            $wpdb->insert(
                $visit_table,
                [
                    'cart_id'     => $cart_id,
                    'entry_url'   => $url,
                    'visit_start' => current_time('mysql'),
                ],
                [ '%d', '%s', '%s' ]
            );
        }

        return wp_send_json_success();
    }

    public static function gm2_ac_mark_abandoned() {
        // Developers can return false to include admin sessions for testing.
        $skip_admin = apply_filters('gm2_ac_skip_admin', true);
        if (
            $skip_admin &&
            function_exists('current_user_can') &&
            current_user_can('manage_options')
        ) {
            return wp_send_json_success();
        }
        if (!check_ajax_referer('gm2_ac_activity', 'nonce', false)) {
            self::log_failure('nonce', __FUNCTION__ . ': invalid nonce', 'warning', [ 'action' => __FUNCTION__ ]);
            return wp_send_json_error('invalid_nonce');
        }

        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $token     = '';
        $url       = esc_url_raw($_POST['url'] ?? '');
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
            if (empty($url) && method_exists(WC()->session, 'get')) {
                $url = WC()->session->get('gm2_ac_last_seen_url');
            }
            if (method_exists(WC()->session, 'set')) {
                WC()->session->set('gm2_ac_last_seen_url', null);
            }
        }
        if (empty($token) && empty($client_id)) {
            self::log_no_cart(__FUNCTION__);
            return wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        if (!empty($client_id)) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE client_id = %s", $client_id));
            if (!$row && !empty($token)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE cart_token = %s", $token));
            }
        } else {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE cart_token = %s", $token));
        }
        if ($row) {
            $update = [];
            $update_format = [];
            $update['abandoned_at'] = current_time('mysql'); $update_format[] = '%s';
            $update['session_start'] = null; $update_format[] = '%s';
            if (!empty($url)) { $update['exit_url'] = $url; $update_format[] = '%s'; }
            if ($row->session_start) {
                $elapsed = current_time('timestamp') - strtotime($row->session_start);
                if ($elapsed < 0) { $elapsed = 0; }
                $update['browsing_time'] = (int) $row->browsing_time + $elapsed; $update_format[] = '%d';
            }
            if (!empty($client_id)) { $update['client_id'] = $client_id; $update_format[] = '%s'; }
            $wpdb->update($table, $update, [ 'id' => $row->id ], $update_format, [ '%d' ]);

            $log_table = $wpdb->prefix . 'wc_ac_visit_log';
            $log_row   = $wpdb->get_row($wpdb->prepare("SELECT id FROM $log_table WHERE cart_id = %d ORDER BY visit_start DESC LIMIT 1", $row->id));
            if ($log_row) {
                $log_update = [ 'visit_end' => current_time('mysql') ];
                $log_format = [ '%s' ];
                if (!empty($url)) { $log_update['exit_url'] = $url; $log_format[] = '%s'; }
                $wpdb->update($log_table, $log_update, [ 'id' => $log_row->id ], $log_format, [ '%d' ]);
            }
        }

        return wp_send_json_success();
    }

    /**
     * Return cart activity and visit logs.
     *
     * Accepts paging arguments to limit the returned number of rows.
     *
     * @param int $_POST['page']     Optional page number starting from 1.
     * @param int $_POST['per_page'] Optional number of items per page.
     *
     * @return void
     */
    public static function gm2_ac_get_activity() {
        if (!check_ajax_referer('gm2_ac_get_activity', 'nonce', false)) {
            self::log_failure('nonce', __FUNCTION__ . ': invalid nonce', 'warning', [ 'action' => __FUNCTION__ ]);
            return wp_send_json_error('invalid_nonce');
        }
        if (!current_user_can('manage_options')) {
            return wp_send_json_error('no_permission');
        }
        $ip        = isset($_POST['ip']) ? sanitize_text_field(wp_unslash($_POST['ip'])) : '';
        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $cart_id   = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
        $page      = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $per_page  = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 50;
        $offset    = ($page - 1) * $per_page;

        global $wpdb;
        $activity_table = $wpdb->prefix . 'wc_ac_cart_activity';
        $visit_table    = $wpdb->prefix . 'wc_ac_visit_log';
        $carts_table    = $wpdb->prefix . 'wc_ac_carts';
        if ($cart_id) {
            $sql        = "SELECT action, sku, quantity, changed_at FROM $activity_table WHERE cart_id = %d ORDER BY changed_at DESC LIMIT %d OFFSET %d";
            $rows       = $wpdb->get_results($wpdb->prepare($sql, $cart_id, $per_page, $offset));
            $visit_sql  = "SELECT entry_url, exit_url, visit_start, visit_end FROM $visit_table WHERE cart_id = %d ORDER BY visit_start DESC LIMIT %d OFFSET %d";
            $visit_rows = $wpdb->get_results($wpdb->prepare($visit_sql, $cart_id, $per_page, $offset));
        } elseif ($client_id !== '') {
            $sql = "SELECT a.action, a.sku, a.quantity, a.changed_at FROM $activity_table a INNER JOIN $carts_table c ON a.cart_id = c.id WHERE c.client_id = %s ORDER BY a.changed_at DESC LIMIT %d OFFSET %d";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $client_id, $per_page, $offset));
            $visit_sql = "SELECT v.entry_url, v.exit_url, v.visit_start, v.visit_end FROM $visit_table v INNER JOIN $carts_table c ON v.cart_id = c.id WHERE c.client_id = %s ORDER BY v.visit_start DESC LIMIT %d OFFSET %d";
            $visit_rows = $wpdb->get_results($wpdb->prepare($visit_sql, $client_id, $per_page, $offset));
        } elseif ($ip !== '') {
            $sql = "SELECT a.action, a.sku, a.quantity, a.changed_at FROM $activity_table a INNER JOIN $carts_table c ON a.cart_id = c.id WHERE c.ip_address = %s ORDER BY a.changed_at DESC LIMIT %d OFFSET %d";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $ip, $per_page, $offset));
            $visit_sql = "SELECT v.entry_url, v.exit_url, v.visit_start, v.visit_end FROM $visit_table v INNER JOIN $carts_table c ON v.cart_id = c.id WHERE c.ip_address = %s ORDER BY v.visit_start DESC LIMIT %d OFFSET %d";
            $visit_rows = $wpdb->get_results($wpdb->prepare($visit_sql, $ip, $per_page, $offset));
        } else {
            $rows       = [];
            $visit_rows = [];
        }
        $data = [];
        if ($rows) {
            foreach ($rows as $row) {
                $data[] = [
                    'action'     => $row->action,
                    'sku'        => $row->sku,
                    'quantity'   => (int) $row->quantity,
                    'changed_at' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row->changed_at),
                ];
            }
        }

        $visit_data = [];
        if ($visit_rows) {
            $dt_format = get_option('date_format') . ' ' . get_option('time_format');
            foreach ($visit_rows as $vrow) {
                $visit_data[] = [
                    'entry_url'   => $vrow->entry_url,
                    'exit_url'    => $vrow->exit_url,
                    'visit_start' => mysql2date($dt_format, $vrow->visit_start),
                    'visit_end'   => $vrow->visit_end ? mysql2date($dt_format, $vrow->visit_end) : '',
                ];
            }
        }
        wp_send_json_success([
            'activity' => $data,
            'visits'   => $visit_data,
        ]);
    }

    public function mark_cart_recovered($order_id) {
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $token = WC()->session->get_customer_id();
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $carts_table WHERE cart_token = %s", $token),
            ARRAY_A
        );
        if ($row) {
            if (empty($row['email'])) {
                $row['email'] = $order->get_billing_email();
            }
            if (empty($row['location'])) {
                $country = $order->get_billing_country();
                $state   = $order->get_billing_state();
                $row['location'] = $country;
                if (!empty($state)) {
                    $row['location'] .= '-' . $state;
                }
            }
            $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
            $row['recovered_order_id'] = $order_id;
            $wpdb->insert($recovered_table, $row, self::build_format_from_data($row));
            $wpdb->delete($carts_table, [ 'id' => $row['id'] ]);
        }
    }

    public function migrate_recovered_carts() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
        $rows = $wpdb->get_results("SELECT * FROM $carts_table WHERE recovered_order_id IS NOT NULL");
        $count = 0;
        if ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $wpdb->insert($recovered_table, $data, self::build_format_from_data($data));
                $wpdb->delete($carts_table, [ 'id' => $row->id ]);
                $count++;
            }
        }
        return $count;
    }

    private function maybe_install() {
        global $wpdb;
        $carts_table     = $wpdb->prefix . 'wc_ac_carts';
        $queue_table     = $wpdb->prefix . 'wc_ac_email_queue';
        $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
        $activity_table  = $wpdb->prefix . 'wc_ac_cart_activity';
        $carts_exists    = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $carts_table));
        $queue_exists    = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
        $recovered_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $recovered_table));
        $activity_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $activity_table));
        if (
            $carts_exists !== $carts_table ||
            $queue_exists !== $queue_table ||
            $recovered_exists !== $recovered_table ||
            $activity_exists !== $activity_table
        ) {
            if (
                $carts_exists === $carts_table &&
                $queue_exists === $queue_table &&
                $recovered_exists === $recovered_table &&
                $activity_exists !== $activity_table
            ) {
                $charset_collate = $wpdb->get_charset_collate();
                $activity_sql = "CREATE TABLE $activity_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            action varchar(20) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            sku varchar(100) DEFAULT NULL,
            quantity int NOT NULL DEFAULT 0,
            changed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($activity_sql);
            } else {
                $this->install();
            }
            return;
        }

        $has_client_id = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'client_id'));
        if (!$has_client_id) {
            $wpdb->query("ALTER TABLE $carts_table ADD client_id varchar(100) DEFAULT NULL AFTER cart_token");
            $wpdb->query("ALTER TABLE $carts_table ADD KEY client_id (client_id)");
        }

        $has_browser = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'browser'));
        if (!$has_browser) {
            $wpdb->query("ALTER TABLE $carts_table ADD browser varchar(50) DEFAULT NULL AFTER user_agent");
        }
        $has_session_start = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'session_start'));
        if (!$has_session_start) {
            $wpdb->query("ALTER TABLE $carts_table ADD session_start datetime DEFAULT NULL AFTER created_at");
        }
        $has_browsing_time = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'browsing_time'));
        if (!$has_browsing_time) {
            $wpdb->query("ALTER TABLE $carts_table ADD browsing_time bigint(20) unsigned DEFAULT 0 AFTER exit_url");
        }
        $has_revisit_count = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'revisit_count'));
        if (!$has_revisit_count) {
            $wpdb->query("ALTER TABLE $carts_table ADD revisit_count int DEFAULT 0 AFTER browsing_time");
        }

        $has_phone = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'phone'));
        if (!$has_phone) {
            $wpdb->query("ALTER TABLE $carts_table ADD phone varchar(50) DEFAULT NULL AFTER email");
        }

        $rec_has_phone = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $recovered_table LIKE %s", 'phone'));
        if (!$rec_has_phone) {
            $wpdb->query("ALTER TABLE $recovered_table ADD phone varchar(50) DEFAULT NULL AFTER email");
        }

        $has_attempts = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $queue_table LIKE %s", 'attempts'));
        if (!$has_attempts) {
            $wpdb->query("ALTER TABLE $queue_table ADD attempts int NOT NULL DEFAULT 0 AFTER sent");
        }
    }

    private static function build_format_from_data($data) {
        $format = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $format[] = '%d';
            } elseif (is_float($value)) {
                $format[] = '%f';
            } else {
                $format[] = '%s';
            }
        }
        return $format;
    }

    public static function get_ip_and_location() {
        $ip = '';
        if (function_exists('wc_get_user_ip')) {
            $ip = wc_get_user_ip();
        } elseif (class_exists('WC_Geolocation')) {
            $ip = \WC_Geolocation::get_ip_address();
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        $ip = sanitize_text_field(wp_unslash($ip));
        $location = '';

        if (!empty($ip)) {
            $key      = 'gm2_ac_geo_' . md5($ip);
            $cached   = get_transient($key);
            if (false !== $cached) {
                return [ 'ip' => $ip, 'location' => $cached ];
            }
        }

        if (class_exists('WC_Geolocation') && !empty($ip)) {
            $geo = \WC_Geolocation::geolocate_ip($ip, false, false);
            if (!empty($geo['country'])) {
                $location = $geo['country'];
                if (!empty($geo['state'])) {
                    $location .= '-' . $geo['state'];
                }
            }
        }
        if (empty($location)) {
            if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
                $location = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']));
            } elseif (!empty($ip)) {
                $response = wp_safe_remote_get(
                    'https://ipapi.co/' . rawurlencode($ip) . '/json/',
                    ['timeout' => 5]
                );
                if (
                    !is_wp_error($response) &&
                    200 === wp_remote_retrieve_response_code($response)
                ) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    if (!empty($data['country'])) {
                        $location = sanitize_text_field($data['country']);
                        $state = $data['region_code'] ?? '';
                        if (empty($state) && !empty($data['region'])) {
                            $state = $data['region'];
                        }
                        $state = sanitize_text_field($state);
                        if (!empty($state)) {
                            $location .= '-' . $state;
                        }
                    }
                }
            }
        }
        if (empty($location)) {
            $location = 'Unknown';
        }

        if (!empty($ip)) {
            $key      = 'gm2_ac_geo_' . md5($ip);
            $lifetime = (int) apply_filters('gm2_abandoned_carts_ip_cache_lifetime', DAY_IN_SECONDS);
            set_transient($key, $location, $lifetime);
        }

        return [ 'ip' => $ip, 'location' => $location ];
    }

    public static function get_browser($agent) {
        $browsers = [
            'Edge' => 'Edge',
            'OPR' => 'Opera',
            'Chrome' => 'Chrome',
            'Safari' => 'Safari',
            'Firefox' => 'Firefox',
            'MSIE' => 'IE',
            'Trident/7.0' => 'IE'
        ];
        foreach ($browsers as $key => $name) {
            if (stripos($agent, $key) !== false) {
                return $name;
            }
        }
        return 'Unknown';
    }

    /**
     * Schedule the recurring cron event used to mark carts abandoned.
     */
    public static function schedule_event() {
        if (get_option('gm2_enable_abandoned_carts', '0') !== '1') {
            return;
        }
        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }

        $hook     = 'gm2_ac_mark_abandoned_cron';
        $schedule = 'gm2_ac_' . $minutes . '_mins';
        $next     = wp_next_scheduled($hook);

        // If the event is missing or on a different interval, reschedule it.
        if (!$next || wp_get_schedule($hook) !== $schedule) {
            if ($next) {
                wp_unschedule_event($next, $hook);
            }
            wp_schedule_event(time(), $schedule, $hook);
        }
    }

    /**
     * Cron callback to mark carts as abandoned after the configured interval.
     */
    public static function cron_mark_abandoned() {
        if (get_option('gm2_enable_abandoned_carts', '0') !== '1') {
            return;
        }
        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }
        $threshold = gmdate('Y-m-d H:i:s', current_time('timestamp') - $minutes * MINUTE_IN_SECONDS);
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE abandoned_at IS NULL AND session_start IS NOT NULL AND session_start <= %s", $threshold));
        if ($rows) {
            foreach ($rows as $row) {
                $update = [
                    'abandoned_at' => current_time('mysql'),
                    'session_start' => null,
                ];
                $format = [ '%s', '%s' ];
                if ($row->session_start) {
                    $elapsed = current_time('timestamp') - strtotime($row->session_start);
                    if ($elapsed < 0) {
                        $elapsed = 0;
                    }
                    $update['browsing_time'] = (int) $row->browsing_time + $elapsed;
                    $format[] = '%d';
                }
                $wpdb->update($table, $update, ['id' => $row->id], $format, ['%d']);
            }
        }
    }

    /**
     * Clear the scheduled cron event for marking carts abandoned.
     */
    public static function clear_scheduled_event() {
        if (wp_next_scheduled('gm2_ac_mark_abandoned_cron')) {
            wp_clear_scheduled_hook('gm2_ac_mark_abandoned_cron');
        }
    }
}

add_action('gm2_ac_mark_abandoned_cron', [Gm2_Abandoned_Carts::class, 'cron_mark_abandoned']);
