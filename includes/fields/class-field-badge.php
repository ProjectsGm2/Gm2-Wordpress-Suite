<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Badge extends GM2_Field {
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
        $text     = $value['text'] ?? '';
        $color    = $value['color'] ?? '';
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';

        if ( 'public' === $context_type ) {
            echo '<span class="gm2-badge" style="background-color:' . esc_attr( $color ) . '">' . esc_html( $text ) . '</span>';
            return;
        }

        echo '<div class="gm2-badge-field">';
        echo '<input type="text" class="gm2-badge-text" name="' . esc_attr( $this->key ) . '[text]" value="' . esc_attr( $text ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<input type="text" class="gm2-color gm2-badge-color" name="' . esc_attr( $this->key ) . '[color]" value="' . esc_attr( $color ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<span class="gm2-badge-preview" style="background-color:' . esc_attr( $color ) . '">' . esc_html( $text ) . '</span>';
        echo '</div>';
    }

    public function sanitize_field_value( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'text' => '', 'color' => '' );
        }
        $text  = sanitize_text_field( $value['text'] ?? '' );
        $color = sanitize_hex_color( $value['color'] ?? '' );
        return array(
            'text'  => $text,
            'color' => $color ? $color : '',
        );
    }
}
