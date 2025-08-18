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
 * Retrieve an attachment object stored in a field.
 *
 * Attempts to resolve the stored value to a \WP_Post attachment object. The
 * value may be saved as an ID or an array containing an `ID` key. When the
 * field is empty the provided default is returned.
 *
 * @param string      $key          Meta field key.
 * @param mixed       $default      Default value when the field is empty.
 * @param int|null    $object_id    Context object ID.
 * @param string      $context_type Context type.
 * @return WP_Post|mixed
 */
function gm2_field_media_object($key, $default = null, $object_id = null, $context_type = 'post') {
    $value = gm2_field($key, '', $object_id, $context_type);
    if (is_array($value) && isset($value['ID'])) {
        $value = $value['ID'];
    }
    if (is_numeric($value)) {
        $attachment = get_post((int) $value);
        if ($attachment instanceof WP_Post) {
            return $attachment;
        }
    }
    return $default;
}

/**
 * Render an image element for a media field.
 *
 * @param string      $key          Meta field key.
 * @param string      $size         Image size to retrieve.
 * @param array       $attr         Optional attributes for the image tag.
 * @param int|null    $object_id    Context object ID.
 * @param string      $context_type Context type.
 * @return string HTML markup or empty string when no image is available.
 */
function gm2_field_image($key, $size = 'full', $attr = [], $object_id = null, $context_type = 'post') {
    $attachment = gm2_field_media_object($key, null, $object_id, $context_type);
    if ($attachment) {
        return wp_get_attachment_image($attachment->ID, $size, false, $attr);
    }

    $url = gm2_field($key, '', $object_id, $context_type);
    if (is_string($url) && $url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
        $attr_str = '';
        foreach ($attr as $k => $v) {
            $attr_str .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
        }
        return sprintf('<img src="%s"%s />', esc_url($url), $attr_str);
    }
    return '';
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
    $templates = [];

    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $type) {
        if (in_array($type, ['post', 'page', 'attachment'], true)) {
            continue;
        }
        $templates[] = 'single-' . $type;
        gm2_ensure_block_template('single-' . $type, "<!-- wp:post-title /-->\n<!-- wp:post-content /-->\n");
        $templates[] = 'archive-' . $type;
        gm2_ensure_block_template('archive-' . $type, "<!-- wp:query {\"inherit\":true} --><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- /wp:query -->\n");
    }

    $patterns = [];
    $groups = get_option('gm2_field_groups', []);
    if (is_array($groups)) {
        foreach ($groups as $group) {
            if (!empty($group['pattern'])) {
                $patterns[] = 'gm2/' . sanitize_key($group['pattern']);
            }
        }
    }

    gm2_update_theme_json_references($templates, $patterns);
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
 * Update or create theme.json entries for custom templates and patterns.
 *
 * @param array $templates Array of template slugs.
 * @param array $patterns  Array of pattern slugs.
 */
function gm2_update_theme_json_references($templates = [], $patterns = []) {
    $theme_dir = get_stylesheet_directory();
    if (!$theme_dir) {
        return;
    }

    $path = trailingslashit($theme_dir) . 'theme.json';
    $data = [];
    if (file_exists($path)) {
        $json = json_decode(file_get_contents($path), true);
        if (is_array($json)) {
            $data = $json;
        }
    } else {
        $data['$schema'] = 'https://schemas.wp.org/wp/6.4/theme.json';
    }

    if (!empty($templates)) {
        $existing = $data['customTemplates'] ?? [];
        foreach ($templates as $slug) {
            $found = false;
            foreach ($existing as $item) {
                if (($item['name'] ?? '') === $slug) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing[] = [
                    'name'  => $slug,
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                ];
            }
        }
        $data['customTemplates'] = $existing;
    }

    if (!empty($patterns)) {
        $existing = $data['patterns'] ?? [];
        foreach ($patterns as $slug) {
            if (!in_array($slug, $existing, true)) {
                $existing[] = $slug;
            }
        }
        $data['patterns'] = $existing;
    }

    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

/**
 * Generate basic Twig and Blade templates listing registered field names.
 * Writes files to the plugin's theme-integration/ directory when enabled.
 */
function gm2_maybe_write_theme_integration_templates() {
    if (get_option('gm2_enable_theme_integration', '0') !== '1') {
        return;
    }

    $groups = get_option('gm2_field_groups', []);
    if (!is_array($groups) || empty($groups)) {
        return;
    }

    $dir = GM2_PLUGIN_DIR . 'theme-integration/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    foreach ($groups as $group_key => $group) {
        $fields = $group['fields'] ?? [];
        if (empty($fields)) {
            continue;
        }
        $slug = sanitize_key($group_key);
        $twig = $dir . $slug . '.twig';
        $blade = $dir . $slug . '.blade.php';
        // Build template content from field names.
        $lines = [];
        foreach ($fields as $field_key => $field) {
            $type = $field['type'] ?? '';
            if ($type === 'media') {
                $lines[] = "{{ gm2_field_image('" . $field_key . "') }}";
                $lines[] = "{{ gm2_field_media_object('" . $field_key . "')|json_encode }}";
            } else {
                $lines[] = "{{ gm2_field('" . $field_key . "') }}";
            }
        }
        $content = implode("\n", $lines) . "\n";
        if (!file_exists($twig)) {
            file_put_contents($twig, $content);
        }
        if (!file_exists($blade)) {
            file_put_contents($blade, $content);
        }
    }
}
add_action('init', 'gm2_maybe_write_theme_integration_templates', 20);

/**
 * Write theme.json snippets derived from registered fields.
 *
 * When enabled via the `gm2_enable_theme_json` option the plugin inspects
 * registered field groups for color and typography fields and generates a
 * `theme.json` file under `theme-integration/` containing the appropriate
 * `settings.color.palette` and `settings.typography.fontFamilies` entries. The
 * file is intended to be merged into a theme's existing configuration.
 */
function gm2_maybe_write_theme_json_snippets() {
    if (get_option('gm2_enable_theme_json', '0') !== '1') {
        return;
    }

    $groups = get_option('gm2_field_groups', []);
    if (!is_array($groups) || empty($groups)) {
        return;
    }

    $palette     = [];
    $typography  = [];

    foreach ($groups as $group) {
        foreach (($group['fields'] ?? []) as $key => $field) {
            $type  = $field['theme'] ?? ($field['type'] ?? '');
            $label = $field['label'] ?? $key;
            $value = gm2_resolve_default($field);

            if (in_array($type, ['color', 'design'], true)) {
                $color = sanitize_hex_color($value);
                if ($color) {
                    $palette[] = [
                        'slug'  => sanitize_key($key),
                        'color' => $color,
                        'name'  => $label,
                    ];
                }
            } elseif ($type === 'typography') {
                if (is_string($value) && $value !== '') {
                    $typography['fontFamilies'][] = [
                        'slug'       => sanitize_key($key),
                        'fontFamily' => $value,
                        'name'       => $label,
                    ];
                }
            }
        }
    }

    $settings = [];
    if (!empty($palette)) {
        $settings['color']['palette'] = $palette;
    }
    if (!empty($typography)) {
        $settings['typography'] = $typography;
    }

    if (empty($settings)) {
        return;
    }

    $dir = GM2_PLUGIN_DIR . 'theme-integration/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    $path = $dir . 'theme.json';
    $json = json_encode(['settings' => $settings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path, $json);
}
add_action('init', 'gm2_maybe_write_theme_json_snippets', 30);
