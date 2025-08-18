<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/fields/loader.php';

/**
 * Evaluate condition groups for a field or argument.
 *
 * Supports show/hide/disable actions. When no conditions are supplied the
 * function defaults to showing the field.
 *
 * @param array $item    Field or arg array possibly containing `conditions` or `conditional` keys.
 * @param int   $post_id Post ID context for meta lookup.
 * @return array { show: bool, disabled: bool }
 */
function gm2_evaluate_conditions($item, $post_id = 0) {
    if (!is_array($item)) {
        return [ 'show' => true, 'disabled' => false ];
    }

    // Build groups from modern `conditions` or legacy `conditional`.
    $groups = [];
    if (!empty($item['conditions']) && is_array($item['conditions'])) {
        $groups = $item['conditions'];
    } elseif (!empty($item['conditional']['field']) && isset($item['conditional']['value'])) {
        $groups = [
            [
                'relation'   => 'AND',
                'conditions' => [
                    [
                        'relation' => 'AND',
                        'target'   => $item['conditional']['field'],
                        'operator' => '=',
                        'value'    => (string) $item['conditional']['value'],
                    ],
                ],
            ],
        ];
    }

    if (empty($groups)) {
        return [ 'show' => true, 'disabled' => false ];
    }

    $result   = null;
    $disabled = false;
    foreach ($groups as $group) {
        $group_res = null;
        foreach (($group['conditions'] ?? []) as $cond) {
            $target = $cond['target'] ?? '';
            if ($target === '') {
                continue;
            }
            // Determine the current value from request or post meta.
            if (isset($_REQUEST[$target])) {
                $current = $_REQUEST[$target];
                if (is_array($current)) {
                    $current = reset($current);
                }
            } else {
                $current = ($post_id) ? get_post_meta($post_id, $target, true) : '';
            }
            $current  = (string) $current;
            $expected = (string) ($cond['value'] ?? '');
            $ok       = false;
            switch ($cond['operator'] ?? '=') {
                case '!=':
                    $ok = $current !== $expected;
                    break;
                case '>':
                    $ok = floatval($current) > floatval($expected);
                    break;
                case '<':
                    $ok = floatval($current) < floatval($expected);
                    break;
                case 'contains':
                    $ok = strpos($current, $expected) !== false;
                    break;
                default:
                    $ok = $current === $expected;
                    break;
            }
            if ($group_res === null) {
                $group_res = $ok;
            } else {
                $rel       = strtoupper($cond['relation'] ?? 'AND');
                $group_res = ($rel === 'OR') ? ($group_res || $ok) : ($group_res && $ok);
            }
        }
        if ($group_res === null) {
            $group_res = false;
        }
        if ($result === null) {
            $result = $group_res;
        } else {
            $rel    = strtoupper($group['relation'] ?? 'AND');
            $result = ($rel === 'OR') ? ($result || $group_res) : ($result && $group_res);
        }

        if ($group_res && !empty($group['action'])) {
            switch ($group['action']) {
                case 'hide':
                    $result = false;
                    break;
                case 'show':
                    $result = true;
                    break;
                case 'disable':
                    $disabled = true;
                    break;
            }
        }
    }

    $visible = ($result === null) ? true : (bool) $result;

    return [ 'show' => $visible, 'disabled' => $disabled ];
}

/**
 * Resolve the default value for a field, supporting static, callback and
 * templated defaults.
 */
function gm2_resolve_default($field, $object_id = 0, $context_type = 'post') {
    // Static default.
    $default = $field['default'] ?? '';

    // Array syntax for defaults allowing value/callback/template.
    if (is_array($default)) {
        if (isset($default['value'])) {
            $default = $default['value'];
        }
        if (isset($default['callback']) && is_callable($default['callback'])) {
            return call_user_func($default['callback'], $object_id, $field, $context_type);
        }
        if (isset($default['template'])) {
            $field['default_template'] = $default['template'];
        }
    }

    // Callback default.
    if (isset($field['default_callback']) && is_callable($field['default_callback'])) {
        return call_user_func($field['default_callback'], $object_id, $field, $context_type);
    }

    // Template default.
    if (!empty($field['default_template'])) {
        $template = $field['default_template'];
        $replacements = [
            '{post_id}' => $object_id,
        ];
        if ($context_type === 'post' && $object_id) {
            $post = get_post($object_id);
            if ($post) {
                $replacements['{post_title}'] = $post->post_title;
                $replacements['{post_slug}']  = $post->post_name;
            }
        }
        $template = strtr($template, $replacements);
        return gm2_render_default_tokens($template);
    }

    return gm2_render_default_tokens($default);
}

