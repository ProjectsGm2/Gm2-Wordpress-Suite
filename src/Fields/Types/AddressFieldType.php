<?php

namespace Gm2\Fields\Types;

final class AddressFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'address';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'line1'       => [ 'type' => 'string' ],
                'line2'       => [ 'type' => 'string' ],
                'city'        => [ 'type' => 'string' ],
                'state'       => [ 'type' => 'string' ],
                'postal_code' => [ 'type' => 'string' ],
                'country'     => [ 'type' => 'string' ],
            ],
            'additionalProperties' => false,
        ];
    }
}
