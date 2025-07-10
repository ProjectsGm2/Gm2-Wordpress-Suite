=== Gm2 WordPress Suite ===
Contributors: gm2team
Tags: admin, tools, suite, performance
Requires at least: 5.6
Tested up to: 6.5
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
A powerful suite of WordPress enhancements including admin tools, frontend optimizations, and ChatGPT-powered content generation.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/gm2-wordpress-suite` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Gm2 Suite menu in the admin sidebar to configure settings. First
   visit **Gm2 → Google OAuth Setup** to enter your client ID and secret, then
   open **Gm2 → ChatGPT** to provide your OpenAI API key and choose a model from
   the dropdown.
   Finally, use **SEO → Connect Google Account** to authorize your Google account. After connecting, you will be able to select your Analytics Measurement ID and Ads Customer ID from dropdown menus.
4. The plugin relies on WordPress's built-in HTTP API and has no external
   dependencies.
5. Follow the steps in the **Google integration** section below to copy your
   Search Console verification code and Google Ads developer token. These values
   cannot be fetched via API and must be entered manually on the SEO settings
   page.
6. Select your Analytics Measurement ID and Ads Customer ID on the
   **SEO → Connect Google Account** page after connecting, or enter them
   manually on the SEO settings screen if needed.

If you plan to distribute or manually upload the plugin, you can create a ZIP
archive with `bash bin/build-plugin.sh`. This command packages the plugin with
all dependencies into `gm2-wordpress-suite.zip` for installation via the
**Plugins → Add New** screen.

== Google integration ==
These credentials must be copied from your Google accounts:

* **Search Console verification code** – Log in to <https://search.google.com/search-console>, open **Settings → Ownership verification**, and choose the *HTML tag* option. Copy the code displayed in the meta tag and paste it into the **Search Console Verification Code** field on the SEO settings page. See <https://support.google.com/webmasters/answer/9008080> for details.
* **Google Ads developer token** – Sign in at <https://ads.google.com/aw/apicenter> and open **Tools & Settings → Setup → API Center** (manager account required). Copy your **Developer token** and enter it into the **Google Ads Developer Token** field. Documentation: <https://developers.google.com/google-ads/api/docs/first-call/dev-token>.
* **Google Ads login customer ID** – Optional manager account ID associated with your developer token. Enter this value if the token belongs to a manager account so requests include the `login-customer-id` header.
* **Google Ads API version** – The plugin uses the API version defined by the `Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION` constant (default `v18`). Update this constant and any tests referencing it when a new Ads API version becomes available.
* **Analytics Admin API version** – The GA4 endpoints use the version specified by `Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION` (default `v1beta`). Update this constant along with related tests when Google releases a new version.

== Troubleshooting ==
If you see errors when connecting your Google account:

* **Missing developer token** – Sign in at <https://ads.google.com/aw/apicenter> and open **Tools & Settings → Setup → API Center** (manager account required). Copy your **Developer token** and enter it in the **Google Ads Developer Token** field on the SEO settings page.
* **No Analytics properties found** or **No Ads accounts found** –
  * Enable the Analytics Admin, Google Analytics (v3) for UA properties, Search Console, and Google Ads APIs for your OAuth client.
  * Confirm the connected Google account can access the required properties and accounts. The OAuth client can belong to a different Google account.
  * Disconnect and reconnect after adjusting permissions.
* **Invalid OAuth state** – Reconnect from **SEO → Connect Google Account** to refresh the authorization flow.
* **Keyword Research returns no results** – Ensure you have entered your Google Ads developer token, connected a Google account with Ads access, and selected a valid Ads customer ID. Missing or invalid credentials will cause the Keyword Planner request to fail.
* "The caller does not have permission" – This usually means your developer token isn't approved for the selected Ads account or the login customer ID is missing or incorrect. Verify the token status in the Google Ads API Center and ensure the OAuth account can access that customer ID.
* **Testing with an unapproved token** – Unapproved developer tokens can be used with [Google Ads test accounts](https://developers.google.com/google-ads/api/docs/best-practices/test-accounts). The login customer ID must be the manager ID for that token, and test accounts don't serve ads and have limited features.

== Breadcrumbs ==
Display a breadcrumb trail anywhere using the `[gm2_breadcrumbs]` shortcode. The output
is an ordered list wrapped in a `<nav>` element with accompanying JSON-LD for search engines.
You can enable automatic breadcrumbs in the footer from **SEO → Schema**.

== Caching ==
Enable HTML, CSS, and JavaScript minification from the SEO &gt; Performance
screen. To enable these options:

1. Navigate to **SEO → Performance** in your WordPress admin area.
2. Check **Minify HTML**, **Minify CSS**, and **Minify JS** as desired.
3. Click **Save Settings**.

For full page caching, hook into the `gm2_set_cache_headers` action
to configure headers or integrate your preferred caching plugin.

== Keyword Research ==
After configuring credentials in **Gm2 → Google OAuth Setup**, connect your Google account from **SEO → Connect Google Account**. The plugin automatically fetches your available Analytics Measurement IDs and Ads Customer IDs so you can select them from dropdown menus. Use the **Keyword Research** tab to generate ideas via the Google Keyword Planner. To fetch keywords you must enter a Google Ads developer token, connect a Google account with Ads access, and select a valid Ads customer ID (without dashes, e.g., `1234567890`). Missing or invalid credentials will result in empty or failed searches. If your developer token belongs to a manager account, provide the optional Login Customer ID so the value is sent with each request.

The Google Ads request also requires a language constant and a geo target constant. These values are configurable on the same screen and default to `languageConstants/1000` (English) and `geoTargetConstants/2840` (United States). If either option is missing or invalid, the keyword search will fail with an error.

== Image Optimization ==
Enter your compression API key and enable the service from the SEO &gt; Performance screen.
When enabled, uploaded images are sent to the API and replaced with the optimized result.

== ChatGPT ==
Configure the integration from **Gm2 → ChatGPT** in your WordPress admin area. Enter your
OpenAI API key and adjust these options:

* **Model** – select the model to use from a dropdown. Options are fetched from
  OpenAI when possible (defaults to `gpt-3.5-turbo`).
* **Temperature** – controls randomness of responses.
* **Max Tokens** – optional limit on the length of completions.
* **API Endpoint** – URL of the chat completions API.

Use the **Test Prompt** box on the same page to send a message and verify your
settings before generating content.

== SEO Guidelines ==
Before researching guidelines, enter your ChatGPT API key on the **Gm2 → ChatGPT**
page. Navigate to **SEO → SEO Guidelines** and click **Research SEO Guidelines**
to generate best practices for your chosen categories. Generated guidelines are
stored under **SEO → SEO Guidelines** so you can review or edit them later.

== AI SEO ==
While editing a post or taxonomy term, open the **AI SEO** tab in the SEO meta
box. Use **AI Research** to request suggestions for titles, descriptions,
keywords and canonical URLs. Select the items you want and click **Implement
Selected** to populate the fields automatically.
The SEO Settings tab also lets you set `max-snippet`, `max-image-preview`, and
`max-video-preview` values that will be added to the robots meta tag.

== Tariff Management ==
Manage percentage-based fees for WooCommerce orders. Open **Gm2 → Tariff** to
add or edit tariffs. Enabled tariffs add a fee to the cart total during
checkout.

== Redirects ==
Create 301 or 302 redirects from the **SEO → Redirects** tab. The plugin logs
the last 100 missing URLs to help you create new redirects.

== Changelog ==
= 1.6.2 =
* Added max-snippet, max-image-preview and max-video-preview options in SEO meta boxes.
= 1.6.1 =
* Improved error guidance for OAuth connection issues.
= 1.6.0 =
* ChatGPT integration with admin settings page.
= 1.5.0 =
* Google Keyword Planner integration for keyword research.
= 1.4.0 =
* Basic HTML/CSS/JS minification options and hooks for caching.
= 1.3.0 =
* Option to auto-fill missing image alt text with product titles.
* Fields for external image compression API keys.
= 1.2.0 =
* Added robots and canonical controls in meta boxes.
* Optional settings to noindex product variants and out-of-stock items.
= 1.1.0 =
* Added Tariff management and WooCommerce checkout integration.
= 1.0.0 =
* Initial release.

== Testing ==
The automated tests use the WordPress test suite and assume `phpunit` is installed globally. See `CONTRIBUTING.md` for setup details.
