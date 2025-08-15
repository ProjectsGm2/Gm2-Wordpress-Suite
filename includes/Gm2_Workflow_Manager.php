<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Workflow_Manager {
    public static function init() {
        add_action('gm2_transition_post_status', [__CLASS__, 'handle_transition'], 10, 2);
    }

    /**
     * Register custom post statuses.
     *
     * @param array $statuses ['status' => 'Label']
     */
    public static function register_statuses($statuses) {
        foreach ($statuses as $status => $label) {
            register_post_status($status, [
                'label'                     => $label,
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop("{$label} <span class='count'>(%s)</span>", "{$label} <span class='count'>(%s)</span>")
            ]);
        }
    }

    /**
     * Schedule a status transition using WP-Cron.
     */
    public static function schedule_transition($post_id, $status, $timestamp) {
        wp_schedule_single_event($timestamp, 'gm2_transition_post_status', [$post_id, $status]);
    }

    /**
     * Perform the scheduled transition.
     */
    public static function handle_transition($post_id, $status) {
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => $status,
        ]);
    }

    /**
     * Add an editorial comment and notify mentioned users.
     */
    public static function add_editorial_comment($post_id, $message, $mentions = []) {
        $comment_id = wp_insert_comment([
            'comment_post_ID' => $post_id,
            'comment_content' => $message,
            'comment_type'    => 'gm2_editorial',
            'user_id'         => get_current_user_id(),
        ]);
        if ($comment_id && !empty($mentions)) {
            foreach ($mentions as $user_id) {
                do_action('gm2_editorial_comment_mention', $comment_id, $user_id);
            }
        }
        return $comment_id;
    }
}

Gm2_Workflow_Manager::init();
