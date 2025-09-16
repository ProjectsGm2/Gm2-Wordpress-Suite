<?php

namespace Gm2\Fields\Types;

final class GroupFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'group';
    }

    public function getSchema(array $settings): array
    {
        $schema = $settings['schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        return [
            'type'                 => 'object',
            'additionalProperties' => true,
        ];
    }
}
