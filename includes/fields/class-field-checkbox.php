<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Checkbox extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $checked  = checked( $value, '1', false );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="checkbox" name="' . esc_attr( $this->key ) . '" value="1"' . $checked . $disabled . ' />';
    }

    public function sanitize( $value ) {
        return $value ? '1' : '';
    }
}
