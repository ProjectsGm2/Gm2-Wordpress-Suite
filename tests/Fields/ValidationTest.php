<?php

use Gm2\Fields\FieldGroupRegistry;
use Gm2\Fields\FieldTypeRegistry;
use Gm2\Fields\Sanitizers\SanitizerRegistry;
use Gm2\Fields\Storage\MetaRegistrar;
use Gm2\Fields\Validation\ValidatorRegistry;

class ValidationTest extends WP_UnitTestCase
{
    public function test_conditional_validation_skips_hidden_fields(): void
    {
        $registry = $this->createRegistry();
        $registry->registerGroup('contact', [
            'contexts' => [ 'post' => [ 'post' ] ],
            'fields'   => [
                'subscribe' => [
                    'type'  => 'checkbox',
                    'label' => 'Subscribe',
                ],
                'email' => [
                    'type'       => 'email',
                    'label'      => 'Email',
                    'conditions' => [
                        'relation' => 'and',
                        [
                            'field'    => 'subscribe',
                            'operator' => '==',
                            'value'    => true,
                        ],
                    ],
                ],
            ],
        ]);

        $errors = $registry->validateGroup('contact', [
            'subscribe' => false,
            'email'     => 'not-an-email',
        ]);

        $this->assertSame([], $errors, 'Email should not validate when subscribe is false.');

        $errors = $registry->validateGroup('contact', [
            'subscribe' => true,
            'email'     => 'not-an-email',
        ]);

        $this->assertArrayHasKey('email', $errors);
        $this->assertInstanceOf(WP_Error::class, $errors['email']);
    }

    public function test_sanitization_applies_per_field_rules(): void
    {
        $registry = $this->createRegistry();
        $registry->registerGroup('contact', [
            'contexts' => [ 'post' => [ 'post' ] ],
            'fields'   => [
                'subscribe' => [ 'type' => 'checkbox' ],
                'email'     => [ 'type' => 'email' ],
            ],
        ]);

        $sanitized = $registry->sanitizeGroup('contact', [
            'subscribe' => 'yes',
            'email'     => ' user@example.com ',
        ]);

        $this->assertTrue($sanitized['subscribe']);
        $this->assertSame('user@example.com', $sanitized['email']);
    }

    private function createRegistry(): FieldGroupRegistry
    {
        return new FieldGroupRegistry(
            new MetaRegistrar(),
            FieldTypeRegistry::withDefaults(),
            ValidatorRegistry::withDefaults(),
            SanitizerRegistry::withDefaults(),
            null
        );
    }
}
