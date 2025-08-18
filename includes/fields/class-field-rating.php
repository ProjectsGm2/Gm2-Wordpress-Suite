<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Rating extends GM2_Field {
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

    protected function render_field( $value, $object_id, $context_type ) {
        $value    = intval( $value );
        $value    = max( 0, min( 5, $value ) );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );

        if ( 'public' === $context_type ) {
            echo '<div class="gm2-rating-display">' . self::stars_html( $value ) . '</div>';
            return;
        }

        echo '<div class="gm2-rating-picker" data-name="' . esc_attr( $this->key ) . '" data-value="' . esc_attr( $value ) . '"' . $disabled . '>';
        for ( $i = 1; $i <= 5; $i++ ) {
            $class = $i <= $value ? 'star active' : 'star';
            echo '<span class="' . $class . '" data-value="' . $i . '">&#9733;</span>';
        }
        echo '<input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" />';
        echo '</div>';
    }

    private static function stars_html( $value ) {
        $html = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $class = $i <= $value ? 'star active' : 'star';
            $html .= '<span class="' . $class . '">&#9733;</span>';
        }
        return $html;
    }

    public function sanitize( $value ) {
        $value = intval( $value );
        if ( $value < 0 ) {
            $value = 0;
        }
        if ( $value > 5 ) {
            $value = 5;
        }
        return $value;
    }
}
