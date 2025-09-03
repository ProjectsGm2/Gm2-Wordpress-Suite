# JavaScript Compatibility Layer

The JavaScript Manager includes a compatibility layer that automatically whitelists essential script handles for common plugins. This prevents critical frontend assets from being deferred or dequeued unexpectedly.

## Default plugin allowances

The following plugins are allowed by default:

- Elementor
- WooCommerce
- Contact Form 7
- Rank Math
- Yoast
- reSmush.it
- Site Kit
- Complianz

These allowances ensure that required scripts from each plugin load normally.

## Opting out

A **Compatibility** tab under **SEO → Performance → JavaScript** lists each default handle. Uncheck a handle to opt out and manage it manually. Disabling a handle may break related functionality, so test changes carefully before deploying them to production.

