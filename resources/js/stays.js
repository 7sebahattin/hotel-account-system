function initStayRowNavigation(row) {
    if (row.dataset.stayRowNavigationReady) return;
    row.dataset.stayRowNavigationReady = '1';

    const navigate = () => {
        const url = row.dataset.stayRowUrl;
        if (url) window.location.assign(url);
    };
    const interactiveSelector = 'a, button, input, select, textarea, label, summary, [role="button"], [role="link"]';
    const isInteractive = (target) => {
        if (!(target instanceof Element)) return false;
        const interactive = target.closest(interactiveSelector);

        return interactive !== null && interactive !== row;
    };

    row.addEventListener('click', (event) => {
        if (!isInteractive(event.target)) navigate();
    });
    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !isInteractive(event.target)) {
            event.preventDefault();
            navigate();
        }
    });
}

if (typeof document !== 'undefined') {
    const initialize = () => {
        document.querySelectorAll('[data-stay-row-url]').forEach(initStayRowNavigation);
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
