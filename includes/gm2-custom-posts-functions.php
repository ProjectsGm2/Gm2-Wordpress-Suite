<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Evaluate condition groups for a field or argument.
 *
 * @param array $item    Field or arg array possibly containing `conditions` or `conditional` keys.
 * @param int   $post_id Post ID context for meta lookup.
 * @return bool True if conditions pass or none are defined.
 */
function gm2_evaluate_conditions($item, $post_id = 0) {
    if (!is_array($item)) {
        return true;
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
        return true;
    }

    $result = null;
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
            $current = (string) $current;
            $expected = (string) ($cond['value'] ?? '');
            $ok = false;
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
                $rel = strtoupper($cond['relation'] ?? 'AND');
                $group_res = ($rel === 'OR') ? ($group_res || $ok) : ($group_res && $ok);
            }
        }
        if ($group_res === null) {
            $group_res = false;
        }
        if ($result === null) {
            $result = $group_res;
        } else {
            $rel = strtoupper($group['relation'] ?? 'AND');
            $result = ($rel === 'OR') ? ($result || $group_res) : ($result && $group_res);
        }
    }

    return ($result === null) ? true : $result;
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
 * @param array  $fields    Field definitions.
 * @param int    $object_id Object identifier.
 * @param string $type      Context type: post, term, user, etc.
 */
function gm2_render_field_group($fields, $object_id, $type = 'post') {
    if (empty($fields) || !is_array($fields)) {
        return;
    }
    uasort($fields, function ($a, $b) {
        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });
    foreach ($fields as $key => $field) {
        $visible = gm2_evaluate_conditions($field, $object_id);
        $wrapper = 'gm2-field';
        if (!empty($field['class'])) {
            $wrapper .= ' ' . sanitize_html_class($field['class']);
        }
        if (!empty($field['container'])) {
            $wrapper .= ' gm2-container-' . sanitize_html_class($field['container']);
        }
        echo '<div class="' . esc_attr($wrapper) . '"';
        if (!$visible) {
            echo ' style="display:none;"';
        }
        echo '>';
        $type   = $field['type'] ?? 'text';
        $label  = $field['label'] ?? $key;
        $value  = '';
        switch ($type) {
            case 'number':
                $value = gm2_get_meta_value($object_id, $key, $type, $field, $type = $type);
                echo '<p><label>' . esc_html($label) . '<br /><input type="number" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '" /></label></p>';
                break;
            case 'checkbox':
                $value = gm2_get_meta_value($object_id, $key, $type, $field, $type = $type);
                echo '<p><label><input type="checkbox" name="' . esc_attr($key) . '" value="1"' . checked($value, '1', false) . ' /> ' . esc_html($label) . '</label></p>';
                break;
            case 'select':
            case 'radio':
                $value   = gm2_get_meta_value($object_id, $key, $type, $field, $type = $type);
                $options = $field['options'] ?? [];
                if ($type === 'select') {
                    echo '<p><label>' . esc_html($label) . '<br /><select name="' . esc_attr($key) . '">';
                    foreach ($options as $ov => $ol) {
                        echo '<option value="' . esc_attr($ov) . '"' . selected($value, $ov, false) . '>' . esc_html($ol) . '</option>';
                    }
                    echo '</select></label></p>';
                } else {
                    echo '<p>' . esc_html($label) . '<br />';
                    foreach ($options as $ov => $ol) {
                        echo '<label><input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($ov) . '"' . checked($value, $ov, false) . ' /> ' . esc_html($ol) . '</label><br />';
                    }
                    echo '</p>';
                }
                break;
            default:
                $value = gm2_get_meta_value($object_id, $key, $type, $field, $type = $type);
                echo '<p><label>' . esc_html($label) . '<br /><input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '" /></label></p>';
        }
        if (!empty($field['instructions'])) {
            echo '<p class="description">' . esc_html($field['instructions']) . '</p>';
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
    if ($value === '' && isset($field['default'])) {
        $value = $field['default'];
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
        if (!gm2_evaluate_conditions($field, $object_id)) {
            continue;
        }
        $type = $field['type'] ?? 'text';
        $val  = $_POST[$key] ?? null;
        if ($type === 'checkbox') {
            $val = $val ? '1' : '0';
        } elseif ($val !== null) {
            $val = sanitize_text_field($val);
        }
        switch ($context_type) {
            case 'user':
                if ($val === null) {
                    delete_user_meta($object_id, $key);
                } else {
                    update_user_meta($object_id, $key, $val);
                }
                break;
            case 'term':
                if ($val === null) {
                    delete_term_meta($object_id, $key);
                } else {
                    update_term_meta($object_id, $key, $val);
                }
                break;
            case 'comment':
                if ($val === null) {
                    delete_comment_meta($object_id, $key);
                } else {
                    update_comment_meta($object_id, $key, $val);
                }
                break;
            case 'option':
                if ($val === null) {
                    delete_option($key);
                } else {
                    update_option($key, $val);
                }
                break;
            case 'site':
                if ($val === null) {
                    delete_site_option($key);
                } else {
                    update_site_option($key, $val);
                }
                break;
            default:
                if ($val === null) {
                    delete_post_meta($object_id, $key);
                } else {
                    update_post_meta($object_id, $key, $val);
                }
                break;
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

