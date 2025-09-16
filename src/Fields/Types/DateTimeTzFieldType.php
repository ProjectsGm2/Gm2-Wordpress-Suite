<?php

namespace Gm2\Fields\Types;

final class DateTimeTzFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'datetime_tz';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'   => 'string',
            'format' => 'date-time',
        ];
    }
}
