<?php

namespace Gm2\Fields\Types;

final class CurrencyFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'currency';
    }

    public function getSchema(array $settings): array
    {
        $schema = [
            'type'       => 'number',
            'multipleOf' => 0.01,
        ];

        if (isset($settings['minimum']) && is_numeric($settings['minimum'])) {
            $schema['minimum'] = (float) $settings['minimum'];
        }
        if (isset($settings['maximum']) && is_numeric($settings['maximum'])) {
            $schema['maximum'] = (float) $settings['maximum'];
        }

        return $schema;
    }
}
