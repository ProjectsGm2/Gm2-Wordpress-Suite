<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Number extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'number' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="number" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        return is_numeric( $value ) ? $value : '';
    }
}
