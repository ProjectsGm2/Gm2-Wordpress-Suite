<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Workflow_Manager {
    /**
     * Array of registered triggers and their actions.
     *
     * @var array
     */
    protected static $triggers = [];

    public static function init() {
        add_action('gm2_transition_post_status', [__CLASS__, 'handle_transition'], 10, 2);

        // Core trigger hooks.
        add_action('save_post', [__CLASS__, 'on_save'], 10, 3);
        add_action('transition_post_status', [__CLASS__, 'on_status_change'], 10, 3);
        add_action('set_object_terms', [__CLASS__, 'on_term_assignment'], 10, 6);
        add_action('updated_post_meta', [__CLASS__, 'on_meta_update'], 10, 4);

        $statuses = get_option('gm2_workflow_statuses', []);
        if (is_array($statuses) && $statuses) {
            self::register_statuses($statuses);
        }

        $workflows = get_option('gm2_workflows', []);
        if (is_array($workflows)) {
            foreach ($workflows as $workflow) {
                $trigger = $workflow['trigger'] ?? '';
                $actions = $workflow['actions'] ?? [];
                if ($trigger && $actions) {
                    self::register_trigger($trigger, $actions);
                }
            }
        }
    }

    /**
     * Register actions for a given trigger event.
     *
     * @param string $event
     * @param array  $actions Array of actions.
     */
    public static function register_trigger($event, $actions) {
        if (!isset(self::$triggers[$event])) {
            self::$triggers[$event] = [];
        }
        self::$triggers[$event][] = (array) $actions;
    }

    /**
     * Run all actions for the given trigger event.
     *
     * @param string $event
     * @param array  $args
     */
    protected static function run_triggers($event, $args) {
        if (empty(self::$triggers[$event])) {
            return;
        }
        foreach (self::$triggers[$event] as $actions) {
            self::handle_actions($actions, $args);
        }
    }

    /**
     * Execute a set of actions.
     *
     * Supported action types:
     *  - email          : Send an email.
     *  - webhook        : POST data to an external URL (e.g., Slack).
     *  - action_scheduler: Queue a job using Action Scheduler if available.
     *  - image_regeneration: Regenerate attachment metadata.
     *  - recalculate_field: Run a callback to recompute fields.
     *  - schedule       : Schedule a single event via WP-Cron.
     *
     * @param array $actions
     * @param array $args
     */
    protected static function handle_actions($actions, $args) {
        foreach ((array) $actions as $action) {
            $type = $action['type'] ?? '';
            switch ($type) {
                case 'email':
                    if (!empty($action['to']) && !empty($action['subject'])) {
                        wp_mail($action['to'], $action['subject'], $action['message'] ?? '');
                    }
                    break;

                case 'webhook':
                    if (!empty($action['url'])) {
                        $body = $action['body'] ?? [];
                        wp_remote_post($action['url'], [
                            'headers' => ['Content-Type' => 'application/json'],
                            'body'    => wp_json_encode($body),
                            'timeout' => 5,
                        ]);
                    }
                    break;

                case 'action_scheduler':
                    if (function_exists('as_enqueue_async_action') && !empty($action['hook'])) {
                        as_enqueue_async_action($action['hook'], $action['args'] ?? [], $action['group'] ?? '');
                    }
                    break;

                case 'image_regeneration':
                    $attachment_id = $action['attachment_id'] ?? ($args[0] ?? 0);
                    $file = get_attached_file($attachment_id);
                    if ($file) {
                        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
                        if (!is_wp_error($metadata)) {
                            wp_update_attachment_metadata($attachment_id, $metadata);
                        }
                    }
                    break;

                case 'recalculate_field':
                    if (is_callable($action['callback'] ?? null)) {
                        call_user_func($action['callback'], $args);
                    }
                    break;

                case 'schedule':
                    if (!empty($action['timestamp']) && !empty($action['hook'])) {
                        wp_schedule_single_event($action['timestamp'], $action['hook'], $action['args'] ?? []);
                    }
                    break;
            }
        }
    }

    /**
     * Triggered on post save.
     */
    public static function on_save($post_id, $post, $update) {
        self::run_triggers('save_post', [$post_id, $post, $update]);
    }

    /**
     * Triggered when a post changes status.
     */
    public static function on_status_change($new_status, $old_status, $post) {
        self::run_triggers('status_change', [$new_status, $old_status, $post]);
    }

    /**
     * Triggered when terms are assigned to an object.
     */
    public static function on_term_assignment($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        self::run_triggers('term_assignment', [$object_id, $terms, $taxonomy]);
    }

    /**
     * Triggered when post meta is updated.
     */
    public static function on_meta_update($meta_id, $object_id, $meta_key, $_meta_value) {
        self::run_triggers('field_change', [$object_id, $meta_key, $_meta_value]);
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
