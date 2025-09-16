<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control for selecting taxonomies or their terms via AJAX.
 */
class TaxonomyTermMulti extends AbstractAjaxSelectControl
{
    public const TYPE = 'gm2-taxonomy-term-multi';

    private const AJAX_ACTION = 'gm2_elementor_taxonomy_terms';

    /**
     * Register Elementor hooks for the control.
     */
    public static function register(): void
    {
        add_action('elementor/controls/register', [static::class, 'register_control']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [static::class, 'ajax_taxonomy_terms']);
    }

    /**
     * Register the control type with Elementor.
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
        $defaults['mode'] = 'terms';
        $defaults['taxonomy_control'] = '';
        return $defaults;
    }

    /**
     * {@inheritDoc}
     */
    public function content_template(): void
    {
        ?>
        <div class="gm2-control gm2-control--taxonomy">
            <select class="gm2-ajax-select" data-action="<?php echo esc_attr(self::AJAX_ACTION); ?>" data-mode="{{ data.mode }}" data-taxonomy-control="{{ data.taxonomy_control }}" data-setting="{{ data.name }}" data-selected='{{{ JSON.stringify( data.controlValue || ( data.multiple ? [] : "" ) ) }}}' <# if ( data.multiple ) { #>multiple="multiple"<# } #>>
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
     * Handle AJAX request for taxonomies or terms depending on the provided mode.
     */
    public static function ajax_taxonomy_terms(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Access denied', 'gm2-wordpress-suite'), 403);
        }

        $mode   = sanitize_key($_POST['mode'] ?? 'terms');
        $search = sanitize_text_field($_POST['search'] ?? '');

        if ($mode === 'taxonomy') {
            $options = [];
            $taxonomies = get_taxonomies(['public' => true], 'objects');
            foreach ($taxonomies as $slug => $object) {
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

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        if ($taxonomy === '') {
            wp_send_json_success([]);
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'search'     => $search,
        ]);

        if (is_wp_error($terms)) {
            wp_send_json_success([]);
        }

        $options = [];
        foreach ($terms as $term) {
            $options[] = [
                'value' => (string) $term->term_id,
                'label' => $term->name,
            ];
        }

        wp_send_json_success($options);
    }
}
