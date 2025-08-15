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
