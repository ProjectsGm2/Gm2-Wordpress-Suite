<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Select extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $options  = $this->args['options'] ?? array();
        $multiple = ! empty( $this->args['multiple'] );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );

        $name    = esc_attr( $this->key ) . ( $multiple ? '[]' : '' );
        $multi   = $multiple ? ' multiple' : '';
        $current = $multiple ? (array) $value : $value;

        echo '<select name="' . $name . '"' . $multi . $disabled . '>';
        foreach ( $options as $ov => $ol ) {
            $selected = $multiple
                ? selected( in_array( $ov, $current, true ), true, false )
                : selected( $current, $ov, false );
            echo '<option value="' . esc_attr( $ov ) . '"' . $selected . '>' . esc_html( $ol ) . '</option>';
        }
        echo '</select>';
    }

    public function sanitize( $value ) {
        $multiple = ! empty( $this->args['multiple'] );
        $options  = $this->args['options'] ?? array();

        if ( $multiple ) {
            $value = is_array( $value ) ? $value : array( $value );
            $out   = array();
            foreach ( $value as $v ) {
                $v = sanitize_text_field( $v );
                if ( array_key_exists( $v, $options ) ) {
                    $out[] = $v;
                }
            }
            return $out;
        }

        $value = sanitize_text_field( $value );
        return array_key_exists( $value, $options ) ? $value : '';
    }
}
