<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_CP_Form {
    private const NONCE_ACTION = 'gm2_cp_form';
    private const HONEYPOT_FIELD = 'gm2_cp_hp';

    /**
     * Store submission results keyed by form identifier for the current request.
     *
     * @var array<string,array>
     */
    private static $results = [];

    /**
     * Register shortcode, block, and submission handler hooks.
     */
    public static function init(): void {
        add_action('init', [ __CLASS__, 'register_block' ]);
        add_action('init', [ __CLASS__, 'maybe_handle_submission' ], 0);
        add_shortcode('gm2_cp_form', [ __CLASS__, 'render_shortcode' ]);
    }

    /**
     * Reset stored results. Primarily exposed for unit tests.
     */
    public static function reset_results(): void {
        self::$results = [];
    }

    /**
     * Retrieve the last stored result for a form identifier.
     *
     * @param string $form_id Form identifier.
     * @return array|null
     */
    public static function get_last_result(string $form_id): ?array {
        return self::$results[ $form_id ] ?? null;
    }

    /**
     * Handle incoming form submissions.
     */
    public static function maybe_handle_submission(): void {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        if (empty($_POST['gm2_cp_form_id']) || empty($_POST['gm2_cp_post_type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $form_id   = sanitize_html_class(wp_unslash((string) $_POST['gm2_cp_form_id'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_type = sanitize_key(wp_unslash((string) $_POST['gm2_cp_post_type'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ('' === $form_id || '' === $post_type || !post_type_exists($post_type)) {
            return;
        }

        $result = self::process_submission($form_id, $post_type);
        self::$results[ $form_id ] = $result;

        $redirect = $result['redirect'] ?? '';
        if ($result['success'] && $redirect) {
            wp_safe_redirect($redirect);
            exit;
        }
    }

    /**
     * Process form submission for a given form/post type.
     *
     * @param string $form_id   Form identifier.
     * @param string $post_type Post type slug.
     * @return array{
     *     success: bool,
     *     message: string,
     *     post_id: int,
     *     errors: array<string,string>,
     *     values: array,
     *     redirect?: string
     * }
     */
    private static function process_submission(string $form_id, string $post_type): array {
        $result = [
            'success' => false,
            'message' => '',
            'post_id' => 0,
            'errors'  => [],
            'values'  => [],
        ];

        $nonce = $_POST['gm2_cp_nonce'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION . '|' . $form_id)) {
            $result['message'] = esc_html__('Your session has expired. Please try again.', 'gm2-wordpress-suite');
            return $result;
        }

        $honeypot = isset($_POST[ self::HONEYPOT_FIELD ]) ? trim((string) wp_unslash($_POST[ self::HONEYPOT_FIELD ])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ('' !== $honeypot) {
            $result['message'] = esc_html__('Submission failed spam checks.', 'gm2-wordpress-suite');
            /**
             * Fires when the honeypot detects a spam submission.
             *
             * @param string $form_id   Form identifier.
             * @param string $post_type Post type slug.
             */
            do_action('gm2_cp_form_honeypot_triggered', $form_id, $post_type);
            return $result;
        }

        $config         = self::get_submission_config($post_type);
        $require_login  = self::resolve_require_login($post_type, $form_id, isset($_POST['gm2_cp_require_login']) ? (bool) $_POST['gm2_cp_require_login'] : null); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $require_login  = apply_filters('gm2_cp_form_require_login', $require_login, $post_type, $form_id, $config);

        if ($require_login && !is_user_logged_in()) {
            $result['message'] = esc_html__('You must be logged in to submit this form.', 'gm2-wordpress-suite');
            return $result;
        }

        $post_id = isset($_POST['gm2_cp_post_id']) ? absint($_POST['gm2_cp_post_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($post_id > 0) {
            $existing = get_post($post_id);
            if (!$existing || $existing->post_type !== $post_type) {
                $result['message'] = esc_html__('Unable to locate the requested item.', 'gm2-wordpress-suite');
                return $result;
            }
            if (!current_user_can('edit_post', $post_id)) {
                $result['message'] = esc_html__('You do not have permission to update this entry.', 'gm2-wordpress-suite');
                return $result;
            }
        }

        $groups = self::get_groups_for_post_type($post_type);
        $fields = self::collect_fields($groups);
        $fields = apply_filters('gm2_cp_form_fields', $fields, $post_type, $form_id, $config);

        $raw = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $raw = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $raw);

        $values = [];

        $supports_title   = post_type_supports($post_type, 'title');
        $supports_content = post_type_supports($post_type, 'editor');
        $supports_excerpt = post_type_supports($post_type, 'excerpt');

        if ($supports_title) {
            $values['post_title'] = sanitize_text_field($raw['post_title'] ?? '');
            if ('' === $values['post_title']) {
                $values['post_title'] = self::default_title($post_type);
            }
        }
        if ($supports_content) {
            $values['post_content'] = isset($raw['post_content']) ? wp_kses_post($raw['post_content']) : '';
        }
        if ($supports_excerpt) {
            $values['post_excerpt'] = isset($raw['post_excerpt']) ? wp_kses_post($raw['post_excerpt']) : '';
        }

        $errors = [];
        $uploads_to_attach = [];

        $original_request = $_REQUEST;
        $_REQUEST         = array_merge($_REQUEST, $raw);

        foreach ($fields as $meta_key => $field) {
            if (!Gm2_Capability_Manager::can_edit_field($meta_key, $post_id)) {
                continue;
            }

            $state = gm2_evaluate_conditions($field, $post_id);
            if (!$state['show']) {
                continue;
            }

            $field_type = $field['type'] ?? 'text';
            $value      = $raw[ $meta_key ] ?? null;

            if (in_array($field_type, [ 'file', 'media', 'audio', 'video', 'gallery' ], true)) {
                $upload_name = $meta_key . '_upload';
                $uploads     = $_FILES[ $upload_name ] ?? null;
                $value       = self::prepare_upload_value($meta_key, $field, $value, $uploads, $errors);
                if (is_array($value) && isset($value['error'])) {
                    $errors[ $meta_key ] = $value['error'];
                    $value               = null;
                } elseif ($value && !empty($value['attachments'])) {
                    $uploads_to_attach = array_merge($uploads_to_attach, $value['attachments']);
                    $value             = $value['value'];
                }
            } elseif ('checkbox' === $field_type) {
                $value = !empty($raw[ $meta_key ]) ? '1' : '';
            }

            if ($value instanceof \WP_Error) {
                $errors[ $meta_key ] = $value->get_error_message();
                continue;
            }

            $valid = gm2_validate_field($meta_key, $field, $value, $post_id, 'post');
            if (is_wp_error($valid)) {
                $errors[ $meta_key ] = $valid->get_error_message();
                continue;
            }

            $class = gm2_get_field_type_class($field_type);
            if ($class && class_exists($class)) {
                $object = new $class($meta_key, $field);
                $values[ $meta_key ] = $object->sanitize($value);
            } else {
                $values[ $meta_key ] = maybe_serialize($value);
            }
        }

        $_REQUEST = $original_request;

        $result['values'] = $values;

        if ($errors) {
            $result['errors']  = $errors;
            $result['message'] = apply_filters(
                'gm2_cp_form_error_message',
                esc_html__('Please correct the highlighted fields.', 'gm2-wordpress-suite'),
                $post_type,
                $form_id,
                $errors,
                $config
            );
            return $result;
        }

        /**
         * Allow developers to alter sanitized values before persistence.
         *
         * @param array  $values    Sanitized field values.
         * @param string $post_type Post type slug.
         * @param int    $post_id   Target post ID (0 for new submissions).
         * @param array  $config    Submission configuration.
         */
        $values = apply_filters('gm2_cp_form_prepared_values', $values, $post_type, $post_id, $config);

        $post_data = [
            'post_type'   => $post_type,
            'post_status' => self::determine_status($post_type, $config, $values, $post_id > 0),
            'post_author' => get_current_user_id(),
        ];

        if ($supports_title) {
            $post_data['post_title'] = $values['post_title'] ?? self::default_title($post_type);
            unset($values['post_title']);
        }
        if ($supports_content) {
            $post_data['post_content'] = $values['post_content'] ?? '';
            unset($values['post_content']);
        }
        if ($supports_excerpt) {
            $post_data['post_excerpt'] = $values['post_excerpt'] ?? '';
            unset($values['post_excerpt']);
        }

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
        }

        /**
         * Filter the post array prior to insertion/updating.
         *
         * @param array $post_data Prepared post arguments.
         * @param array $values    Sanitized meta values.
         * @param array $config    Submission configuration.
         * @param bool  $updating  Whether an existing post is being updated.
         */
        $post_data = apply_filters('gm2_cp_form_post_data', $post_data, $values, $config, $post_id > 0);

        if ($post_id > 0) {
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id) || 0 === $post_id) {
            $result['message'] = esc_html__('We were unable to save your submission. Please try again.', 'gm2-wordpress-suite');
            if ($post_id instanceof \WP_Error) {
                $result['errors']['post'] = $post_id->get_error_message();
            }
            return $result;
        }

        do_action('gm2_cp_form_before_save', $post_id, $post_type, $values, $config);

        foreach ($values as $meta_key => $value) {
            $field      = $fields[ $meta_key ] ?? [];
            $field_type = $field['type'] ?? 'text';
            $class      = gm2_get_field_type_class($field_type);

            if ($class && class_exists($class)) {
                $object = new $class($meta_key, $field);
                $object->save($post_id, $value, 'post');
            } else {
                if ('' === $value || null === $value) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }

        if ($uploads_to_attach) {
            foreach ($uploads_to_attach as $attachment_id) {
                if (get_post_field('post_parent', $attachment_id)) {
                    continue;
                }
                wp_update_post([
                    'ID'          => $attachment_id,
                    'post_parent' => $post_id,
                ]);
            }
        }

        do_action('gm2_cp_form_after_save', $post_id, $post_type, $values, $config);

        self::send_notifications($post_id, $post_type, $config, $values, $post_data['post_status'], $post_data, $post_id > 0);

        $result['success'] = true;
        $result['post_id'] = $post_id;
        $result['values']  = [];
        $result['message'] = apply_filters(
            'gm2_cp_form_success_message',
            esc_html__('Thank you! Your submission is now under review.', 'gm2-wordpress-suite'),
            $post_type,
            $form_id,
            $config,
            $post_id
        );

        /**
         * Allow overriding the redirect URL after successful submission.
         *
         * @param string $redirect Redirect URL.
         * @param int    $post_id  Created/updated post ID.
         * @param string $post_type Post type slug.
         * @param array  $config Submission configuration.
         */
        $result['redirect'] = apply_filters('gm2_cp_form_success_redirect', '', $post_id, $post_type, $config);

        return $result;
    }

    /**
     * Determine review status for a submission.
     *
     * @param string $post_type Post type slug.
     * @param array  $config    Submission configuration.
     * @param array  $values    Sanitized values.
     * @param bool   $updating  Whether the post already exists.
     * @return string
     */
    private static function determine_status(string $post_type, array $config, array $values, bool $updating): string {
        $default = $config['under_review_status'] ?? 'pending';
        $default = is_string($default) ? $default : 'pending';

        $requires_review = $config['require_review'] ?? true;
        if (!is_bool($requires_review)) {
            $requires_review = true;
        }

        $requires_review = apply_filters('gm2_cp_form_requires_review', $requires_review, $post_type, $values, $config, $updating);

        if (!$requires_review && !$updating) {
            $publish_status = $config['publish_status'] ?? 'publish';
            $publish_status = apply_filters('gm2_cp_form_publish_status', $publish_status, $post_type, $values, $config);
            if (is_string($publish_status) && get_post_status_object($publish_status)) {
                return $publish_status;
            }
            return 'publish';
        }

        $status = apply_filters('gm2_cp_form_under_review_status', $default, $post_type, $values, $config, $updating);
        if (!is_string($status) || !get_post_status_object($status)) {
            $status = 'pending';
        }
        return $status;
    }

    /**
     * Render the shortcode output.
     *
     * @param array       $atts    Shortcode attributes.
     * @param string|null $content Ignored content.
     * @return string
     */
    public static function render_shortcode($atts = [], $content = null): string {
        $atts = shortcode_atts(
            [
                'post_type' => '',
                'post_id'       => 0,
                'require_login' => null,
                'form_id'       => '',
            ],
            $atts,
            'gm2_cp_form'
        );

        return self::render_form([
            'post_type'     => $atts['post_type'],
            'post_id'       => (int) $atts['post_id'],
            'require_login' => $atts['require_login'],
            'form_id'       => $atts['form_id'],
        ]);
    }

    /**
     * Register the block type for the form.
     */
    public static function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('gm2/cp-form', [
            'attributes'      => [
                'postType'     => [ 'type' => 'string' ],
                'postId'       => [ 'type' => 'integer' ],
                'requireLogin' => [ 'type' => 'boolean' ],
                'formId'       => [ 'type' => 'string' ],
            ],
            'render_callback' => [ __CLASS__, 'render_block' ],
        ]);
    }

    /**
     * Render callback for the block type.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render_block(array $attributes): string {
        return self::render_form([
            'post_type'     => $attributes['postType'] ?? '',
            'post_id'       => isset($attributes['postId']) ? (int) $attributes['postId'] : 0,
            'require_login' => $attributes['requireLogin'] ?? null,
            'form_id'       => $attributes['formId'] ?? '',
        ]);
    }

    /**
     * Render the submission form.
     *
     * @param array $args Render arguments.
     * @return string
     */
    public static function render_form(array $args): string {
        $post_type = sanitize_key($args['post_type'] ?? $args['postType'] ?? '');
        if ('' === $post_type || !post_type_exists($post_type)) {
            return '';
        }

        $form_id = $args['form_id'] ?? $args['formId'] ?? '';
        $form_id = $form_id ? sanitize_html_class($form_id) : 'gm2_cp_form_' . $post_type;
        if ('' === $form_id) {
            $form_id = 'gm2_cp_form_' . $post_type;
        }

        $post_id = isset($args['post_id']) ? (int) $args['post_id'] : (int) ($args['postId'] ?? 0);
        if ($post_id > 0 && get_post_type($post_id) !== $post_type) {
            $post_id = 0;
        }

        $override = null;
        if (isset($args['require_login'])) {
            $override = self::to_bool($args['require_login']);
        } elseif (isset($args['requireLogin'])) {
            $override = self::to_bool($args['requireLogin']);
        }

        $config        = self::get_submission_config($post_type);
        $require_login = self::resolve_require_login($post_type, $form_id, $override);
        $require_login = apply_filters('gm2_cp_form_require_login', $require_login, $post_type, $form_id, $config);

        $result = self::$results[ $form_id ] ?? null;

        if ($require_login && !is_user_logged_in()) {
            $message = apply_filters(
                'gm2_cp_form_login_message',
                esc_html__('Please log in to submit this form.', 'gm2-wordpress-suite'),
                $post_type,
                $form_id,
                $config
            );
            return '<div class="gm2-cp-form gm2-cp-form-login-required" id="' . esc_attr($form_id) . '">' . wpautop(esc_html($message)) . '</div>';
        }

        $groups = self::get_groups_for_post_type($post_type);
        $fields = self::collect_fields($groups);

        $values = [];
        if ($result && !$result['success'] && !empty($result['values'])) {
            $values = $result['values'];
        } elseif ($post_id > 0) {
            $values = self::load_existing_values($post_id, $fields);
        }

        $supports_title   = post_type_supports($post_type, 'title');
        $supports_content = post_type_supports($post_type, 'editor');
        $supports_excerpt = post_type_supports($post_type, 'excerpt');

        if ($post_id > 0) {
            if ($supports_title) {
                $values['post_title'] = get_post_field('post_title', $post_id);
            }
            if ($supports_content) {
                $values['post_content'] = get_post_field('post_content', $post_id);
            }
            if ($supports_excerpt) {
                $values['post_excerpt'] = get_post_field('post_excerpt', $post_id);
            }
        }

        $field_errors = $result['errors'] ?? [];
        $success_msg  = ($result && $result['success']) ? $result['message'] : '';
        $error_msg    = ($result && !$result['success']) ? $result['message'] : '';

        $original_request = $_REQUEST;
        if ($values) {
            $_REQUEST = array_merge($_REQUEST, $values);
        }

        ob_start();
        echo '<div class="gm2-cp-form" id="' . esc_attr($form_id) . '">';
        if ($success_msg) {
            echo '<div class="gm2-cp-form-notice gm2-cp-form-success">' . wpautop(esc_html($success_msg)) . '</div>';
        }
        if ($error_msg) {
            echo '<div class="gm2-cp-form-notice gm2-cp-form-error">' . wpautop(esc_html($error_msg)) . '</div>';
        }
        echo '<form method="post" enctype="multipart/form-data">';

        if ($supports_title) {
            $title_value = $values['post_title'] ?? '';
            echo '<div class="gm2-cp-field gm2-cp-field-title' . (isset($field_errors['post_title']) ? ' gm2-cp-field-has-error' : '') . '">';
            echo '<label for="' . esc_attr($form_id . '_post_title') . '">' . esc_html__('Title', 'gm2-wordpress-suite') . '</label>';
            echo '<input type="text" name="post_title" id="' . esc_attr($form_id . '_post_title') . '" value="' . esc_attr($title_value) . '" required />';
            if (!empty($field_errors['post_title'])) {
                echo '<p class="gm2-cp-error">' . esc_html($field_errors['post_title']) . '</p>';
            }
            echo '</div>';
        }

        if ($supports_content) {
            $content_value = $values['post_content'] ?? '';
            echo '<div class="gm2-cp-field gm2-cp-field-content' . (isset($field_errors['post_content']) ? ' gm2-cp-field-has-error' : '') . '">';
            echo '<label for="' . esc_attr($form_id . '_post_content') . '">' . esc_html__('Content', 'gm2-wordpress-suite') . '</label>';
            echo '<textarea name="post_content" id="' . esc_attr($form_id . '_post_content') . '" rows="6">' . esc_textarea($content_value) . '</textarea>';
            if (!empty($field_errors['post_content'])) {
                echo '<p class="gm2-cp-error">' . esc_html($field_errors['post_content']) . '</p>';
            }
            echo '</div>';
        }

        if ($supports_excerpt) {
            $excerpt_value = $values['post_excerpt'] ?? '';
            echo '<div class="gm2-cp-field gm2-cp-field-excerpt' . (isset($field_errors['post_excerpt']) ? ' gm2-cp-field-has-error' : '') . '">';
            echo '<label for="' . esc_attr($form_id . '_post_excerpt') . '">' . esc_html__('Excerpt', 'gm2-wordpress-suite') . '</label>';
            echo '<textarea name="post_excerpt" id="' . esc_attr($form_id . '_post_excerpt') . '" rows="3">' . esc_textarea($excerpt_value) . '</textarea>';
            if (!empty($field_errors['post_excerpt'])) {
                echo '<p class="gm2-cp-error">' . esc_html($field_errors['post_excerpt']) . '</p>';
            }
            echo '</div>';
        }

        foreach ($groups as $group) {
            $title = $group['title'] ?? '';
            $group_fields = $group['fields'] ?? [];
            if ($title) {
                echo '<fieldset class="gm2-cp-group">';
                echo '<legend>' . esc_html($title) . '</legend>';
            } else {
                echo '<div class="gm2-cp-group">';
            }

            foreach ($group_fields as $meta_key => $field) {
                $field_key = self::normalize_field_key($meta_key, $field);
                if (!$field_key) {
                    continue;
                }
                if (!Gm2_Capability_Manager::can_read_field($field_key, $post_id)) {
                    continue;
                }
                $state = gm2_evaluate_conditions($field, $post_id);
                if (!$state['show']) {
                    continue;
                }
                $field_value = $values[ $field_key ] ?? gm2_get_meta_value($post_id, $field_key, 'post', $field);
                self::render_field($form_id, $field_key, $field, $field_value, $field_errors[ $field_key ] ?? '');
            }

            if ($title) {
                echo '</fieldset>';
            } else {
                echo '</div>';
            }
        }

        echo '<input type="hidden" name="gm2_cp_form_id" value="' . esc_attr($form_id) . '" />';
        echo '<input type="hidden" name="gm2_cp_post_type" value="' . esc_attr($post_type) . '" />';
        if ($post_id > 0) {
            echo '<input type="hidden" name="gm2_cp_post_id" value="' . esc_attr((string) $post_id) . '" />';
        }
        if (null !== $override) {
            echo '<input type="hidden" name="gm2_cp_require_login" value="' . ($override ? '1' : '0') . '" />';
        }
        wp_nonce_field(self::NONCE_ACTION . '|' . $form_id, 'gm2_cp_nonce');
        echo '<div class="gm2-cp-honeypot" aria-hidden="true">';
        echo '<label class="screen-reader-text" for="' . esc_attr($form_id . '_hp') . '">' . esc_html__('Leave this field empty', 'gm2-wordpress-suite') . '</label>';
        echo '<input type="text" name="' . esc_attr(self::HONEYPOT_FIELD) . '" id="' . esc_attr($form_id . '_hp') . '" value="" tabindex="-1" autocomplete="off" />';
        echo '</div>';

        echo '<button type="submit" class="gm2-cp-submit">' . esc_html__('Submit', 'gm2-wordpress-suite') . '</button>';
        echo '</form>';
        echo '</div>';

        $output = ob_get_clean();

        $_REQUEST = $original_request;

        return $output;
    }

    /**
     * Render an individual field.
     *
     * @param string $form_id Form identifier.
     * @param string $meta_key Meta key.
     * @param array  $field Field definition.
     * @param mixed  $value Current value.
     * @param string $error Error message.
     */
    private static function render_field(string $form_id, string $meta_key, array $field, $value, string $error): void {
        $type  = $field['type'] ?? 'text';
        $label = $field['label'] ?? $meta_key;
        $required = !empty($field['required']);
        $input_id = $form_id . '_' . $meta_key;
        $classes  = 'gm2-cp-field gm2-cp-field-' . sanitize_html_class($type);
        if ($error) {
            $classes .= ' gm2-cp-field-has-error';
        }

        echo '<div class="' . esc_attr($classes) . '">';
        if (!in_array($type, [ 'checkbox' ], true)) {
            echo '<label for="' . esc_attr($input_id) . '">' . esc_html($label);
            if ($required) {
                echo ' <span class="gm2-cp-required">*</span>';
            }
            echo '</label>';
        }

        switch ($type) {
            case 'textarea':
            case 'wysiwyg':
            case 'markdown':
                echo '<textarea name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" rows="5"' . ($required ? ' required' : '') . '>' . esc_textarea((string) $value) . '</textarea>';
                break;
            case 'number':
                $min = isset($field['min']) ? ' min="' . esc_attr($field['min']) . '"' : '';
                $max = isset($field['max']) ? ' max="' . esc_attr($field['max']) . '"' : '';
                echo '<input type="number" name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr((string) $value) . '"' . $min . $max . ($required ? ' required' : '') . ' />';
                break;
            case 'email':
                echo '<input type="email" name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr((string) $value) . '"' . ($required ? ' required' : '') . ' />';
                break;
            case 'url':
                echo '<input type="url" name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr((string) $value) . '"' . ($required ? ' required' : '') . ' />';
                break;
            case 'date':
            case 'time':
            case 'datetime':
                $input_type = ('datetime' === $type) ? 'datetime-local' : $type;
                echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr((string) $value) . '"' . ($required ? ' required' : '') . ' />';
                break;
            case 'checkbox':
                $checked = !empty($value) ? ' checked' : '';
                echo '<label><input type="checkbox" name="' . esc_attr($meta_key) . '" value="1" id="' . esc_attr($input_id) . '"' . $checked . ($required ? ' required' : '') . ' /> ' . esc_html($label) . '</label>';
                break;
            case 'radio':
                $options = $field['options'] ?? [];
                foreach ($options as $option_value => $option_label) {
                    $checked = ((string) $value === (string) $option_value) ? ' checked' : '';
                    $option_id = $input_id . '_' . sanitize_html_class((string) $option_value);
                    echo '<label class="gm2-cp-option"><input type="radio" name="' . esc_attr($meta_key) . '" id="' . esc_attr($option_id) . '" value="' . esc_attr((string) $option_value) . '"' . $checked . ($required ? ' required' : '') . ' /> ' . esc_html((string) $option_label) . '</label>';
                }
                break;
            case 'select':
                $options  = $field['options'] ?? [];
                $multiple = !empty($field['multiple']);
                $current  = $multiple ? (array) $value : [ (string) $value ];
                echo '<select name="' . esc_attr($meta_key) . ($multiple ? '[]' : '') . '" id="' . esc_attr($input_id) . '"' . ($multiple ? ' multiple' : '') . ($required ? ' required' : '') . '>';
                foreach ($options as $option_value => $option_label) {
                    $selected = in_array((string) $option_value, array_map('strval', $current), true) ? ' selected' : '';
                    echo '<option value="' . esc_attr((string) $option_value) . '"' . $selected . '>' . esc_html((string) $option_label) . '</option>';
                }
                echo '</select>';
                break;
            case 'file':
            case 'media':
            case 'audio':
            case 'video':
                $existing = $value ? (array) $value : [];
                $existing_id = is_array($existing) ? reset($existing) : $existing;
                if ($existing_id) {
                    echo '<input type="hidden" name="' . esc_attr($meta_key) . '" value="' . esc_attr((string) $existing_id) . '" />';
                    $url = wp_get_attachment_url((int) $existing_id);
                    if ($url) {
                        echo '<p class="gm2-cp-current-file"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(wp_basename($url)) . '</a></p>';
                    }
                }
                echo '<input type="file" name="' . esc_attr($meta_key) . '_upload" id="' . esc_attr($input_id) . '"' . ($required && !$existing_id ? ' required' : '') . ' />';
                break;
            case 'gallery':
                $existing = is_array($value) ? array_filter(array_map('intval', $value)) : [];
                foreach ($existing as $attachment_id) {
                    echo '<input type="hidden" name="' . esc_attr($meta_key) . '[]" value="' . esc_attr((string) $attachment_id) . '" />';
                }
                echo '<input type="file" name="' . esc_attr($meta_key) . '_upload[]" id="' . esc_attr($input_id) . '" multiple' . ($required && empty($existing) ? ' required' : '') . ' />';
                break;
            default:
                echo '<input type="text" name="' . esc_attr($meta_key) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr((string) $value) . '"' . ($required ? ' required' : '') . ' />';
                break;
        }

        if (!empty($field['instructions'])) {
            echo '<p class="gm2-cp-instructions">' . esc_html($field['instructions']) . '</p>';
        }

        if ($error) {
            echo '<p class="gm2-cp-error">' . esc_html($error) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Convert arbitrary truthy values to booleans.
     *
     * @param mixed $value Raw value.
     * @return bool|null
     */
    private static function to_bool($value): ?bool {
        if (null === $value || '' === $value) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool) (int) $value;
        }
        $value = strtolower((string) $value);
        if (in_array($value, [ '1', 'true', 'yes', 'on' ], true)) {
            return true;
        }
        if (in_array($value, [ '0', 'false', 'no', 'off' ], true)) {
            return false;
        }
        return null;
    }

    /**
     * Gather field groups for a post type.
     *
     * @param string $post_type Post type slug.
     * @return array<int,array>
     */
    private static function get_groups_for_post_type(string $post_type): array {
        $all = get_option('gm2_field_groups', []);
        if (!is_array($all)) {
            return [];
        }

        $matched = [];
        foreach ($all as $group_key => $group) {
            if (!is_array($group)) {
                continue;
            }
            $scope   = $group['scope'] ?? 'post_type';
            $objects = (array) ($group['objects'] ?? []);
            $fields  = $group['fields'] ?? [];

            $include = false;
            if ('post_type' === $scope && in_array($post_type, $objects, true)) {
                $include = true;
            } elseif (!empty($group['location'])) {
                $include = gm2_match_location($group['location'], [ 'post_type' => $post_type ]);
            }

            if ($include) {
                $matched[] = [
                    'key'    => $group_key,
                    'title'  => $group['title'] ?? '',
                    'fields' => $fields,
                ];
            }
        }

        return $matched;
    }

    /**
     * Flatten group fields into a single map keyed by meta key.
     *
     * @param array $groups Field groups.
     * @return array<string,array>
     */
    private static function collect_fields(array $groups): array {
        $fields = [];
        foreach ($groups as $group) {
            foreach (($group['fields'] ?? []) as $maybe_key => $field) {
                $meta_key = self::normalize_field_key($maybe_key, $field);
                if (!$meta_key || !is_array($field)) {
                    continue;
                }
                $fields[ $meta_key ] = $field;
            }
        }
        return $fields;
    }

    /**
     * Normalize the meta key for a field definition.
     *
     * @param mixed $maybe_key Array key.
     * @param array $field     Field definition.
     * @return string
     */
    private static function normalize_field_key($maybe_key, array $field): string {
        if (is_string($maybe_key) && '' !== $maybe_key) {
            return $maybe_key;
        }
        if (!empty($field['name']) && is_string($field['name'])) {
            return $field['name'];
        }
        if (!empty($field['key']) && is_string($field['key'])) {
            return $field['key'];
        }
        return '';
    }

    /**
     * Prepare file uploads for a field.
     *
     * @param string     $meta_key Meta key.
     * @param array      $field    Field definition.
     * @param mixed      $current  Current value.
     * @param array|null $uploads  Uploaded file data.
     * @param array      $errors   Reference to error array.
     * @return array|string|int|null
     */
    private static function prepare_upload_value(string $meta_key, array $field, $current, $uploads, array &$errors) {
        $type = $field['type'] ?? 'file';

        $existing = [];
        if ('gallery' === $type) {
            $existing = is_array($current) ? array_filter(array_map('absint', $current)) : [];
        } elseif ($current) {
            $existing = [ absint($current) ];
        }

        $attachments = [];
        if ($uploads && self::has_upload($uploads)) {
            $files = self::normalize_uploads($uploads);
            foreach ($files as $file) {
                $uploaded = self::handle_upload($file, $field);
                if (is_wp_error($uploaded)) {
                    return [ 'error' => $uploaded->get_error_message() ];
                }
                $attachments[] = $uploaded;
            }
        }

        if ($attachments) {
            if ('gallery' === $type) {
                $value = array_merge($existing, $attachments);
            } else {
                $value = reset($attachments);
            }
            return [
                'attachments' => $attachments,
                'value'       => $value,
            ];
        }

        if ('gallery' === $type) {
            return $existing;
        }

        return $existing ? reset($existing) : null;
    }

    /**
     * Check whether uploaded data contains a file.
     *
     * @param array $uploads Uploaded data.
     * @return bool
     */
    private static function has_upload(array $uploads): bool {
        if (is_array($uploads['name'])) {
            foreach ($uploads['name'] as $index => $name) {
                if (UPLOAD_ERR_NO_FILE !== (int) ($uploads['error'][ $index ] ?? UPLOAD_ERR_NO_FILE)) {
                    return true;
                }
            }
            return false;
        }
        return isset($uploads['error']) && UPLOAD_ERR_NO_FILE !== (int) $uploads['error'];
    }

    /**
     * Normalise the $_FILES structure into individual file arrays.
     *
     * @param array $uploads Uploaded data.
     * @return array<int,array>
     */
    private static function normalize_uploads(array $uploads): array {
        $normalised = [];
        if (is_array($uploads['name'])) {
            foreach ($uploads['name'] as $index => $name) {
                $error = $uploads['error'][ $index ] ?? UPLOAD_ERR_OK;
                if (UPLOAD_ERR_NO_FILE === (int) $error) {
                    continue;
                }
                $normalised[] = [
                    'name'     => $name,
                    'type'     => $uploads['type'][ $index ] ?? '',
                    'tmp_name' => $uploads['tmp_name'][ $index ] ?? '',
                    'error'    => $error,
                    'size'     => $uploads['size'][ $index ] ?? 0,
                ];
            }
        } else {
            $normalised[] = $uploads;
        }
        return $normalised;
    }

    /**
     * Handle a single file upload.
     *
     * @param array $file  Uploaded file data.
     * @param array $field Field definition.
     * @return int|\WP_Error Attachment ID or error.
     */
    private static function handle_upload(array $file, array $field) {
        if (!empty($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            return new \WP_Error('gm2_upload_error', esc_html__('Upload failed. Please try again.', 'gm2-wordpress-suite'));
        }

        $max_size = (int) ($field['max_size'] ?? 0);
        $max_size = (int) apply_filters('gm2_cp_form_max_file_size', $max_size, $field, $file);
        if ($max_size > 0 && (int) $file['size'] > $max_size) {
            return new \WP_Error('gm2_file_size', esc_html__('File is too large.', 'gm2-wordpress-suite'));
        }

        $allowed = array_map('strtolower', (array) ($field['allowed_types'] ?? []));
        if (!$allowed) {
            switch ($field['type'] ?? '') {
                case 'gallery':
                    $allowed = [ 'image' ];
                    break;
                case 'audio':
                    $allowed = [ 'audio' ];
                    break;
                case 'video':
                    $allowed = [ 'video' ];
                    break;
            }
        }
        $allowed = apply_filters('gm2_cp_form_allowed_file_types', $allowed, $field, $file);

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $mime  = strtolower($check['type'] ?? ($file['type'] ?? ''));
        $ext   = strtolower($check['ext'] ?? pathinfo($file['name'], PATHINFO_EXTENSION));
        $top   = $mime ? strtok($mime, '/') : '';

        if ($allowed && !in_array($mime, $allowed, true) && !($top && in_array($top, $allowed, true)) && !in_array($ext, $allowed, true)) {
            return new \WP_Error('gm2_file_type', esc_html__('Invalid file type.', 'gm2-wordpress-suite'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload($file, [ 'test_form' => false ]);
        if (isset($uploaded['error'])) {
            return new \WP_Error('gm2_upload_error', $uploaded['error']);
        }

        $attachment = [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        if ($metadata) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return $attachment_id;
    }

    /**
     * Load existing field values for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $fields  Field definitions.
     * @return array
     */
    private static function load_existing_values(int $post_id, array $fields): array {
        $values = [];
        foreach ($fields as $meta_key => $field) {
            $values[ $meta_key ] = gm2_get_meta_value($post_id, $meta_key, 'post', $field);
        }
        return $values;
    }

    /**
     * Retrieve submission configuration for a post type.
     *
     * @param string $post_type Post type slug.
     * @return array
     */
    private static function get_submission_config(string $post_type): array {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            $config = [];
        }
        $submission = $config['post_types'][ $post_type ]['submission'] ?? [];
        if (!is_array($submission)) {
            $submission = [];
        }
        return apply_filters('gm2_cp_form_submission_config', $submission, $post_type, $config);
    }

    /**
     * Determine if login is required.
     *
     * @param string   $post_type Post type slug.
     * @param string   $form_id   Form identifier.
     * @param bool|null $override Override value from shortcode/block.
     * @return bool
     */
    private static function resolve_require_login(string $post_type, string $form_id, ?bool $override): bool {
        if (null !== $override) {
            return $override;
        }
        $config = self::get_submission_config($post_type);
        return !empty($config['require_login']);
    }

    /**
     * Send notifications for a submission.
     *
     * @param int    $post_id     Post ID.
     * @param string $post_type   Post type slug.
     * @param array  $config      Submission configuration.
     * @param array  $values      Sanitized meta values.
     * @param string $status      Post status after save.
     * @param array  $post_data   Post data array.
     * @param bool   $updating    Whether the submission updated an existing post.
     */
    private static function send_notifications(int $post_id, string $post_type, array $config, array $values, string $status, array $post_data, bool $updating): void {
        $summary = self::build_summary($values);
        $admin_subject = $config['admin_subject'] ?? sprintf(
            __('New %s submission received', 'gm2-wordpress-suite'),
            $post_type
        );
        $admin_message = $config['admin_message'] ?? sprintf(
            "%%s\n\n%%s",
            sprintf(__('A new submission (ID #%d) is awaiting review.', 'gm2-wordpress-suite'), $post_id),
            $summary
        );

        $admin_recipients = $config['admin_emails'] ?? get_option('admin_email');
        $admin_recipients = array_filter(array_map('sanitize_email', (array) $admin_recipients));

        $admin_email = [
            'to'       => $admin_recipients,
            'subject'  => $admin_subject,
            'message'  => self::replace_email_tokens($admin_message, $post_id, $post_type, $values, $summary, $status, $updating),
            'headers'  => [],
            'post_id'  => $post_id,
            'post_type'=> $post_type,
        ];

        $admin_email = apply_filters('gm2_cp_form_admin_email', $admin_email, $config, $values, $post_data, $updating);

        if (!empty($admin_email['to'])) {
            wp_mail($admin_email['to'], $admin_email['subject'], $admin_email['message'], $admin_email['headers'] ?? []);
        }

        $submitter_field = $config['submitter_email_field'] ?? '';
        $submitter_email = '';
        if ($submitter_field && isset($values[ $submitter_field ])) {
            $raw = $values[ $submitter_field ];
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            $submitter_email = sanitize_email((string) $raw);
        }

        if (!$submitter_email) {
            return;
        }

        $submitter_subject = $config['submitter_subject'] ?? __('Thank you for your submission', 'gm2-wordpress-suite');
        $submitter_message = $config['submitter_message'] ?? sprintf(
            "%s\n\n%s",
            __('We have received your submission and will review it shortly.', 'gm2-wordpress-suite'),
            $summary
        );

        $submitter_email_args = [
            'to'       => $submitter_email,
            'subject'  => $submitter_subject,
            'message'  => self::replace_email_tokens($submitter_message, $post_id, $post_type, $values, $summary, $status, $updating),
            'headers'  => [],
        ];

        $submitter_email_args = apply_filters('gm2_cp_form_submitter_email', $submitter_email_args, $config, $values, $post_data, $updating);

        if (!empty($submitter_email_args['to'])) {
            wp_mail($submitter_email_args['to'], $submitter_email_args['subject'], $submitter_email_args['message'], $submitter_email_args['headers'] ?? []);
        }
    }

    /**
     * Replace template tokens within email content.
     *
     * @param string $content Email content.
     * @param int    $post_id Post ID.
     * @param string $post_type Post type slug.
     * @param array  $values Submitted values.
     * @param string $summary Field summary.
     * @param string $status  Post status.
     * @param bool   $updating Whether submission updated an existing post.
     * @return string
     */
    private static function replace_email_tokens(string $content, int $post_id, string $post_type, array $values, string $summary, string $status, bool $updating): string {
        $tokens = [
            '{post_id}'     => (string) $post_id,
            '{post_type}'   => $post_type,
            '{status}'      => $status,
            '{summary}'     => $summary,
            '{permalink}'   => get_permalink($post_id) ?: '',
            '{edit_link}'   => get_edit_post_link($post_id) ?: '',
            '{is_update}'   => $updating ? '1' : '0',
            '{site_name}'   => get_bloginfo('name'),
        ];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
            }
            $tokens['{' . $key . '}'] = (string) $value;
        }

        $content = strtr($content, $tokens);

        return apply_filters('gm2_cp_form_email_content', $content, $post_id, $post_type, $values, $status, $updating);
    }

    /**
     * Build a plain-text summary of submitted fields.
     *
     * @param array $values Sanitized meta values.
     * @return string
     */
    private static function build_summary(array $values): string {
        $lines = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $formatted = implode(', ', array_map('strval', $value));
            } elseif (is_scalar($value)) {
                $formatted = (string) $value;
            } else {
                $formatted = wp_json_encode($value);
            }
            $lines[] = sprintf('%s: %s', $key, $formatted);
        }
        $summary = implode("\n", $lines);

        return apply_filters('gm2_cp_form_submission_summary', $summary, $values);
    }

    /**
     * Generate a default title for submissions.
     *
     * @param string $post_type Post type slug.
     * @return string
     */
    private static function default_title(string $post_type): string {
        $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
        return apply_filters(
            'gm2_cp_form_default_title',
            sprintf(__('Submission on %s', 'gm2-wordpress-suite'), wp_date($format)),
            $post_type
        );
    }
}

Gm2_CP_Form::init();
