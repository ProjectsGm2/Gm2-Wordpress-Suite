<?php
class FieldValidationTest extends WP_UnitTestCase {
    public function test_time_validation() {
        $field = [ 'type' => 'time' ];
        $this->assertTrue( gm2_validate_field('time_field', $field, '14:30') );
        $res = gm2_validate_field('time_field', $field, '25:00');
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_datetime_validation() {
        $field = [ 'type' => 'datetime' ];
        $this->assertTrue( gm2_validate_field('datetime_field', $field, '2024-05-27 15:30') );
        $res = gm2_validate_field('datetime_field', $field, '2024-99-99 10:00');
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_daterange_validation() {
        $field = [ 'type' => 'daterange' ];
        $valid = [ 'start' => '2024-05-01', 'end' => '2024-05-10' ];
        $this->assertTrue( gm2_validate_field('range_field', $field, $valid) );
        $invalid = [ 'start' => '2024-05-10', 'end' => '2024-05-01' ];
        $res = gm2_validate_field('range_field', $field, $invalid);
        $this->assertInstanceOf( WP_Error::class, $res );
    }

    public function test_measurement_validation_callback() {
        $field = [
            'type' => 'measurement',
            'validate_callback' => function( $value ) {
                return ($value['value'] ?? 0) >= 0 ? true : 'Must be positive';
            },
        ];
        $this->assertTrue( gm2_validate_field('measure', $field, [ 'value' => 5, 'unit' => 'px' ]) );
        $res = gm2_validate_field('measure', $field, [ 'value' => -1, 'unit' => 'px' ]);
        $this->assertInstanceOf( WP_Error::class, $res );
        $this->assertSame( 'Must be positive', $res->get_error_message() );
    }

    public function test_schedule_validation_callback() {
        $field = [
            'type' => 'schedule',
            'validate_callback' => function( $value ) {
                foreach ( $value as $row ) {
                    if ( ($row['end'] ?? '') <= ($row['start'] ?? '') ) {
                        return 'End must be after start';
                    }
                }
                return true;
            },
        ];
        $valid = [ [ 'day' => 'Mon', 'start' => '09:00', 'end' => '10:00' ] ];
        $this->assertTrue( gm2_validate_field('sched', $field, $valid) );
        $invalid = [ [ 'day' => 'Tue', 'start' => '10:00', 'end' => '09:00' ] ];
        $res = gm2_validate_field('sched', $field, $invalid);
        $this->assertInstanceOf( WP_Error::class, $res );
        $this->assertSame( 'End must be after start', $res->get_error_message() );
    }
}
