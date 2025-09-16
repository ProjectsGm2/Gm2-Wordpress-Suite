<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control for selecting meta keys via AJAX.
 */
class MetaKeySelect extends AbstractAjaxSelectControl
{
    public const TYPE = 'gm2-meta-key-select';

    private const AJAX_ACTION = 'gm2_elementor_meta_keys';

    /**
     * Register Elementor hooks for the control.
     */
    public static function register(): void
    {
        add_action('elementor/controls/register', [static::class, 'register_control']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [static::class, 'ajax_meta_keys']);
    }

    /**
     * Register the control with Elementor.
     */
    public static function register_control(Controls_Manager $controls_manager): void
    {
        $controls_manager->register_control(self::TYPE, new self());
    }

    /**
     * {@inheritDoc}
     */
    public function get_type(): string
    {
        return self::TYPE;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_default_settings(): array
    {
        $defaults = parent::get_default_settings();
        $defaults['post_type_control'] = '';
        return $defaults;
    }

    /**
     * {@inheritDoc}
     */
    public function content_template(): void
    {
        ?>
        <div class="gm2-control gm2-control--meta-key">
            <select class="gm2-ajax-select" data-action="<?php echo esc_attr(self::AJAX_ACTION); ?>" data-post-type-control="{{ data.post_type_control }}" data-setting="{{ data.name }}" data-selected='{{{ JSON.stringify( data.controlValue || "" ) }}}'>
                <# if ( data.options ) { #>
                    <# _.each( data.options, function( label, value ) { #>
                        <option value="{{ value }}">{{ label }}</option>
                    <# } ); #>
                <# } #>
            </select>
        </div>
        <?php
    }

    /**
     * Handle AJAX request to retrieve distinct meta keys.
     */
    public static function ajax_meta_keys(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Access denied', 'gm2-wordpress-suite'), 403);
        }

        global $wpdb;

        $post_types = array_filter(array_map('sanitize_key', (array) ($_POST['post_types'] ?? [])));
        $search     = sanitize_text_field($_POST['search'] ?? '');

        $limit = 50;
        if ($post_types) {
            $placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
            $sql          = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key <> ''
                  AND p.post_type IN ($placeholders)
                ORDER BY pm.meta_key ASC
                LIMIT %d",
                array_merge($post_types, [$limit])
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT DISTINCT meta_key
                FROM {$wpdb->postmeta}
                WHERE meta_key <> ''
                ORDER BY meta_key ASC
                LIMIT %d",
                $limit
            );
        }

        $keys = (array) $wpdb->get_col($sql);

        $options = [];
        foreach ($keys as $key) {
            $label = sanitize_text_field($key);
            if ($search !== '' && stripos($label, $search) === false && stripos($key, $search) === false) {
                continue;
            }
            $options[] = [
                'value' => $key,
                'label' => $label,
            ];
        }

        wp_send_json_success($options);
    }
}
