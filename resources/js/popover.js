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
        if (document.querySelector('details[data-popover]')) initPopoverDismiss();
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
