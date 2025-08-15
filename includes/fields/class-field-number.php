<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Number extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="number" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        return is_numeric( $value ) ? $value : '';
    }
}
