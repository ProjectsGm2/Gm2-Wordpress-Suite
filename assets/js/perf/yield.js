function idle(cb, {timeout = 200} = {}) {
    if (typeof requestIdleCallback === 'function') {
        return requestIdleCallback(cb, { timeout });
    }
    return setTimeout(cb, 1);
}

function raf(cb) {
    return requestAnimationFrame(cb);
}

function chunk(iterable, chunkSize = 50, onChunk, onDone) {
    const iterator = iterable[Symbol.iterator]();
    function run() {
        let count = 0;
        let next = iterator.next();
        while (!next.done && count < chunkSize) {
            if (onChunk) {
                onChunk(next.value);
            }
            count++;
            next = iterator.next();
        }
        if (next.done) {
            if (onDone) {
                onDone();
            }
        } else {
            raf(run);
        }
    }
    run();
}

function yieldToMain() {
    return new Promise((resolve) => {
        setTimeout(resolve, 0);
    });
}

function processInSlices(task, sliceMs = 12) {
    function run() {
        const deadline = performance.now() + sliceMs;
        let more = true;
        while (more && performance.now() < deadline) {
            more = task(deadline);
        }
        if (more !== false) {
            setTimeout(run, 0);
        }
    }
    setTimeout(run, 0);
}

export { idle, raf, chunk, yieldToMain, processInSlices };
