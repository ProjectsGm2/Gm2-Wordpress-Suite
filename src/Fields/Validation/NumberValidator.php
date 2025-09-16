<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class NumberValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        return $this->invalid($field, sprintf('%s must be numeric.', $field->getLabel()));
    }
}