/**
 * Replace dynamic tokens within a default string.
 *
 * Currently supports date tokens of the form `{date:FORMAT}` which are
 * formatted using the site's timezone.
 *
 * @param mixed $value Value containing tokens.
 * @return mixed Value with tokens replaced.
 */
function gm2_render_default_tokens($value) {
    if (!is_string($value)) {
        return $value;
    }

    $value = preg_replace_callback('/{date:([^}]+)}/', function ($matches) {
        $format = trim($matches[1]);
        return wp_date($format, time(), wp_timezone());
    }, $value);

    return $value;
}

/**
 * Validate a value against field rules.
 *
 * @param string $key         Field key/meta key.
 * @param array  $field       Field definition.
 * @param mixed  $value       Value to validate.
 * @param int    $object_id   Context object ID.
 * @param string $context_type Context type (post, user, etc.).
 * @return true|WP_Error True when valid.
 */
function gm2_validate_field($key, $field, $value, $object_id = 0, $context_type = 'post') {
    $messages = $field['messages'] ?? [];

    if (!empty($field['validate_callback']) && is_callable($field['validate_callback'])) {
        $res = call_user_func($field['validate_callback'], $value, $object_id, $field, $context_type);
        if ($res !== true) {
            $msg = is_string($res) ? $res : ($messages['callback'] ?? __('Invalid value.', 'gm2-wordpress-suite'));
            return new WP_Error('gm2_callback', $msg);
        }
    }

    $is_empty = ($value === '' || $value === null || (is_array($value) && count(array_filter($value, function ($v) {
        return $v !== '' && $v !== null;
    })) === 0));

    if (!empty($field['required']) && $is_empty) {
        $msg = $messages['required'] ?? __('This field is required.', 'gm2-wordpress-suite');
        return new WP_Error('gm2_required', $msg);
    }

    if ($is_empty) {
        return true; // Nothing more to validate.
    }

    $numeric = is_numeric($value);
    $length  = $numeric ? $value : (is_array($value) ? count($value) : strlen((string) $value));

    if (isset($field['min']) && $length < $field['min']) {
        $msg = $messages['min'] ?? sprintf(__('Minimum value is %s.', 'gm2-wordpress-suite'), $field['min']);
        return new WP_Error('gm2_min', $msg);
    }
    if (isset($field['max']) && $length > $field['max']) {
        $msg = $messages['max'] ?? sprintf(__('Maximum value is %s.', 'gm2-wordpress-suite'), $field['max']);
        return new WP_Error('gm2_max', $msg);
    }

    if (!empty($field['regex']) && is_string($value) && !preg_match($field['regex'], $value)) {
        $msg = $messages['regex'] ?? __('Invalid format.', 'gm2-wordpress-suite');
        return new WP_Error('gm2_regex', $msg);
    }

    $type = $field['type'] ?? '';
    if ($type === 'time') {
        $tz = wp_timezone();
        $dt = date_create_from_format('H:i:s', $value, $tz);
        if (!$dt) {
            $dt = date_create_from_format('H:i', $value, $tz);
        }
        if (!$dt) {
            $msg = $messages['time'] ?? __('Invalid time.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_time', $msg);
        }
    } elseif ($type === 'datetime') {
        $tz  = wp_timezone();
        $val = is_string($value) ? str_replace('T', ' ', $value) : $value;
        $dt  = $val ? date_create($val, $tz) : false;
        if (!$dt) {
            $msg = $messages['datetime'] ?? __('Invalid date/time.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_datetime', $msg);
        }
    } elseif ($type === 'daterange') {
        if (!is_array($value) || !array_key_exists('start', $value) || !array_key_exists('end', $value)) {
            $msg = $messages['daterange'] ?? __('Invalid date range.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_daterange', $msg);
        }
        $tz    = wp_timezone();
        $start = $value['start'] !== '' ? date_create($value['start'], $tz) : false;
        $end   = $value['end'] !== '' ? date_create($value['end'], $tz) : false;
        if (!$start || !$end || $start > $end) {
            $msg = $messages['daterange'] ?? __('Invalid date range.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_daterange', $msg);
        }
    }

    if (is_array($value)) {
        if (isset($field['min_rows']) && count($value) < $field['min_rows']) {
            $msg = $messages['min_rows'] ?? sprintf(__('Minimum %s rows required.', 'gm2-wordpress-suite'), $field['min_rows']);
            return new WP_Error('gm2_min_rows', $msg);
        }
        if (isset($field['max_rows']) && count($value) > $field['max_rows']) {
            $msg = $messages['max_rows'] ?? sprintf(__('Maximum %s rows allowed.', 'gm2-wordpress-suite'), $field['max_rows']);
            return new WP_Error('gm2_max_rows', $msg);
        }
    }

    if (!empty($field['unique'])) {
        global $wpdb;
        $scope   = $field['unique_scope'] ?? $context_type;
        $existing = false;
        $maybe_serialized = maybe_serialize($value);
        switch ($scope) {
            case 'post_type':
            case 'post':
            case 'site':
                if ($context_type === 'post') {
                    $args = [
                        'post_type'      => ($scope === 'post_type') ? get_post_type($object_id) : 'any',
                        'post__not_in'   => [ $object_id ],
                        'meta_query'     => [ [ 'key' => $key, 'value' => $value ] ],
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                    ];
                    $existing = get_posts($args);
                }
                break;
            case 'user':
                $sql      = $wpdb->prepare("SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s AND user_id != %d LIMIT 1", $key, $maybe_serialized, (int) $object_id);
                $existing = $wpdb->get_var($sql);
                break;
            case 'term':
                $sql      = $wpdb->prepare("SELECT meta_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s AND term_id != %d LIMIT 1", $key, $maybe_serialized, (int) $object_id);
                $existing = $wpdb->get_var($sql);
                break;
            case 'comment':
                $sql      = $wpdb->prepare("SELECT meta_id FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value = %s AND comment_id != %d LIMIT 1", $key, $maybe_serialized, (int) $object_id);
                $existing = $wpdb->get_var($sql);
                break;
            case 'option':
                $sql      = $wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name != %s AND option_value = %s LIMIT 1", $key, $maybe_serialized);
                $existing = $wpdb->get_var($sql);
                break;
        }
        if ($existing) {
            $msg = $messages['unique'] ?? __('Value must be unique.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_unique', $msg);
        }
    }

    if (in_array($type, ['media', 'file', 'audio', 'video', 'gallery'], true) && $value) {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_filter(array_map('intval', $ids));
        $allowed = array_map('strtolower', (array) ($field['allowed_types'] ?? []));
        if (!$allowed) {
            if ($type === 'gallery') {
                $allowed = ['image'];
            } elseif ($type === 'audio') {
                $allowed = ['audio'];
            } elseif ($type === 'video') {
                $allowed = ['video'];
            }
        }
        foreach ($ids as $id) {
            if ($allowed) {
                $mime = get_post_mime_type($id);
                $mime = $mime ? strtolower($mime) : '';
                $top  = strstr($mime, '/', true);
                $is_allowed = in_array($mime, $allowed, true) || ($top && in_array($top, $allowed, true));
                if (!$is_allowed) {
                    $msg = $messages['file_type'] ?? __('Invalid file type.', 'gm2-wordpress-suite');
                    return new WP_Error('gm2_file_type', $msg);
                }
            }
            if (!empty($field['max_size'])) {
                $file = get_attached_file($id);
                if ($file && filesize($file) > $field['max_size']) {
                    $msg = $messages['file_size'] ?? __('File is too large.', 'gm2-wordpress-suite');
                    return new WP_Error('gm2_file_size', $msg);
                }
            }
        }
    }

    return true;
}

