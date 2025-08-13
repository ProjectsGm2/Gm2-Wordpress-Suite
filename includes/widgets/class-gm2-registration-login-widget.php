<?php
namespace Gm2;

if (!defined('ABSPATH')) { exit; }

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if ( class_exists('\Elementor\Widget_Base') ) {
class GM2_Registration_Login_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gm2_registration_login';
    }
    public function get_title() {
        return __( 'Gm2 Login/Register', 'gm2-wordpress-suite' );
    }
    public function get_icon() {
        return 'eicon-lock-user';
    }
    public function get_categories() {
        return [ 'general' ];
    }
    public function get_style_depends() {
        return [ 'gm2-login-widget' ];
    }
    public function get_script_depends() {
        return [ 'gm2-login-widget' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'gm2_login_toggles',
            [ 'label' => __( 'Display Options', 'gm2-wordpress-suite' ) ]
        );
        $this->add_control(
            'show_login_form',
            [
                'label' => __( 'Show Login Form', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        $this->add_control(
            'show_register_form',
            [
                'label' => __( 'Show Registration Form', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        $this->add_control(
            'edit_mode_default_form',
            [
                'label' => __( 'Default Form in Editor', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'login'    => __( 'Login', 'gm2-wordpress-suite' ),
                    'register' => __( 'Register', 'gm2-wordpress-suite' ),
                ],
                'default' => 'login',
                'condition' => [
                    'show_login_form'    => 'yes',
                    'show_register_form' => 'yes',
                ],
            ]
        );
        $this->add_control(
            'show_remember',
            [
                'label' => __( 'Show "Remember Me"', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        $this->add_control(
            'show_google',
            [
                'label' => __( 'Show Google Button', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        $this->add_control(
            'login_placeholder',
            [
                'label' => __( 'Username Placeholder', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        $this->add_control(
            'pass_placeholder',
            [
                'label' => __( 'Password Placeholder', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        $this->end_controls_section();

        // Form container styles
        $this->start_controls_section(
            'gm2_form_container',
            [
                'label' => __( 'Form Container', 'gm2-wordpress-suite' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_responsive_control(
            'form_width',
            [
                'label' => __( 'Width', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ '%', 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'form_max_width',
            [
                'label' => __( 'Max Width', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ '%', 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'form_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type'  => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'form_margin',
            [
                'label' => __( 'Margin', 'gm2-wordpress-suite' ),
                'type'  => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'form_bg',
                'selector' => '{{WRAPPER}} .gm2-login-widget',
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'selector' => '{{WRAPPER}} .gm2-login-widget',
            ]
        );
        $this->add_responsive_control(
            'form_radius',
            [
                'label' => __( 'Border Radius', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'form_shadow',
                'selector' => '{{WRAPPER}} .gm2-login-widget',
            ]
        );
        $this->end_controls_section();

        // Label styles
        $this->start_controls_section(
            'gm2_label_style',
            [
                'label' => __( 'Field Labels', 'gm2-wordpress-suite' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} label',
            ]
        );
        $this->add_control(
            'label_color',
            [
                'label' => __( 'Color', 'gm2-wordpress-suite' ),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} label' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'label_spacing',
            [
                'label' => __( 'Spacing', 'gm2-wordpress-suite' ),
                'type'  => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} label' => 'margin-bottom: {{BOTTOM}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();

        // Input styles
        $this->start_controls_section(
            'gm2_input_style',
            [
                'label' => __( 'Input Fields', 'gm2-wordpress-suite' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} input',
            ]
        );
        $this->add_control(
            'input_text_color',
            [
                'label' => __( 'Text Color', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} input' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'input_bg',
                'selector' => '{{WRAPPER}} input',
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} input',
            ]
        );
        $this->add_responsive_control(
            'input_radius',
            [
                'label' => __( 'Border Radius', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();

        // Button styles
        $this->start_controls_section(
            'gm2_button_style',
            [
                'label' => __( 'Buttons', 'gm2-wordpress-suite' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .gm2-login-widget .gm2-btn',
            ]
        );
        $this->add_control(
            'button_text_color',
            [
                'label' => __( 'Text Color', 'gm2-wordpress-suite' ),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget .gm2-btn' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'button_bg',
                'selector' => '{{WRAPPER}} .gm2-login-widget .gm2-btn',
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .gm2-login-widget .gm2-btn',
            ]
        );
        $this->add_responsive_control(
            'button_radius',
            [
                'label' => __( 'Border Radius', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget .gm2-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __( 'Padding', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget .gm2-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'button_align',
            [
                'label' => __( 'Alignment', 'gm2-wordpress-suite' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [ 'title' => __( 'Left', 'gm2-wordpress-suite' ), 'icon' => 'eicon-text-align-left' ],
                    'center' => [ 'title' => __( 'Center', 'gm2-wordpress-suite' ), 'icon' => 'eicon-text-align-center' ],
                    'right' => [ 'title' => __( 'Right', 'gm2-wordpress-suite' ), 'icon' => 'eicon-text-align-right' ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-login-widget .gm2-btn-wrap' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_section();
    }

    public function restrict_auth_roles( $user ) {
        if ( is_wp_error( $user ) || ! $user ) {
            return $user;
        }
        if ( $user instanceof \WP_User ) {
            $roles = (array) $user->roles;
            if ( in_array( 'customer', $roles, true ) || in_array( 'subscriber', $roles, true ) ) {
                return $user;
            }
        }
        return new \WP_Error( 'gm2_invalid_role', __( 'Only customers can log in.', 'gm2-wordpress-suite' ) );
    }

    protected function render() {
        $in_edit_mode = (
            ( defined( 'ELEMENTOR_EDITOR' ) && ELEMENTOR_EDITOR ) ||
            ( class_exists( '\Elementor\Plugin' )
              && ( ( $p = \Elementor\Plugin::instance() )
                   && ( ( isset( $p->editor ) && method_exists( $p->editor, 'is_edit_mode' ) && $p->editor->is_edit_mode() )
                        || ( isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode() ) ) ) )
            || ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] )
            || isset( $_GET['elementor-preview'] )
        );

        if ( is_user_logged_in() && ! $in_edit_mode ) {
            echo '<div class="gm2-login-widget-logged">' .
                esc_html__( 'You are already logged in.', 'gm2-wordpress-suite' ) .
                '</div>';
            return;
        }
        $settings = $this->get_settings_for_display();
        add_filter( 'authenticate', [ $this, 'restrict_auth_roles' ], 30 );
        $wrap_class = 'gm2-login-widget';
        if ( $in_edit_mode ) {
            $wrap_class .= ' gm2-edit-mode';
        }
        $wrap_attrs = [
            'class' => $wrap_class,
            'data-show-remember' => $settings['show_remember'] === 'yes' ? 'yes' : 'no',
            'data-login-placeholder' => esc_attr( $settings['login_placeholder'] ),
            'data-pass-placeholder'  => esc_attr( $settings['pass_placeholder'] ),
        ];
        $attr_html = '';
        foreach ( $wrap_attrs as $k => $v ) {
            $attr_html .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
        }
        echo '<div' . $attr_html . '>';
        if ( $settings['show_login_form'] === 'yes' ) {
            $login_classes = 'gm2-login-form';
            if ( $in_edit_mode && ( ! isset( $settings['edit_mode_default_form'] ) || 'login' === $settings['edit_mode_default_form'] ) ) {
                $login_classes .= ' active';
            }
            echo '<div class="' . esc_attr( $login_classes ) . '">';
            woocommerce_login_form( [ 'redirect' => home_url() ] );
            echo '</div>';
        }
        if ( $settings['show_register_form'] === 'yes' ) {
            $register_classes = 'gm2-register-form';
            $register_style = '';
            if ( $in_edit_mode ) {
                if ( isset( $settings['edit_mode_default_form'] ) && 'register' === $settings['edit_mode_default_form'] ) {
                    $register_classes .= ' active';
                }
            } else {
                $register_style = ' style="display:none"';
            }
            echo '<div class="' . esc_attr( $register_classes ) . '"' . $register_style . '>';
            if ( did_action( 'init' ) && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_template' ) ) {
                ob_start();
                wc_get_template( 'myaccount/form-login.php' );
                $tpl = ob_get_clean();
                if ( preg_match( '#<form[^>]*class="[^"]*woocommerce-form[^"]*register[^"]*"[^>]*>.*?</form>#s', $tpl, $m ) ) {
                    echo $m[0];
                } else {
                    echo $tpl;
                }
            } else {
                echo '<p class="gm2-woocommerce-placeholder">' . esc_html__( 'WooCommerce registration form will appear here', 'gm2-wordpress-suite' ) . '</p>';
            }
            echo '</div>';
        }
        if ( $settings['show_google'] === 'yes' && apply_filters( 'gm2_sitekit_login_enabled', true ) && class_exists( 'Google\\Site_Kit\\Plugin' ) ) {
            try {
                $url = \Google\Site_Kit\Plugin::get()->get_authentication()->get_google_login_url();
            } catch ( \Throwable $e ) {
                $url = '';
            }
            if ( $url ) {
                echo '<div class="gm2-btn-wrap gm2-google-wrap"><a class="gm2-btn gm2-google-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Continue with Google', 'gm2-wordpress-suite' ) . '</a></div>';
            }
        }
        echo '</div>';
        remove_filter( 'authenticate', [ $this, 'restrict_auth_roles' ], 30 );
    }
}
}
