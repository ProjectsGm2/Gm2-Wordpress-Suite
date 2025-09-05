/**
 * DOM audit utilities.
 *
 * Traverses the DOM to collect size and depth metrics.
 *
 * @return {Promise<{totalElements:number,maxDepth:number,topOffenders:string[]}>}
 */
import { idle } from './yield.js';

const DEFAULT_THRESHOLDS = { totalElements: 600, maxDepth: 12 };
const DESCENDANT_LIMIT = 500;

function getSelector(el) {
    if (!el || el === document.body) {
        return 'body';
    }
    if (el.id) {
        return `#${el.id}`;
    }
    const parts = [];
    let current = el;
    while (current && current !== document.body) {
        let part = current.tagName.toLowerCase();
        if (current.className) {
            const cls = current.className.trim().split(/\s+/)[0];
            if (cls) {
                part += `.${cls}`;
            }
        }
        parts.unshift(part);
        current = current.parentElement;
    }
    return `body > ${parts.join(' > ')}`;
}

/**
 * Audit DOM size and structure.
 *
 * Runs only once and caches the result on window.aePerf.domAudit.
 *
 * @return {Promise<{totalElements:number,maxDepth:number,topOffenders:string[]}>}
 */
export function auditDom() {
    const ae = (window.aePerf = window.aePerf || {});
    if (ae.domAudit) {
        return Promise.resolve(ae.domAudit);
    }
    if (ae.domAuditPromise) {
        return ae.domAuditPromise;
    }
    const thresholds = {
        ...DEFAULT_THRESHOLDS,
        ...(window.AE_PERF_FLAGS && window.AE_PERF_FLAGS.domAuditThresholds),
    };
    ae.domAuditPromise = new Promise((resolve) => {
        idle(() => {
            let totalElements = 0;
            let maxDepth = 0;
            const topOffenders = [];

            function walk(node, depth) {
                totalElements++;
                if (depth > maxDepth) {
                    maxDepth = depth;
                }
                let descendants = 0;
                for (let child = node.firstElementChild; child; child = child.nextElementSibling) {
                    descendants += walk(child, depth + 1);
                }
                if (descendants > DESCENDANT_LIMIT) {
                    topOffenders.push(getSelector(node));
                }
                return descendants + 1;
            }

            if (document.body) {
                walk(document.body, 1);
            }
            const result = { totalElements, maxDepth, topOffenders };
            if (
                result.totalElements > thresholds.totalElements ||
                result.maxDepth > thresholds.maxDepth
            ) {
                // eslint-disable-next-line no-console
                console.warn('DOM audit thresholds exceeded', result);
            }
            ae.domAudit = result;
            delete ae.domAuditPromise;
            resolve(result);
        });
    });
    return ae.domAuditPromise;
}

/**
 * Initialize DOM audit.
 *
 * @return {void}
 */
export function init() {
    auditDom();
}
