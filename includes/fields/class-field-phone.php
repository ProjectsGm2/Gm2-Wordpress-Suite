<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Phone extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="tel" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        $value = sanitize_text_field( $value );
        return preg_match( '/^\+?[0-9\s\-()]+$/', $value ) ? $value : '';
    }
}
