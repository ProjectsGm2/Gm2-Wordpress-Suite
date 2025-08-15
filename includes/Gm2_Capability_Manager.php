<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Capability_Manager {
    /**
     * Initialize capability mapping hooks.
     */
    public static function init() {
        add_filter('register_post_type_args', [__CLASS__, 'filter_cpt_caps'], 10, 2);
        add_filter('register_taxonomy_args', [__CLASS__, 'filter_tax_caps'], 10, 2);
        add_filter('user_has_cap', [__CLASS__, 'filter_user_caps'], 10, 3);
    }

    /**
     * Merge custom capability mappings for post types from option `gm2_cpt_cap_map`.
     */
    public static function filter_cpt_caps($args, $post_type) {
        $map = get_option('gm2_cpt_cap_map', []);
        if (isset($map[$post_type]) && is_array($map[$post_type])) {
            $args['capabilities'] = array_merge($args['capabilities'] ?? [], $map[$post_type]);
        }
        return $args;
    }

    /**
     * Merge custom capability mappings for taxonomies from option `gm2_tax_cap_map`.
     */
    public static function filter_tax_caps($args, $taxonomy) {
        $map = get_option('gm2_tax_cap_map', []);
        if (isset($map[$taxonomy]) && is_array($map[$taxonomy])) {
            $args['capabilities'] = array_merge($args['capabilities'] ?? [], $map[$taxonomy]);
        }
        return $args;
    }

    /**
     * Enforce field-level capabilities stored in option `gm2_field_caps`.
     */
    public static function filter_user_caps($allcaps, $caps, $args) {
        $cap = $args[0] ?? '';
        $user_id = $args[1] ?? 0;
        if (strpos($cap, 'gm2_field_') !== 0) {
            return $allcaps;
        }
        $map = get_option('gm2_field_caps', []);
        $pieces = explode('_', $cap, 4); // gm2_field_{read/edit}_{field}
        $action = $pieces[2] ?? '';
        $field = $pieces[3] ?? '';
        if (!$field || !$action) {
            return $allcaps;
        }
        $roles = $map[$field][$action] ?? [];
        if (empty($roles)) {
            // No roles restriction means allowed.
            $allcaps[$cap] = true;
            return $allcaps;
        }
        $user = get_userdata($user_id);
        $allowed = false;
        if ($user) {
            foreach ((array) $user->roles as $role) {
                if (in_array($role, $roles, true)) {
                    $allowed = true;
                    break;
                }
            }
        }
        $allcaps[$cap] = $allowed;
        return $allcaps;
    }

    /**
     * Check if current (or specified) user can read a field.
     */
    public static function can_read_field($field, $post_id = 0, $user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        return user_can($user_id, "gm2_field_read_{$field}", $post_id);
    }

    /**
     * Check if current (or specified) user can edit a field.
     */
    public static function can_edit_field($field, $post_id = 0, $user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        return user_can($user_id, "gm2_field_edit_{$field}", $post_id);
    }
}

Gm2_Capability_Manager::init();
