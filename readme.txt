=== Gm2 WordPress Suite ===
Contributors: gm2team
Tags: admin, tools, suite, performance
Requires at least: 5.6
Tested up to: 6.5
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
A powerful suite of WordPress enhancements including admin tools, frontend optimizations, and ChatGPT-powered content generation.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/gm2-wordpress-suite` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Gm2 Suite menu in the admin sidebar to configure settings. First
   visit **Gm2 → Google OAuth Setup** to enter your client ID and secret, then
   use **SEO → Connect Google Account** to authorize your Google account.
4. All required PHP libraries, including the Google API client, are bundled in
   the plugin. No additional installation steps are required.

If you plan to distribute or manually upload the plugin, you can create a ZIP
archive with `bash bin/build-plugin.sh`. This command packages the plugin with
all dependencies into `gm2-wordpress-suite.zip` for installation via the
**Plugins → Add New** screen.

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
After configuring credentials in **Gm2 → Google OAuth Setup**, connect your
Google account from **SEO → Connect Google Account** and use the
**Keyword Research** tab to generate ideas via the Google Keyword Planner.

== Image Optimization ==
Enter your compression API key and enable the service from the SEO &gt; Performance screen.
When enabled, uploaded images are sent to the API and replaced with the optimized result.

== Changelog ==
= 1.7.0 =
* Selectable ChatGPT model in settings page.
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
