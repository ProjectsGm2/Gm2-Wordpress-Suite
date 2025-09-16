<?php

namespace Gm2\Fields\Types;

final class TimeFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'time';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'    => 'string',
            'pattern' => '^\\d{2}:\\d{2}(?::\\d{2})?$',
        ];
    }
}
