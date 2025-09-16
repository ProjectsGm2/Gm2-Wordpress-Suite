<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class ComputedValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        return true;
    }
}
