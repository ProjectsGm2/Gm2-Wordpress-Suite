<?php

namespace Gm2\Fields\Types;

final class UrlFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'url';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'   => 'string',
            'format' => 'uri',
        ];
    }
}
