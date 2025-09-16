<?php

namespace Gm2\Fields\Types;

final class RepeaterFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'repeater';
    }

    public function getSchema(array $settings): array
    {
        $schema = $settings['schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        return [
            'type'  => 'array',
            'items' => [ 'type' => 'object' ],
        ];
    }

    public function isSingle(array $settings): bool
    {
        return true;
    }
}