/**
 * Register custom post types and taxonomies from stored configuration.
 */
function gm2_register_custom_posts() {
    $config = get_option('gm2_custom_posts_config', []);
    if (!is_array($config)) {
        return;
    }
    foreach ($config['post_types'] ?? [] as $slug => $pt) {
        $args = [];
        foreach ($pt['args'] ?? [] as $key => $val) {
            $value = is_array($val) && array_key_exists('value', $val) ? $val['value'] : $val;
            if ($key === 'supports' && is_array($value)) {
                $args['supports'] = $value;
            } elseif ($key === 'labels' && is_array($value)) {
                $args['labels'] = $value;
            } elseif ($key === 'rewrite' && is_array($value)) {
                $args['rewrite'] = $value;
            } elseif ($key === 'capabilities' && is_array($value)) {
                $args['capabilities'] = $value;
            } elseif ($key === 'template') {
                if (is_string($value)) {
                    $decoded = json_decode(wp_unslash($value), true);
                    if (is_array($decoded)) {
                        $args['template'] = $decoded;
                    }
                } elseif (is_array($value)) {
                    $args['template'] = $value;
                }
            } elseif ($key === 'template_lock') {
                if (in_array($value, [ 'all', 'insert' ], true)) {
                    $args['template_lock'] = $value;
                } elseif ($value === true || $value === '1' || $value === 1) {
                    $args['template_lock'] = 'all';
                } elseif ($value === false || $value === '0' || $value === 0 || $value === '') {
                    // Explicitly no lock; omit to fall back to default behaviour.
                }
            } else {
                $args[$key] = $value;
            }
        }
        $args = apply_filters('gm2_register_post_type_args', $args, $slug, $pt);
        register_post_type($slug, $args);
    }

    foreach ($config['taxonomies'] ?? [] as $slug => $tax) {
        $args = [];
        foreach ($tax['args'] ?? [] as $key => $val) {
            $args[$key] = is_array($val) && array_key_exists('value', $val) ? $val['value'] : $val;
        }
        register_taxonomy($slug, $tax['post_types'] ?? [], $args);

        // Output and save basic term fields.
        add_action("{$slug}_add_form_fields", function () {
            ?>
            <div class="form-field term-color-wrap">
                <label for="color"><?php echo esc_html__('Color', 'gm2-wordpress-suite'); ?></label>
                <input type="text" name="color" id="color" value="" />
            </div>
            <div class="form-field term-icon-wrap">
                <label for="icon"><?php echo esc_html__('Icon', 'gm2-wordpress-suite'); ?></label>
                <input type="text" name="icon" id="icon" value="" />
            </div>
            <div class="form-field term-order-wrap">
                <label for="_gm2_order"><?php echo esc_html__('Order', 'gm2-wordpress-suite'); ?></label>
                <input type="number" name="_gm2_order" id="_gm2_order" value="" />
            </div>
            <?php
        });

        add_action("{$slug}_edit_form_fields", function ($term) {
            $color = get_term_meta($term->term_id, 'color', true);
            $icon  = get_term_meta($term->term_id, 'icon', true);
            $order = get_term_meta($term->term_id, '_gm2_order', true);
            ?>
            <tr class="form-field term-color-wrap">
                <th scope="row"><label for="color"><?php echo esc_html__('Color', 'gm2-wordpress-suite'); ?></label></th>
                <td><input type="text" name="color" id="color" value="<?php echo esc_attr($color); ?>" /></td>
            </tr>
            <tr class="form-field term-icon-wrap">
                <th scope="row"><label for="icon"><?php echo esc_html__('Icon', 'gm2-wordpress-suite'); ?></label></th>
                <td><input type="text" name="icon" id="icon" value="<?php echo esc_attr($icon); ?>" /></td>
            </tr>
            <tr class="form-field term-order-wrap">
                <th scope="row"><label for="_gm2_order"><?php echo esc_html__('Order', 'gm2-wordpress-suite'); ?></label></th>
                <td><input type="number" name="_gm2_order" id="_gm2_order" value="<?php echo esc_attr($order); ?>" /></td>
            </tr>
            <?php
        });

        $save_term_meta = function ($term_id) {
            if (isset($_POST['color'])) {
                $color = sanitize_hex_color($_POST['color']);
                if ($color) {
                    update_term_meta($term_id, 'color', $color);
                } else {
                    delete_term_meta($term_id, 'color');
                }
            }
            if (isset($_POST['icon'])) {
                update_term_meta($term_id, 'icon', sanitize_text_field($_POST['icon']));
            }
            if (isset($_POST['_gm2_order'])) {
                update_term_meta($term_id, '_gm2_order', (int) $_POST['_gm2_order']);
            }
        };
        add_action("created_{$slug}", $save_term_meta);
        add_action("edited_{$slug}", $save_term_meta);
        // Register default term meta keys.
        register_term_meta($slug, 'color', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_hex_color',
            'description'       => 'Term color',
        ]);
        register_term_meta($slug, 'icon', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'Term icon',
        ]);
        register_term_meta($slug, '_gm2_order', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'intval',
            'description'       => 'Term order',
        ]);

        // Register term meta fields.
        foreach ($tax['term_fields'] ?? [] as $meta_key => $field) {
            $type = ($field['type'] ?? '') === 'number' ? 'number' : 'string';
            register_term_meta($slug, $meta_key, [
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => ($type === 'number') ? 'intval' : 'sanitize_text_field',
                'description'       => $field['description'] ?? '',
            ]);
        }

        // Ensure default terms exist with meta.
        if (!empty($tax['default_terms']) && taxonomy_exists($slug)) {
            foreach ($tax['default_terms'] as $term) {
                if (!is_array($term)) {
                    continue;
                }
                $existing = term_exists($term['slug'], $slug);
                if ($existing && is_array($existing)) {
                    $term_id = $existing['term_id'];
                    if (!empty($term['description'])) {
                        wp_update_term($term_id, $slug, [ 'description' => $term['description'] ]);
                    }
                } elseif ($existing) {
                    $term_id = $existing;
                    if (!empty($term['description'])) {
                        wp_update_term($term_id, $slug, [ 'description' => $term['description'] ]);
                    }
                } else {
                    $insert_args = [ 'slug' => $term['slug'] ];
                    if (!empty($term['description'])) {
                        $insert_args['description'] = $term['description'];
                    }
                    $inserted = wp_insert_term($term['name'], $slug, $insert_args);
                    if (is_wp_error($inserted)) {
                        continue;
                    }
                    $term_id = $inserted['term_id'];
                }
                if (!empty($term['color'])) {
                    update_term_meta($term_id, 'color', $term['color']);
                }
                if (!empty($term['icon'])) {
                    update_term_meta($term_id, 'icon', $term['icon']);
                }
                if (isset($term['order'])) {
                    update_term_meta($term_id, '_gm2_order', (int) $term['order']);
                }
                if (!empty($term['meta']) && is_array($term['meta'])) {
                    foreach ($term['meta'] as $mk => $mv) {
                        update_term_meta($term_id, $mk, $mv);
                    }
                }
            }
        }
    }
}
add_action('init', 'gm2_register_custom_posts');

