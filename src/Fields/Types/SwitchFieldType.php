<?php

namespace Gm2\Fields\Types;

final class SwitchFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'switch';
    }

    public function getSchema(array $settings): array
    {
        return [ 'type' => 'boolean' ];
    }
}
