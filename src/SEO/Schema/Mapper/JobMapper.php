<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class JobMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'date_posted'     => __('Date posted', 'gm2-wordpress-suite'),
        'employment_type' => __('Employment type', 'gm2-wordpress-suite'),
        'company'         => __('Company name', 'gm2-wordpress-suite'),
    ];

    public function __construct()
    {
        parent::__construct(
            'job',
            'JobPosting',
            'gm2_schema_job',
            __('Job Posting', 'gm2-wordpress-suite')
        );
    }

    protected function mapData(WP_Post $post): array
    {
        $locationType   = $this->normalizeJobLocationType($this->getField($post, 'job_location_type'));
        $employmentType = $this->normalizeEmploymentType($this->getField($post, 'employment_type'));
        $applyUrlValue  = $this->getField($post, 'apply_url');
        $applyUrl       = is_string($applyUrlValue) && $applyUrlValue !== '' ? esc_url_raw($applyUrlValue) : null;

        return [
            '@id'                           => trailingslashit(get_permalink($post)) . '#job',
            'title'                         => get_the_title($post),
            'description'                   => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'datePosted'                    => $this->getField($post, 'date_posted'),
            'validThrough'                  => $this->getField($post, 'valid_through'),
            'employmentType'                => $employmentType,
            'jobLocationType'               => $locationType,
            'hiringOrganization'            => $this->buildHiringOrganization($post),
            'jobLocation'                   => $this->buildJobLocation($post, $locationType),
            'baseSalary'                    => $this->buildSalary($post),
            'offers'                        => $this->buildOffers($post, $applyUrl),
            'jobBenefits'                   => $this->sanitizeMultiline($this->getField($post, 'job_benefits')),
            'educationRequirements'         => $this->sanitizeText($this->getField($post, 'education_level')),
            'experienceRequirements'        => $this->sanitizeMultiline($this->getField($post, 'experience_requirements')),
            'applicationContact'            => $this->buildApplicationContact($post, $applyUrl),
            'industry'                      => $this->sanitizeText($this->getField($post, 'industry')),
            'applicantLocationRequirements' => $this->buildApplicantLocation($post),
            'directApply'                   => $this->normalizeBool($this->getField($post, 'direct_apply')),
            'url'                           => $applyUrl ?: esc_url_raw(get_permalink($post)),
        ];
    }

    private function buildJobLocation(WP_Post $post, ?string $locationType): array
    {
        if ($locationType === 'Remote') {
            return [];
        }

        $place = [
            '@type'  => 'Place',
            'name'   => $this->sanitizeText($this->getField($post, 'job_location_name')),
            'address'=> $this->buildAddress([
                'streetAddress'   => $this->getField($post, 'job_street'),
                'addressLocality' => $this->getField($post, 'job_city'),
                'addressRegion'   => $this->getField($post, 'job_region'),
                'postalCode'      => $this->getField($post, 'job_postal_code'),
                'addressCountry'  => $this->getField($post, 'job_country'),
            ]),
            'geo'    => $this->buildGeo(
                $this->toFloat($this->getField($post, 'job_latitude')),
                $this->toFloat($this->getField($post, 'job_longitude'))
            ),
        ];

        return $this->filterEmpty($place);
    }

    private function buildHiringOrganization(WP_Post $post): array
    {
        $value       = $this->getField($post, 'company');
        $companyPost = null;

        $companyId = $this->extractCompanyId($value);
        if ($companyId !== null) {
            $candidate = get_post($companyId);
            if ($candidate instanceof WP_Post) {
                $companyPost = $candidate;
            }
        }

        $sameAs    = $this->normalizeSameAs($this->getField($post, 'company_same_as', []));
        $fallback  = $this->getField($post, 'company_url');
        $fallback  = is_string($fallback) && $fallback !== '' ? esc_url_raw($fallback) : null;

        if ($companyPost instanceof WP_Post) {
            $url = get_permalink($companyPost);

            return $this->filterEmpty([
                '@type'       => 'Organization',
                'name'        => $this->sanitizeText($companyPost->post_title),
                'url'         => $url ? esc_url_raw($url) : $fallback,
                'description' => $this->sanitizeText($companyPost->post_excerpt ?: $companyPost->post_content),
                'sameAs'      => $sameAs,
            ]);
        }

        if (is_string($value) && trim($value) !== '') {
            return $this->filterEmpty([
                '@type'  => 'Organization',
                'name'   => $this->sanitizeText($value),
                'url'    => $fallback,
                'sameAs' => $sameAs,
            ]);
        }

        return [];
    }

    private function buildSalary(WP_Post $post): array
    {
        $currency = $this->getField($post, 'salary_currency');
        $value    = $this->toFloat($this->getField($post, 'salary_value'));
        $min      = $this->toFloat($this->getField($post, 'salary_min'));
        $max      = $this->toFloat($this->getField($post, 'salary_max'));
        $unit     = $this->getField($post, 'salary_unit_text');

        if ($value === null) {
            $value = $min ?? $max;
        }

        if ($value === null && $min === null && $max === null && $this->isEmpty($currency)) {
            return [];
        }

        $quantitative = $this->filterEmpty([
            '@type'    => 'QuantitativeValue',
            'value'    => $value,
            'minValue' => $min,
            'maxValue' => $max,
            'unitText' => $unit ?: null,
        ]);

        return $this->filterEmpty([
            '@type'    => 'MonetaryAmount',
            'currency' => $currency ?: null,
            'value'    => $quantitative,
        ]);
    }

    private function buildOffers(WP_Post $post, ?string $applyUrl): array
    {
        $currency = $this->getField($post, 'salary_currency');
        $value    = $this->toFloat($this->getField($post, 'salary_value'));
        $min      = $this->toFloat($this->getField($post, 'salary_min'));
        $max      = $this->toFloat($this->getField($post, 'salary_max'));
        $unit     = $this->getField($post, 'salary_unit_text');

        if ($value === null) {
            $value = $min ?? $max;
        }

        if ($value === null && $this->isEmpty($currency)) {
            return [];
        }

        $specification = $this->filterEmpty([
            '@type'         => 'PriceSpecification',
            'priceCurrency' => $currency ?: null,
            'price'         => $value,
            'minPrice'      => $min,
            'maxPrice'      => $max,
            'unitText'      => $unit ?: null,
        ]);

        $offer = $this->filterEmpty([
            '@type'             => 'Offer',
            'priceCurrency'     => $currency ?: null,
            'price'             => $value,
            'priceSpecification'=> $specification,
            'url'               => $applyUrl,
        ]);

        return $offer ? [ $offer ] : [];
    }

    private function buildApplicationContact(WP_Post $post, ?string $applyUrl): array
    {
        $email = $this->getField($post, 'apply_email');
        $email = is_string($email) ? sanitize_email($email) : null;
        if ($email === '') {
            $email = null;
        }

        $url = $this->getField($post, 'apply_url');
        $url = is_string($url) && $url !== '' ? esc_url_raw($url) : $applyUrl;

        if ($email === null && $url === null) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'ContactPoint',
            'email' => $email,
            'url'   => $url,
        ]);
    }

    private function buildApplicantLocation(WP_Post $post): array
    {
        $allowed = $this->getField($post, 'applicant_region');
        if ($this->isEmpty($allowed)) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'Country',
            'name'  => $this->sanitizeText((string) $allowed),
        ]);
    }

    private function sanitizeMultiline(mixed $value): string
    {
        if ($this->isEmpty($value)) {
            return '';
        }

        if (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        return trim(sanitize_textarea_field($value));
    }

    private function normalizeEmploymentType(mixed $value): array|string|null
    {
        if (is_array($value)) {
            $types = array_values(array_filter(array_map(
                static fn($item) => is_string($item) || is_numeric($item) ? sanitize_text_field((string) $item) : null,
                $value
            )));

            if ($types === []) {
                return null;
            }

            return count($types) === 1 ? $types[0] : $types;
        }

        if (is_string($value) || is_numeric($value)) {
            $type = sanitize_text_field((string) $value);
            return $type !== '' ? $type : null;
        }

        return null;
    }

    private function normalizeJobLocationType(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value   = trim($value);
        $allowed = ['OnSite', 'Hybrid', 'Remote'];

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function extractCompanyId(mixed $value): ?int
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof WP_Post) {
                    $item = $item->ID;
                } elseif (is_array($item) && isset($item['ID']) && is_numeric($item['ID'])) {
                    $item = $item['ID'];
                }

                if (is_numeric($item)) {
                    $id = (int) $item;
                    if ($id > 0) {
                        return $id;
                    }
                }
            }

            return null;
        }

        if ($value instanceof WP_Post) {
            $id = (int) $value->ID;
            return $id > 0 ? $id : null;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function normalizeSameAs(mixed $value): array
    {
        if ($this->isEmpty($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = preg_split('/[\r\n]+/', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $links = [];
        foreach ($value as $link) {
            if (!is_string($link)) {
                continue;
            }

            $link = trim($link);
            if ($link === '') {
                continue;
            }

            $links[] = esc_url_raw($link);
        }

        return array_values(array_unique(array_filter($links)));
    }

    private function normalizeBool(mixed $value): ?bool
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) (int) $value;
        }

        $value = strtolower(trim((string) $value));
        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }
}
