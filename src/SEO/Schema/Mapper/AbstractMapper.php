<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Error;
use WP_Post;

abstract class AbstractMapper implements MapperInterface
{
    protected array $requiredFields = [];

    public function __construct(
        protected string $postType,
        protected string $schemaType,
        protected string $optionName,
        protected string $label
    ) {
    }

    public function getPostType(): string
    {
        return $this->postType;
    }

    public function getSchemaType(): string
    {
        return $this->schemaType;
    }

    public function getOptionName(): string
    {
        return $this->optionName;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function map(WP_Post $post)
    {
        $missing = $this->missingFields($post);
        if (!empty($missing)) {
            return new WP_Error(
                'gm2_schema_missing_fields',
                sprintf(
                    /* translators: %s: comma separated field labels */
                    __('Missing required schema fields: %s', 'gm2-wordpress-suite'),
                    implode(', ', $missing)
                ),
                [
                    'fields'  => array_keys($missing),
                    'post_id' => $post->ID,
                    'schema'  => $this->schemaType,
                ]
            );
        }

        $data = $this->filterEmpty($this->mapData($post));
        if (!isset($data['@type'])) {
            $data['@type'] = $this->schemaType;
        }

        return $data;
    }

    public function getRequiredFieldMap(): array
    {
        return $this->requiredFields;
    }

    protected function missingFields(WP_Post $post): array
    {
        $missing = [];
        foreach ($this->requiredFields as $key => $label) {
            $value = $this->getField($post, $key);
            if ($this->isEmpty($value)) {
                $missing[$key] = $label;
            }
        }

        return $missing;
    }

    protected function getField(WP_Post $post, string $key, mixed $default = ''): mixed
    {
        return gm2_field($key, $default, $post->ID);
    }

    protected function sanitizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(wp_strip_all_tags($value));
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $filtered = preg_replace('/[^0-9.+-]/', '', $value);
            if ($filtered === '' || $filtered === null) {
                return null;
            }

            return is_numeric($filtered) ? (float) $filtered : null;
        }

        return null;
    }

    protected function toInt(mixed $value): ?int
    {
        $float = $this->toFloat($value);
        if ($float === null) {
            return null;
        }

        return (int) round($float);
    }

    protected function filterEmpty(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterEmpty($value);
                if ($value === []) {
                    unset($data[$key]);
                    continue;
                }

                $data[$key] = $value;
                continue;
            }

            if ($value === null) {
                unset($data[$key]);
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function buildAddress(array $parts): array
    {
        $address = ['@type' => 'PostalAddress'];
        foreach ($parts as $schemaKey => $value) {
            if ($value !== null && $value !== '') {
                $address[$schemaKey] = $value;
            }
        }

        $address = $this->filterEmpty($address);

        if (count($address) === 1 && isset($address['@type'])) {
            return [];
        }

        return $address;
    }

    protected function buildGeo(?float $latitude, ?float $longitude): array
    {
        if ($latitude === null || $longitude === null) {
            return [];
        }

        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];
    }

    protected function hasAny(array $values): bool
    {
        foreach ($values as $value) {
            if (!$this->isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

    protected function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || $value === '';
    }

    abstract protected function mapData(WP_Post $post): array;
}
