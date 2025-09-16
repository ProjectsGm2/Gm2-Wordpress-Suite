<?php

namespace Gm2\Fields\Types;

final class SelectFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'select';
    }

    public function getSchema(array $settings): array
    {
        $schema = [ 'type' => 'string' ];
        $options = $settings['options'] ?? [];
        if (is_array($options) && $options !== []) {
            $enum = [];
            foreach ($options as $value => $_label) {
                if (is_int($value)) {
                    $value = (string) $value;
                }
                if (is_string($value)) {
                    $enum[] = $value;
                }
            }
            if ($enum !== []) {
                $schema['enum'] = array_values(array_unique($enum));
            }
        }

        return $schema;
    }
}
