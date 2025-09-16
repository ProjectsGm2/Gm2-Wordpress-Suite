<?php

namespace Gm2\Fields\Types;

interface FieldTypeInterface
{
    public function getName(): string;

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function getSchema(array $settings): array;

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function getRestSettings(array $settings): array;

    /**
     * @param array<string, mixed> $settings
     */
    public function isSingle(array $settings): bool;
}
