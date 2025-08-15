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
        return strtr($template, $replacements);
    }

    return $default;
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

    if (!empty($field['unique']) && $context_type === 'post') {
        $scope = $field['unique_scope'] ?? 'post_type';
        $args  = [
            'post_type'      => ($scope === 'post_type') ? get_post_type($object_id) : 'any',
            'post__not_in'   => [ $object_id ],
            'meta_query'     => [ [ 'key' => $key, 'value' => $value ] ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];
        $existing = get_posts($args);
        if ($existing) {
            $msg = $messages['unique'] ?? __('Value must be unique.', 'gm2-wordpress-suite');
            return new WP_Error('gm2_unique', $msg);
        }
    }

    if (($field['type'] ?? '') === 'media' && $value) {
        if (!empty($field['allowed_types'])) {
            $mime    = get_post_mime_type($value);
            $allowed = array_map('strtolower', (array) $field['allowed_types']);
            if ($mime && !in_array(strtolower($mime), $allowed, true)) {
                $msg = $messages['file_type'] ?? __('Invalid file type.', 'gm2-wordpress-suite');
                return new WP_Error('gm2_file_type', $msg);
            }
        }
        if (!empty($field['max_size'])) {
            $file = get_attached_file($value);
            if ($file && filesize($file) > $field['max_size']) {
                $msg = $messages['file_size'] ?? __('File is too large.', 'gm2-wordpress-suite');
                return new WP_Error('gm2_file_size', $msg);
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
            } elseif ($key === 'template' && is_array($value)) {
                $args['template'] = $value;
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
                } elseif ($existing) {
                    $term_id = $existing;
                } else {
                    $inserted = wp_insert_term($term['name'], $slug, [ 'slug' => $term['slug'] ]);
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
        $state   = gm2_evaluate_conditions($field, $object_id);
        $visible = $state['show'];
        $field['disabled'] = $state['disabled'];
        $wrapper = 'gm2-field';
        if (!empty($field['class'])) {
            $wrapper .= ' ' . sanitize_html_class($field['class']);
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
        echo '>';
        if ($class && class_exists($class)) {
            $obj   = new $class($key, $field);
            $value = gm2_get_meta_value($object_id, $key, $context_type, $field);
            $obj->render_admin($value, $object_id, $context_type);
        }
        echo '</div>';
    }
}

/**
 * Retrieve a stored meta value for any object type.
 */
function gm2_get_meta_value($object_id, $key, $context_type, $field, $type = 'post') {
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
 * Save field group data for any object type.
 */
function gm2_save_field_group($fields, $object_id, $context_type = 'post') {
    if (empty($fields) || !is_array($fields)) {
        return;
    }
    foreach ($fields as $key => $field) {
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
                break;
        }
    }
}
add_action('init', 'gm2_register_field_groups');

