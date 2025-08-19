<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Icon extends GM2_Field {
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
        if ( defined( 'GM2_PLUGIN_URL' ) ) {
            wp_enqueue_style( 'gm2-extra-fields', GM2_PLUGIN_URL . 'admin/css/gm2-extra-fields.css', array(), defined( 'GM2_VERSION' ) ? GM2_VERSION : false );
            wp_enqueue_script( 'gm2-extra-fields', GM2_PLUGIN_URL . 'admin/js/gm2-extra-fields.js', array( 'jquery' ), defined( 'GM2_VERSION' ) ? GM2_VERSION : false, true );
        }
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = is_string( $value ) ? $value : '';
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';

        if ( 'public' === $context_type ) {
            echo '<span class="gm2-icon-preview dashicons ' . esc_attr( $value ) . '"></span>';
            return;
        }

        echo '<div class="gm2-icon-field">';
        echo '<input type="text" class="gm2-icon-input" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<span class="gm2-icon-preview dashicons ' . esc_attr( $value ) . '"></span>';
        echo '</div>';
    }

    public function sanitize( $value ) {
        return sanitize_text_field( $value );
    }
}