/**
 * Evaluate field group location rules against a context array.
 *
 * @param array $groups  Array of location groups.
 * @param array $context Context values such as post_type, template, taxonomy.
 * @return bool True when any group matches.
 */
function gm2_match_location($groups, $context = []) {
    if (empty($groups) || !is_array($groups)) {
        return true;
    }
    foreach ($groups as $group) {
        $relation = strtoupper($group['relation'] ?? 'AND');
        $rules    = $group['rules'] ?? [];
        if (!is_array($rules)) {
            continue;
        }
        $group_pass = ($relation === 'AND');
        foreach ($rules as $rule) {
            $param    = $rule['param'] ?? '';
            $operator = $rule['operator'] ?? '==';
            $value    = $rule['value'] ?? '';
            $current  = $context[$param] ?? '';
            $ok       = ($operator === '!=') ? ($current !== $value) : ($current === $value);
            if ($relation === 'AND' && !$ok) {
                $group_pass = false;
                break;
            }
            if ($relation === 'OR' && $ok) {
                $group_pass = true;
                break;
            }
        }
        if ($group_pass) {
            return true;
        }
    }
    return false;
}

/**
 * Render a generic set of fields for any object context.
 *
 * Supports lazy-loading of tab or accordion containers. Developers can
 * filter `gm2_should_lazy_load_group` to control whether a group should be
 * rendered immediately. When a group is deferred, the action
 * `gm2_lazy_load_group_placeholder` fires and a placeholder element is output
 * for asynchronous loading.
 *
 * @param array  $fields       Field definitions.
 * @param int    $object_id    Object identifier.
 * @param string $context_type Context type: post, term, user, etc.
 */
