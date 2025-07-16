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
    }

    public function register_widget( $widgets_manager ) {
        $widgets_manager->register( new GM2_QD_Widget() );
    }
}

if ( class_exists( '\\Elementor\\Widget_Base' ) ) {
class GM2_QD_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gm2_quantity_discounts';
    }
    public function get_title() {
        return __( 'Gm2 Qnty Discounts', 'gm2-wordpress-suite' );
    }
    public function get_icon() {
        return 'eicon-cart-medium';
    }
    public function get_categories() {
        // Prefer the WooCommerce section if available. This requires
        // Elementor Pro, otherwise widgets default to the General
        // category so they remain visible in all installations.
        $manager = \Elementor\Plugin::instance()->elements_manager;
        if ( method_exists( $manager, 'get_categories' ) ) {
            $categories = $manager->get_categories();
            if ( isset( $categories['woocommerce'] ) ) {
                return [ 'woocommerce' ];
            }
        }
        return [ 'general' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'gm2_qd_style',
            [
                'label' => __( 'Options Style', 'gm2-wordpress-suite' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_control(
            'text_color',
            [
                'label' => __( 'Text Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'bg_color',
            [
                'label' => __( 'Background', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'font_size',
            [
                'label' => __( 'Font Size', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::NUMBER,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option' => 'font-size: {{VALUE}}px;',
                ],
            ]
        );
        $this->end_controls_section();
    }

    protected function render() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }
        global $product;
        if ( ! $product ) {
            return;
        }
        $rules = $this->get_rules( $product->get_id() );
        if ( empty( $rules ) ) {
            return;
        }
        echo '<div class="gm2-qd-options">';
        foreach ( $rules as $rule ) {
            $qty   = intval( $rule['min'] );
            $label = sprintf( __( 'Qty: %d', 'gm2-wordpress-suite' ), $qty );
            echo '<button type="button" class="gm2-qd-option" data-qty="' . esc_attr( $qty ) . '">' . esc_html( $label ) . '</button>';
        }
        echo '</div>';
    }

    private function get_rules( $product_id ) {
        $m = new Gm2_Quantity_Discount_Manager();
        $groups = $m->get_groups();
        foreach ( $groups as $g ) {
            if ( ! empty( $g['products'] ) && in_array( $product_id, $g['products'], true ) ) {
                return $g['rules'] ?? [];
            }
        }
        return [];
    }
}
}
