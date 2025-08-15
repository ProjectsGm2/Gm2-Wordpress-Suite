<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Commerce extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-commerce"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        return is_numeric( $value ) ? $value : '';
    }
}