function gm2_render_field_group($fields, $object_id, $context_type = 'post') {
    if (empty($fields) || !is_array($fields)) {
        return;
    }
    uasort($fields, function ($a, $b) {
        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });
    foreach ($fields as $key => $field) {
        if (!\Gm2\Gm2_Capability_Manager::can_read_field($key, $object_id)) {
            continue;
        }
        $container = $field['container'] ?? '';
        if (in_array($container, [ 'tab', 'accordion' ], true)) {
            $defer = apply_filters('gm2_should_lazy_load_group', true, $key, $field, $object_id, $context_type);
            if ($defer) {
                do_action('gm2_lazy_load_group_placeholder', $key, $field, $object_id, $context_type);
                echo '<div class="gm2-lazy-group-placeholder" data-group="' . esc_attr($key) . '" data-context="' . esc_attr($context_type) . '" data-object="' . esc_attr($object_id) . '"></div>';
                continue;
            }
        }

        $state   = gm2_evaluate_conditions($field, $object_id);
        $visible = $state['show'];
        $field['disabled'] = $state['disabled'];
        $wrapper = 'gm2-field';
        if (!empty($field['class'])) {
            $wrapper .= ' ' . sanitize_html_class($field['class']);
        }
        if (!empty($field['admin_class'])) {
            $wrapper .= ' ' . sanitize_html_class($field['admin_class']);
        }
        if (!empty($field['container'])) {
            $wrapper .= ' gm2-container-' . sanitize_html_class($field['container']);
        }
        $type  = $field['type'] ?? 'text';
        $class = gm2_get_field_type_class($type);
        echo '<div class="' . esc_attr($wrapper) . '" data-type="' . esc_attr($type) . '"';
        if ($field['disabled']) {
            echo ' data-disabled="1"';
        }
        if (!$visible) {
            echo ' style="display:none;"';
        }
        if (!empty($field['tab'])) {
            echo ' data-tab="' . esc_attr($field['tab']) . '"';
        }
        if (!empty($field['accordion'])) {
            echo ' data-accordion="' . esc_attr($field['accordion']) . '"';
        }
        if (!empty($field['placeholder'])) {
            echo ' data-placeholder="' . esc_attr($field['placeholder']) . '"';
        }
        if (!empty($field['admin_class'])) {
            echo ' data-admin-class="' . esc_attr($field['admin_class']) . '"';
        }
        echo '>';
        if ($class && class_exists($class)) {
            $obj   = new $class($key, $field);
            $value = gm2_get_meta_value($object_id, $key, $context_type, $field);
            $obj->render_admin($value, $object_id, $context_type);
        }
        if (!empty($field['instructions'])) {
            echo '<p class="description">' . esc_html($field['instructions']) . '</p>';
        }
        echo '</div>';
    }
}

