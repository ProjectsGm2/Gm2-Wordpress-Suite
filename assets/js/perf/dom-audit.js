/**
 * Log DOM size metrics for auditing.
 *
 * @return {void}
 */
export function init() {
    requestAnimationFrame(() => {
        const elements = document.getElementsByTagName('*').length;
        // eslint-disable-next-line no-console
        console.log('DOM size:', elements);
    });
}
