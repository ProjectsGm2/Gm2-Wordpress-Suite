<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Post;

class CourseMapper extends AbstractMapper
{
    protected array $requiredFields = [
        'provider'    => __('Provider', 'gm2-wordpress-suite'),
        'course_code' => __('Course code', 'gm2-wordpress-suite'),
    ];

    public function __construct()
    {
        parent::__construct(
            'course',
            'Course',
            'gm2_schema_course',
            __('Course', 'gm2-wordpress-suite')
        );
    }

    protected function mapData(WP_Post $post): array
    {
        $provider = $this->filterEmpty([
            '@type' => 'Organization',
            'name'  => $this->sanitizeText($this->getField($post, 'provider')),
            'sameAs'=> $this->normalizeSameAs($this->getField($post, 'provider_same_as', [])),
            'url'   => esc_url_raw((string) $this->getField($post, 'provider_url')),
        ]);

        $courseUrl = $this->getField($post, 'course_url');

        return [
            '@id'           => trailingslashit(get_permalink($post)) . '#course',
            'name'          => get_the_title($post),
            'description'   => $this->sanitizeText($post->post_excerpt ?: $post->post_content),
            'provider'      => $provider,
            'courseCode'    => $this->sanitizeText($this->getField($post, 'course_code')),
            'url'           => $courseUrl ? esc_url_raw((string) $courseUrl) : esc_url_raw(get_permalink($post)),
            'inLanguage'    => $this->getField($post, 'course_language'),
            'educationalLevel' => $this->getField($post, 'course_level'),
            'coursePrerequisites' => $this->getField($post, 'course_prerequisites'),
            'courseInstance' => $this->buildCourseInstance($post),
        ];
    }

    private function buildCourseInstance(WP_Post $post): array
    {
        $name  = $this->getField($post, 'course_instance_name');
        $start = $this->getField($post, 'course_instance_start');
        $end   = $this->getField($post, 'course_instance_end');
        $url   = $this->getField($post, 'course_instance_url');

        if ($this->isEmpty($name) && $this->isEmpty($start) && $this->isEmpty($end) && $this->isEmpty($url)) {
            return [];
        }

        $location = [
            '@type'   => 'Place',
            'name'    => $this->sanitizeText($this->getField($post, 'course_location_name')),
            'address' => $this->buildAddress([
                'streetAddress'   => $this->getField($post, 'course_location_address'),
                'addressLocality' => $this->getField($post, 'course_location_city'),
                'addressRegion'   => $this->getField($post, 'course_location_region'),
                'postalCode'      => $this->getField($post, 'course_location_postal_code'),
                'addressCountry'  => $this->getField($post, 'course_location_country'),
            ]),
        ];

        $location = $this->filterEmpty($location);

        return $this->filterEmpty([
            '@type'     => 'CourseInstance',
            'name'      => $this->sanitizeText((string) $name),
            'startDate' => $start ?: null,
            'endDate'   => $end ?: null,
            'location'  => $location,
            'url'       => $url ? esc_url_raw((string) $url) : null,
            'courseMode'=> $this->getField($post, 'course_mode'),
            'instructor'=> $this->buildInstructor($post),
        ]);
    }

    private function buildInstructor(WP_Post $post): array
    {
        $name = $this->getField($post, 'course_instructor');
        if ($this->isEmpty($name)) {
            return [];
        }

        return $this->filterEmpty([
            '@type' => 'Person',
            'name'  => $this->sanitizeText((string) $name),
            'url'   => esc_url_raw((string) $this->getField($post, 'course_instructor_url')),
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
}
