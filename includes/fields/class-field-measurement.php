<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Measurement extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = is_array( $value ) ? $value : array();
        $val      = $value['value'] ?? '';
        $unit_val = $value['unit'] ?? '';
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $units = $this->args['units'] ?? array( 'px', 'em', '%' );

        echo '<input type="number" name="' . esc_attr( $this->key ) . '[value]" value="' . esc_attr( $val ) . '"' . $disabled . $placeholder_attr . ' /> ';
        echo '<select name="' . esc_attr( $this->key ) . '[unit]"' . $disabled . '>';
        foreach ( $units as $unit ) {
            $selected = selected( $unit_val, $unit, false );
            echo '<option value="' . esc_attr( $unit ) . '"' . $selected . '>' . esc_html( $unit ) . '</option>';
        }
        echo '</select>';
    }

    public function sanitize( $value ) {
        $units = $this->args['units'] ?? array( 'px', 'em', '%' );
        if ( ! is_array( $value ) ) {
            return array( 'value' => '', 'unit' => $units[0] );
        }
        $val = isset( $value['value'] ) && is_numeric( $value['value'] ) ? $value['value'] : '';
        $unit = isset( $value['unit'] ) && in_array( $value['unit'], $units, true ) ? $value['unit'] : $units[0];
        return array( 'value' => $val, 'unit' => $unit );
    }
}
