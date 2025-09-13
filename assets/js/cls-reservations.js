/**
 * Reserve space to prevent layout shifts.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.clsReservations || {};
    const reservations = Array.isArray(cfg.reservations) ? cfg.reservations : [];

    const detectSticky = (selector, isHeader) => {
        const nodes = document.querySelectorAll(selector);
        let height = 0;
        nodes.forEach((el) => {
            const style = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            if (style.position === 'fixed') {
                if (isHeader && Math.abs(rect.top) < 1) {
                    height = Math.max(height, rect.height);
                }
                if (!isHeader && Math.abs(rect.bottom - window.innerHeight) < 1) {
                    height = Math.max(height, rect.height);
                }
            }
        });
        if (height) {
            if (isHeader) {
                document.body.style.paddingTop = `${height}px`;
            } else {
                document.body.style.paddingBottom = `${height}px`;
            }
        }
    };

    window.requestAnimationFrame(() => {
        if (cfg.stickyHeader) {
            detectSticky(cfg.stickyHeaderSelector || '[data-sticky="true"]', true);
        }
        if (cfg.stickyFooter) {
            detectSticky(cfg.stickyFooterSelector || '[data-sticky="true"]', false);
        }
    });

    const reserve = (el, res) => {
        if (el.classList.contains('cls-reserved-box')) {
            return;
        }
        if (res.min > 0) {
            el.style.minHeight = `${res.min}px`;
        }
        el.classList.add('cls-reserved-box');
        if (!res.unreserve) {
            return;
        }
        let done = false;
        const release = () => {
            if (done) {
                return;
            }
            done = true;
            el.style.minHeight = '';
            el.classList.remove('cls-reserved-box');
            el.removeEventListener('load', release, true);
            adsObs.disconnect();
            clearInterval(interval);
            clearTimeout(timer);
        };
        el.addEventListener('load', release, true);
        const checkAds = () => {
            el.querySelectorAll('.adsbygoogle').forEach((ad) => {
                if (ad.clientHeight > 0 || ad.dataset.adStatus === 'filled') {
                    release();
                }
            });
        };
        const adsObs = new MutationObserver(checkAds);
        adsObs.observe(el, { childList: true, subtree: true });
        const interval = setInterval(checkAds, 200);
        const timer = setTimeout(release, 3000);
        checkAds();
        el.querySelectorAll('img,iframe').forEach((m) => {
            if ((m.tagName === 'IMG' && m.complete) || (m.tagName === 'IFRAME' && m.complete)) {
                release();
            }
        });
    };

    const applyReservations = (root) => {
        reservations.forEach((res) => {
            if (root.matches && root.matches(res.selector)) {
                reserve(root, res);
            }
            root.querySelectorAll && root.querySelectorAll(res.selector).forEach((el) => reserve(el, res));
        });
    };

    applyReservations(document);

    const mo = new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) {
                    return;
                }
                applyReservations(node);
            });
        });
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
});
