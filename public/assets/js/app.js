const root = document.documentElement;

function setTheme(theme) {
    root.dataset.theme = theme;
    localStorage.setItem('oems-theme', theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        button.textContent = `Use ${nextTheme} theme`;
        button.setAttribute('aria-label', `Use ${nextTheme} theme`);
    });
}

setTheme(root.dataset.theme || 'light');

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
    });
});

const menuButton = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

if (menuButton && mobileMenu) {
    menuButton.addEventListener('click', () => {
        const opening = mobileMenu.hidden;
        mobileMenu.hidden = !opening;
        menuButton.setAttribute('aria-expanded', String(opening));
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
    });
});

document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('[data-flash-message]')?.remove();
    });
});

const sidebar = document.querySelector('[data-dashboard-sidebar]');
const overlay = document.querySelector('[data-dashboard-overlay]');
const openSidebar = document.querySelector('[data-dashboard-open]');
const closeSidebar = document.querySelector('[data-dashboard-close]');

function toggleSidebar(open) {
    if (!sidebar || !overlay) {
        return;
    }

    sidebar.classList.toggle('is-open', open);
    overlay.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
}

openSidebar?.addEventListener('click', () => toggleSidebar(true));
closeSidebar?.addEventListener('click', () => toggleSidebar(false));
overlay?.addEventListener('click', () => toggleSidebar(false));

if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    const revealItems = document.querySelectorAll('[data-reveal]');
    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            currentObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12 });

    revealItems.forEach((item) => {
        item.classList.add('reveal-pending');
        observer.observe(item);
    });
}

