<?php

class Gm2EvaluateConditionsSanitizationTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        $_REQUEST = [];
    }

    public function tearDown(): void {
        parent::tearDown();
        $_REQUEST = [];
    }

    private function build_field_with_condition(array $condition): array {
        return [
            'conditions' => [
                [
                    'conditions' => [
                        array_merge(
                            [
                                'relation' => 'AND',
                                'operator' => '=',
                            ],
                            $condition
                        ),
                    ],
                ],
            ],
        ];
    }

    public function test_request_value_is_sanitized_before_evaluation(): void {
        $_REQUEST['example_field'] = '  match  ';
        $field = $this->build_field_with_condition([
            'target' => 'example_field',
            'value'  => 'match',
        ]);

        $state = gm2_evaluate_conditions($field, 0);

        $this->assertTrue($state['show']);
    }

    public function test_array_request_value_is_sanitized_before_evaluation(): void {
        $_REQUEST['example_field'] = [ ' match ' ];
        $field = $this->build_field_with_condition([
            'target' => 'example_field',
            'value'  => 'match',
        ]);

        $state = gm2_evaluate_conditions($field, 0);

        $this->assertTrue($state['show']);
    }

    public function test_numeric_comparison_is_preserved_after_sanitization(): void {
        $_REQUEST['price'] = '10';
        $field = $this->build_field_with_condition([
            'target'   => 'price',
            'operator' => '>',
            'value'    => '5',
        ]);

        $state = gm2_evaluate_conditions($field, 0);

        $this->assertTrue($state['show']);
    }
}
