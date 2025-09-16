<?php

namespace Gm2\Fields\Types;

final class TextFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'text';
    }

    public function getSchema(array $settings): array
    {
        $schema = [ 'type' => 'string' ];

        if (isset($settings['max_length']) && is_int($settings['max_length']) && $settings['max_length'] > 0) {
            $schema['maxLength'] = $settings['max_length'];
        }

        return $schema;
    }
}