/**
 * Simple wrapper used for deferred metadata lookups. The callback is executed
 * only when the value is accessed via {@see get()} or string casting.
 */
class GM2_Lazy_Meta_Value {
    /** @var callable */
    private $resolver;

    /** @var bool */
    private $loaded = false;

    /** @var mixed */
    private $value;

    /**
     * @param callable $resolver Callback that resolves the actual value.
     */
    public function __construct(callable $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * Retrieve the resolved value, loading it on first access.
     *
     * @return mixed
     */
    public function get() {
        if (!$this->loaded) {
            $this->value  = call_user_func($this->resolver);
            $this->loaded = true;
        }
        return $this->value;
    }

    public function __toString() {
        $value = $this->get();
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }
        return (string) $value;
    }
}

/**
 * Low level metadata fetcher used by {@see gm2_get_meta_value()} and the lazy
 * loader. This function contains the original switch statement and default
 * resolution logic.
 */
function gm2_fetch_meta_value($object_id, $key, $context_type, $field, $type = 'post') {
    switch ($context_type) {
        case 'user':
            $value = get_user_meta($object_id, $key, true);
            break;
        case 'term':
            $value = get_term_meta($object_id, $key, true);
            break;
        case 'comment':
            $value = get_comment_meta($object_id, $key, true);
            break;
        case 'option':
            $value = get_option($key);
            break;
        case 'site':
            $value = get_site_option($key);
            break;
        default:
            $value = get_post_meta($object_id, $key, true);
    }
    if ($value === '' || $value === null) {
        $value = gm2_resolve_default($field, $object_id, $context_type);
    }
    return $value;
}

/**
 * Retrieve a stored meta value for any object type.
 *
 * The `gm2_lazy_load_meta_value` filter allows heavy fields to be loaded
 * lazily. When the filter returns true a {@see GM2_Lazy_Meta_Value} instance is
 * returned instead of the raw value. The actual lookup is deferred until the
 * object is accessed. Developers may wrap the resolver callback via the
 * `gm2_lazy_meta_loader` filter for custom caching or instrumentation.
 */
function gm2_get_meta_value($object_id, $key, $context_type, $field, $type = 'post') {
    $defer = apply_filters(
        'gm2_lazy_load_meta_value',
        false,
        $object_id,
        $key,
        $context_type,
        $field,
        $type
    );

    if ($defer) {
        $resolver = function () use ($object_id, $key, $context_type, $field, $type) {
            return gm2_fetch_meta_value($object_id, $key, $context_type, $field, $type);
        };

        $resolver = apply_filters(
            'gm2_lazy_meta_loader',
            $resolver,
            $object_id,
            $key,
            $context_type,
            $field,
            $type
        );

        return new GM2_Lazy_Meta_Value($resolver);
    }

    return gm2_fetch_meta_value($object_id, $key, $context_type, $field, $type);
}

