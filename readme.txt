=== Gm2 WordPress Suite ===
Contributors: gm2team
Tags: admin, tools, suite, performance
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.6.16
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
A powerful suite of WordPress enhancements including admin tools, frontend optimizations, and ChatGPT-powered content generation.

Key features include:
* SEO tools with breadcrumbs, caching and structured data
* ChatGPT-powered content generation and keyword research
* WooCommerce quantity discounts with a dedicated Elementor widget (requires WooCommerce)
* Tariff management and redirects
* Expanded SEO Context feeds AI prompts with business details

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
5. Ensure the PHP DOM/LibXML extension is installed. Without it, HTML analysis
   and AI research features will be unavailable.
6. Follow the steps in the **Google integration** section below to copy your
   Search Console verification code and Google Ads developer token. These values
   cannot be fetched via API and must be entered manually on the SEO settings
   page.
7. Select your Analytics Measurement ID and Ads Customer ID on the
   **SEO → Connect Google Account** page after connecting, or enter them
   manually on the SEO settings screen if needed.
8. Install and activate WooCommerce (required for Quantity Discounts).
9. Install and activate Elementor to use the Gm2 Qnty Discounts widget on product pages. With Elementor Pro the widget appears in the **WooCommerce** section when editing Single Product templates. Otherwise look under **General**.
10. Open **Gm2 → Quantity Discounts** and create discount groups to define pricing rules.

**Note:** WooCommerce and Elementor Pro must be active. Elementor 3.5+ is recommended and older versions are supported with a fallback.

If you plan to distribute or manually upload the plugin, you can create a ZIP
archive with `bash bin/build-plugin.sh`. This command packages the plugin with
all dependencies into `gm2-wordpress-suite.zip` for installation via the
**Plugins → Add New** screen.

== Feature Toggles ==
The main **Gm2 Suite** page lets administrators enable or disable major modules.
Check or uncheck **Tariff**, **SEO**, **Quantity Discounts**, **Google OAuth Setup**
or **ChatGPT** and click *Save* to hide their menus and functionality. All
features are enabled by default.

== Google integration ==
These credentials must be copied from your Google accounts:

* **Search Console verification code** – Log in to <https://search.google.com/search-console>, open **Settings → Ownership verification**, and choose the *HTML tag* option. Copy the code displayed in the meta tag and paste it into the **Search Console Verification Code** field on the SEO settings page. See <https://support.google.com/webmasters/answer/9008080> for details.
* **Google Ads developer token** – Sign in at <https://ads.google.com/aw/apicenter> and open **Tools & Settings → Setup → API Center** (manager account required). Copy your **Developer token** and enter it into the **Google Ads Developer Token** field. Documentation: <https://developers.google.com/google-ads/api/docs/first-call/dev-token>.
* **Google Ads login customer ID** – Optional manager account ID associated with your developer token. Enter this value if the token belongs to a manager account so requests include the `login-customer-id` header.
* **Google Ads API version** – The plugin uses the API version defined by the `Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION` constant (default `v18`). Update this constant and any tests referencing it when a new Ads API version becomes available.
* **Analytics Admin API version** – The GA4 endpoints use the version specified by `Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION` (default `v1beta`). Update this constant along with related tests when Google releases a new version.
* **Automatic redirect URI registration** – Enter your Google Cloud Project ID and Service Account JSON path on the **Google OAuth Setup** page (or define `GM2_GCLOUD_PROJECT_ID` and `GM2_SERVICE_ACCOUNT_JSON` in `wp-config.php`). The plugin uses these values to add the current site's redirect URI to the OAuth client via the Google Cloud API.

== Troubleshooting ==
If you see errors when connecting your Google account:

