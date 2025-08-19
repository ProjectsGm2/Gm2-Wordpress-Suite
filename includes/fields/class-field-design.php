<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Design extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-color"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize( $value ) {
        $clean = sanitize_hex_color( $value );
        return $clean ? $clean : '';
    }
}
