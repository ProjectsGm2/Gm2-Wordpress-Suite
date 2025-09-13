<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Checkbox extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $checked  = checked( $value, '1', false );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="checkbox" name="' . esc_attr( $this->key ) . '" value="1"' . $checked . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        return $value ? '1' : '';
    }
}
