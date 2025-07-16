<?php
namespace Gm2;

if (!defined('ABSPATH')) { exit; }

class Gm2_Elementor_Quantity_Discounts {
    public function __construct() {
        // Register the widget after Elementor initializes so the
        // `elementor/widgets/register` hook is available.
        add_action('elementor/init', [ $this, 'init' ]);
    }

    public function init() {
        if (
            ! class_exists('Elementor\\Plugin') ||
            ! class_exists('WooCommerce') ||
            ! class_exists('Elementor\\Widget_Base')
        ) {
            return;
        }
        add_action('elementor/widgets/register', [ $this, 'register_widget' ]);
        add_action('elementor/widgets/widgets_registered', [ $this, 'register_widget' ]);
    }

    public function register_widget( $widgets_manager ) {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';

        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new GM2_QD_Widget() );
        } else {
            $widgets_manager->register_widget_type( new GM2_QD_Widget() );
        }
    }
}
