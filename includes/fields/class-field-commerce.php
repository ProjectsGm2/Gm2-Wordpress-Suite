<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Commerce extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-commerce"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize( $value ) {
        return is_numeric( $value ) ? $value : '';
    }
}
