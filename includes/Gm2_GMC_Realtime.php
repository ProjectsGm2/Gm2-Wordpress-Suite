<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles real-time Google Merchant Centre data updates.
 */
class Gm2_GMC_Realtime {
    /**
     * Fields that should be updated in real time.
     *
     * @var array
     */
    public const REALTIME_FIELDS = ['price', 'availability', 'inventory'];

    /**
     * Bootstraps hooks.
     */
    public function run() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('updated_postmeta', [$this, 'maybe_record_update'], 10, 4);
    }

    /**
     * Returns the list of real-time fields.
     *
     * @return array
     */
    public static function get_fields() {
        return apply_filters('gm2_gmc_realtime_fields', self::REALTIME_FIELDS);
    }

    /**
     * Registers REST API routes used for updates.
     */
    public function register_routes() {
        register_rest_route(
            'gm2/v1',
            '/gmc/realtime',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_updates'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Returns the latest data for the defined fields.
     *
     * @return \WP_REST_Response
     */
    public function get_updates() {
        $data = get_option('gm2_gmc_realtime', []);
        return rest_ensure_response($data);
    }

    /**
     * Stores new values when relevant product meta changes.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $object_id  Object ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function maybe_record_update($meta_id, $object_id, $meta_key, $meta_value) {
        if (get_post_type($object_id) !== 'product') {
            return;
        }
        $fields = self::get_fields();
        if (!in_array($meta_key, $fields, true)) {
            return;
        }
        $data = get_option('gm2_gmc_realtime', []);
        if (!isset($data[$object_id])) {
            $data[$object_id] = [];
        }
        $data[$object_id][$meta_key] = $meta_value;
        update_option('gm2_gmc_realtime', $data);
    }
}
