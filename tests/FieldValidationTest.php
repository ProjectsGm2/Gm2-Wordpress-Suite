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
}
