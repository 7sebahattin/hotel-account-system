/*
 * Mobil kenar çubuğu çekmecesi.
 *
 * Harici bir JS paketi eklenmedi: bu davranış için birkaç satır yeterli.
 * Masaüstünde kenar çubuğu zaten sabit; bu kod yalnızca mobil çekmeceyi açar/kapatır.
 */
const drawer = {
    panel: null,
    overlay: null,
    toggle: null,

    open() {
        this.panel?.classList.remove('-translate-x-full');
        this.overlay?.classList.remove('hidden');
        this.toggle?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden', 'md:overflow-auto');
    },

    close() {
        this.panel?.classList.add('-translate-x-full');
        this.overlay?.classList.add('hidden');
        this.toggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden', 'md:overflow-auto');
    },
};

document.addEventListener('DOMContentLoaded', () => {
    drawer.panel = document.querySelector('[data-sidebar]');
    drawer.overlay = document.querySelector('[data-sidebar-overlay]');
    drawer.toggle = document.querySelector('[data-sidebar-toggle]');

    if (!drawer.panel || !drawer.toggle) {
        return;
    }

    drawer.toggle.addEventListener('click', () => {
        drawer.panel.classList.contains('-translate-x-full') ? drawer.open() : drawer.close();
    });

    drawer.overlay?.addEventListener('click', () => drawer.close());

    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => drawer.close());

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            drawer.close();
        }
    });
});
