<?php

namespace Gm2\Fields\Types;

final class FileFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'file';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'    => 'integer',
            'minimum' => 1,
        ];
    }
}
