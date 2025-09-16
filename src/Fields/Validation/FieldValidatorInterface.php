<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;
use WP_Error;

interface FieldValidatorInterface
{
    /**
     * @param array<string, mixed> $context
     * @return true|WP_Error
     */
    public function validate(FieldDefinition $field, mixed $value, array $context = []);
}
