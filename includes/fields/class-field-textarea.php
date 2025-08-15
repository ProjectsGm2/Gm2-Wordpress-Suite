<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Textarea extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<textarea name="' . esc_attr( $this->key ) . '" rows="5" cols="40"' . $disabled . '>' . esc_textarea( $value ) . '</textarea>';
    }
}
