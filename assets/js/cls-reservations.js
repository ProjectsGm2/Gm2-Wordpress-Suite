/**
 * Reserve space to prevent layout shifts.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.clsReservations || {};
    const reservations = Array.isArray(cfg.reservations) ? cfg.reservations : [];

    const updateStickyPadding = () => {
        if (!cfg.stickyHeader && !cfg.stickyFooter) {
            return;
        }
        let top = 0;
        let bottom = 0;
        document.querySelectorAll('.cls-reserved-box').forEach((box) => {
            const style = getComputedStyle(box);
            const rect = box.getBoundingClientRect();
            if (cfg.stickyHeader && style.position === 'fixed' && Math.abs(rect.top) < 1) {
                top = Math.max(top, rect.height);
            }
            if (cfg.stickyFooter && style.position === 'fixed' && Math.abs(rect.bottom - window.innerHeight) < 1) {
                bottom = Math.max(bottom, rect.height);
            }
        });
        document.body.style.paddingTop = top ? `${top}px` : '';
        document.body.style.paddingBottom = bottom ? `${bottom}px` : '';
    };

    const reserve = (el, res) => {
        if (el.classList.contains('cls-reserved-box')) {
            return;
        }
        if (res.min > 0) {
            el.style.minHeight = `${res.min}px`;
        }
        el.classList.add('cls-reserved-box');
        updateStickyPadding();
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
            updateStickyPadding();
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
