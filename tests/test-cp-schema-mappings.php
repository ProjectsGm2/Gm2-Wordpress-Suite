<?php
use Gm2\Gm2_CP_Schema;

class CPSchemaMappingsTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_cp_schema_map');
        foreach (['business', 'event', 'listing', 'job', 'course'] as $pt) {
            if (post_type_exists($pt)) {
                unregister_post_type($pt);
            }
        }
        remove_filter('gm2_seo_cp_schema', '__return_true');
        parent::tearDown();
    }

    public function test_local_business_schema_outputs_mapped_values() {
        register_post_type('business');
        update_option('gm2_cp_schema_map', [
            'business' => [
                'type' => 'LocalBusiness',
                'map'  => [
                    'name' => 'business_name',
                    'address.streetAddress' => 'street',
                    'address.addressLocality' => 'city',
                ],
            ],
        ]);
        $post_id = self::factory()->post->create([
            'post_type' => 'business',
            'post_title' => 'Biz',
        ]);
        update_post_meta($post_id, 'business_name', 'Acme Co');
        update_post_meta($post_id, 'street', '123 Main');
        update_post_meta($post_id, 'city', 'Metropolis');
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertIsArray($data);
        $this->assertSame('LocalBusiness', $data['@type']);
        $this->assertSame('Acme Co', $data['name']);
        $this->assertSame('123 Main', $data['address']['streetAddress']);
        $this->assertSame('Metropolis', $data['address']['addressLocality']);
    }

    public function test_event_archive_outputs_item_list() {
        register_post_type('event', ['has_archive' => true]);
        update_option('gm2_cp_schema_map', [
            'event' => [
                'type' => 'Event',
                'map'  => [
                    'name'      => 'event_name',
                    'startDate' => 'start',
                    'endDate'   => 'end',
                ],
            ],
        ]);
        $ids = [];
        $ids[] = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'First Event',
        ]);
        $ids[] = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'Second Event',
        ]);
        update_post_meta($ids[0], 'event_name', 'Alpha');
        update_post_meta($ids[0], 'start', '2024-01-01');
        update_post_meta($ids[0], 'end', '2024-01-02');
        update_post_meta($ids[1], 'event_name', 'Beta');
        update_post_meta($ids[1], 'start', '2024-02-01');
        update_post_meta($ids[1], 'end', '2024-02-02');
        $this->go_to('/?post_type=event');
        ob_start();
        Gm2_CP_Schema::archive_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertIsArray($data);
        $this->assertSame('ItemList', $data['@type']);
        $this->assertCount(2, $data['itemListElement']);
        $this->assertSame('Alpha', $data['itemListElement'][0]['item']['name']);
        $this->assertSame('Event', $data['itemListElement'][0]['item']['@type']);
    }

    public function test_schema_disabled_via_filter_prevents_duplicates() {
        register_post_type('business');
        update_option('gm2_cp_schema_map', [
            'business' => [
                'type' => 'LocalBusiness',
                'map'  => [ 'name' => 'business_name' ],
            ],
        ]);
        $post_id = self::factory()->post->create([
            'post_type' => 'business',
            'post_title' => 'Biz',
        ]);
        update_post_meta($post_id, 'business_name', 'Solo');
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_filter('gm2_seo_cp_schema', '__return_true', 10, 3);
        ob_start();
        echo '<script type="application/ld+json">{"@type":"WebPage"}</script>';
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();
        $this->assertSame(1, substr_count($output, '<script type="application/ld+json">'));
        $this->assertStringNotContainsString('LocalBusiness', $output);
    }

    public function test_real_estate_listing_schema_includes_address_and_offer() {
        register_post_type('listing');
        update_option('gm2_cp_schema_map', [
            'listing' => [
                'type' => 'RealEstateListing',
                'map'  => [
                    'name' => 'listing_name',
                    'description' => 'listing_description',
                    'url' => 'listing_url',
                    'address.streetAddress' => 'listing_street',
                    'address.addressLocality' => 'listing_city',
                    'address.addressRegion' => 'listing_state',
                    'address.postalCode' => 'listing_postal',
                    'offers.price' => 'listing_price',
                    'offers.priceCurrency' => 'listing_currency',
                ],
            ],
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Luxury Condo',
        ]);

        update_post_meta($post_id, 'listing_name', 'Luxury Condo Downtown');
        update_post_meta($post_id, 'listing_description', 'Spacious condo with city views.');
        update_post_meta($post_id, 'listing_url', 'https://example.com/listings/luxury-condo');
        update_post_meta($post_id, 'listing_street', '100 Market Street');
        update_post_meta($post_id, 'listing_city', 'Metropolis');
        update_post_meta($post_id, 'listing_state', 'NY');
        update_post_meta($post_id, 'listing_postal', '10101');
        update_post_meta($post_id, 'listing_price', '750000');
        update_post_meta($post_id, 'listing_currency', 'USD');

        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        ob_start();
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('RealEstateListing', $data['@type']);
        $this->assertSame('Luxury Condo Downtown', $data['name']);
        $this->assertSame('Spacious condo with city views.', $data['description']);
        $this->assertSame('https://example.com/listings/luxury-condo', $data['url']);
        $this->assertSame('PostalAddress', $data['address']['@type']);
        $this->assertSame('100 Market Street', $data['address']['streetAddress']);
        $this->assertSame('Metropolis', $data['address']['addressLocality']);
        $this->assertSame('NY', $data['address']['addressRegion']);
        $this->assertSame('10101', $data['address']['postalCode']);
        $this->assertSame('Offer', $data['offers']['@type']);
        $this->assertSame('750000', $data['offers']['price']);
        $this->assertSame('USD', $data['offers']['priceCurrency']);

        wp_reset_postdata();
    }

    public function test_job_posting_schema_includes_nested_organization_location_and_salary() {
        register_post_type('job');
        update_option('gm2_cp_schema_map', [
            'job' => [
                'type' => 'JobPosting',
                'map'  => [
                    'title' => 'job_title',
                    'description' => 'job_description',
                    'datePosted' => 'job_date_posted',
                    'employmentType' => 'job_employment_type',
                    'hiringOrganization.name' => 'job_company',
                    'jobLocation.address.streetAddress' => 'job_street',
                    'jobLocation.address.addressLocality' => 'job_city',
                    'baseSalary.value' => 'job_salary_value',
                    'baseSalary.currency' => 'job_salary_currency',
                ],
            ],
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'job',
            'post_title' => 'Software Engineer',
        ]);

        update_post_meta($post_id, 'job_title', 'Software Engineer');
        update_post_meta($post_id, 'job_description', 'Build and maintain web applications.');
        update_post_meta($post_id, 'job_date_posted', '2024-03-01');
        update_post_meta($post_id, 'job_employment_type', 'Full-time');
        update_post_meta($post_id, 'job_company', 'Example Corp');
        update_post_meta($post_id, 'job_street', '123 Code Road');
        update_post_meta($post_id, 'job_city', 'Springfield');
        update_post_meta($post_id, 'job_salary_value', '120000');
        update_post_meta($post_id, 'job_salary_currency', 'USD');

        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        ob_start();
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('JobPosting', $data['@type']);
        $this->assertSame('Software Engineer', $data['title']);
        $this->assertSame('Build and maintain web applications.', $data['description']);
        $this->assertSame('2024-03-01', $data['datePosted']);
        $this->assertSame('Full-time', $data['employmentType']);
        $this->assertSame('Organization', $data['hiringOrganization']['@type']);
        $this->assertSame('Example Corp', $data['hiringOrganization']['name']);
        $this->assertSame('Place', $data['jobLocation']['@type']);
        $this->assertSame('PostalAddress', $data['jobLocation']['address']['@type']);
        $this->assertSame('123 Code Road', $data['jobLocation']['address']['streetAddress']);
        $this->assertSame('Springfield', $data['jobLocation']['address']['addressLocality']);
        $this->assertSame('MonetaryAmount', $data['baseSalary']['@type']);
        $this->assertSame('120000', $data['baseSalary']['value']);
        $this->assertSame('USD', $data['baseSalary']['currency']);

        wp_reset_postdata();
    }

    public function test_course_schema_includes_course_instance_with_location() {
        register_post_type('course');
        update_option('gm2_cp_schema_map', [
            'course' => [
                'type' => 'Course',
                'map'  => [
                    'provider.name' => 'provider',
                    'provider.alternateName' => 'organization_name',
                    'provider.department' => 'department',
                    'provider.url' => 'website',
                    'provider.email' => 'contact_email',
                    'provider.telephone' => 'contact_phone',
                    'courseCode' => 'course_code',
                    'url' => 'course_url',
                    'coursePrerequisites' => 'prerequisites',
                    'timeRequired' => 'duration_iso',
                    'courseInstance.courseMode' => 'modality',
                    'courseInstance.eventAttendanceMode' => 'modality',
                    'courseInstance.startDate' => 'start_date_time',
                    'courseInstance.endDate' => 'end_date_time',
                    'courseInstance.duration' => 'duration_iso',
                    'courseInstance.location.name' => 'provider',
                    'courseInstance.location.url' => 'website',
                    'courseInstance.location.email' => 'contact_email',
                    'courseInstance.location.telephone' => 'contact_phone',
                    'courseInstance.location.timeZone' => 'start_time_zone',
                    'courseInstance.offers.price' => 'amount',
                    'courseInstance.offers.priceCurrency' => 'currency',
                    'courseInstance.offers.url' => 'course_url',
                ],
            ],
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'course',
            'post_title' => 'Intro to Robotics',
        ]);

        update_post_meta($post_id, 'provider', 'Robotics Academy');
        update_post_meta($post_id, 'organization_name', 'Robotics Academy Institute');
        update_post_meta($post_id, 'department', 'School of Engineering');
        update_post_meta($post_id, 'website', 'https://example.edu/robotics');
        update_post_meta($post_id, 'contact_email', 'learn@example.edu');
        update_post_meta($post_id, 'contact_phone', '(555) 123-4567');
        update_post_meta($post_id, 'course_code', 'ROB101');
        update_post_meta($post_id, 'course_url', 'https://example.edu/courses/robotics-101');
        update_post_meta($post_id, 'prerequisites', 'Familiarity with algebra and basic programming.');
        update_post_meta($post_id, 'duration_iso', 'P6W');
        update_post_meta($post_id, 'modality', 'hybrid');
        update_post_meta($post_id, 'start_date_time', '2024-09-01T09:00:00-05:00');
        update_post_meta($post_id, 'end_date_time', '2024-10-13T17:00:00-05:00');
        update_post_meta($post_id, 'start_time_zone', 'America/Chicago');
        update_post_meta($post_id, 'amount', '1999.99');
        update_post_meta($post_id, 'currency', 'USD');

        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        ob_start();
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Course', $data['@type']);
        $this->assertSame('ROB101', $data['courseCode']);
        $this->assertSame('https://example.edu/courses/robotics-101', $data['url']);
        $this->assertSame('Familiarity with algebra and basic programming.', $data['coursePrerequisites']);
        $this->assertSame('P6W', $data['timeRequired']);

        $this->assertSame('Organization', $data['provider']['@type']);
        $this->assertSame('Robotics Academy', $data['provider']['name']);
        $this->assertSame('Robotics Academy Institute', $data['provider']['alternateName']);
        $this->assertSame('School of Engineering', $data['provider']['department']);
        $this->assertSame('https://example.edu/robotics', $data['provider']['url']);
        $this->assertSame('learn@example.edu', $data['provider']['email']);
        $this->assertSame('(555) 123-4567', $data['provider']['telephone']);

        $instance = $data['courseInstance'];
        $this->assertSame('CourseInstance', $instance['@type']);
        $this->assertSame('hybrid', $instance['courseMode']);
        $this->assertSame('https://schema.org/MixedEventAttendanceMode', $instance['eventAttendanceMode']);
        $this->assertSame('2024-09-01T09:00:00-05:00', $instance['startDate']);
        $this->assertSame('2024-10-13T17:00:00-05:00', $instance['endDate']);
        $this->assertSame('P6W', $instance['duration']);

        $location = $instance['location'];
        $this->assertSame('Place', $location['@type']);
        $this->assertSame('Robotics Academy', $location['name']);
        $this->assertSame('https://example.edu/robotics', $location['url']);
        $this->assertSame('learn@example.edu', $location['email']);
        $this->assertSame('(555) 123-4567', $location['telephone']);
        $this->assertSame('America/Chicago', $location['timeZone']);

        $offers = $instance['offers'];
        $this->assertSame('Offer', $offers['@type']);
        $this->assertSame('1999.99', $offers['price']);
        $this->assertSame('USD', $offers['priceCurrency']);
        $this->assertSame('https://example.edu/courses/robotics-101', $offers['url']);

        wp_reset_postdata();
    }
}
