<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Schedule extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = is_array( $value ) ? $value : array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $days     = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
        echo '<div class="gm2-schedule" data-key="' . esc_attr( $this->key ) . '">';
        foreach ( $value as $row ) {
            $day   = $row['day'] ?? '';
            $start = $row['start'] ?? '';
            $end   = $row['end'] ?? '';
            echo '<div class="gm2-schedule-row">';
            echo '<select name="' . esc_attr( $this->key ) . '[day][]"' . $disabled . '>';
            foreach ( $days as $d ) {
                $selected = selected( $day, $d, false );
                echo '<option value="' . esc_attr( $d ) . '"' . $selected . '>' . esc_html( $d ) . '</option>';
            }
            echo '</select> ';
            echo '<input type="time" name="' . esc_attr( $this->key ) . '[start][]" value="' . esc_attr( $start ) . '"' . $disabled . ' /> ';
            echo '<input type="time" name="' . esc_attr( $this->key ) . '[end][]" value="' . esc_attr( $end ) . '"' . $disabled . ' /> ';
            echo '<button type="button" class="button gm2-schedule-remove">&times;</button>';
            echo '</div>';
        }
        echo '<div class="gm2-schedule-row">';
        echo '<select name="' . esc_attr( $this->key ) . '[day][]"' . $disabled . '>';
        foreach ( $days as $d ) {
            echo '<option value="' . esc_attr( $d ) . '">' . esc_html( $d ) . '</option>';
        }
        echo '</select> ';
        echo '<input type="time" name="' . esc_attr( $this->key ) . '[start][]" value=""' . $disabled . ' /> ';
        echo '<input type="time" name="' . esc_attr( $this->key ) . '[end][]" value=""' . $disabled . ' /> ';
        echo '<button type="button" class="button gm2-schedule-remove">&times;</button>';
        echo '</div>';
        echo '<p><button type="button" class="button gm2-schedule-add" data-target="' . esc_attr( $this->key ) . '">' . esc_html__( 'Add Time', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
    }

    public function sanitize( $value ) {
        $clean = array();
        if ( is_array( $value ) && isset( $value['day'], $value['start'], $value['end'] ) ) {
            $count = count( $value['day'] );
            for ( $i = 0; $i < $count; $i++ ) {
                $day   = sanitize_text_field( $value['day'][ $i ] ?? '' );
                $start = sanitize_text_field( $value['start'][ $i ] ?? '' );
                $end   = sanitize_text_field( $value['end'][ $i ] ?? '' );
                if ( $day && $start && $end ) {
                    $clean[] = array(
                        'day'   => $day,
                        'start' => $start,
                        'end'   => $end,
                    );
                }
            }
        }
        return $clean;
    }
}
