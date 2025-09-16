<?php

namespace Gm2\Fields\Types;

final class EmailFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'email';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'   => 'string',
            'format' => 'email',
        ];
    }
}
