<?php

namespace Gm2\Fields\Types;

final class WysiwygFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'wysiwyg';
    }

    public function getSchema(array $settings): array
    {
        return [ 'type' => 'string' ];
    }
}
