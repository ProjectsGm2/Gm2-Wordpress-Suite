<?php

namespace Gm2\Fields\Types;

final class CheckboxFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'checkbox';
    }

    public function getSchema(array $settings): array
    {
        return [ 'type' => 'boolean' ];
    }
}
