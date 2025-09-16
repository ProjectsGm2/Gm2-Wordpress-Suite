<?php

namespace Gm2\Fields\Types;

final class ImageFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'image';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'    => 'integer',
            'minimum' => 1,
        ];
    }
}
