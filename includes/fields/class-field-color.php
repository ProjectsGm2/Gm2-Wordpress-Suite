<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Color extends GM2_Field {
    private static $assets_hooked = false;

    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'color' );
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
        $disabled         = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="text" class="gm2-color" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    protected function sanitize_field_value( $value ) {
        $clean = sanitize_hex_color( $value );
        return $clean ? $clean : '';
    }
}
