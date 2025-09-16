# Frontend Submission

Gm2 WordPress Suite ships a front-end submission stack that mirrors the
field definitions you register for each custom post type. Site builders can
expose the same experience either through a shortcode/block or via an
Elementor form action, while administrators retain control over review
statuses and email notifications.

## Prerequisites

* Define the custom post type and its fields. See
  [fields-and-validation.md](fields-and-validation.md) for conditional logic
  and validation hooks and [field-definition-schema.md](field-definition-schema.md)
  for the field blueprint format consumed by the submission renderer.
* When working inside Elementor, review the dynamic content helpers in
  [using-with-elementor.md](using-with-elementor.md) so that confirmation
  pages and dashboards can reuse submitted metadata.

## Shortcode and block usage

Use the `[gm2_cp_form]` shortcode anywhere shortcodes are allowed:

```html
[gm2_cp_form post_type="directory" require_login="1" form_id="gm2_directory_form"]
```

The shortcode accepts four attributes:

* `post_type` – required. Targets a registered custom post type. Invalid
  values abort rendering to avoid leaking forms for unknown objects.【F:frontend/class-gm2-cp-form.php†L457-L472】
* `post_id` – optional. Prefills the form for editing an existing post of the
  same type and ensures the current user can edit it.【F:frontend/class-gm2-cp-form.php†L125-L135】【F:frontend/class-gm2-cp-form.php†L469-L520】
* `require_login` – optional. Overrides the submission rule stored in the
  post type configuration so forms can be gated per instance.【F:frontend/class-gm2-cp-form.php†L474-L496】【F:frontend/class-gm2-cp-form.php†L1052-L1066】
* `form_id` – optional. Sets an explicit identifier used for nonce and result
  lookups; defaults to `gm2_cp_form_{post_type}`.【F:frontend/class-gm2-cp-form.php†L463-L468】

A block equivalent is registered as `gm2/cp-form` and exposes the same
properties (`postType`, `postId`, `requireLogin`, `formId`). Add it to any
block-based template to embed the submission UI without hand-writing
shortcodes.【F:frontend/class-gm2-cp-form.php†L420-L449】

Every render pulls the matching field groups (scoped to the chosen post type)
from the `gm2_field_groups` option, honours capability checks, and outputs the
correct control for each field type so the front-end mirrors the admin editor.【F:frontend/class-gm2-cp-form.php†L138-L200】【F:frontend/class-gm2-cp-form.php†L498-L606】【F:frontend/class-gm2-cp-form.php†L654-L744】

## Elementor Forms action

Elementor Pro users can drive submissions through the **Gm2: Create/Update
Post** action registered under **Form → Actions After Submit**.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L50-L157】
Configure it by:

1. Selecting the target post type and desired status (defaults to
   `pending`).【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L178-L197】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L849-L869】
2. Optionally supplying a form identifier and site ID for multisite targets.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L170-L208】
3. Mapping Elementor field IDs to GM2 meta keys via the repeater so the action
   knows which custom fields to update.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L270-L298】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L465-L532】
4. Mapping Elementor field IDs to taxonomy slugs, including whether multiple
   terms should be processed, to automatically assign taxonomy relationships
   after saving.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L300-L337】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L1288-L1404】
5. Adding hidden fields whose IDs match `gm2_cp_nonce` and `gm2_cp_hp`. The
   nonce should store `wp_create_nonce( 'gm2_cp_form|{form_id}' )`; any value
   entered into the honeypot cancels the submission.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L248-L266】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L716-L747】
6. (Optional) Supplying field IDs for title, content, excerpt, or an existing
   post ID to support edits and drafts.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L211-L246】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L435-L447】

When the form fires, the action sanitizes each field, validates file uploads,
updates post meta, assigns taxonomy terms, and queues media uploads exactly
like the shortcode flow, so both entry points share the same review and
notification pipeline.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L404-L556】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L1132-L1479】

## Review workflow and statuses

Each submission runs through `determine_status()` to decide whether it should
stay under review or publish immediately. The logic honours these settings in
`gm2_custom_posts_config[ post_type ][ submission ]`:

* `require_review` (default `true`) – keep new entries pending unless
  explicitly disabled.【F:frontend/class-gm2-cp-form.php†L363-L386】
