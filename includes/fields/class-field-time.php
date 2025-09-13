<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Time extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $min = isset( $this->args['time_min'] ) ? ' min="' . esc_attr( $this->args['time_min'] ) . '"' : '';
        $max = isset( $this->args['time_max'] ) ? ' max="' . esc_attr( $this->args['time_max'] ) . '"' : '';
        echo '<input type="time" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $min . $max . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( $value === '' ) {
            return '';
        }

        $tz = wp_timezone();
        $dt = date_create_from_format( 'H:i:s', $value, $tz );
        if ( ! $dt ) {
            $dt = date_create_from_format( 'H:i', $value, $tz );
        }
        if ( $dt ) {
            return $dt->format( 'H:i:s' );
        }
        return '';
    }
}
