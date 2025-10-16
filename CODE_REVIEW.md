# Code Review Findings

## Critical bugs

- **Bulk post selection ignores list-table filters.** The AJAX handler reconstructs a new `WP_Query` from the raw query string but only forwards `post_type`, `post_status`, and `s`. Filters for author, taxonomy, date, custom fields, or the current list-table view (e.g., clicking “All”, “Mine”, or filtering by category) are discarded, so the returned IDs no longer match the items visible in the UI. Running bulk operations with these IDs can therefore touch content the user never selected. This happens in `Gm2_Bulk_Review::ajax_fetch_post_ids()` when the `$args` array is built and passed to `WP_Query`.【F:admin/class-gm2-bulk-review.php†L39-L54】
- **Taxonomy bulk selection has the same desync problem.** The term query is rebuilt with only `taxonomy` and `search`, plus `hide_empty => false` and `number => 0`, so any ordering, pagination, or filter choices on the screen are ignored. That means the returned ID list can include terms that are not part of the current filtered result set, and it forces WordPress to load every term in the taxonomy, which is prohibitive on large sites. This occurs in `Gm2_Bulk_Taxonomies::ajax_fetch_term_ids()`.【F:admin/class-gm2-bulk-taxonomies.php†L39-L58】
- **ChatGPT prompts are over-sanitized.** `sanitize_text_field()` strips newlines and collapses interior whitespace, so multi-line prompts entered in the settings page are mangled before being sent to the API. Switching to `sanitize_textarea_field()` (or escaping on output instead) would preserve legitimate formatting while still securing the value. The issue lives in `Gm2_Admin::display_chatgpt_page()` when the prompt is read from `$_POST`.【F:admin/class-gm2-admin.php†L37-L65】

## Performance and reliability concerns

- **Returning every matching ID does not scale.** Both bulk AJAX handlers request every record (`posts_per_page => -1` / `number => 0`) and immediately echo the full ID list to the browser. On sites with tens of thousands of posts or terms this will time out or exhaust memory; at minimum the query should respect pagination or stream IDs in chunks.【F:admin/class-gm2-bulk-review.php†L46-L54】【F:admin/class-gm2-bulk-taxonomies.php†L46-L58】
- **JavaScript lacks error handling.** The bulk-selection scripts assume the AJAX response resolves successfully and always exposes `data.data`. A network error or a `success: false` payload will currently fall through silently, leaving stale button state. Adding `.catch` handlers and checking `data.success` would make the UI resilient.【F:admin/js/gm2-bulk-review.js†L12-L37】【F:admin/js/gm2-bulk-taxonomies.js†L12-L37】

## Duplication and maintainability

- **Bulk selection scripts are near duplicates.** `gm2-bulk-review.js` and `gm2-bulk-taxonomies.js` only differ by element IDs, option names, and the AJAX action. Extracting a reusable initializer (or data attributes) would reduce maintenance cost and keep behavior consistent.【F:admin/js/gm2-bulk-review.js†L1-L65】【F:admin/js/gm2-bulk-taxonomies.js†L1-L65】
- **Test stubs repeat large scaffolding.** The PHPUnit tests for bulk posts and terms each declare their own copies of WordPress stub functions and bootstrap logic. Moving the shared scaffolding into a helper file (or leveraging `tests/bootstrap.php`) would remove duplication and keep the fake environment in sync.【F:tests/BulkReviewTest.php†L1-L47】【F:tests/BulkTaxonomiesTest.php†L1-L47】

## Suggested follow-up

1. Pass the full query vars from the current list tables into the AJAX handlers (or reuse `WP_List_Table`’s prepared query) and gate access with `current_user_can()` so the returned IDs always match what the user can see.
2. Preserve prompt formatting in the ChatGPT settings form by using a textarea-specific sanitizer when saving and continuing to escape on output.
3. Add result/error guards to the bulk-selection JavaScript and consider streaming or paginating the ID collection to avoid loading entire datasets client-side.
4. Consolidate duplicate JavaScript and PHPUnit scaffolding into shared helpers to ease future maintenance.
