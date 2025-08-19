<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Datetime extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $min = isset( $this->args['datetime_min'] ) ? ' min="' . esc_attr( $this->args['datetime_min'] ) . '"' : '';
        $max = isset( $this->args['datetime_max'] ) ? ' max="' . esc_attr( $this->args['datetime_max'] ) . '"' : '';
        echo '<input type="datetime-local" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $min . $max . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize( $value ) {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( $value === '' ) {
            return '';
        }
        $value = str_replace( 'T', ' ', $value );
        $tz = wp_timezone();
        $dt = date_create( $value, $tz );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }
        return '';
    }
}
