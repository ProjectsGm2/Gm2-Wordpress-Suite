<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Audit_Log {
    public static function init() {
        add_action('updated_post_meta', [__CLASS__, 'log_update'], 10, 4);
        add_action('added_post_meta', [__CLASS__, 'log_add'], 10, 4);
        add_action('deleted_post_meta', [__CLASS__, 'log_delete'], 10, 4);
        add_action('gm2_audit_purge', [__CLASS__, 'purge']);
        self::schedule_purge();
    }

    /**
     * Create table and schedule purge on install.
     */
    public static function install() {
        self::maybe_create_table();
        self::schedule_purge();
    }

    protected static function table() {
        global $wpdb;
        return $wpdb->prefix . 'gm2_audit_log';
    }

    protected static function maybe_create_table() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                object_id bigint(20) unsigned NOT NULL,
                meta_key varchar(255) NOT NULL,
                old_value longtext NULL,
                new_value longtext NULL,
                user_id bigint(20) unsigned NOT NULL,
                changed_at datetime NOT NULL,
                pii tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY object_id (object_id)
            ) $charset_collate;";
            dbDelta($sql);
        }
    }

    public static function log_change($object_id, $meta_key, $old, $new) {
        self::maybe_create_table();
        global $wpdb;
        $pii_fields = get_option('gm2_pii_fields', []);
        $pii = in_array($meta_key, $pii_fields, true) ? 1 : 0;
        $wpdb->insert(self::table(), [
            'object_id' => $object_id,
            'meta_key' => $meta_key,
            'old_value' => maybe_serialize($old),
            'new_value' => maybe_serialize($new),
            'user_id' => get_current_user_id(),
            'changed_at' => current_time('mysql'),
            'pii' => $pii,
        ]);
    }

    public static function log_update($meta_id, $object_id, $meta_key, $_meta_value) {
        $old = get_post_meta($object_id, $meta_key, true);
        self::log_change($object_id, $meta_key, $old, $_meta_value);
    }

    public static function log_add($meta_id, $object_id, $meta_key, $_meta_value) {
        self::log_change($object_id, $meta_key, '', $_meta_value);
    }

    public static function log_delete($meta_id, $object_id, $meta_key, $_meta_value) {
        self::log_change($object_id, $meta_key, $_meta_value, '');
    }

    public static function tag_field_as_pii($field_key) {
        $fields = get_option('gm2_pii_fields', []);
        if (!in_array($field_key, $fields, true)) {
            $fields[] = $field_key;
            update_option('gm2_pii_fields', $fields);
        }
    }

    public static function purge() {
        global $wpdb;
        $days = absint(get_option('gm2_audit_log_retention_days', 30));
        $table = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE changed_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
    }

    public static function schedule_purge() {
        if (!wp_next_scheduled('gm2_audit_purge')) {
            wp_schedule_event(time(), 'daily', 'gm2_audit_purge');
        }
    }
}

Gm2_Audit_Log::init();
