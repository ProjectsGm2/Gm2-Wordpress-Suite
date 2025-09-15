<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Stock extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'stock' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $min      = (int) get_option( 'gm2_stock_min', 0 );
        $max      = (int) get_option( 'gm2_stock_max', 0 );
        $max_attr = $max > 0 ? ' max="' . esc_attr( $max ) . '"' : '';
        echo '<input type="number" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '"' . $max_attr . ' class="gm2-stock"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        if ( ! is_numeric( $value ) ) {
            return '';
        }
        $value = (int) $value;
        $min   = (int) get_option( 'gm2_stock_min', 0 );
        $max   = (int) get_option( 'gm2_stock_max', 0 );
        if ( $value < $min ) {
            return '';
        }
        if ( $max > 0 && $value > $max ) {
            return '';
        }
        return $value;
    }
}
