<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class GalleryValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_array($value)) {
            return $this->invalid($field, sprintf('%s must be an array of attachment IDs.', $field->getLabel()));
        }

        foreach ($value as $item) {
            if (!is_numeric($item)) {
                return $this->invalid($field, sprintf('%s must only contain attachment IDs.', $field->getLabel()));
            }
        }

        return true;
    }
}
