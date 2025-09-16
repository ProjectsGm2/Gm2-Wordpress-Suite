<?php

namespace Gm2\Fields\Types;

final class ComputedFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'computed';
    }

    public function getSchema(array $settings): array
    {
        $schema = $settings['schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        $type = $settings['return_type'] ?? 'string';
        if (!in_array($type, [ 'string', 'number', 'integer', 'boolean', 'array', 'object' ], true)) {
            $type = 'string';
        }

        return [ 'type' => $type ];
    }
}
