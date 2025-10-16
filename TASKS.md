# CODE_REVIEW.md Follow-up Tasks

The following tasks translate the issues highlighted in `CODE_REVIEW.md` into actionable follow-up work. Each item should be tracked and completed to ensure the plugin aligns with the review findings.

## Critical Bugs

- [ ] **Align bulk post selections with current list-table filters.**
  - Capture the active `WP_List_Table` query vars (including author, taxonomy, date, search, status, and custom filters) and reuse them inside `Gm2_Bulk_Review::ajax_fetch_post_ids()`.
  - Validate access with `current_user_can()` to ensure IDs returned belong to content the user can manage.
  - Add automated coverage that exercises the AJAX handler with filtered views.

- [ ] **Respect taxonomy list filters in bulk term selection.**
  - Reuse the taxonomy screen’s current query vars instead of reconstructing a new `WP_Term_Query` in `Gm2_Bulk_Taxonomies::ajax_fetch_term_ids()`.
  - Ensure capability checks restrict results to terms the user can edit.
  - Add tests that confirm filtered taxonomy views return matching IDs only.

- [x] **Preserve ChatGPT prompt formatting.**
  - Replace `sanitize_text_field()` with a textarea-safe sanitizer (e.g., `sanitize_textarea_field()`) in `Gm2_Admin::display_chatgpt_page()` when saving prompts.
  - Double-check output escaping so stored prompts render with their original formatting.
  - Extend settings tests (or add new ones) covering multi-line prompt submissions.

## Performance and Reliability

- [ ] **Prevent unbounded ID fetches in bulk operations.**
  - Avoid `posts_per_page => -1` / `number => 0` by paginating requests or streaming IDs in batches.
  - Provide user feedback (e.g., progress indicators) when large result sets require multiple requests.
  - Update tests and documentation to reflect the new strategy.

- [ ] **Harden JavaScript bulk-selection flows.**
  - Add error handling for failed AJAX responses in both bulk selection scripts, checking `data.success` and catching network exceptions.
  - Surface user-friendly messages and reset UI state when failures occur.
  - Add front-end tests (or manual QA steps) that simulate AJAX failures.

## Maintainability Improvements

- [ ] **Consolidate duplicate bulk-selection scripts.**
  - Extract shared logic between `gm2-bulk-review.js` and `gm2-bulk-taxonomies.js` into a reusable initializer or module.
  - Parameterize behavior via data attributes or configuration objects.
  - Ensure documentation and build steps reflect the new structure.

- [ ] **Deduplicate PHPUnit scaffolding.**
  - Move shared WordPress stubs and bootstrap code from `BulkReviewTest` and `BulkTaxonomiesTest` into a common helper or `tests/bootstrap.php`.
  - Update each test case to rely on the shared scaffolding, keeping behavior identical.
  - Confirm the PHPUnit suite still passes after refactoring.

