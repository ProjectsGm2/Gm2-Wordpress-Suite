<?php
namespace Gm2;

use Elementor\Icons_Manager;

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
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'option_border',
                'selector' => '{{WRAPPER}} .gm2-qd-option',
            ]
        );
        $this->add_responsive_control(
            'option_radius',
            [
                'label' => __( 'Border Radius', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'option_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->start_controls_tabs( 'option_style_tabs' );
        $this->start_controls_tab(
            'option_style_normal',
            [ 'label' => __( 'Normal', 'gm2-wordpress-suite' ) ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'option_bg_normal',
                'selector' => '{{WRAPPER}} .gm2-qd-option',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'option_style_hover',
            [ 'label' => __( 'Hover', 'gm2-wordpress-suite' ) ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'option_bg_hover',
                'selector' => '{{WRAPPER}} .gm2-qd-option:hover',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'option_style_active',
            [ 'label' => __( 'Active', 'gm2-wordpress-suite' ) ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'option_bg_active',
                'selector' => '{{WRAPPER}} .gm2-qd-option.active',
            ]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section(
            'gm2_qd_label_style',
            [
                'label' => __( 'Quantity Label', 'gm2-wordpress-suite' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .gm2-qd-label',
            ]
        );
        $this->start_controls_tabs( 'label_style_tabs' );
        $this->start_controls_tab(
            'label_style_normal',
            [ 'label' => __( 'Normal', 'gm2-wordpress-suite' ) ]
        );
        $this->add_control(
            'label_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-label' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'label_text_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-label',
            ]
        );
        $this->add_responsive_control(
            'label_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'label_background',
                'selector' => '{{WRAPPER}} .gm2-qd-label',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'label_style_hover',
            [ 'label' => __( 'Hover', 'gm2-wordpress-suite' ) ]
        );
        $this->add_control(
            'label_hover_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-label' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'label_hover_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-label',
            ]
        );
        $this->add_responsive_control(
            'label_hover_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'label_hover_bg',
                'selector' => '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-label',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'label_style_active',
            [ 'label' => __( 'Active', 'gm2-wordpress-suite' ) ]
        );
        $this->add_control(
            'label_active_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-label' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'label_active_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-label',
            ]
        );
        $this->add_responsive_control(
            'label_active_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'label_active_bg',
                'selector' => '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-label',
            ]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section(
            'gm2_qd_price_style',
            [
                'label' => __( 'Price', 'gm2-wordpress-suite' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_control(
            'currency_icon',
            [
                'label'   => __( 'Currency Icon', 'gm2-wordpress-suite' ),
                'type'    => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value'   => 'fas fa-dollar-sign',
                    'library' => 'fa-solid',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'price_typography',
                'selector' => '{{WRAPPER}} .gm2-qd-price',
            ]
        );
        $this->start_controls_tabs( 'price_style_tabs' );
        $this->start_controls_tab(
            'price_style_normal',
            [ 'label' => __( 'Normal', 'gm2-wordpress-suite' ) ]
        );
        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __( 'Icon Size (Normal)', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range' => [
                    'px'  => [ 'min' => 1,  'max' => 100 ],
                    'em'  => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                    'rem' => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-currency-icon'      => 'font-size: {{SIZE}}{{UNIT}} !important;',
                    '{{WRAPPER}} .gm2-qd-currency-icon svg,\n                     {{WRAPPER}} .gm2-qd-currency-icon i' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );
        $this->add_control(
            'price_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-price' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'price_text_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-price',
            ]
        );
        $this->add_responsive_control(
            'price_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'price_background',
                'selector' => '{{WRAPPER}} .gm2-qd-price',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'price_style_hover',
            [ 'label' => __( 'Hover', 'gm2-wordpress-suite' ) ]
        );
        $this->add_responsive_control(
            'icon_size_hover',
            [
                'label' => __( 'Icon Size (Hover)', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range' => [
                    'px'  => [ 'min' => 1,  'max' => 100 ],
                    'em'  => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                    'rem' => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-currency-icon'      => 'font-size: {{SIZE}}{{UNIT}} !important;',
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-currency-icon svg,\n                     {{WRAPPER}} .gm2-qd-option:hover .gm2-qd-currency-icon i' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );
        $this->add_control(
            'price_hover_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-price' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'price_hover_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-price',
            ]
        );
        $this->add_responsive_control(
            'price_hover_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'price_hover_bg',
                'selector' => '{{WRAPPER}} .gm2-qd-option:hover .gm2-qd-price',
            ]
        );
        $this->end_controls_tab();
        $this->start_controls_tab(
            'price_style_active',
            [ 'label' => __( 'Active', 'gm2-wordpress-suite' ) ]
        );
        $this->add_responsive_control(
            'icon_size_active',
            [
                'label' => __( 'Icon Size (Active)', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range' => [
                    'px'  => [ 'min' => 1,  'max' => 100 ],
                    'em'  => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                    'rem' => [ 'min' => 0.1, 'max' => 10, 'step' => 0.1 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-currency-icon'      => 'font-size: {{SIZE}}{{UNIT}} !important;',
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-currency-icon svg,\n                     {{WRAPPER}} .gm2-qd-option.active .gm2-qd-currency-icon i' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );
        $this->add_control(
            'price_active_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-price' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'price_active_shadow',
                'selector' => '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-price',
            ]
        );
        $this->add_responsive_control(
            'price_active_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'price_active_bg',
                'selector' => '{{WRAPPER}} .gm2-qd-option.active .gm2-qd-price',
            ]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();
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
        $in_edit_mode = false;
        if ( class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor ) {
            $in_edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();
        }

        if ( ! $in_edit_mode && ( ! function_exists( 'is_product' ) || ! is_product() ) ) {
            return;
        }
        global $product;
        if ( ! $product && $in_edit_mode && function_exists( 'wc_get_product' ) ) {
            $preview_id = 0;
            if ( function_exists( 'get_post_meta' ) ) {
                $preview_id = intval( get_post_meta( get_the_ID(), '_elementor_preview_id', true ) );
            }
            if ( ! $preview_id && isset( $_GET['preview_id'] ) ) {
                $preview_id = intval( $_GET['preview_id'] );
            }
            if ( $preview_id ) {
                $product = wc_get_product( $preview_id );
            }
        }
        if ( ! $product ) {
            return;
        }
        $rules = $this->get_rules( $product->get_id() );
        if ( empty( $rules ) ) {
            return;
        }
        $settings = $this->get_settings_for_display();
        echo '<div class="gm2-qd-options">';
        foreach ( $rules as $rule ) {
            $qty   = intval( $rule['min'] );
            $label = ! empty( $rule['label'] )
                ? $rule['label']
                : sprintf( __( 'Qty: %d', 'gm2-wordpress-suite' ), $qty );
            $price_raw = $this->calculate_discounted_price( $product, $rule );
            $price     = wc_price( $price_raw, [ 'currency' => '', 'price_format' => '%2$s' ] );

            echo '<button class="gm2-qd-option" data-qty="' . esc_attr( $qty ) . '">';
            echo '<span class="gm2-qd-label">' . esc_html( $label ) . '</span>';
            echo '<span class="gm2-qd-price">';
            if ( ! empty( $settings['currency_icon']['value'] ) ) {
                Icons_Manager::render_icon( $settings['currency_icon'], [ 'aria-hidden' => 'true', 'class' => 'gm2-qd-currency-icon' ] );
            } else {
                echo '<span class="gm2-qd-currency-icon">' . esc_html( get_woocommerce_currency_symbol() ) . '</span>';
            }
            echo '<span class="gm2-qd-amount">' . wp_kses_post( $price ) . '</span>';
            echo '</span>';
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
