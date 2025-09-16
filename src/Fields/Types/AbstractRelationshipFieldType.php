<?php

namespace Gm2\Fields\Types;

abstract class AbstractRelationshipFieldType extends AbstractFieldType
{
    public function getSchema(array $settings): array
    {
        $multiple = (bool) ($settings['multiple'] ?? true);
        if ($multiple) {
            return [
                'type'  => 'array',
                'items' => [ 'type' => 'integer', 'minimum' => 1 ],
            ];
        }

        return [
            'type'    => 'integer',
            'minimum' => 1,
        ];
    }

    public function isSingle(array $settings): bool
    {
        return true;
    }
}
