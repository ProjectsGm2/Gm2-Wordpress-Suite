<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class SelectValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_scalar($value)) {
            return $this->invalid($field, sprintf('%s must be a scalar value.', $field->getLabel()));
        }

        $value   = (string) $value;
        $options = $field->getOptions();
        if ($options !== [] && !array_key_exists($value, $options)) {
            return $this->invalid($field, sprintf('%s has an invalid selection.', $field->getLabel()));
        }

        return true;
    }
}
