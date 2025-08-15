<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Number extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        echo '<input type="number" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" />';
    }

    public function sanitize( $value ) {
        return is_numeric( $value ) ? $value : '';
    }
}
