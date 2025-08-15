<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to fetch a field value with optional default.
 *
 * @param string      $key          Meta field key.
 * @param mixed       $default      Default value if meta is empty.
 * @param int|null    $object_id    Object ID. Defaults to current post.
 * @param string      $context_type Context type: post, term, user, option, site.
 *
 * @return mixed
 */
function gm2_field($key, $default = '', $object_id = null, $context_type = 'post') {
    if (!$key) {
        return $default;
    }

    if ($object_id === null) {
        switch ($context_type) {
            case 'post':
                $object_id = get_the_ID();
                break;
            case 'term':
                $object_id = get_queried_object_id();
                break;
            case 'user':
                $user = wp_get_current_user();
                $object_id = $user ? $user->ID : 0;
                break;
            default:
                $object_id = 0;
        }
    }

    $value = '';
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
            $value = get_option($key, $default);
            break;
        case 'site':
            $value = get_site_option($key, $default);
            break;
        default:
            $value = get_post_meta($object_id, $key, true);
    }

    if ($value !== '' && $value !== null) {
        return $value;
    }

    $field = gm2_find_field_definition($key);
    if ($field) {
        return gm2_resolve_default($field, $object_id, $context_type);
    }

    return $default;
}

/**
 * Locate a field definition from stored field groups.
 *
 * @param string $key Field key.
 * @return array|null
 */
function gm2_find_field_definition($key) {
    $groups = get_option('gm2_field_groups', []);
    foreach ($groups as $group) {
        if (!empty($group['fields'][$key])) {
            return $group['fields'][$key];
        }
    }
    return null;
}

/**
 * Register block templates for custom post types and archives.
 * Runs only when the feature is enabled via option.
 */
function gm2_maybe_generate_block_templates() {
    if (get_option('gm2_enable_block_templates', '0') !== '1') {
        return;
    }

    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $type) {
        if (in_array($type, ['post', 'page', 'attachment'], true)) {
            continue;
        }
        gm2_ensure_block_template('single-' . $type, "<!-- wp:post-title /-->\n<!-- wp:post-content /-->\n");
        gm2_ensure_block_template('archive-' . $type, "<!-- wp:query {\"inherit\":true} --><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- /wp:query -->\n");
    }
}
add_action('init', 'gm2_maybe_generate_block_templates');

/**
 * Create a wp_template post if it does not already exist.
 *
 * @param string $slug    Template slug.
 * @param string $content Block template content.
 */
function gm2_ensure_block_template($slug, $content) {
    $exists = get_posts([
        'post_type'      => 'wp_template',
        'post_status'    => 'publish',
        'name'           => $slug,
        'posts_per_page' => 1,
    ]);
    if ($exists) {
        return;
    }
    wp_insert_post([
        'post_type'   => 'wp_template',
        'post_status' => 'publish',
        'post_name'   => $slug,
        'post_title'  => ucwords(str_replace('-', ' ', $slug)),
        'post_content'=> $content,
        'tax_input'   => [ 'wp_theme' => get_stylesheet() ],
    ]);
}

/**
 * Register simple block patterns based on configured field groups.
 */
function gm2_register_field_group_patterns() {
    $groups = get_option('gm2_field_groups', []);
    if (!is_array($groups)) {
        return;
    }
    foreach ($groups as $group) {
        if (empty($group['pattern'])) {
            continue;
        }
        $slug = 'gm2/' . sanitize_key($group['pattern']);
        $content = '';
        foreach (($group['fields'] ?? []) as $field) {
            $label = $field['label'] ?? ($field['name'] ?? '');
            $content .= sprintf("<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->\n", esc_html($label));
        }
        register_block_pattern($slug, [
            'title'       => $group['pattern'],
            'description' => $group['description'] ?? '',
            'content'     => $content,
        ]);
    }
}
add_action('init', 'gm2_register_field_group_patterns');