/**
 * Enqueue an asynchronous action using Action Scheduler or WP-Cron.
 *
 * @param string $hook  Hook name to trigger.
 * @param array  $args  Optional arguments for the hook.
 * @param string $group Optional group for Action Scheduler.
 */
function gm2_enqueue_async_action($hook, $args = [], $group = 'gm2') {
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action($hook, $args, $group);
    } else {
        wp_schedule_single_event(time(), $hook, $args);
    }
}

/**
 * Queue a background job to regenerate attachment metadata.
 *
 * @param int $attachment_id Attachment ID.
 */
function gm2_queue_thumbnail_regeneration($attachment_id) {
    if (!wp_attachment_is_image($attachment_id)) {
        return;
    }

    gm2_enqueue_async_action('gm2_generate_thumbnails', [ $attachment_id ]);
}

/**
 * Queue a background job to optimize an image attachment.
 *
 * @param int $attachment_id Attachment ID.
 */
function gm2_queue_image_optimization($attachment_id) {
    if (!wp_attachment_is_image($attachment_id)) {
        return;
    }

    gm2_enqueue_async_action('gm2_optimize_image', [ $attachment_id ]);
}

/**
 * Save field group data for any object type.
 */
function gm2_save_field_group($fields, $object_id, $context_type = 'post') {
    if (empty($fields) || !is_array($fields)) {
        return;
    }
    foreach ($fields as $key => $field) {
        if (!\Gm2\Gm2_Capability_Manager::can_edit_field($key, $object_id)) {
            continue;
        }
        $state = gm2_evaluate_conditions($field, $object_id);
        if (!$state['show']) {
            continue;
        }
        $type  = $field['type'] ?? 'text';
        $class = gm2_get_field_type_class($type);
        $val   = $_POST[$key] ?? null;
        $valid = gm2_validate_field($key, $field, $val, $object_id, $context_type);
        if (is_wp_error($valid)) {
            wp_die($valid->get_error_message());
        }
        if ($class && class_exists($class)) {
            $obj = new $class($key, $field);
            $obj->save($object_id, $val, $context_type);
        }
    }
}

/**
 * Register field groups and attach them to various WordPress objects.
 */
