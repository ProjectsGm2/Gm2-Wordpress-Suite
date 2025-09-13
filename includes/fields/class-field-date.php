<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Date extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $min = isset( $this->args['date_min'] ) ? ' min="' . esc_attr( $this->args['date_min'] ) . '"' : '';
        $max = isset( $this->args['date_max'] ) ? ' max="' . esc_attr( $this->args['date_max'] ) . '"' : '';
        echo '<input type="date" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $min . $max . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        return sanitize_text_field( $value );
    }
}
