# Caching and Lazy Loading

The suite uses several techniques to reduce database load and speed up requests:

- **Database indexes** – custom indexes are added to WordPress meta tables and
  to plugin tables such as `gm2_audit_log`, `wc_ac_carts` and
  `wc_ac_email_queue`. The `wc_ac_carts` table includes an `ip_address` index so
  IP-based lookups avoid full table scans.
- **Lazy metadata loading** – `gm2_get_meta_value()` now exposes the
  `gm2_lazy_load_meta_value` filter. When enabled for a field the function
  returns a lightweight `GM2_Lazy_Meta_Value` object that defers the actual
  database read until the value is accessed.
- **Object caching** – values retrieved through the custom tables API are stored
  in WordPress's object cache (`wp_cache_set`/`wp_cache_get`) for the duration of
  the request.

A basic performance test (`tests/test-lazy-meta-value.php`) verifies that the
lazy loader does not hit the database until the value is used.
