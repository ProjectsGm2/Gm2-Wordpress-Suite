<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control for selecting one or more post types.
 */
class PostTypeSelect extends AbstractAjaxSelectControl
{
    public const TYPE = 'gm2-post-type-select';

    private const AJAX_ACTION = 'gm2_elementor_post_types';

    /**
     * Register Elementor hooks for the control.
     */
    public static function register(): void
    {
        add_action('elementor/controls/register', [static::class, 'register_control']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [static::class, 'ajax_post_types']);
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
    public function content_template(): void
    {
        ?>
        <div class="gm2-control gm2-control--post-type">
            <select class="gm2-ajax-select" data-action="<?php echo esc_attr(self::AJAX_ACTION); ?>" data-setting="{{ data.name }}" data-selected='{{{ JSON.stringify( data.controlValue || ( data.multiple ? [] : "" ) ) }}}' <# if ( data.multiple ) { #>multiple="multiple"<# } #>>
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
     * Handle AJAX request to fetch available post types.
     */
    public static function ajax_post_types(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Access denied', 'gm2-wordpress-suite'), 403);
        }

        $search = sanitize_text_field($_POST['search'] ?? '');

        $options = [];
        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $slug => $object) {
            $label = $object->labels->singular_name ?? $object->labels->name ?? $slug;
            if ($search !== '' && stripos($label, $search) === false && stripos($slug, $search) === false) {
                continue;
            }
            $options[] = [
                'value' => $slug,
                'label' => $label,
            ];
        }

        wp_send_json_success($options);
    }
}
