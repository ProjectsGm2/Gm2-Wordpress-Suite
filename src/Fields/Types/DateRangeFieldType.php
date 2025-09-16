<?php

namespace Gm2\Fields\Types;

final class DateRangeFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'daterange';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'start' => [ 'type' => 'string', 'format' => 'date' ],
                'end'   => [ 'type' => 'string', 'format' => 'date' ],
            ],
            'required'             => [ 'start', 'end' ],
            'additionalProperties' => false,
        ];
    }
}
