<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Base_Data_Control;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for Elementor select controls that fetch options via AJAX.
 */
abstract class AbstractAjaxSelectControl extends Base_Data_Control
{
    /** Script handle shared by all GM2 Elementor controls. */
    protected const SCRIPT_HANDLE = 'gm2-elementor-controls';

    /** Nonce action used for AJAX security checks. */
    protected const NONCE_ACTION = 'gm2_elementor_controls';

    /**
     * {@inheritDoc}
     */
    public function enqueue(): void
    {
        if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
            wp_register_script(
                self::SCRIPT_HANDLE,
                GM2_PLUGIN_URL . 'public/js/gm2-elementor-controls.js',
                ['jquery'],
                defined('GM2_VERSION') ? GM2_VERSION : false,
                true
            );
        }

        wp_enqueue_script(self::SCRIPT_HANDLE);

        static $localized = false;
        if (!$localized) {
            wp_localize_script(
                self::SCRIPT_HANDLE,
                'gm2ElementorControls',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce(self::NONCE_ACTION),
                ]
            );
            $localized = true;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function get_default_settings(): array
    {
        return [
            'label_block' => true,
            'multiple'    => false,
            'placeholder' => '',
            'options'     => [],
        ];
    }
}
