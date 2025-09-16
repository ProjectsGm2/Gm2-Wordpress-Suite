<?php

namespace Gm2\Fields\Storage;

use Gm2\Fields\FieldDefinition;

final class MetaRegistrar
{
    public function registerPostField(string $postType, FieldDefinition $field, callable $sanitize, callable $validate): void
    {
        register_post_meta(
            $postType,
            $field->getKey(),
            $this->buildArgs($field, $sanitize, $validate, 'post', $postType)
        );
    }

    public function registerTermField(string $taxonomy, FieldDefinition $field, callable $sanitize, callable $validate): void
    {
        register_term_meta(
            $taxonomy,
            $field->getKey(),
            $this->buildArgs($field, $sanitize, $validate, 'term', $taxonomy)
        );
    }

    public function registerUserField(FieldDefinition $field, callable $sanitize, callable $validate): void
    {
        register_meta(
            'user',
            $field->getKey(),
            $this->buildArgs($field, $sanitize, $validate, 'user', null)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArgs(FieldDefinition $field, callable $sanitize, callable $validate, string $objectType, ?string $subType): array
    {
        $schema     = $field->getType()->getSchema($field->getSettings());
        $baseRest   = $field->getType()->getRestSettings($field->getSettings());
        $customRest = $field->getRestSettings();
        $showInRest = array_replace_recursive($baseRest, $customRest);

        if (!isset($showInRest['schema'])) {
            $showInRest['schema'] = $schema;
        }

        $args = [
            'type'              => $schema['type'] ?? 'string',
            'single'            => $field->getType()->isSingle($field->getSettings()),
            'sanitize_callback' => $sanitize,
            'validate_callback' => $validate,
            'auth_callback'     => static fn () => true,
            'default'           => $field->getDefault(),
            'show_in_rest'      => $showInRest,
        ];

        if ($field->getDescription() !== null) {
            $args['description'] = $field->getDescription();
        }

        if ($objectType === 'post' || $objectType === 'term') {
            $args['object_subtype'] = $subType;
        }

        return $args;
    }
}
