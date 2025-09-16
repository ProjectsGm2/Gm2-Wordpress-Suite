import { test, expect } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = 'Basic ' + Buffer.from(process.env.WP_AUTH || 'admin:password').toString('base64');

const headers = {
  Authorization: AUTH,
  'Content-Type': 'application/json',
};

test('gm2 widget preview renders field, map and hours', async ({ request }) => {
  const fieldGroups = {
    test_details: {
      title: 'Test Details',
      contexts: { post: ['post'] },
      fields: {
        blurb: { type: 'text', label: 'Blurb' },
        map: { type: 'geopoint', label: 'Map' },
        hours: { type: 'repeater', label: 'Hours' },
      },
    },
  };

  const fieldResponse = await request.post(
    `${BASE_URL}/wp-json/gm2-test/v1/widget-preview`,
    {
      headers,
      data: {
        field_groups: fieldGroups,
        widget: 'gm2_field',
        settings: {
          post_type: 'post',
          field_key: 'test_details::blurb',
          fallback: 'Missing',
          html_tag: 'p',
        },
        post: {
          post_type: 'post',
          meta: { blurb: 'Sanitized <strong>text</strong>' },
        },
      },
    }
  );
  expect(fieldResponse.ok()).toBeTruthy();
  const fieldJson = await fieldResponse.json();
  expect(fieldJson.html).toContain('Sanitized text');

  const mapResponse = await request.post(
    `${BASE_URL}/wp-json/gm2-test/v1/widget-preview`,
    {
      headers,
      data: {
        widget: 'gm2_map',
        settings: {
          post_type: 'post',
          field_key: 'test_details::map',
          display: 'link',
          provider_url: 'https://maps.example.com/?lat={{lat}}&lng={{lng}}',
          link_text: 'Map link',
        },
        post: {
          post_type: 'post',
          meta: { map: { lat: 51.5, lng: -0.1 } },
        },
      },
    }
  );
  expect(mapResponse.ok()).toBeTruthy();
  const mapJson = await mapResponse.json();
  expect(mapJson.html).toContain('maps.example.com');
  expect(mapJson.html).toContain('Map link');

  const hoursResponse = await request.post(
    `${BASE_URL}/wp-json/gm2-test/v1/widget-preview`,
    {
      headers,
      data: {
        widget: 'gm2_opening_hours',
        settings: {
          post_type: 'post',
          field_key: 'test_details::hours',
          closed_label: 'Closed',
        },
        post: {
          post_type: 'post',
          meta: {
            hours: [
              { day: 'Monday', start: '08:00', end: '17:30' },
              { day: 'Tuesday', status: 'closed' },
            ],
          },
        },
      },
    }
  );
  expect(hoursResponse.ok()).toBeTruthy();
  const hoursJson = await hoursResponse.json();
  expect(hoursJson.html).toContain('Monday');
  expect(hoursJson.html).toMatch(/8:00\s*(am|AM)/);
  expect(hoursJson.html).toContain('Closed');
});
