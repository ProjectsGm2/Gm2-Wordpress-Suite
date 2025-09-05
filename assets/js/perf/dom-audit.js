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
const BADGE_ID = 'ae-dom-audit';

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

function renderBadge(result, thresholds) {
    const bar = document.getElementById('wp-admin-bar-top-secondary');
    if (!bar) {
        return;
    }
    let li = document.getElementById(`wp-admin-bar-${BADGE_ID}`);
    if (!li) {
        li = document.createElement('li');
        li.id = `wp-admin-bar-${BADGE_ID}`;
        const a = document.createElement('a');
        a.className = 'ab-item';
        li.appendChild(a);
        bar.appendChild(li);
    }
    const link = li.querySelector('a');
    if (!link) {
        return;
    }
    link.textContent = `DOM: ${result.totalElements} / d${result.maxDepth}`;
    const ratios = {
        total: result.totalElements / thresholds.totalElements,
        depth: result.maxDepth / thresholds.maxDepth,
    };
    const maxRatio = Math.max(ratios.total, ratios.depth);
    let color = '#46b450'; // green
    if (maxRatio >= 1) {
        color = '#dc3232'; // red
    } else if (maxRatio >= 0.8) {
        color = '#ffb900'; // amber
    }
    link.style.background = color;
    link.style.color = '#fff';
}

/**
 * Initialize DOM audit.
 *
 * @return {void}
 */
export function init() {
    const flags = window.AE_PERF_FLAGS || {};
    if (!window.wp || !flags.isAdmin) {
        return;
    }
    const thresholds = {
        ...DEFAULT_THRESHOLDS,
        ...(flags.domAuditThresholds || {}),
    };
    const ae = (window.aePerf = window.aePerf || {});

    function runAndRender() {
        auditDom().then((res) => renderBadge(res, thresholds));
    }

    ae.refreshDomAudit = () => {
        delete ae.domAudit;
        delete ae.domAuditPromise;
        runAndRender();
    };

    if (ae.domAudit) {
        renderBadge(ae.domAudit, thresholds);
    } else {
        runAndRender();
    }
}
