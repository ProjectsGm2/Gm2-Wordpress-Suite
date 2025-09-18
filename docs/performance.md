# Performance module

The Performance module exposes optional front‑end helpers and now ships with an analytics dashboard under **SEO → Performance**. The screen summarises autoload health, provides query cache toggles, and lists the heaviest `wp_options` rows so you can keep the database lean.

## Autoload insights

The “Autoload footprint” card aggregates `autoload = 'yes'` payloads and surfaces status badges:

* **Healthy** below 500&nbsp;KB.
* **Warning** between 500&nbsp;KB and 800&nbsp;KB.
* **Critical** above 800&nbsp;KB.

The table of “Largest autoloaded options” highlights rows over 50&nbsp;KB. Remediation tips are included directly on the page:

1. Set rarely used options to `autoload = 'no'` via `update_option( $name, $value, false );` or `AutoloadManager::get_autoload_flag()`.
2. Hook `gm2_performance_autoload_disabled_options` to enforce custom exclusions.
3. Trim or archive legacy payloads that no longer need to load on every request.

### WP‑CLI helpers

Use the bundled command to inspect live data without opening the admin UI:

```bash
wp gm2 perf autoload --threshold=75000 --limit=10
```

Additional subcommands are available:

* `wp gm2 perf autoload totals`&nbsp;— prints aggregate counts and flags warnings when thresholds are exceeded.
* `wp gm2 perf autoload managed`&nbsp;— lists options that default to `autoload = 'no'` via `AutoloadManager`.

Options reported by the CLI can be migrated with core helpers:

```php
update_option( 'gm2_content_rules', $payload, false ); // false sets autoload = 'no'
```

## Query cache controls

The query cache section toggles the deterministic query cache and its transient fallback:

| Option | Default | Description |
| --- | --- | --- |
| `gm2_perf_query_cache_enabled` | `1` | When disabled `QueryCacheManager` forces a cache bypass for all requests. |
| `gm2_perf_query_cache_use_transients` | `0` | Overrides the `gm2_query_cache_use_transients` filter, persisting payloads via transients when object caching is unavailable. |

## Front‑end helpers

Feature flags appear beneath the cache controls. Each toggle maps to an option that is also filterable via `ae/perf/flag`:

| Flag | Option | Description |
| --- | --- | --- |
| `webWorker` | `ae_perf_webworker` | Enable Web Worker offloading. |
| `longTasks` | `ae_perf_longtasks` | Observe and log `longtask` entries. |
| `noThrash` | `ae_perf_nothrash` | Batch DOM reads and writes via `aePerf.dom.measure` and `aePerf.dom.mutate`. |
| `passive` | `ae_perf_passive` | Default scroll and touch handlers to passive. |
| `passivePatch` | `ae_perf_passive_patch` | Allow the passive listener patch to run. |
| `domAudit` | `ae_perf_domaudit` | Log total DOM nodes after paint. |

Enabling `longTasks` logs per‑second summaries of `longtask` entries. Lifetime totals are available via `aePerf.getLongTaskSummary()`. If `AE_PERF_FLAGS.longTaskBudgetMs` is defined, a warning is emitted when the last 10 s of long tasks exceed this budget.

### Long task monitoring

Enable observation by setting `AE_PERF_FLAGS.longTasks` and optionally provide a budget with `AE_PERF_FLAGS.longTaskBudgetMs`:

```html
<script>
window.AE_PERF_FLAGS = {
  longTasks: true,
  longTaskBudgetMs: 200 // 200 ms per 10 s window
};
</script>
```

With the flag enabled the browser console prints summaries for each `longtask` and warns when the budget is exceeded:

```
[aePerf] longtask: 120 ms (total 120 ms)
[aePerf] longtask: 150 ms (total 270 ms)
[aePerf] long task budget exceeded: 270 ms / 200 ms
```

Disable monitoring by turning off the flag. No `longtask` messages appear when the observer is inactive:

```html
<script>
window.AE_PERF_FLAGS = { longTasks: false };
</script>
```

To offload expensive tasks to a Web Worker, use `aePerf.runTask`:

```js
if (window.aePerf?.runTask) {
  const hash = await window.aePerf.runTask('sha1', { text: longString });
}
```

Note that disabling the `webWorker` flag prevents worker creation, causing the fallback to run on the main thread.

Batch DOM reads and writes:

```js
if (window.aePerf?.dom) {
  const height = await aePerf.dom.measure(() => el.offsetHeight);
  await aePerf.dom.mutate(() => { el.style.height = `${height}px`; });
}
```

Both methods return Promises, so `await` can be used. Append `?aePerfDebug=1` to the URL to log if a measure callback mutates or a mutate callback performs layout reads.

Developers may override any flag:

```php
add_filter( 'ae/perf/flag', function( $enabled, $feature ) {
    if ( $feature === 'webWorker' ) {
        return false; // force disable
    }
    return $enabled;
}, 10, 2 );
```

Disabling a flag or removing the filter safely rolls back to WordPress default behaviour.

`window.aePerf.debug()` prints the current flag state to the browser console.

## Passive event listeners

Use `aePerf.addPassive` to attach listeners that default to `{ passive: true, capture: false }`:

```js
if (window.aePerf?.addPassive) {
  aePerf.addPassive(window, 'scroll', onScroll);
}
```

Provide `{ passive: false }` to retain cancelable behaviour. Setting the global `window.AE_PERF_DISABLE_PASSIVE` to `true` before the bootstrap script loads disables both the helper and the patch described below.

When the `passive` flag is enabled, `AE_PERF_FLAGS.passivePatch` controls whether `EventTarget.prototype.addEventListener` is patched so that `scroll`, `touchstart`, `touchmove`, and `wheel` listeners become passive when no options are supplied.

Themes or plugins can disable the patch if third‑party code misbehaves (for example, Google reCAPTCHA or other iframe‑embedded widgets) via:

```php
add_filter( 'ae/perf/passive_allow_patch', '__return_false' );
```

The patch skips nested browsing contexts, but interacting with iframes may still require opting out. Calling `preventDefault()` inside a patched listener logs a console warning and does not stop the default action.

## Troubleshooting

Widgets such as Google reCAPTCHA or carousel scripts may interfere with the passive listener patch. To disable only the patch while keeping other helpers active, filter the flag:

```php
add_filter('ae/perf/flag', fn($on,$feature)=> $feature==='passivePatch' ? false : $on, 10, 2);
```

This opts out of the patch without affecting other performance features.
