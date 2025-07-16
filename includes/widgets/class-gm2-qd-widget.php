<?php
namespace Gm2;

if (!defined('ABSPATH')) { exit; }

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
        // Show the widget with other WooCommerce elements so
        // it's easier to find when building product templates.
        return [ 'woocommerce' ];
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

    /**
     * Calculate the discounted price for a product rule.
     *
     * @param \WC_Product $product The WooCommerce product.
     * @param array       $rule    Discount rule data.
     * @return float               Discounted price ready for display.
     */
    private function calculate_discounted_price( $product, $rule ) {
        if ( ! function_exists( 'wc_get_price_to_display' ) ) {
            return 0;
        }

        $base = (float) wc_get_price_to_display( $product );
        $price = $base;

        if ( isset( $rule['type'] ) && $rule['type'] === 'percent' ) {
            $price = $base * ( 1 - ( (float) $rule['amount'] / 100 ) );
        } else {
            $price = $base - (float) $rule['amount'];
        }

        if ( $price < 0 ) {
            $price = 0;
        }

        return $price;
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
            $label = ! empty( $rule['label'] )
                ? $rule['label']
                : sprintf( __( 'Qty: %d', 'gm2-wordpress-suite' ), $qty );
            $price = wc_price( $this->calculate_discounted_price( $product, $rule ) );

            echo '<button class="gm2-qd-option" data-qty="' . esc_attr( $qty ) . '">';
            echo '<span class="gm2-qd-label">' . esc_html( $label ) . '</span>';
            echo '<span class="gm2-qd-price">' . wp_kses_post( $price ) . '</span>';
            echo '</button>';
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
