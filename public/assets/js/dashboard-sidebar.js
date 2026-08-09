(() => {
    const sidebar = document.querySelector('[data-dashboard-sidebar]');
    const overlay = document.querySelector('[data-dashboard-overlay]');
    const openButton = document.querySelector('[data-dashboard-open]');
    const closeButton = document.querySelector('[data-dashboard-close]');
    const background = document.querySelector('[data-dashboard-main]');

    if (!sidebar || !overlay || !openButton || !closeButton || !background) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 64rem)');
    let previousOverflow = '';

    const setBackgroundInert = (inert) => {
        background.inert = inert;
        if (inert) {
            background.setAttribute('inert', '');
        } else {
            background.removeAttribute('inert');
        }
    };

    const close = (restoreFocus = false) => {
        const wasOpen = sidebar.classList.contains('is-open');
        sidebar.classList.remove('is-open');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        overlay.hidden = true;
        openButton.setAttribute('aria-expanded', 'false');
        setBackgroundInert(false);
        document.body.style.overflow = previousOverflow;

        if (desktopQuery.matches) {
            sidebar.removeAttribute('aria-hidden');
        } else {
            sidebar.setAttribute('aria-hidden', 'true');
        }

        if (restoreFocus && wasOpen) {
            openButton.focus();
        }
    };

    const open = () => {
        if (desktopQuery.matches || sidebar.classList.contains('is-open')) {
            return;
        }

        previousOverflow = document.body.style.overflow;
        sidebar.classList.add('is-open');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        sidebar.setAttribute('aria-hidden', 'false');
        overlay.hidden = false;
        openButton.setAttribute('aria-expanded', 'true');
        setBackgroundInert(true);
        document.body.style.overflow = 'hidden';
        closeButton.focus();
    };

    const focusableElements = () => Array.from(sidebar.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )).filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');

    openButton.addEventListener('click', open);
    closeButton.addEventListener('click', () => close(true));
    overlay.addEventListener('click', () => close(true));
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopQuery.matches) {
                close(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (!sidebar.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = focusableElements();
        if (focusable.length === 0) {
            event.preventDefault();
            closeButton.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const syncBreakpoint = () => close(false);
    desktopQuery.addEventListener?.('change', syncBreakpoint);
    window.addEventListener('pagehide', () => close(false));
    window.addEventListener('pageshow', () => close(false));
    syncBreakpoint();
})();
