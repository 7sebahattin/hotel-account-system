function initStayRowNavigation(row) {
    if (row.dataset.stayRowNavigationReady) return;
    row.dataset.stayRowNavigationReady = '1';

    const navigate = () => {
        const url = row.dataset.stayRowUrl;
        if (url) window.location.assign(url);
    };
    const interactiveSelector = 'a, button, input, select, textarea, label, summary, details, [role="button"], [role="link"]';
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

/*
    Açılır paneller (`<details data-popover>`: satır menüsü, sıralama, "Daha
    fazla filtre") dışarı tıklanınca veya Escape ile kapanır. JS yoksa native
    <details> davranışı aynen çalışır; bu yalnızca bir kolaylıktır.
*/
function initPopoverDismiss() {
    if (document.documentElement.dataset.popoverDismissReady) return;
    document.documentElement.dataset.popoverDismissReady = '1';

    const closeAll = (except) => {
        document.querySelectorAll('details[data-popover][open]').forEach((details) => {
            if (details !== except) details.removeAttribute('open');
        });
    };

    document.addEventListener('click', (event) => {
        const inside = event.target instanceof Element ? event.target.closest('details[data-popover]') : null;
        closeAll(inside);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const open = document.querySelector('details[data-popover][open]');
        closeAll(null);
        open?.querySelector('summary')?.focus();
    });
}

if (typeof document !== 'undefined') {
    const initialize = () => {
        document.querySelectorAll('[data-stay-row-url]').forEach(initStayRowNavigation);
        if (document.querySelector('details[data-popover]')) initPopoverDismiss();
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
