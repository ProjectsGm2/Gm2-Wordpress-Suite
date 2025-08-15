<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Computed extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $cb = $this->args['callback'] ?? null;
        if ( is_callable( $cb ) ) {
            $value = call_user_func( $cb, $object_id );
        }
        echo '<span class="gm2-computed" data-key="' . esc_attr( $this->key ) . '">' . esc_html( $value ) . '</span>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        // Computed fields are read-only.
    }
}
