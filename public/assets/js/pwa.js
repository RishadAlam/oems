(() => {
    'use strict';

    const installButton = document.querySelector('[data-pwa-install]');
    let installPrompt = null;

    window.addEventListener('load', async () => {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        try {
            await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/',
                updateViaCache: 'none',
            });
        } catch {
            // The server-rendered application remains fully usable without PWA support.
        }
    });

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        if (installButton) {
            installButton.hidden = false;
            installButton.disabled = false;
        }
    });

    installButton?.addEventListener('click', async () => {
        if (!installPrompt) {
            return;
        }
        installButton.disabled = true;
        const prompt = installPrompt;
        installPrompt = null;
        try {
            await prompt.prompt();
            await prompt.userChoice;
        } finally {
            installButton.hidden = true;
            installButton.disabled = false;
        }
    });

    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        if (installButton) {
            installButton.hidden = true;
            installButton.disabled = false;
        }
    });
})();
