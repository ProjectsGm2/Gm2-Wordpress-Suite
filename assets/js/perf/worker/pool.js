/**
 * Simple worker pool for AE tasks.
 */

const DEFAULT_SIZE = 2;
const TASK_TIMEOUT = 10000;

export class WorkerPool {
    constructor(size = DEFAULT_SIZE) {
        const limit = Math.min(4, typeof navigator !== 'undefined' && navigator.hardwareConcurrency ? navigator.hardwareConcurrency : DEFAULT_SIZE);
        this.size = Math.min(size, limit);
        this.workers = [];
        this.nextWorker = 0;
        this.taskId = 0;
        for (let i = 0; i < this.size; i += 1) {
            this.workers[i] = this._spawnWorker(i);
        }
    }

    _spawnWorker(index) {
        const worker = new Worker(new URL('./ae-worker.js', import.meta.url), { type: 'module' });
        const tasks = new Map();

        const handleMessage = (e) => {
            const { id, result, error } = e.data || {};
            const task = tasks.get(id);
            if (!task) {
                return;
            }
            clearTimeout(task.timer);
            tasks.delete(id);
            if (error) {
                task.reject(new Error(error));
            } else {
                task.resolve(result);
            }
        };

        const handleError = (err) => {
            tasks.forEach((t) => {
                clearTimeout(t.timer);
                t.reject(err instanceof Error ? err : new Error('Worker error'));
            });
            tasks.clear();
            worker.terminate();
            this.workers[index] = this._spawnWorker(index);
        };

        worker.addEventListener('message', handleMessage);
        worker.addEventListener('error', handleError);
        worker.addEventListener('messageerror', handleError);

        return { worker, tasks };
    }

    runTask(name, payload) {
        const id = ++this.taskId;
        const workerObj = this.workers[this.nextWorker];
        this.nextWorker = (this.nextWorker + 1) % this.workers.length;
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                workerObj.tasks.delete(id);
                reject(new Error('Task timed out'));
            }, TASK_TIMEOUT);
            workerObj.tasks.set(id, { resolve, reject, timer });
            try {
                workerObj.worker.postMessage({ id, name, payload });
            } catch (err) {
                clearTimeout(timer);
                workerObj.tasks.delete(id);
                reject(err);
            }
        });
    }
}
