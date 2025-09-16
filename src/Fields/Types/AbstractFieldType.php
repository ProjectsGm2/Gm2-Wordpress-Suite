<?php

namespace Gm2\Fields\Types;

abstract class AbstractFieldType implements FieldTypeInterface
{
    public function getRestSettings(array $settings): array
    {
        return [
            'schema' => $this->getSchema($settings),
        ];
    }

    public function isSingle(array $settings): bool
    {
        return !($settings['multiple'] ?? false);
    }
}
