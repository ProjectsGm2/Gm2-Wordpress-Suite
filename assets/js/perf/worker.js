/**
 * Offload work to a Web Worker.
 *
 * Placeholder implementation that spins up a no-op worker.
 *
 * @return {void}
 */
export function init() {
    if (typeof Worker === 'undefined') {
        return;
    }
    const blob = new Blob(['self.onmessage=e=>{self.postMessage(e.data)}'], { type: 'text/javascript' });
    const worker = new Worker(URL.createObjectURL(blob));
    worker.postMessage('');
    worker.onmessage = () => {
        worker.terminate();
    };
}