* **Missing developer token** – Sign in at <https://ads.google.com/aw/apicenter> and open **Tools & Settings → Setup → API Center** (manager account required). Copy your **Developer token** and enter it in the **Google Ads Developer Token** field on the SEO settings page.
* **No Analytics properties found** or **No Ads accounts found** –
  * Enable the Analytics Admin, Google Analytics (v3) for UA properties, Search Console, and Google Ads APIs for your OAuth client.
  * Confirm the connected Google account can access the required properties and accounts. The OAuth client can belong to a different Google account.
  * Disconnect and reconnect after adjusting permissions.
* **Invalid OAuth state** – Reconnect from **SEO → Connect Google Account** to refresh the authorization flow.
* **Keyword Research returns no results** – Ensure you have entered your Google Ads developer token, connected a Google account with Ads access, and selected a valid Ads customer ID. When these details are missing or the Keyword Planner request fails, the plugin now falls back to ChatGPT and displays a notice that metrics are unavailable.
* **Google Ads API did not return keyword metrics** – The Keyword Planner may return keyword ideas without search volume data. AI Research still analyzes the top results and displays a notice when metrics are missing. Verify the selected Ads account and date range have sufficient data.
* "The caller does not have permission" – This usually means your developer token isn't approved for the selected Ads account or the login customer ID is missing or incorrect. Verify the token status in the Google Ads API Center and ensure the OAuth account can access that customer ID.
* **Testing with an unapproved token** – Unapproved developer tokens can be used with [Google Ads test accounts](https://developers.google.com/google-ads/api/docs/best-practices/test-accounts). The login customer ID must be the manager ID for that token, and test accounts don't serve ads and have limited features.
* **Viewing debug logs** – Add `define('WP_DEBUG', true);` and `define('WP_DEBUG_LOG', true);` to your `wp-config.php` file, then check `wp-content/debug.log` for errors.
* **Check DOM extension** – Run `php -m | grep -i dom` to verify the DOM/LibXML extension is loaded. The plugin requires this for HTML analysis.
* **Gm2 Qnty Discounts widget missing** – Ensure Elementor is active and loads before this plugin, or deactivate and reactivate plugins so Elementor triggers the `elementor/loaded` action.

=== WP Debugging ===
If AI Research fails or returns unexpected results, enable WordPress debugging and check for PHP errors:

1. Edit your `wp-config.php` file and add:
   `define('WP_DEBUG', true);`
   `define('WP_DEBUG_LOG', true);`
2. Retry the request and examine `wp-content/debug.log` for error messages.

The AI features depend on the DOM/LibXML extension. Verify it is installed by running `php -m | grep -i dom` before using AI Research.

== Diagnostics ==
As a first step, open **Gm2 → Diagnostics** to detect plugin conflicts, missing files or theme customizations that may disable SEO output. Any detected issues are listed as an admin notice with steps to resolve them.

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
After configuring credentials in **Gm2 → Google OAuth Setup**, connect your Google account from **SEO → Connect Google Account**. The plugin automatically fetches your available Analytics Measurement IDs and Ads Customer IDs so you can select them from dropdown menus. Use the **Keyword Research** tab or the AI SEO workflow to generate ideas via the Google Keyword Planner. To fetch keywords you must enter a Google Ads developer token, connect a Google account with Ads access, and select a valid Ads customer ID (without dashes, e.g., `1234567890`). Missing or invalid credentials will result in empty or failed searches. If your developer token belongs to a manager account, provide the optional Login Customer ID so the value is sent with each request.

Keyword metrics include **Avg. Monthly Searches**, **competition**, **3‑month change** and **year-over-year change**. These values refine the seed keywords returned by ChatGPT and are sorted by popularity when displayed in the AI SEO modal.

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
Use **SEO → SEO Guidelines** to define rule sets for each post type and taxonomy.
Click **AI Research Guideline Rules** to have ChatGPT suggest best-practice rules
for the selected content type. Saved rules are stored in the `gm2_guideline_rules`
option and are combined automatically when generating AI content.

== Content Rules ==
Open the **Rules** tab under **SEO** to define checks for each post type and
taxonomy. Rules are grouped into categories like *SEO Title*, *SEO Description*,
*Focus Keywords*, *Long Tail Keywords*, *Canonical URL*, *Content* and
*General*. Each category provides its own textarea where you can enter multiple
line-based rules. These category rules work alongside the text in **SEO
Guidelines** and are considered when running **AI Research** on a post or
taxonomy term.

Within the Rules table, each post type or taxonomy row now includes a single
**AI Research Content Rules** button positioned beneath all of its category
textareas. Click this button to fetch best-practice suggestions from ChatGPT for
the selected content type. ChatGPT is told which content type is being analyzed
and instructed to
return an array of short, measurable rules for each requested category. The
results are saved automatically so you can refine them at any time. ChatGPT is
instructed to respond **only with JSON** where each key matches one of the slugs
you provided and each value is an array of rules.

Category names returned by ChatGPT may contain spaces or hyphens. These are
normalized to use underscores when saving so keys like "SEO Title" or
"seo-title" populate the `seo_title` textarea.
Synonyms such as "content in post" or "content in product" will also populate
the `content` category automatically.

Example JSON response:

```
{
  "seo_title": [
    "Use the main keyword first",
    "Keep under 60 characters"
  ],
  "seo_description": [
    "Summarize the page in under 160 characters",
    "Include a call to action"
  ]
}
```

== SEO Context ==
Open the **Context** tab under **SEO** to store detailed business information used in AI prompts. Answer each question and the plugin will append your responses to every ChatGPT request:

* **Business Model** – How does the company make money (product sales, services, subscriptions, ads, affiliate, hybrid)?
* **Industry Category** – Which industry best describes your business? If e-commerce, list main product categories and any flagship items. For services or SaaS, outline your core offerings and modules.
* **Target Audience** – Who are your core customer segments and where are they located?
* **Unique Selling Points** – What differentiates your brand from competitors in terms of price, quality, experience, or mission?
* **Revenue Streams** – List the major sources of revenue such as products, services, subscriptions or ads.
* **Primary Goal** – What is the website's main objective?
* **Brand Voice** – Describe the desired style or tone (professional, casual, luxury, authoritative, playful, eco-friendly, etc.).
* **Competitors** – List main online competitors and what makes your offer stronger or unique.
* **Core Offerings** – What are the key products or services you provide?
* **Geographic Focus** – Which regions or locations do you primarily target?
* **Keyword Data** – Do you have existing keyword research or rankings to share?
* **Competitor Landscape** – How would you describe the competitive landscape in your niche?
* **Success Metrics** – Which KPIs will track SEO success (sales, leads, traffic, rankings)?
* **Buyer Personas** – Describe your ideal buyers and their pain points.
* **Project Description** – Short summary of your project or website. Used when other fields are empty.
* **Custom Prompts** – Default instructions appended to AI requests.
* **Business Context Prompt** – One-click builder that combines your answers into a single prompt summarizing the business.

To build the prompt, ensure the ChatGPT feature is enabled and save your API key and model under **Gm2 → ChatGPT**. Then return to **SEO → Context** and click **Generate AI Prompt** below the Business Context Prompt field. The plugin merges all of your Context answers and sends them to ChatGPT. The response is inserted into the textarea so you can tweak it before saving.

== SEO Settings ==
The SEO meta box appears when editing posts, pages, any public custom post types and taxonomy terms. In the
**SEO Settings** tab you can enter a custom title and description, focus keywords and long tail keywords, toggle
`noindex` or `nofollow`, and upload an Open Graph image. Focus and long tail keywords are combined into a `<meta name="keywords">` tag on the front end. Click **Select Image**
to open the WordPress media library and choose a picture for the `og:image` and
`twitter:image` tags. When inserting a link, you can pick `nofollow` or `sponsored`
from the link dialog and the plugin will automatically apply the attribute.

Use the **Max Snippet**, **Max Image Preview**, and **Max Video Preview** fields
to control how search engines display your content. Values entered here are
added to the robots meta tag.

The **Sitemap** tab lets you regenerate your XML sitemap. Each time a sitemap is
created, the plugin pings Google and Bing so they can re-crawl it. The sitemap is
written to `sitemap.xml` in your WordPress directory by default. This path can be
changed via the **Sitemap Path** field. You can also edit your robots file from
**SEO → Robots.txt**.

Enable **Clean Slugs** from **SEO → General** to strip stopwords from new
permalinks. Enter the words to remove in the accompanying field.

== AI SEO ==
While editing a post or taxonomy term, open the **AI SEO** tab in the SEO meta box. Click **AI Research** to run a two-step workflow. You'll first be asked whether to use any existing SEO field values. If all fields are empty and no site context is set under **SEO → Context**, you'll be prompted for a short description so ChatGPT has extra context. Answers saved in the Context tab are automatically included in each prompt, so you only need to provide extra details when those fields are empty.

Step one sends the post content to ChatGPT which returns a title, description
and a list of seed keywords. If no seed keywords are returned, the plugin
generates them automatically before querying Google Ads. Step two refines those keywords with the Google
Keyword Planner, ranking them by **Avg. Monthly Searches**, **competition** and
the **3‑month** and **year-over-year change** metrics. A valid Google Ads
developer token, connected account and customer ID are required for this step.
If these credentials are missing or the Keyword Planner fails, AI SEO falls back
to the ChatGPT suggestions and adds a notice that metrics are unavailable.

The results suggest a title, description, focus keywords, canonical URL, page
name and slug. Any detected HTML issues—such as multiple `<h1>` tags—are listed
with optional fixes. Use the **Select all** checkbox to implement the results
and automatically populate the SEO fields.

If the description field in the SEO Settings tab is left empty, the plugin sends
an excerpt of the post to ChatGPT and uses the response as the meta description.
Enabling **Auto-fill missing alt text** under **SEO → Performance** also uses
ChatGPT to generate alt text for new images when none is provided. Enable
**Clean Image Filenames** in the same section to automatically rename uploads
using a sanitized version of the attachment title.

On taxonomy edit screens you'll also find a **Generate Description** button next
to the description field. The prompt can be customised via the
`gm2_tax_desc_prompt` setting and includes any saved SEO guidelines for that
taxonomy.


The SEO Settings tab also lets you set `max-snippet`, `max-image-preview`, and
`max-video-preview` values that will be added to the robots meta tag.

== Structured Data ==
Enable **Article Schema** from **SEO → Schema** to output Schema.org Article
markup on posts. The markup includes the headline, author and publication date
and helps search engines understand your content.

== Tariff Management ==
Manage percentage-based fees for WooCommerce orders. Open **Gm2 → Tariff** to
add or edit tariffs. Enabled tariffs add a fee to the cart total during
checkout.

== Quantity Discounts ==
This feature requires WooCommerce to be active.
After activating WooCommerce, open **Gm2 → Quantity Discounts** and click **Add Discount Group** to define bulk pricing rules. Choose the products or categories to apply, enter the minimum quantity and specify either a percentage or fixed discount. When customers meet the threshold the discount is applied automatically in the cart. Install Elementor to add the **Gm2 Qnty Discounts** widget on product pages, giving shoppers buttons for preset quantities that match your rules. If Elementor Pro is active the widget lives in the **WooCommerce** section; otherwise it appears in **General**. The selected rule and discounted price are saved in order item meta and appear in emails and on the admin order screen.

== Redirects ==
Create 301 or 302 redirects from the **SEO → Redirects** tab. The plugin logs
the last 100 missing URLs to help you create new redirects.

== Planned Features ==
* **Search Console metrics** – new **SEO → Analytics** tab will show clicks and
  impressions from connected sites.
* **Expanded rules** – guideline rules for each content type editable under
  **SEO → SEO Guidelines**.
* **Duplicate checks** – upcoming report in **SEO → Tools** that flags posts
  with duplicate titles or descriptions.
* **Slug cleanup** – bulk action in **SEO → General** to remove stopwords from
  existing slugs.
* **Canonical for variations** – meta box option to set a canonical URL on
  individual product variations.
* **Image alt keyword checks** – SEO meta box warning when alt text is missing
  or lacks the focus keyword.
* **Link count tracking** – post list columns showing internal and external
  link counts with a totals widget.
* **PageSpeed** – run Google PageSpeed tests from a new **Performance →
  PageSpeed** screen.
* **Taxonomy intro length enforcement** – minimum description length setting
  under **SEO → Taxonomies**.
* **AI-generated taxonomy descriptions** – generate term descriptions via an
  **AI SEO** button on taxonomy edit screens.
* **Image filename normalization** – automatically sanitize filenames when
  **Clean Image Filenames** is enabled.
* **Bulk AI suggestions** – bulk action on the posts list to generate AI SEO
  suggestions for multiple items with post type and category filters.
* **Outbound link rel controls** – dropdown in the link dialog to apply
  `nofollow` or `sponsored` to outbound links.

== Changelog ==
= 1.6.16 =
* When multiple discount groups include the same product, the product now gets the highest available percentage.
= 1.6.15 =
* Combined focus and long tail keywords into a `<meta name="keywords">` tag on the front end.
= 1.6.14 =
* Added wrap option to the Gm2 Qnty Discounts widget so quantity buttons can wrap on smaller screens.
= 1.6.13 =
* Added icon color style options for the Gm2 Qnty Discounts widget currency icon.
= 1.6.12 =
* Improved dropdown behavior in Quantity Discounts admin.
= 1.6.11 =
* Fixed category dropdown overlay after removing a selection in Quantity Discounts admin.
= 1.6.10 =
* Keyword research now falls back to ChatGPT when Ads credentials are missing or the Keyword Planner request fails. A notice explains that metrics are unavailable.
= 1.6.9 =
* Added warning when Google Ads metrics are missing. AI Research continues using the top keywords and displays a notice.
= 1.6.8 =
* Added three-month and year-over-year change metrics to keyword research results.
= 1.6.7 =
* Fixed display of keyword research results when API returns complex objects.
= 1.6.6 =
* AI Research now refines ChatGPT keywords using Google Keyword Planner metrics (Avg. Monthly Searches, competition, 3‑month and YoY change). Results can auto-populate SEO fields and require valid Ads credentials.
= 1.6.5 =
* Added loading state for AI Research Content Rules button.
* Fixed nested-array handling for AI Research Content Rules.
= 1.6.4 =
* Optional slug cleaner with customizable stopwords.
* Option to clean image filenames on upload.
* Updated AI SEO JavaScript for more accurate suggestions.
* Bulk AI page now filters posts by type and category.
= 1.6.3 =
* Expanded documentation for Open Graph images, robots meta settings, sitemap
  pinging, robots.txt editing, AI-generated descriptions and alt text, and
  Article schema.
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
The PHPUnit tests rely on the WordPress test suite and the `phpunit` executable.


1. **Install PHP and Composer.** PHPUnit is required for the tests and can be installed globally:
   ```bash
   composer global require phpunit/phpunit
   ```
   Ensure the Composer global bin directory is on your `PATH`.
2. **Install the WordPress test suite.** Run the helper script before executing the tests:
   ```bash
   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
   ```
   This downloads WordPress and prepares the test database.
3. **Run the tests.** Execute `phpunit` or `make test` from the project root. The Makefile will install the test suite automatically if it is missing. Set `WP_TESTS_DIR` if you installed the suite elsewhere.

The tests cover the AJAX endpoints used for content analysis and AI research (`gm2_check_rules`, `gm2_keyword_ideas`, `gm2_research_guidelines` and `gm2_ai_research`).
