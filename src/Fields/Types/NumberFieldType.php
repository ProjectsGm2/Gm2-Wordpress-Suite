<?php

namespace Gm2\Fields\Types;

final class NumberFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'number';
    }

    public function getSchema(array $settings): array
    {
        $schema = [ 'type' => 'number' ];

        if (isset($settings['minimum']) && is_numeric($settings['minimum'])) {
            $schema['minimum'] = (float) $settings['minimum'];
        }
        if (isset($settings['maximum']) && is_numeric($settings['maximum'])) {
            $schema['maximum'] = (float) $settings['maximum'];
        }
        if (isset($settings['step']) && is_numeric($settings['step'])) {
            $schema['multipleOf'] = max(0.0001, (float) $settings['step']);
        }

        return $schema;
    }
}
