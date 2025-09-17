<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class JobMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'date_posted'      => __('Date posted', 'gm2-wordpress-suite'),
        'employment_type'  => __('Employment type', 'gm2-wordpress-suite'),
        'company'          => __('Company name', 'gm2-wordpress-suite'),
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
        $location = $this->buildJobLocation($post);
        $salary   = $this->buildSalary($post);
        $hiring   = $this->buildHiringOrganization($post);

        $applyUrl = $this->getField($post, 'apply_url');

        return [
            '@id'             => trailingslashit(get_permalink($post)) . '#job',
            'title'           => get_the_title($post),
            'description'     => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'datePosted'      => $this->getField($post, 'date_posted'),
            'employmentType'  => $this->sanitizeText($this->getField($post, 'employment_type')),
            'hiringOrganization' => $hiring,
            'jobLocation'     => $location,
            'baseSalary'      => $salary,
            'industry'        => $this->getField($post, 'industry'),
            'validThrough'    => $this->getField($post, 'valid_through'),
            'applicantLocationRequirements' => $this->buildApplicantLocation($post),
            'directApply'     => $this->normalizeBool($this->getField($post, 'direct_apply')),
            'url'             => $applyUrl ? esc_url_raw((string) $applyUrl) : esc_url_raw(get_permalink($post)),
        ];
    }

    private function buildJobLocation(WP_Post $post): array
    {
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
        ];

        $place = $this->filterEmpty($place);
        if (empty($place)) {
            return [];
        }

        return $place;
    }

    private function buildHiringOrganization(WP_Post $post): array
    {
        $name = $this->getField($post, 'company');
        if ($this->isEmpty($name)) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'Organization',
            'name'  => $this->sanitizeText((string) $name),
            'sameAs'=> $this->normalizeSameAs($this->getField($post, 'company_same_as', [])),
            'url'   => esc_url_raw((string) $this->getField($post, 'company_url')),
        ]);
    }

    private function buildSalary(WP_Post $post): array
    {
        $currency = $this->getField($post, 'salary_currency');
        $value    = $this->toFloat($this->getField($post, 'salary_value'));
        $min      = $this->toFloat($this->getField($post, 'salary_min'));
        $max      = $this->toFloat($this->getField($post, 'salary_max'));
        $unit     = $this->getField($post, 'salary_unit_text');

        if ($value === null && $min === null && $max === null && $this->isEmpty($currency)) {
            return [];
        }

        $quantitative = $this->filterEmpty([
            '@type'     => 'QuantitativeValue',
            'value'     => $value,
            'minValue'  => $min,
            'maxValue'  => $max,
            'unitText'  => $unit ?: null,
        ]);

        return $this->filterEmpty([
            '@type'     => 'MonetaryAmount',
            'currency'  => $currency ?: null,
            'value'     => $quantitative,
        ]);
    }

    private function buildApplicantLocation(WP_Post $post): array
    {
        $allowed = $this->getField($post, 'applicant_region');
        if ($this->isEmpty($allowed)) {
            return [];
        }

        return $this->filterEmpty([
            '@type'          => 'Country',
            'name'           => $this->sanitizeText((string) $allowed),
        ]);
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
