<?php

declare(strict_types=1);

namespace Gm2\Elementor\Forms\Action;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use Gm2\Elementor\GM2_Field_Key_Control;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\ElementorPro\\Modules\\Forms\\Classes\\Action_Base')) {
    return;
}

/**
 * Elementor form action for creating or updating GM2 custom posts.
 */
class CreateOrUpdatePost extends Action_Base {
    private const ACTION_NAME = 'gm2_cp_create_post';
    private const NONCE_ACTION = 'gm2_cp_form';
    private const DEFAULT_NONCE_FIELD = 'gm2_cp_nonce';
    private const DEFAULT_HONEYPOT_FIELD = 'gm2_cp_hp';
    private const DEFAULT_THROTTLE_LIMIT = 3;
    private const DEFAULT_THROTTLE_WINDOW = 60;

    private static bool $hooksRegistered = false;

    /** @var array<int, true> */
    private static array $legacyHandledRecords = [];

    /**
     * Register hooks.
     */
    public static function bootstrap(): void {
        if (!class_exists('\\ElementorPro\\Modules\\Forms\\Classes\\Action_Base')) {
            return;
        }

        if (did_action('elementor_pro/init')) {
            self::register_hooks();
            return;
        }

        add_action('elementor_pro/init', [ __CLASS__, 'register_hooks' ]);
    }

    /**
     * Register Elementor hooks for the action.
     */
    public static function register_hooks(): void {
        if (self::$hooksRegistered) {
            return;
        }

        self::$hooksRegistered = true;

        add_action('elementor_pro/forms/actions/register', [ __CLASS__, 'register_action' ]);
        add_action('elementor_pro/forms/new_record', [ __CLASS__, 'maybe_handle_legacy_submission' ], 10, 2);
    }

    /**
     * Register the action with Elementor's form actions manager.
     *
     * @param object $actions_manager Actions manager instance.
     */
    public static function register_action($actions_manager): void {
        if (is_object($actions_manager) && method_exists($actions_manager, 'register_action')) {
            $actions_manager->register_action(new self());
        }
    }

    /**
     * Execute the action when Elementor does not use the new actions registry.
     *
     * @param object|null $record       Form record instance.
     * @param object|null $ajax_handler Ajax handler for messaging.
     */
    public static function maybe_handle_legacy_submission($record, $ajax_handler = null): void {
        if (!is_object($record) || !method_exists($record, 'get_form_settings')) {
            return;
        }

        $actions = $record->get_form_settings('submit_actions');
        if (!is_array($actions)) {
            return;
        }

        $actions = array_map(static function ($value): string {
            return is_string($value) ? $value : (string) $value;
        }, $actions);

        if (!in_array(self::ACTION_NAME, $actions, true)) {
            return;
        }

        if (is_object($ajax_handler)) {
            $registered = null;
            if (method_exists($ajax_handler, 'get_registered_actions')) {
                $registered = $ajax_handler->get_registered_actions();
            } elseif (method_exists($ajax_handler, 'get_actions')) {
                $registered = $ajax_handler->get_actions();
            }

            if ($registered instanceof \Traversable) {
                $registered = iterator_to_array($registered);
            }

            if (is_array($registered)) {
                foreach ($registered as $key => $action) {
                    if ($key === self::ACTION_NAME) {
                        return;
                    }
                    if ($action instanceof Action_Base && $action->get_name() === self::ACTION_NAME) {
                        return;
                    }
                }
            }
        }

        $key = function_exists('spl_object_id') ? spl_object_id($record) : null;
        if (null !== $key && isset(self::$legacyHandledRecords[ $key ])) {
            return;
        }

        if (null !== $key) {
            self::$legacyHandledRecords[ $key ] = true;
        }

        $action = new self();
        $action->run($record, $ajax_handler);
    }

    /**
     * Action identifier.
     */
    public function get_name(): string {
        return self::ACTION_NAME;
    }

