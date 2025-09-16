<?php

namespace Gm2\Fields\Types;

final class GeopointFieldType extends AbstractFieldType
{
    public function getName(): string
    {
        return 'geopoint';
    }

    public function getSchema(array $settings): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'lat' => [ 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ],
                'lng' => [ 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ],
            ],
            'required'   => [ 'lat', 'lng' ],
        ];
    }
}
