<?php

namespace Gm2\Fields\Types;

final class DateFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'date';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'   => 'string',
            'format' => 'date',
        ];
    }
}