    /**
     * Human readable label.
     */
    public function get_label(): string {
        return __('Gm2: Create/Update Post', 'gm2-wordpress-suite');
    }
    /**
     * Register Elementor controls for configuring the action.
     *
     * @param \Elementor\Widget_Base $widget Widget instance.
     */
    public function register_settings_section($widget): void {
        if (!is_object($widget) || !method_exists($widget, 'start_controls_section')) {
            return;
        }

        $widget->start_controls_section(
            'gm2_cp_form_action',
            [
                'label'      => __('Gm2: Create/Update Post', 'gm2-wordpress-suite'),
                'condition'  => [ 'submit_actions' => $this->get_name() ],
                'tab'        => self::control_constant('TAB_CONTENT', 'content'),
            ]
        );

        $widget->add_control(
            'gm2_cp_form_id',
            [
                'label'       => __('Form Identifier', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
                'description' => __('Used when verifying the nonce. Leave empty to auto-generate.', 'gm2-wordpress-suite'),
            ]
        );

        $widget->add_control(
            'gm2_cp_post_type',
            [
                'label'       => __('Post Type', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('SELECT', 'select'),
                'label_block' => true,
                'options'     => $this->get_post_type_options(),
            ]
        );

        $widget->add_control(
            'gm2_cp_post_status',
            [
                'label'       => __('Post Status', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('SELECT', 'select'),
                'label_block' => true,
                'default'     => 'pending',
                'options'     => $this->get_post_status_options(),
            ]
        );

        $widget->add_control(
            'gm2_cp_required_permissions',
            [
                'label'       => __('Allowed Roles / Capabilities', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('SELECT2', 'select2'),
                'label_block' => true,
                'multiple'    => true,
                'options'     => $this->get_role_capability_options(),
                'description' => __('Restrict submissions to users who match one of the selected roles or capabilities.', 'gm2-wordpress-suite'),
            ]
        );

        if (is_multisite()) {
            $widget->add_control(
                'gm2_cp_site_id',
                [
                    'label'       => __('Site ID', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('NUMBER', 'number'),
                    'description' => __('Target site for the submission. Defaults to the current site.', 'gm2-wordpress-suite'),
                    'default'     => get_current_blog_id(),
                ]
            );
        }

        $widget->add_control(
            'gm2_cp_post_id_field',
            [
                'label'       => __('Post ID Field', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
                'description' => __('Optional field ID containing an existing post ID to update.', 'gm2-wordpress-suite'),
            ]
        );

        $widget->add_control(
            'gm2_cp_title_field',
            [
                'label'       => __('Title Field', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
            ]
        );

        $widget->add_control(
            'gm2_cp_content_field',
            [
                'label'       => __('Content Field', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
            ]
        );

        $widget->add_control(
            'gm2_cp_excerpt_field',
            [
                'label'       => __('Excerpt Field', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
            ]
        );

        $widget->add_control(
            'gm2_cp_nonce_field',
            [
                'label'       => __('Nonce Field ID', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
                'default'     => self::DEFAULT_NONCE_FIELD,
                'description' => __('Hidden field storing wp_create_nonce("gm2_cp_form|{form_id}").', 'gm2-wordpress-suite'),
            ]
        );

        $widget->add_control(
            'gm2_cp_honeypot_field',
            [
                'label'       => __('Honeypot Field ID', 'gm2-wordpress-suite'),
                'type'        => self::control_constant('TEXT', 'text'),
                'label_block' => true,
                'default'     => self::DEFAULT_HONEYPOT_FIELD,
                'description' => __('Submissions are rejected when this field contains any value.', 'gm2-wordpress-suite'),
            ]
        );

        if (class_exists('\\Elementor\\Repeater')) {
            $repeater = new Repeater();
            $repeater->add_control(
                'form_field',
                [
                    'label'       => __('Form Field ID', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('TEXT', 'text'),
                    'label_block' => true,
                ]
            );
            $repeater->add_control(
                'meta_key',
                [
                    'label'       => __('GM2 Meta Key', 'gm2-wordpress-suite'),
                    'type'        => class_exists('Gm2\\Elementor\\GM2_Field_Key_Control') ? GM2_Field_Key_Control::TYPE : self::control_constant('TEXT', 'text'),
                    'label_block' => true,
                ]
            );

            $widget->add_control(
                'gm2_cp_meta_map',
                [
                    'label'       => __('Field → Meta Map', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('REPEATER', 'repeater'),
                    'fields'      => $repeater->get_controls(),
                    'title_field' => '{{{ meta_key }}}',
                    'description' => __('Pairs of Elementor field IDs and GM2 meta keys to update.', 'gm2-wordpress-suite'),
                ]
            );

            $taxonomy_repeater = new Repeater();
            $taxonomy_repeater->add_control(
                'form_field',
                [
                    'label'       => __('Form Field ID', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('TEXT', 'text'),
                    'label_block' => true,
                ]
            );
            $taxonomy_repeater->add_control(
                'taxonomy',
                [
                    'label'       => __('Taxonomy Slug', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('TEXT', 'text'),
                    'label_block' => true,
                ]
            );
            $taxonomy_repeater->add_control(
                'allow_multiple',
                [
                    'label'        => __('Allow Multiple Terms', 'gm2-wordpress-suite'),
                    'type'         => self::control_constant('SWITCHER', 'switcher'),
                    'return_value' => 'yes',
                    'label_on'     => __('Yes', 'gm2-wordpress-suite'),
                    'label_off'    => __('No', 'gm2-wordpress-suite'),
                ]
            );

            $widget->add_control(
                'gm2_cp_taxonomy_map',
                [
                    'label'       => __('Field → Taxonomy Map', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('REPEATER', 'repeater'),
                    'fields'      => $taxonomy_repeater->get_controls(),
                    'title_field' => '{{{ taxonomy }}}',
                    'description' => __('Assign Elementor field values to taxonomy terms.', 'gm2-wordpress-suite'),
                ]
            );
        } else {
            $widget->add_control(
                'gm2_cp_meta_map',
                [
                    'label'       => __('Field → Meta Map', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('TEXT', 'text'),
                    'description' => __('Configure field mappings once Elementor assets are loaded.', 'gm2-wordpress-suite'),
                ]
            );
            $widget->add_control(
                'gm2_cp_taxonomy_map',
                [
                    'label'       => __('Field → Taxonomy Map', 'gm2-wordpress-suite'),
                    'type'        => self::control_constant('TEXT', 'text'),
                    'description' => __('Configure taxonomy mappings once Elementor assets are loaded.', 'gm2-wordpress-suite'),
                ]
            );
        }

        $widget->end_controls_section();
    }
    /**
     * Reset repeater data when exporting templates.
     *
     * @param array $element Form element settings.
     * @return array
     */
    public function on_export($element): array {
        if (isset($element['gm2_cp_meta_map'])) {
            $element['gm2_cp_meta_map'] = [];
        }
        if (isset($element['gm2_cp_taxonomy_map'])) {
            $element['gm2_cp_taxonomy_map'] = [];
        }
        return $element;
    }

    /**
     * Execute the action.
     *
     * @param object $record       Form record instance.
     * @param object $ajax_handler Ajax handler for messaging.
     */
    public function run($record, $ajax_handler): void {
        $settings  = $this->resolve_form_settings($record);
        $post_type = $this->resolve_post_type($settings);
        if (!$post_type) {
            return;
        }

        $fields = $this->normalize_form_fields($record);
        $form_id = $this->resolve_form_id($settings, $post_type, $record);

        if (!$this->verify_nonce($fields, $settings, $form_id)) {
            $this->record_error($record, $ajax_handler, __('Your session has expired. Please reload and try again.', 'gm2-wordpress-suite'));
            return;
        }

        if (!$this->check_honeypot($fields, $settings)) {
            $this->record_error($record, $ajax_handler, __('Submission blocked by anti-spam checks.', 'gm2-wordpress-suite'));
            return;
        }

        if (!$this->check_submission_throttle($form_id, $settings, $record, $ajax_handler)) {
            return;
        }

        $target_site = $this->resolve_site_id($settings);
        $restore     = false;

        if ($target_site && $target_site !== get_current_blog_id()) {
            switch_to_blog($target_site);
            $restore = true;
        }

        try {
            $post_id  = $this->resolve_post_id($fields, $settings);
            $updating = $post_id > 0;

            if ($updating) {
                $existing = get_post($post_id);
                if (!$existing || $existing->post_type !== $post_type) {
                    $this->record_error($record, $ajax_handler, __('Unable to locate the requested entry.', 'gm2-wordpress-suite'));
                    return;
                }
                if (!current_user_can('edit_post', $post_id)) {
                    $this->record_error($record, $ajax_handler, __('You do not have permission to update this post.', 'gm2-wordpress-suite'));
                    return;
                }
            }

            if (!$this->user_has_required_permissions($settings, $post_id, $updating)) {
                $this->record_error($record, $ajax_handler, __('You do not have permission to submit this form.', 'gm2-wordpress-suite'));
                return;
            }

            $post_data = [
                'post_type'   => $post_type,
                'post_status' => $this->resolve_post_status($settings, $updating, $post_id),
                'post_author' => get_current_user_id(),
            ];

            if ($updating) {
                $post_data['ID'] = $post_id;
            }

            $title = $this->resolve_field_value($fields, $settings['gm2_cp_title_field'] ?? '');
            if (null !== $title) {
                $post_data['post_title'] = sanitize_text_field($title);
            }

            $content = $this->resolve_field_value($fields, $settings['gm2_cp_content_field'] ?? '');
            if (null !== $content) {
                $post_data['post_content'] = wp_kses_post(is_array($content) ? implode("\n", $content) : (string) $content);
            }

            $excerpt = $this->resolve_field_value($fields, $settings['gm2_cp_excerpt_field'] ?? '');
            if (null !== $excerpt) {
                $post_data['post_excerpt'] = wp_kses_post(is_array($excerpt) ? implode("\n", $excerpt) : (string) $excerpt);
            }

            /**
             * Filter the prepared post data before persistence.
             *
             * @param array $post_data Prepared post array.
             * @param array $settings  Action settings.
             * @param array $fields    Submitted form fields.
             * @param object $record   Form record instance.
             * @param bool  $updating  Whether an existing post is updated.
             */
            $post_data = apply_filters('gm2_cp_elementor_post_data', $post_data, $settings, $fields, $record, $updating);

            $meta_updates = [];
            $upload_queue = [];
            $errors       = [];

            $mapping = $settings['gm2_cp_meta_map'] ?? [];
            if (is_array($mapping)) {
                foreach ($mapping as $map) {
                    if (!is_array($map)) {
                        continue;
                    }
                    $field_id = $this->normalize_field_id($map['form_field'] ?? '');
                    $meta_key = $this->normalize_meta_key($map['meta_key'] ?? '');
                    if (!$field_id || !$meta_key) {
                        continue;
                    }

                    $field = $this->get_field_entry($fields, $field_id);
                    if (!$field) {
                        $meta_updates[$meta_key] = '';
                        continue;
                    }

                    if ($this->is_file_field($field)) {
                        $files = $this->extract_files_for_field($record, $field_id);
                        if ($files) {
                            $validated = $this->validate_files($files, $field, $meta_key, $errors);
                            if ($validated) {
                                $upload_queue[] = [
                                    'meta_key' => $meta_key,
                                    'files'    => $validated,
                                ];
                            }
                        }
                        continue;
                    }

                    $value = $this->sanitize_meta_value($field, $meta_key, $settings, $record);
                    $meta_updates[$meta_key] = $value;
                }
            }

            if ($errors) {
                $this->record_error($record, $ajax_handler, implode(' ', array_unique($errors)));
                return;
            }

            /**
             * Filter the sanitized meta updates prior to saving.
             *
             * @param array $meta_updates Key/value map of meta updates.
             * @param array $settings     Action settings.
             * @param array $fields       Submitted fields.
             * @param object $record      Form record instance.
             * @param bool  $updating     Whether an existing post is updated.
             */
            $meta_updates = apply_filters('gm2_cp_elementor_meta_updates', $meta_updates, $settings, $fields, $record, $updating);

            $result = $updating ? wp_update_post($post_data, true) : wp_insert_post($post_data, true);
            if (is_wp_error($result) || !$result) {
                $this->record_error($record, $ajax_handler, __('Unable to save the submission.', 'gm2-wordpress-suite'));
                return;
            }

            $post_id = (int) $result;

            foreach ($meta_updates as $meta_key => $value) {
                if ($value === '' || $value === null) {
                    delete_post_meta($post_id, $meta_key);
                    continue;
                }
                update_post_meta($post_id, $meta_key, $value);
            }

            if ($upload_queue) {
                $this->process_upload_queue($upload_queue, $post_id, $errors);
                if ($errors) {
                    $this->record_error($record, $ajax_handler, implode(' ', array_unique($errors)));
                    return;
                }
            }

            $taxonomy_result = $this->assign_taxonomies($post_id, $post_type, $settings, $fields);
            if ($taxonomy_result['errors']) {
                $this->record_error($record, $ajax_handler, implode(' ', array_unique($taxonomy_result['errors'])));
                return;
            }

            /**
             * Fires after the Elementor submission is stored.
             *
             * @param int    $post_id       Saved post ID.
             * @param string $post_type     Post type slug.
             * @param array  $meta_updates  Saved meta values.
             * @param array  $settings      Action settings.
             * @param bool   $updating      Whether an existing post was updated.
             * @param array  $assigned_terms Map of taxonomy slugs to assigned terms.
             */
            do_action('gm2_cp_elementor_after_save', $post_id, $post_type, $meta_updates, $settings, $updating, $taxonomy_result['assigned']);

            if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_success_message')) {
                $ajax_handler->add_success_message(__('Submission saved successfully.', 'gm2-wordpress-suite'));
            }
        } finally {
            if ($restore) {
                restore_current_blog();
            }
        }
    }
    /**
     * Resolve control constant values while providing fallbacks for tests.
     *
     * @param string $name     Constant name.
     * @param mixed  $fallback Default value when the constant is missing.
     * @return mixed
     */
    private static function control_constant(string $name, $fallback) {
        $constant = 'Elementor\\Controls_Manager::' . $name;
        if (defined($constant)) {
            return constant($constant);
        }
        return $fallback;
    }

    /**
     * Retrieve available post types for the select control.
     *
     * @return array<string,string>
     */
    private function get_post_type_options(): array {
        $options = [];
        $types   = get_post_types(['show_ui' => true], 'objects');
        foreach ($types as $slug => $object) {
            $label = $object->labels->singular_name ?? $object->label ?? $slug;
            $options[ $slug ] = $label;
        }
        if (!$options) {
            $options['post'] = __('Post', 'gm2-wordpress-suite');
        }
        return $options;
    }

    /**
     * Valid post status options.
     *
     * @return array<string,string>
     */
    private function get_post_status_options(): array {
        $choices  = [];
        $statuses = get_post_stati(['internal' => false], 'objects');
        foreach ($statuses as $status => $object) {
            $label = $object->label ?? $status;
            $choices[ $status ] = $label;
        }
        if (!$choices) {
            $choices = [
                'pending' => __('Pending', 'gm2-wordpress-suite'),
                'draft'   => __('Draft', 'gm2-wordpress-suite'),
                'publish' => __('Publish', 'gm2-wordpress-suite'),
            ];
        }
        return $choices;
    }

    /**
     * Retrieve available role and capability options for the permissions control.
     *
     * @return array<string,string>
     */
    private function get_role_capability_options(): array {
        $role_options = [];
        $capabilities = [];

        if (function_exists('wp_roles')) {
            $wp_roles = wp_roles();
            if ($wp_roles instanceof \WP_Roles) {
                foreach ($wp_roles->roles as $slug => $details) {
                    $name = $details['name'] ?? $slug;
                    $role_options[ 'role:' . sanitize_key($slug) ] = sprintf(__('Role: %s', 'gm2-wordpress-suite'), translate_user_role($name));
                }

                foreach ($wp_roles->role_objects as $role) {
                    if (!$role instanceof \WP_Role) {
                        continue;
                    }
                    foreach ($role->capabilities as $capability => $grant) {
                        if ($grant) {
                            $capabilities[ sanitize_key($capability) ] = true;
                        }
                    }
                }
            }
        }

        $options = $role_options;

        if ($capabilities) {
            $cap_keys = array_keys($capabilities);
            sort($cap_keys);
            foreach ($cap_keys as $capability) {
                $options[ 'cap:' . $capability ] = sprintf(__('Capability: %s', 'gm2-wordpress-suite'), $capability);
            }
        }

        if (!$options) {
            $options['cap:read'] = __('Capability: read', 'gm2-wordpress-suite');
        }

        return $options;
    }

    /**
     * Resolve form settings from the record.
     *
     * @param object $record Form record instance.
     * @return array
     */
    private function resolve_form_settings($record): array {
        if (is_object($record) && method_exists($record, 'get_form_settings')) {
            $settings = $record->get_form_settings();
            if (is_array($settings)) {
                return $settings;
            }
        }
        return [];
    }

    /**
     * Determine the target post type.
     *
     * @param array $settings Action settings.
     * @return string
     */
    private function resolve_post_type(array $settings): string {
        $post_type = isset($settings['gm2_cp_post_type']) ? sanitize_key((string) $settings['gm2_cp_post_type']) : '';
        if (!$post_type || !post_type_exists($post_type)) {
            return '';
        }
        return $post_type;
    }

    /**
     * Normalize Elementor field identifiers.
     *
     * @param object $record Form record instance.
     * @return array<string,array>
     */
    private function normalize_form_fields($record): array {
        if (!is_object($record) || !method_exists($record, 'get')) {
            return [];
        }
        $fields = $record->get('fields');
        if (!is_array($fields)) {
            return [];
        }
        $normalized = [];
        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $id = (string) ($field['id'] ?? $field['field_id'] ?? $key);
            if ($id === '') {
                $id = (string) ($field['name'] ?? '');
            }
            if ($id === '') {
                continue;
            }
            $normalized[ $id ] = $field;
        }
        return $normalized;
    }

    /**
     * Resolve the Elementor form identifier used during nonce verification.
     *
     * @param array  $settings Action settings.
     * @param string $post_type Target post type.
     * @param object $record Form record instance.
     * @return string
     */
    private function resolve_form_id(array $settings, string $post_type, $record): string {
        $form_id = isset($settings['gm2_cp_form_id']) ? (string) $settings['gm2_cp_form_id'] : '';
        $form_id = sanitize_html_class($form_id);
        if ($form_id) {
            return $form_id;
        }
        if (is_object($record) && method_exists($record, 'get_form_settings')) {
            $maybe = $record->get_form_settings('form_id');
            if (is_string($maybe) && $maybe !== '') {
                return sanitize_html_class($maybe);
            }
        }
        return 'gm2_cp_form_' . $post_type;
    }

    /**
     * Verify nonce value.
     *
     * @param array  $fields   Normalized fields.
     * @param array  $settings Action settings.
     * @param string $form_id  Form identifier.
     * @return bool
     */
    private function verify_nonce(array $fields, array $settings, string $form_id): bool {
        $field_id = $this->normalize_field_id($settings['gm2_cp_nonce_field'] ?? self::DEFAULT_NONCE_FIELD);
        if (!$field_id) {
            $field_id = self::DEFAULT_NONCE_FIELD;
        }
        $value = $this->get_field_raw_value($fields, $field_id);
        if (!$value && isset($_POST['form_fields'][ $field_id ])) {
            $value = wp_unslash((string) $_POST['form_fields'][ $field_id ]);
        }
        if (!$value) {
            return false;
        }
        return (bool) wp_verify_nonce($value, self::NONCE_ACTION . '|' . $form_id);
    }

    /**
     * Check honeypot field for spam submissions.
     *
     * @param array $fields   Normalized fields.
     * @param array $settings Action settings.
     * @return bool
     */
    private function check_honeypot(array $fields, array $settings): bool {
        $field_id = $this->normalize_field_id($settings['gm2_cp_honeypot_field'] ?? self::DEFAULT_HONEYPOT_FIELD);
        if (!$field_id) {
            $field_id = self::DEFAULT_HONEYPOT_FIELD;
        }
        $value = $this->get_field_raw_value($fields, $field_id);
        if (!$value && isset($_POST['form_fields'][ $field_id ])) {
            $value = wp_unslash((string) $_POST['form_fields'][ $field_id ]);
        }
        return '' === trim((string) $value);
    }

    /**
     * Check whether the current request exceeds the submission throttle.
     *
     * @param string $form_id  Form identifier.
     * @param array  $settings Action settings.
     * @param object $record   Form record instance.
     * @param object $ajax_handler Ajax handler instance.
     * @return bool
     */
    private function check_submission_throttle(string $form_id, array $settings, $record, $ajax_handler): bool {
        $limits = [
            'limit'  => self::DEFAULT_THROTTLE_LIMIT,
            'window' => self::DEFAULT_THROTTLE_WINDOW,
        ];

        /**
         * Filter the submission throttle limits.
         *
         * @param array  $limits    Array containing `limit` and `window` keys.
         * @param string $form_id   Form identifier.
         * @param array  $settings  Action settings.
         * @param object $record    Form record instance.
         */
        $limits = apply_filters('gm2_cp_elementor_throttle_limits', $limits, $form_id, $settings, $record);

        $limit  = isset($limits['limit']) ? (int) $limits['limit'] : 0;
        $window = isset($limits['window']) ? (int) $limits['window'] : 0;

        if ($limit < 1 || $window < 1) {
            return true;
        }

        $identifier = $this->resolve_throttle_identifier();
        $key        = 'gm2_cp_form_throttle_' . md5($form_id . '|' . $identifier);
        $now        = time();

        $data = get_transient($key);
        if (!is_array($data) || !isset($data['start'], $data['count']) || ($data['start'] + $window) <= $now) {
            $data = [
                'start' => $now,
                'count' => 1,
            ];
            set_transient($key, $data, $window);
            return true;
        }

        if ($data['count'] >= $limit) {
            $expires_in = max($data['start'] + $window - $now, 1);
            set_transient($key, $data, $expires_in);
            $this->record_error($record, $ajax_handler, __('Please wait before submitting the form again.', 'gm2-wordpress-suite'));
            return false;
        }

        $data['count']++;
        $expires_in = max($data['start'] + $window - $now, 1);
        set_transient($key, $data, $expires_in);

        return true;
    }

    /**
     * Resolve the throttle identifier for the current visitor.
     *
     * @return string
     */
    private function resolve_throttle_identifier(): string {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'user:' . $user_id;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '';
        if ('' !== $ip) {
            return 'ip:' . $ip;
        }

        return 'anonymous';
    }

    /**
     * Resolve target site ID for multisite submissions.
     *
     * @param array $settings Action settings.
     * @return int|null
     */
    private function resolve_site_id(array $settings): ?int {
        if (!is_multisite()) {
            return null;
        }
        $raw = $settings['gm2_cp_site_id'] ?? '';
        if ('' === $raw) {
            return null;
        }
        $site_id = (int) $raw;
        if ($site_id <= 0) {
            return null;
        }
        $site = get_site($site_id);
        if (!$site) {
            return null;
        }
        return $site_id;
    }

    /**
     * Extract post ID when updating existing content.
     *
     * @param array $fields   Normalized fields.
     * @param array $settings Action settings.
     * @return int
     */
    private function resolve_post_id(array $fields, array $settings): int {
        $field_id = $this->normalize_field_id($settings['gm2_cp_post_id_field'] ?? '');
        if (!$field_id) {
            return 0;
        }
        $value = $this->resolve_field_value($fields, $field_id);
        if (null === $value) {
            return 0;
        }
        return absint(is_array($value) ? reset($value) : $value);
    }
    /**
     * Resolve a sanitized field value.
     *
     * @param array  $fields   Normalized fields.
     * @param string $field_id Field identifier.
     * @return mixed|null
     */
    private function resolve_field_value(array $fields, string $field_id) {
        if (!$field_id) {
            return null;
        }
        $field = $this->get_field_entry($fields, $field_id);
        if (!$field) {
            return null;
        }
        $value = $field['raw_value'] ?? $field['value'] ?? null;
        if (null === $value) {
            return null;
        }
        if (is_array($value)) {
            return array_map(static function ($item) {
                return is_string($item) ? wp_unslash($item) : $item;
            }, $value);
        }
        return wp_unslash((string) $value);
    }

    /**
     * Fetch the raw value for a field.
     *
     * @param array  $fields   Normalized fields.
     * @param string $field_id Field identifier.
     * @return string
     */
    private function get_field_raw_value(array $fields, string $field_id): string {
        $field = $this->get_field_entry($fields, $field_id);
        if (!$field) {
            return '';
        }
        $value = $field['raw_value'] ?? $field['value'] ?? '';
        if (is_array($value)) {
            $value = implode('', array_map(static function ($item) {
                return is_string($item) ? wp_unslash($item) : '';
            }, $value));
        }
        return is_string($value) ? wp_unslash($value) : '';
    }

    /**
     * Resolve the post status for the submission.
     *
     * @param array $settings Action settings.
     * @param bool  $updating Whether an existing post is updated.
     * @param int   $post_id  Existing post ID when updating.
     * @return string
     */
    private function resolve_post_status(array $settings, bool $updating, int $post_id): string {
        $status = isset($settings['gm2_cp_post_status']) ? sanitize_key((string) $settings['gm2_cp_post_status']) : '';
        if (!$status && $updating && $post_id > 0) {
            $status = get_post_field('post_status', $post_id) ?: '';
        }
        if (!$status) {
            $status = 'pending';
        }
        $object = get_post_status_object($status);
        if (!$object) {
            $status = 'pending';
        }
        /**
         * Filter the status assigned to submissions.
         *
         * @param string $status   Post status slug.
         * @param array  $settings Action settings.
         * @param bool   $updating Whether an existing post is updated.
         * @param int    $post_id  Existing post ID (0 for new submissions).
         */
        return apply_filters('gm2_cp_elementor_post_status', $status, $settings, $updating, $post_id);
    }

    /**
     * Normalize configured permission requirements.
     *
     * @param mixed $value Raw setting value.
     * @return array<int, array{type:string,value:string}>
     */
    private function normalize_permission_requirements($value): array {
        if (is_string($value)) {
            $value = $value === '' ? [] : [ $value ];
        }
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['value'] ?? '';
            }
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ('' === $item) {
                continue;
            }

            if (0 === strncmp($item, 'role:', 5)) {
                $slug = sanitize_key(substr($item, 5));
                if ('' === $slug) {
                    continue;
                }
                $normalized[] = [
                    'type'  => 'role',
                    'value' => $slug,
                ];
                continue;
            }

            if (0 === strncmp($item, 'cap:', 4)) {
                $capability = sanitize_key(substr($item, 4));
                if ('' === $capability) {
                    continue;
                }
                $normalized[] = [
                    'type'  => 'cap',
                    'value' => $capability,
                ];
            }
        }

        if (!$normalized) {
            return [];
        }

        $unique = [];
        foreach ($normalized as $requirement) {
            $key = $requirement['type'] . ':' . $requirement['value'];
            if (isset($unique[ $key ])) {
                continue;
            }
            $unique[ $key ] = $requirement;
        }

        return array_values($unique);
    }

    /**
     * Determine whether the current user satisfies configured permissions.
     *
     * @param array $settings Action settings.
     * @param int   $post_id  Existing post ID when updating.
     * @param bool  $updating Whether an existing post is being updated.
     * @return bool
     */
    private function user_has_required_permissions(array $settings, int $post_id, bool $updating): bool {
        $requirements = $this->normalize_permission_requirements($settings['gm2_cp_required_permissions'] ?? []);
        if (!$requirements) {
            return true;
        }

        if ($updating && $post_id > 0 && current_user_can('edit_post', $post_id)) {
            return true;
        }

        $user       = wp_get_current_user();
        $user_roles = [];
        if ($user && $user instanceof \WP_User && is_array($user->roles)) {
            $user_roles = $user->roles;
        }

        foreach ($requirements as $requirement) {
            if ('role' === $requirement['type']) {
                if (in_array($requirement['value'], $user_roles, true)) {
                    return true;
                }
                continue;
            }

            if (current_user_can($requirement['value'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve a field entry by ID.
     *
     * @param array  $fields   Normalized fields.
     * @param string $field_id Field identifier.
     * @return array|null
     */
    private function get_field_entry(array $fields, string $field_id): ?array {
        if (isset($fields[ $field_id ])) {
            return $fields[ $field_id ];
        }
        foreach ($fields as $id => $field) {
            if (!is_array($field)) {
                continue;
            }
            $candidate = $field['id'] ?? $field['field_id'] ?? $id;
            if ((string) $candidate === $field_id) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Normalize field identifier input.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function normalize_field_id($value): string {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return $value;
    }

    /**
     * Normalize meta key input.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function normalize_meta_key($value): string {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return sanitize_key($value);
    }

    /**
     * Normalize taxonomy slug input.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function normalize_taxonomy($value): string {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return sanitize_key($value);
    }

    /**
     * Normalize switcher/boolean values.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private function normalize_switch_value($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (!is_string($value)) {
            return false;
        }
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Normalize taxonomy term values from a submission.
     *
     * @param mixed $raw            Submitted value.
     * @param bool  $allow_multiple Whether multiple terms should be processed.
     * @return array<int, int|string>
     */
    private function normalize_taxonomy_terms($raw, bool $allow_multiple): array {
        $values = [];

        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $item = $item['value'] ?? '';
                }
                $values = array_merge($values, $this->normalize_taxonomy_terms($item, true));
            }
        } elseif (is_string($raw) || is_numeric($raw)) {
            $pieces = preg_split('/[\r\n,]+/', (string) $raw);
            if ($pieces) {
                foreach ($pieces as $piece) {
                    $sanitized = $this->sanitize_term_input($piece);
                    if ('' === $sanitized || null === $sanitized) {
                        continue;
                    }
                    $values[] = $sanitized;
                    if (!$allow_multiple) {
                        break;
                    }
                }
            }
        }

        $normalized = [];
        foreach ($values as $value) {
            if ($value === '' || $value === null || $value === 0) {
                continue;
            }
            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        if (!$allow_multiple && $normalized) {
            return [ $normalized[0] ];
        }

        return $normalized;
    }

    /**
     * Sanitize a single taxonomy term value.
     *
     * @param mixed $value Raw value.
     * @return int|string
     */
    private function sanitize_term_input($value) {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value) && !is_string($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            return '';
        }
        $value = trim(wp_unslash($value));
        if ($value === '') {
            return '';
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        return sanitize_text_field($value);
    }

    /**
     * Determine whether the field contains file uploads.
     *
     * @param array $field Field data.
     * @return bool
     */
    private function is_file_field(array $field): bool {
        $type = $field['type'] ?? '';
        return in_array($type, ['upload', 'file'], true);
    }

    /**
     * Extract uploaded files for a form field.
     *
     * @param object $record   Form record instance.
     * @param string $field_id Field identifier.
     * @return array<int,array>
     */
    private function extract_files_for_field($record, string $field_id): array {
        $files = [];

        if (is_object($record) && method_exists($record, 'get')) {
            $record_files = $record->get('files');
            if (is_array($record_files) && isset($record_files[ $field_id ])) {
                $files = $this->normalize_file_entries($record_files[ $field_id ]);
            }
        }

        if (!$files && isset($_FILES['form_fields'])) {
            $files = $this->normalize_files_from_form($_FILES['form_fields'], $field_id);
        }

        if (!$files && isset($_FILES[ $field_id ])) {
            $files = $this->normalize_file_entries($_FILES[ $field_id ]);
        }

        return $files;
    }

    /**
     * Normalize file arrays from record/$_FILES structures.
     *
     * @param array $source Source array.
     * @return array<int,array>
     */
    private function normalize_file_entries($source): array {
        $files = [];
        if (!is_array($source)) {
            return $files;
        }

        if (isset($source['name'])) {
            if (is_array($source['name'])) {
                foreach ($source['name'] as $index => $name) {
                    $files[] = [
                        'name'     => is_string($name) ? $name : '',
                        'type'     => $source['type'][ $index ] ?? '',
                        'tmp_name' => $source['tmp_name'][ $index ] ?? '',
                        'error'    => $source['error'][ $index ] ?? 0,
                        'size'     => $source['size'][ $index ] ?? 0,
                    ];
                }
            } else {
                $files[] = [
                    'name'     => is_string($source['name']) ? $source['name'] : '',
                    'type'     => $source['type'] ?? '',
                    'tmp_name' => $source['tmp_name'] ?? '',
                    'error'    => $source['error'] ?? 0,
                    'size'     => $source['size'] ?? 0,
                ];
            }
        } elseif (isset($source[0]) && is_array($source[0])) {
            foreach ($source as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $files[] = [
                    'name'     => $item['name'] ?? '',
                    'type'     => $item['type'] ?? '',
                    'tmp_name' => $item['tmp_name'] ?? '',
                    'error'    => $item['error'] ?? 0,
                    'size'     => $item['size'] ?? 0,
                ];
            }
        }

        return array_values(array_filter($files, static function ($file) {
            return !empty($file['tmp_name']);
        }));
    }

    /**
     * Normalize nested $_FILES structure used by Elementor forms.
     *
     * @param array  $form_files Nested form_fields array.
     * @param string $field_id   Field identifier.
     * @return array<int,array>
     */
    private function normalize_files_from_form(array $form_files, string $field_id): array {
        if (!isset($form_files['name'][ $field_id ])) {
            return [];
        }
        $names    = $form_files['name'][ $field_id ];
        $types    = $form_files['type'][ $field_id ] ?? [];
        $tmp      = $form_files['tmp_name'][ $field_id ] ?? [];
        $errors   = $form_files['error'][ $field_id ] ?? [];
        $sizes    = $form_files['size'][ $field_id ] ?? [];
        $files    = [];

        if (is_array($names)) {
            foreach ($names as $index => $name) {
                $files[] = [
                    'name'     => is_string($name) ? $name : '',
                    'type'     => $types[ $index ] ?? '',
                    'tmp_name' => $tmp[ $index ] ?? '',
                    'error'    => $errors[ $index ] ?? 0,
                    'size'     => $sizes[ $index ] ?? 0,
                ];
            }
        } else {
            $files[] = [
                'name'     => is_string($names) ? $names : '',
                'type'     => is_array($types) ? ($types[0] ?? '') : $types,
                'tmp_name' => is_array($tmp) ? ($tmp[0] ?? '') : $tmp,
                'error'    => is_array($errors) ? ($errors[0] ?? 0) : $errors,
                'size'     => is_array($sizes) ? ($sizes[0] ?? 0) : $sizes,
            ];
        }

        return array_values(array_filter($files, static function ($file) {
            return !empty($file['tmp_name']);
        }));
    }

    /**
     * Validate uploaded files against size and type restrictions.
     *
     * @param array $files   Normalized file entries.
     * @param array $field   Field definition.
     * @param string $meta_key Meta key being updated.
     * @param array $errors  Reference to error messages.
     * @return array<int,array>
     */
    private function validate_files(array $files, array $field, string $meta_key, array &$errors): array {
        if (!function_exists('wp_check_filetype_and_ext')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $max_size = (int) apply_filters('gm2_cp_elementor_max_file_size', wp_max_upload_size(), $field, $meta_key);
        $allowed  = apply_filters('gm2_cp_elementor_allowed_file_types', get_allowed_mime_types(), $field, $meta_key);
        if (!is_array($allowed)) {
            $allowed = [];
        }

        $valid = [];
        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? 0);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = sprintf(__('Upload error for %s.', 'gm2-wordpress-suite'), esc_html($file['name'] ?? $meta_key));
                continue;
            }
            if ($max_size > 0 && (int) ($file['size'] ?? 0) > $max_size) {
                $errors[] = sprintf(__('File %s exceeds the maximum allowed size.', 'gm2-wordpress-suite'), esc_html($file['name'] ?? $meta_key));
                continue;
            }
            $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed ?: null);
            $mime  = $check['type'] ?: ($file['type'] ?? '');
            if ($allowed) {
                $allowed_mimes = array_values($allowed);
                if (!$mime || !in_array($mime, $allowed_mimes, true)) {
                    $errors[] = sprintf(__('File type for %s is not permitted.', 'gm2-wordpress-suite'), esc_html($file['name'] ?? $meta_key));
                    continue;
                }
            }
            $file['type'] = $mime;
            $file['name'] = sanitize_file_name($file['name'] ?? '');
            $valid[]      = $file;
        }

        return $valid;
    }

    /**
     * Sanitize meta value prior to persistence.
     *
     * @param array  $field    Field data.
     * @param string $meta_key Meta key being updated.
     * @param array  $settings Action settings.
     * @param object $record   Form record instance.
     * @return mixed
     */
    private function sanitize_meta_value(array $field, string $meta_key, array $settings, $record) {
        $value = $field['raw_value'] ?? $field['value'] ?? '';
        $type  = $field['type'] ?? '';

        if (is_array($value)) {
            if (in_array($type, ['checkbox', 'acceptance', 'radio'], true)) {
                $value = array_map(static function ($item) {
                    return $item ? '1' : '';
                }, $value);
            } else {
                $value = array_map(static function ($item) {
                    return is_string($item) ? sanitize_text_field(wp_unslash($item)) : $item;
                }, $value);
            }
        } else {
            $value = is_string($value) ? wp_unslash($value) : $value;
            if (is_string($value)) {
                switch ($type) {
                    case 'textarea':
                    case 'wysiwyg':
                        $value = wp_kses_post($value);
                        break;
                    case 'url':
                        $value = esc_url_raw($value);
                        break;
                    case 'number':
                    case 'range':
                        $value = $value === '' ? '' : 0 + $value;
                        break;
                    case 'checkbox':
                    case 'acceptance':
                        $value = $value ? '1' : '';
                        break;
                    default:
                        $value = sanitize_text_field($value);
                        break;
                }
            }
        }

        /**
         * Filter sanitized meta values before saving.
         *
         * @param mixed  $value    Sanitized value.
         * @param string $meta_key Meta key being updated.
         * @param array  $field    Field configuration.
         * @param array  $settings Action settings.
         * @param object $record   Form record instance.
         */
        return apply_filters('gm2_cp_elementor_meta_value', $value, $meta_key, $field, $settings, $record);
    }

    /**
     * Assign taxonomy terms to the saved post.
     *
     * @param int    $post_id   Saved post ID.
     * @param string $post_type Post type slug.
     * @param array  $settings  Action settings.
     * @param array  $fields    Normalized form fields.
     * @return array{errors: array<int,string>, assigned: array<string, array<int|string>>}
     */
    private function assign_taxonomies(int $post_id, string $post_type, array $settings, array $fields): array {
        $result = [
            'errors'   => [],
            'assigned' => [],
        ];

        $mapping = $settings['gm2_cp_taxonomy_map'] ?? [];
        if (!is_array($mapping) || !$mapping) {
            return $result;
        }

        foreach ($mapping as $map) {
            if (!is_array($map)) {
                continue;
            }

            $field_id = $this->normalize_field_id($map['form_field'] ?? '');
            $taxonomy = $this->normalize_taxonomy($map['taxonomy'] ?? '');

            if (!$field_id || !$taxonomy) {
                continue;
            }

            $taxonomy_object = get_taxonomy($taxonomy);
            if (!$taxonomy_object) {
                $result['errors'][] = sprintf(__('Taxonomy "%s" does not exist.', 'gm2-wordpress-suite'), $taxonomy);
                continue;
            }

            if (!is_object_in_taxonomy($post_type, $taxonomy)) {
                $result['errors'][] = sprintf(
                    __('Taxonomy "%1$s" cannot be assigned to %2$s posts.', 'gm2-wordpress-suite'),
                    $taxonomy,
                    $post_type
                );
                continue;
            }

            $raw_value = $this->resolve_field_value($fields, $field_id);
            if (null === $raw_value) {
                continue;
            }

            $allow_multiple = $this->normalize_switch_value($map['allow_multiple'] ?? '');
            $terms          = $this->normalize_taxonomy_terms($raw_value, $allow_multiple);

            /**
             * Filter the sanitized taxonomy terms prior to assignment.
             *
             * @param array<int,string|int> $terms      Sanitized term identifiers.
             * @param string                $taxonomy   Taxonomy slug being updated.
             * @param array                 $map        Raw mapping configuration.
             * @param array                 $fields     Normalized submission fields.
             * @param int                   $post_id    Saved post ID.
             * @param string                $post_type  Post type slug.
             * @param array                 $settings   Action settings.
             */
            $terms = apply_filters(
                'gm2_cp_elementor_taxonomy_terms',
                $terms,
                $taxonomy,
                $map,
                $fields,
                $post_id,
                $post_type,
                $settings
            );

            if ($terms === null || $terms === '') {
                $terms = [];
            }

            if (!is_array($terms)) {
                $terms = [ $terms ];
            }

            $normalized_terms = [];
            foreach ($terms as $term) {
                if (is_int($term)) {
                    if ($term <= 0) {
                        continue;
                    }
                    if (!in_array($term, $normalized_terms, true)) {
                        $normalized_terms[] = $term;
                    }
                    continue;
                }

                if (!is_string($term)) {
                    continue;
                }

                $term = trim($term);
                if ($term === '') {
                    continue;
                }

                if (!in_array($term, $normalized_terms, true)) {
                    $normalized_terms[] = $term;
                }
            }

            if (!$allow_multiple && $normalized_terms) {
                $normalized_terms = [ $normalized_terms[0] ];
            }

            $set = wp_set_object_terms($post_id, $normalized_terms, $taxonomy, false);
            if (is_wp_error($set)) {
                $result['errors'][] = sprintf(
                    __('Failed to assign terms for %1$s: %2$s', 'gm2-wordpress-suite'),
                    $taxonomy,
                    $set->get_error_message()
                );
                continue;
            }

            $result['assigned'][ $taxonomy ] = $normalized_terms;
        }

        return $result;
    }

    /**
     * Queue error messages on the record/ajax handler.
     *
     * @param object $record       Form record instance.
     * @param object $ajax_handler Ajax handler instance.
     * @param string $message      Error message.
     */
    private function record_error($record, $ajax_handler, string $message): void {
        if (is_object($record) && method_exists($record, 'add_error')) {
            $record->add_error('gm2_cp_form_action', $message);
        }
        if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error_message')) {
            $ajax_handler->add_error_message($message);
        }
    }

    /**
     * Process queued uploads and attach them to the saved post.
     *
     * @param array $queue   Upload queue.
     * @param int   $post_id Saved post ID.
     * @param array $errors  Reference to error messages.
     */
    private function process_upload_queue(array $queue, int $post_id, array &$errors): void {
        if (!$queue) {
            return;
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        foreach ($queue as $item) {
            $meta_key = $item['meta_key'];
            $files    = $item['files'];
            $ids      = [];

            foreach ($files as $file) {
                $file_array = [
                    'name'     => $file['name'],
                    'type'     => $file['type'] ?? '',
                    'tmp_name' => $file['tmp_name'],
                    'error'    => $file['error'] ?? 0,
                    'size'     => $file['size'] ?? 0,
                ];
                $attachment_id = media_handle_sideload($file_array, $post_id);
                if (is_wp_error($attachment_id)) {
                    $errors[] = sprintf(__('Failed to attach %s.', 'gm2-wordpress-suite'), esc_html($file['name'] ?? $meta_key));
                    continue;
                }
                $ids[] = (int) $attachment_id;
            }

            if (!$ids) {
                continue;
            }

            $value = count($ids) === 1 ? $ids[0] : $ids;
            /**
             * Filter the value stored after handling uploads.
             *
             * @param mixed $value Stored value (ID or array of IDs).
             * @param string $meta_key Meta key being updated.
             * @param array $ids Attachment IDs.
             * @param int   $post_id Saved post ID.
             */
            $value = apply_filters('gm2_cp_elementor_uploaded_meta_value', $value, $meta_key, $ids, $post_id);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
