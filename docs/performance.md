# Performance module

The Performance module exposes optional front‑end helpers that can be toggled from **SEO → Performance**.

| Flag | Option | Description |
| --- | --- | --- |
| `worker` | `ae_perf_worker` | Enable Web Worker offloading. |
| `longTasks` | `ae_perf_long_tasks` | Observe and log `longtask` entries. |
| `noThrash` | `ae_perf_no_thrash` | Batch DOM reads and writes via `aePerf.dom.measure` and `aePerf.dom.mutate`. |
| `passive_listeners` | `ae_perf_passive_listeners` | Default scroll and touch handlers to passive. |
| `dom_audit` | `ae_perf_dom_audit` | Log total DOM nodes after paint. |

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

Note that disabling the `worker`/`webWorker` flag prevents worker creation, causing the fallback to run on the main thread.

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
    if ( $feature === 'worker' ) {
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

When the `passive_listeners` flag is enabled, `AE_PERF_FLAGS.passivePatch` controls whether `EventTarget.prototype.addEventListener` is patched so that `scroll`, `touchstart`, `touchmove`, and `wheel` listeners become passive when no options are supplied.

Themes or plugins can disable the patch if third‑party code misbehaves (for example, Google reCAPTCHA or other iframe‑embedded widgets) via:

```php
add_filter( 'ae/perf/passive_allow_patch', '__return_false' );
```

The patch skips nested browsing contexts, but interacting with iframes may still require opting out. Calling `preventDefault()` inside a patched listener logs a console warning and does not stop the default action.
