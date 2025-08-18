<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Daterange extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $start = is_array( $value ) ? ( $value['start'] ?? '' ) : '';
        $end   = is_array( $value ) ? ( $value['end'] ?? '' ) : '';
        echo '<input type="date" name="' . esc_attr( $this->key ) . '[start]" value="' . esc_attr( $start ) . '"' . $disabled . ' />';
        echo ' - ';
        echo '<input type="date" name="' . esc_attr( $this->key ) . '[end]" value="' . esc_attr( $end ) . '"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        $result = array( 'start' => '', 'end' => '' );
        if ( ! is_array( $value ) ) {
            return $result;
        }
        $tz = wp_timezone();
        if ( ! empty( $value['start'] ) ) {
            $start = date_create( trim( $value['start'] ), $tz );
            if ( $start ) {
                $result['start'] = $start->format( 'Y-m-d' );
            }
        }
        if ( ! empty( $value['end'] ) ) {
            $end = date_create( trim( $value['end'] ), $tz );
            if ( $end ) {
                $result['end'] = $end->format( 'Y-m-d' );
            }
        }
        return $result;
    }
}