function gm2_register_field_groups() {
    $groups = get_option('gm2_field_groups', []);
    if (!is_array($groups)) {
        return;
    }

    foreach ($groups as $group_key => $group) {
        $fields    = $group['fields'] ?? [];
        $locations = $group['location'] ?? [];
        $scope     = $group['scope'] ?? 'post_type';

        foreach ($fields as $key => $field) {
            if (!empty($field['pii'])) {
                \Gm2\Gm2_Audit_Log::tag_field_as_pii($key, $field['retention'] ?? null);
            }
        }

        switch ($scope) {
            case 'taxonomy':
                foreach (($group['objects'] ?? []) as $tax) {
                    add_action($tax . '_add_form_fields', function () use ($fields) {
                        gm2_render_field_group($fields, 0, 'term');
                    });
                    add_action($tax . '_edit_form_fields', function ($term) use ($fields) {
                        gm2_render_field_group($fields, $term->term_id, 'term');
                    });
                    add_action('created_' . $tax, function ($term_id) use ($fields) {
                        gm2_save_field_group($fields, $term_id, 'term');
                    });
                    add_action('edited_' . $tax, function ($term_id) use ($fields) {
                        gm2_save_field_group($fields, $term_id, 'term');
                    });
                }
                break;
            case 'user':
                add_action('show_user_profile', function ($user) use ($fields) {
                    gm2_render_field_group($fields, $user->ID, 'user');
                });
                add_action('edit_user_profile', function ($user) use ($fields) {
                    gm2_render_field_group($fields, $user->ID, 'user');
                });
                add_action('personal_options_update', function ($user_id) use ($fields) {
                    gm2_save_field_group($fields, $user_id, 'user');
                });
                add_action('edit_user_profile_update', function ($user_id) use ($fields) {
                    gm2_save_field_group($fields, $user_id, 'user');
                });
                break;
            case 'comment':
                add_action('add_meta_boxes_comment', function () use ($fields, $group_key) {
                    add_meta_box('gm2_fg_' . $group_key, esc_html__('Fields', 'gm2-wordpress-suite'), function ($comment) use ($fields) {
                        gm2_render_field_group($fields, $comment->comment_ID, 'comment');
                    }, 'comment', 'normal', 'default');
                });
                add_action('edit_comment', function ($comment_id) use ($fields) {
                    gm2_save_field_group($fields, $comment_id, 'comment');
                });
                break;
            case 'media':
                add_filter('attachment_fields_to_edit', function ($form_fields, $post) use ($fields, $group_key) {
                    ob_start();
                    gm2_render_field_group($fields, $post->ID, 'post');
                    $html = ob_get_clean();
                    $form_fields['gm2_fg_' . $group_key] = [
                        'label' => __('Fields', 'gm2-wordpress-suite'),
                        'input' => 'html',
                        'html'  => $html,
                    ];
                    return $form_fields;
                }, 10, 2);
                add_filter('attachment_fields_to_save', function ($post, $attachment) use ($fields) {
                    gm2_save_field_group($fields, $post['ID'], 'post');
                    return $post;
                }, 10, 2);
                break;
            case 'options_page':
                add_action('admin_menu', function () use ($group, $fields, $group_key) {
                    $slug = 'gm2_fg_' . sanitize_key($group_key);
                    add_options_page($group['title'] ?? $slug, $group['title'] ?? $slug, 'manage_options', $slug, function () use ($group, $fields, $slug) {
                        echo '<div class="wrap"><h1>' . esc_html($group['title'] ?? '') . '</h1><form method="post" action="options.php">';
                        settings_fields($slug);
                        gm2_render_field_group($fields, $slug, 'option');
                        submit_button();
                        echo '</form></div>';
                    });
                    register_setting($slug, $slug);
                });
                add_action('admin_post_update_' . 'gm2_fg_' . sanitize_key($group_key), function () use ($fields, $group_key) {
                    gm2_save_field_group($fields, 'gm2_fg_' . sanitize_key($group_key), 'option');
                });
                break;
            case 'term':
                foreach (($group['objects'] ?? []) as $tax) {
                    add_action($tax . '_edit_form_fields', function ($term) use ($fields) {
                        gm2_render_field_group($fields, $term->term_id, 'term');
                    });
                    add_action('edited_' . $tax, function ($term_id) use ($fields) {
                        gm2_save_field_group($fields, $term_id, 'term');
                    });
                }
                break;
            case 'site':
                add_action('network_admin_menu', function () use ($group, $fields, $group_key) {
                    $slug = 'gm2_fg_network_' . sanitize_key($group_key);
                    add_menu_page($group['title'] ?? $slug, $group['title'] ?? $slug, 'manage_network_options', $slug, function () use ($group, $fields, $slug) {
                        echo '<div class="wrap"><h1>' . esc_html($group['title'] ?? '') . '</h1><form method="post" action="edit.php?action=' . esc_attr($slug) . '">';
                        wp_nonce_field($slug);
                        gm2_render_field_group($fields, $slug, 'site');
                        submit_button();
                        echo '</form></div>';
                    });
                });
                add_action('network_admin_edit_' . 'gm2_fg_network_' . sanitize_key($group_key), function () use ($fields, $group_key) {
                    check_admin_referer('gm2_fg_network_' . sanitize_key($group_key));
                    gm2_save_field_group($fields, 'gm2_fg_network_' . sanitize_key($group_key), 'site');
                    wp_safe_redirect(network_admin_url('admin.php?page=gm2_fg_network_' . sanitize_key($group_key) . '&updated=true'));
                    exit;
                });
                break;
            case 'post_type':
            default:
                foreach (($group['objects'] ?? []) as $pt) {
                    $use_block = function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type($pt);
                    if (!$use_block) {
                        add_action('add_meta_boxes_' . $pt, function () use ($fields, $group, $group_key, $pt, $locations) {
                            add_meta_box('gm2_fg_' . $group_key, $group['title'] ?? $group_key, function ($post) use ($fields, $locations) {
                                $context = [
                                    'post_type' => $post->post_type,
                                    'template'  => get_page_template_slug($post->ID),
                                ];
                                if (gm2_match_location($locations, $context)) {
                                    gm2_render_field_group($fields, $post->ID, 'post');
                                }
                            }, $pt, 'normal', 'default');
                        });
                        add_action('save_post_' . $pt, function ($post_id) use ($fields, $locations) {
                            $context = [
                                'post_type' => get_post_type($post_id),
                                'template'  => get_page_template_slug($post_id),
                            ];
                            if (gm2_match_location($locations, $context)) {
                                gm2_save_field_group($fields, $post_id, 'post');
                            }
                        });
                    }
                }
                break;
        }
    }
}
add_action('init', 'gm2_register_field_groups');

