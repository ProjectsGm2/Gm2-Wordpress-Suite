<?php

namespace Gm2\Fields\Types;

final class TelFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'tel';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'    => 'string',
            'pattern' => '^[0-9+()\\s.-]+$',
        ];
    }
}