* `under_review_status` (default `pending`) – post status applied to new or
  updated entries awaiting approval.【F:frontend/class-gm2-cp-form.php†L364-L387】
* `publish_status` – optional fallback when review is disabled; defaults to
  `publish`.【F:frontend/class-gm2-cp-form.php†L374-L381】

To register and enforce a dedicated "Under Review" status for a directory
post type:

```php
add_action('init', function () {
    register_post_status('gm2_under_review', [
        'label'                     => _x('Under Review', 'post'),
        'public'                    => false,
        'internal'                  => false,
        'protected'                 => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
    ]);
});

add_filter('gm2_cp_form_under_review_status', function ($status, $post_type) {
    return ('directory' === $post_type) ? 'gm2_under_review' : $status;
}, 10, 2);
```

The same status slug can be passed to Elementor's action or injected via the
`gm2_cp_elementor_post_status` filter when using Elementor forms.【F:frontend/class-gm2-cp-form.php†L383-L385】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L849-L869】

## Email notifications

The submission handler sends two emails after saving:

* An admin alert using `admin_emails`, `admin_subject`, and `admin_message`
  from the submission config (defaults to the site admin address and a generic
  message).【F:frontend/class-gm2-cp-form.php†L1079-L1107】
* A submitter acknowledgement when `submitter_email_field` resolves to a valid
  email, using `submitter_subject` and `submitter_message`.【F:frontend/class-gm2-cp-form.php†L1109-L1141】

Tokens available in both templates include `{post_id}`, `{post_type}`,
`{status}`, `{summary}`, `{permalink}`, `{edit_link}`, `{is_update}`,
`{site_name}`, and `{field_key}` placeholders for every submitted field.【F:frontend/class-gm2-cp-form.php†L1156-L1177】

A practical way to configure per-type notifications and enforce review status
is to merge the defaults inside `gm2_cp_form_submission_config`:

```php
add_filter('gm2_cp_form_submission_config', function ($submission, $post_type) {
    if ('directory' !== $post_type) {
        return $submission;
    }

    return wp_parse_args($submission, [
        'require_review'       => true,
        'under_review_status'  => 'gm2_under_review',
        'admin_emails'         => [ 'editor@example.com', 'qa@example.com' ],
        'admin_subject'        => 'New directory listing awaiting review',
        'admin_message'        => "{site_name} received listing #{post_id}.\n\n{summary}",
        'submitter_email_field'=> 'contact_email',
        'submitter_subject'    => 'Thanks! Your listing is being reviewed',
        'submitter_message'    => "We will reply once the status changes.\n\n{summary}",
    ]);
}, 10, 2);
```

You can further customize the outgoing payloads with
`gm2_cp_form_admin_email`, `gm2_cp_form_submitter_email`, and the shared
`gm2_cp_form_email_content` filter.【F:frontend/class-gm2-cp-form.php†L1094-L1138】【F:frontend/class-gm2-cp-form.php†L1175-L1177】

## Security and moderation safeguards

Both delivery mechanisms share a hardened pipeline:

* Nonces (`gm2_cp_nonce`) and honeypot fields (`gm2_cp_hp`) block replay and
  automated submissions.【F:frontend/class-gm2-cp-form.php†L97-L114】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L248-L266】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L716-L747】
* Optional login enforcement combines configuration defaults with per-form
  overrides before rendering the form or processing the payload.【F:frontend/class-gm2-cp-form.php†L474-L496】【F:frontend/class-gm2-cp-form.php†L116-L123】
* Capability checks ensure users can only edit posts and fields they are
  allowed to touch.【F:frontend/class-gm2-cp-form.php†L125-L175】
* Field values pass through the same sanitizers and validation classes used by
  the admin editor, including file-type/size checks and upload handling.【F:frontend/class-gm2-cp-form.php†L185-L217】【F:frontend/class-gm2-cp-form.php†L880-L1008】
* Elementor submissions reuse similar sanitization, file validation, taxonomy
  assignment, and media attachment routines before metadata is stored.【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L404-L556】【F:src/Elementor/Forms/Action/CreateOrUpdatePost.php†L1288-L1479】
* Default success and error messages may be filtered to plug into custom
  moderation queues or redirect flows.【F:frontend/class-gm2-cp-form.php†L224-L349】

With these tools you can stand up self-service submission flows, review queues
and notifications without sacrificing control or security.
