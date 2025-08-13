<?php
namespace Gm2;

if (!defined('ABSPATH')) { exit; }

class Gm2_Elementor_Registration_Login {
    public function run() {
        if ( ! class_exists('WooCommerce') ) {
            return;
        }
        add_action( 'elementor/init', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! class_exists('Elementor\\Plugin') || ! class_exists('Elementor\\Widget_Base') ) {
            return;
        }
        add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widget' ] );
    }

    public function register_widget( $widgets_manager = null ) {
        if ( $widgets_manager === null && class_exists( '\Elementor\Plugin' ) ) {
            $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
        }
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }
        if ( ! class_exists( GM2_Registration_Login_Widget::class ) ) {
            require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-registration-login-widget.php';
        }
        if ( ! class_exists( GM2_Registration_Login_Widget::class ) ) {
            return;
        }
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new GM2_Registration_Login_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new GM2_Registration_Login_Widget() );
        }
    }
}
