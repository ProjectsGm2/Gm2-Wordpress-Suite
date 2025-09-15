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

    public function test_regex_invalid_pattern_returns_error() {
        $field = [ 'regex' => '/(unclosed' ];
        $res   = gm2_validate_field('regex_field', $field, 'value');

        $this->assertInstanceOf( WP_Error::class, $res );
        $this->assertSame( 'gm2_regex_invalid', $res->get_error_code() );
    }

    public function test_regex_invalid_pattern_uses_custom_message() {
        $field = [
            'regex'    => '/(unclosed',
            'messages' => [ 'regex' => 'Custom invalid format.' ],
        ];
        $res = gm2_validate_field('regex_field', $field, 'value');

        $this->assertInstanceOf( WP_Error::class, $res );
        $this->assertSame( 'gm2_regex_invalid', $res->get_error_code() );
        $this->assertSame( 'Custom invalid format.', $res->get_error_message() );
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

    public function test_text_field_sanitize_filter_value_saved() {
        $post_id = self::factory()->post->create();
        $fields = [
            'custom_text' => [
                'type'  => 'text',
                'label' => 'Custom Text',
            ],
        ];

        $filtered_values = [];
        $callback = function( $value, $field = null ) use ( &$filtered_values ) {
            $filtered = 'filtered-' . $value;
            $filtered_values[] = $filtered;
            return $filtered;
        };

        add_filter( 'gm2_cp_field_sanitize_text', $callback, 10, 2 );

        gm2_save_field_group( $fields, $post_id, 'post', [ 'custom_text' => 'RawValue' ] );

        remove_filter( 'gm2_cp_field_sanitize_text', $callback, 10 );

        $this->assertNotEmpty( $filtered_values, 'The gm2_cp_field_sanitize_text filter should run.' );
        $this->assertSame( 'filtered-RawValue', get_post_meta( $post_id, 'custom_text', true ) );
    }
}
