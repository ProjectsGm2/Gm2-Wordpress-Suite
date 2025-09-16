<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Base_Data_Control;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control capturing a price range and associated meta key.
 */
class Price extends Base_Data_Control
{
    public const TYPE = 'gm2-price';

    /**
     * Register Elementor hooks for the control.
     */
    public static function register(): void
    {
        add_action('elementor/controls/register', [static::class, 'register_control']);
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
        return [
            'label_block'  => true,
            'show_key'     => true,
            'placeholders' => [
                'key' => __('Meta key (default: _price)', 'gm2-wordpress-suite'),
                'min' => __('Minimum price', 'gm2-wordpress-suite'),
                'max' => __('Maximum price', 'gm2-wordpress-suite'),
            ],
            'default'      => [
                'key' => '_price',
                'min' => '',
                'max' => '',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function content_template(): void
    {
        ?>
        <#
        var current = data.controlValue || {};
        var defaults = data.default || {};
        var placeholders = data.placeholders || {};
        var key = (typeof current.key !== 'undefined') ? current.key : (defaults.key || '');
        var min = (typeof current.min !== 'undefined') ? current.min : (defaults.min || '');
        var max = (typeof current.max !== 'undefined') ? current.max : (defaults.max || '');
        #>
        <div class="gm2-control gm2-control--price">
            <# if ( data.show_key ) { #>
                <input type="text" class="gm2-price-control__key" data-setting="{{ data.name }}[key]" placeholder="{{ placeholders.key }}" value="{{ key }}" />
            <# } #>
            <div class="gm2-price-control__range">
                <input type="number" step="any" class="gm2-price-control__min" data-setting="{{ data.name }}[min]" placeholder="{{ placeholders.min }}" value="{{ min }}" />
                <input type="number" step="any" class="gm2-price-control__max" data-setting="{{ data.name }}[max]" placeholder="{{ placeholders.max }}" value="{{ max }}" />
            </div>
        </div>
        <?php
    }
}
