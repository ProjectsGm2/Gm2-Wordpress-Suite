<?php

declare(strict_types=1);

namespace Gm2\Elementor\Controls;

use Elementor\Base_Data_Control;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control that captures a numeric value alongside a selectable unit.
 */
class Unit extends Base_Data_Control
{
    public const TYPE = 'gm2-unit';

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
            'label_block' => true,
            'placeholder' => '',
            'units'       => [
                'km' => __('Kilometers', 'gm2-wordpress-suite'),
                'mi' => __('Miles', 'gm2-wordpress-suite'),
            ],
            'default'     => [
                'value' => '',
                'unit'  => 'km',
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
        var value = (typeof current.value !== 'undefined') ? current.value : (defaults.value || '');
        var unit = current.unit || defaults.unit || '';
        #>
        <div class="gm2-control gm2-control--unit">
            <input type="number" step="any" class="gm2-unit-control__value" placeholder="{{ data.placeholder }}" data-setting="{{ data.name }}[value]" value="{{ value }}" />
            <select class="gm2-unit-control__unit" data-setting="{{ data.name }}[unit]">
                <# _.each( data.units, function( label, key ) { #>
                    <option value="{{ key }}" <# if ( key === unit ) { #>selected<# } #>>{{ label }}</option>
                <# } ); #>
            </select>
        </div>
        <?php
    }
}
