<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Email extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="email" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        $value = sanitize_email( $value );
        return is_email( $value ) ? $value : '';
    }
}
