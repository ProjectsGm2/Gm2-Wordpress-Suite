<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Design extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-color" />';
    }

    public function sanitize( $value ) {
        $clean = sanitize_hex_color( $value );
        return $clean ? $clean : '';
    }
}
