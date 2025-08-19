<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Gradient extends GM2_Field {
    private static $assets_hooked = false;

    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args );
        if ( ! self::$assets_hooked ) {
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            self::$assets_hooked = true;
        }
    }

    public static function enqueue_assets() {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        if ( defined( 'GM2_PLUGIN_URL' ) ) {
            wp_enqueue_style( 'gm2-extra-fields', GM2_PLUGIN_URL . 'admin/css/gm2-extra-fields.css', array( 'wp-color-picker' ), defined( 'GM2_VERSION' ) ? GM2_VERSION : false );
            wp_enqueue_script( 'gm2-extra-fields', GM2_PLUGIN_URL . 'admin/js/gm2-extra-fields.js', array( 'jquery', 'wp-color-picker' ), defined( 'GM2_VERSION' ) ? GM2_VERSION : false, true );
        }
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = is_array( $value ) ? $value : array();
        $start    = $value['start'] ?? '';
        $end      = $value['end'] ?? '';
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';

        if ( 'public' === $context_type ) {
            $style = ( $start && $end ) ? ' style="background: linear-gradient(' . esc_attr( $start ) . ',' . esc_attr( $end ) . ');"' : '';
            echo '<div class="gm2-gradient-display"' . $style . '></div>';
            return;
        }

        echo '<div class="gm2-gradient-field">';
        echo '<input type="text" class="gm2-color gm2-gradient-start" name="' . esc_attr( $this->key ) . '[start]" value="' . esc_attr( $start ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<input type="text" class="gm2-color gm2-gradient-end" name="' . esc_attr( $this->key ) . '[end]" value="' . esc_attr( $end ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<div class="gm2-gradient-preview"></div>';
        echo '</div>';
    }

    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'start' => '', 'end' => '' );
        }
        $start = sanitize_hex_color( $value['start'] ?? '' );
        $end   = sanitize_hex_color( $value['end'] ?? '' );
        return array(
            'start' => $start ? $start : '',
            'end'   => $end ? $end : '',
        );
    }
}
