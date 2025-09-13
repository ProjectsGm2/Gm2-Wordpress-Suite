/**
 * Reserve aspect ratios for media.
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-aspect]').forEach((el) => {
        const ratio = el.dataset.aspect;
        if (ratio) {
            el.style.aspectRatio = ratio;
        }
    });
    document.querySelectorAll('img[data-w][data-h]').forEach((img) => {
        if (!img.getAttribute('width') && !img.getAttribute('height')) {
            const w = parseInt(img.dataset.w, 10);
            const h = parseInt(img.dataset.h, 10);
            if (w > 0 && h > 0) {
                img.style.aspectRatio = `${w} / ${h}`;
            }
        }
    });
});
