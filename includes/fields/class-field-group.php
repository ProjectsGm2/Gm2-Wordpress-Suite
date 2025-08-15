<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Group extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        echo '<div class="gm2-field-group" data-key="' . esc_attr( $this->key ) . '"></div>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        // Groups hold sub-fields; nothing to save directly.
    }
}
