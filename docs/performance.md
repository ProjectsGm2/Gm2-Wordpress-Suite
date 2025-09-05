# Performance module

The Performance module exposes optional front‑end helpers that can be toggled from **SEO → Performance**.

| Flag | Option | Description |
| --- | --- | --- |
| `worker` | `ae_perf_worker` | Enable Web Worker offloading. |
| `long_tasks` | `ae_perf_long_tasks` | Observe and log `longtask` entries. |
| `layout_thrash` | `ae_perf_layout_thrash` | Batch DOM reads and writes via `aePerf.measure` and `aePerf.mutate`. |
| `noThrash` | `ae_perf_no_thrash` | Batch DOM reads and writes via `fastdom-lite`. |
| `passive_listeners` | `ae_perf_passive_listeners` | Default scroll and touch handlers to passive. |
| `dom_audit` | `ae_perf_dom_audit` | Log total DOM nodes after paint. |

To offload expensive tasks to a Web Worker, use `aePerf.runTask`:

```js
if (window.aePerf?.runTask) {
  const hash = await window.aePerf.runTask('sha1', { text: longString });
}
```

Note that disabling the `worker`/`webWorker` flag prevents worker creation, causing the fallback to run on the main thread.

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
