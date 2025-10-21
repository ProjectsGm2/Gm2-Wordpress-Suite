# GitHub Updater QA Checklist

Use this checklist to validate GitHub updater behaviour across release and branch channels for both public and private repositories.

## Configuration Scenarios

- **Public release updates**
  - Configure owner/repo for a public repository and ensure the updater detects the latest tagged release.
  - Verify that the changelog link and package URL resolve without authentication.
- **Private release updates**
  - Add a valid personal access token and confirm authenticated download succeeds.
  - Test invalid or revoked tokens and expect authentication notices.
- **Branch channel updates**
  - Switch to a long-lived branch and confirm commit-based version strings are produced.
  - Validate fallback behaviour if the branch reference is missing.

## Failure Handling

- **HTTP 404**: Missing repository or branch surfaces an admin notice without modifying the update transient.
- **Bad token / HTTP 401**: Auth failures generate actionable error messages and backoff timers.
- **API rate limit / HTTP 403**: Verify exponential backoff is respected and admin notices are recorded.

## Manual Checks

- Use the **“Check Now”** button to trigger `gm2_github_updater_refresh` and confirm the cache is cleared and rebuilt.
- Run `wp gm2 updater check` and `wp gm2 updater update` with WP-CLI to verify CLI parity.

## Scheduled Behaviour

- Confirm the warmup cron (`gm2_github_updater_warmup`) is registered for the configured interval.
- Validate that disabling the plugin removes scheduled events.

## Rollback Validation

- After updating, roll back to the previous version via the WordPress plugins screen.
- Ensure updater metadata refreshes after rollback without stale cache entries.

## Audit Trail

- Review admin notices stored in `gm2_github_updater_notices` following each scenario.
- Inspect logs when `gm2_updater_enable_logging` is enabled to confirm diagnostic output.

