<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Phone extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="tel" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize( $value ) {
        $value = sanitize_text_field( $value );
        return preg_match( '/^\+?[0-9\s\-()]+$/', $value ) ? $value : '';
    }
}
