<?php

namespace Gm2\Fields\Types;

final class ColorFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'color';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'    => 'string',
            'pattern' => '^#(?:[0-9a-fA-F]{3}){1,2}$',
        ];
    }
}
