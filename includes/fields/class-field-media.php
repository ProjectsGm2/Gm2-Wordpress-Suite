<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Media extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $button = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '">' . esc_html__( 'Select Media', 'gm2-wordpress-suite' ) . '</button>';
        echo '<input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" />' . $button;
    }

    public function sanitize( $value ) {
        return absint( $value );
    }
}
