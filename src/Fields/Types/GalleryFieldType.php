<?php

namespace Gm2\Fields\Types;

final class GalleryFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'gallery';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'  => 'array',
            'items' => [
                'type'    => 'integer',
                'minimum' => 1,
            ],
        ];
    }
}
