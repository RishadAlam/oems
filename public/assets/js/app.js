const root = document.documentElement;

function readStoredTheme() {
    try {
        const stored = window.localStorage.getItem('oems-theme');
        return ['light', 'dark'].includes(stored) ? stored : null;
    } catch (error) {
        return null;
    }
}

function writeStoredTheme(theme) {
    try {
        window.localStorage.setItem('oems-theme', theme);
    } catch (error) {
        // Theme selection still works for this page when storage is unavailable.
    }
}

function setTheme(theme, persist = false) {
    root.dataset.theme = theme;
    const nextTheme = theme === 'dark' ? 'light' : 'dark';

    if (persist) {
        writeStoredTheme(theme);
    }

    const themeColor = document.querySelector('meta[name="theme-color"]');
    themeColor?.setAttribute('content', theme === 'dark' ? '#0d1420' : '#f5f7fb');

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = `Switch to ${nextTheme} theme`;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        const icon = button.querySelector('[data-theme-icon]');
        if (icon) {
            icon.classList.toggle('ph-moon', nextTheme === 'dark');
            icon.classList.toggle('ph-sun', nextTheme === 'light');
        }

        const text = button.querySelector('[data-theme-label]');
        if (text) {
            text.textContent = label;
        }
    });
}

const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
setTheme(root.dataset.theme || readStoredTheme() || (systemTheme.matches ? 'dark' : 'light'));

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
});

systemTheme.addEventListener?.('change', (event) => {
    if (readStoredTheme() === null) {
        setTheme(event.matches ? 'dark' : 'light');
    }
});

const menuButton = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

if (menuButton && mobileMenu) {
    const desktopMenuQuery = window.matchMedia('(min-width: 64rem)');

    const closeMobileMenu = (restoreFocus = true) => {
        if (mobileMenu.hidden) {
            return;
        }

        mobileMenu.hidden = true;
        menuButton.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            menuButton.focus();
        }
    };

    const openMobileMenu = () => {
        mobileMenu.hidden = false;
        menuButton.setAttribute('aria-expanded', 'true');
        mobileMenu.querySelector('a, button')?.focus();
    };

    menuButton.addEventListener('click', () => {
        if (mobileMenu.hidden) {
            openMobileMenu();
        } else {
            closeMobileMenu();
        }
    });

    mobileMenu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => closeMobileMenu());
    });

    const syncMenuToViewport = (event) => {
        if (event.matches) {
            closeMobileMenu(false);
        }
    };

    desktopMenuQuery.addEventListener?.('change', syncMenuToViewport);

    document.addEventListener('click', (event) => {
        if (!mobileMenu.hidden && !mobileMenu.contains(event.target) && !menuButton.contains(event.target)) {
            closeMobileMenu(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !mobileMenu.hidden) {
            closeMobileMenu();
        }
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const wasShowing = input.type === 'text';
        const isShowing = !wasShowing;
        const fieldName = button.dataset.passwordLabel || 'password';
        const label = `${isShowing ? 'Hide' : 'Show'} ${fieldName}`;

        input.type = isShowing ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(isShowing));
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        const icon = button.querySelector('.ph');
        icon?.classList.toggle('ph-eye', !isShowing);
        icon?.classList.toggle('ph-eye-slash', isShowing);
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
    openSidebar?.setAttribute('aria-expanded', String(open));
    sidebar.setAttribute('aria-hidden', String(!open));
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        closeSidebar?.focus();
    }
}

openSidebar?.addEventListener('click', () => toggleSidebar(true));
closeSidebar?.addEventListener('click', () => {
    toggleSidebar(false);
    openSidebar?.focus();
});
overlay?.addEventListener('click', () => {
    toggleSidebar(false);
    openSidebar?.focus();
});

sidebar?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
        if (!window.matchMedia('(min-width: 64rem)').matches) {
            toggleSidebar(false);
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) {
        toggleSidebar(false);
        openSidebar?.focus();
    }
});

const desktopDashboard = window.matchMedia('(min-width: 64rem)');

function syncDashboardBreakpoint(event) {
    if (!sidebar || !overlay || !openSidebar) {
        return;
    }

    if (event.matches) {
        sidebar.classList.remove('is-open');
        sidebar.removeAttribute('aria-hidden');
        overlay.hidden = true;
        openSidebar.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        return;
    }

    sidebar.setAttribute('aria-hidden', String(!sidebar.classList.contains('is-open')));
}

syncDashboardBreakpoint(desktopDashboard);
desktopDashboard.addEventListener?.('change', syncDashboardBreakpoint);

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

const checkInCamera = document.querySelector('[data-check-in-camera]');

if (checkInCamera) {
    const startCamera = checkInCamera.querySelector('[data-check-in-camera-start]');
    const stopCamera = checkInCamera.querySelector('[data-check-in-camera-stop]');
    const video = checkInCamera.querySelector('[data-check-in-video]');
    const cameraStatus = checkInCamera.querySelector('[data-check-in-camera-status]');
    const codeInput = document.querySelector('#ticket-code');
    const checkInForm = document.querySelector('[data-check-in-form]');
    let cameraStream = null;
    let scanFrame = null;
    let scanning = false;
    let cameraStarting = false;
    let cameraGeneration = 0;

    const setCameraStatus = (message) => {
        if (cameraStatus) {
            cameraStatus.textContent = message;
        }
    };

    const stopCheckInCamera = () => {
        cameraGeneration += 1;
        cameraStarting = false;
        scanning = false;

        if (scanFrame !== null) {
            cancelAnimationFrame(scanFrame);
            scanFrame = null;
        }

        cameraStream?.getTracks().forEach((track) => track.stop());
        cameraStream = null;

        if (video) {
            video.srcObject = null;
            video.hidden = true;
        }

        if (startCamera) {
            startCamera.hidden = false;
            startCamera.disabled = false;
        }

        if (stopCamera) {
            stopCamera.hidden = true;
        }
    };

    const cameraSupported = 'BarcodeDetector' in globalThis
        && typeof navigator.mediaDevices?.getUserMedia === 'function';

    if (!cameraSupported) {
        setCameraStatus('Camera scanning is not supported here. Enter the printed ticket number instead.');
        if (startCamera) {
            startCamera.hidden = true;
        }
    } else {
        startCamera?.addEventListener('click', async () => {
            if (cameraStarting || scanning) {
                return;
            }

            const generation = ++cameraGeneration;
            const isCurrentGeneration = () => generation === cameraGeneration && !document.hidden;
            let acquiredStream = null;
            cameraStarting = true;
            startCamera.disabled = true;

            try {
                const formats = typeof BarcodeDetector.getSupportedFormats === 'function'
                    ? await BarcodeDetector.getSupportedFormats()
                    : ['qr_code'];

                if (!isCurrentGeneration()) {
                    return;
                }

                if (!formats.includes('qr_code')) {
                    setCameraStatus('QR scanning is not supported here. Enter the printed ticket number instead.');
                    startCamera.hidden = true;
                    return;
                }

                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                acquiredStream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: { ideal: 'environment' } },
                });

                if (!isCurrentGeneration()) {
                    acquiredStream.getTracks().forEach((track) => track.stop());
                    acquiredStream = null;
                    return;
                }

                cameraStream = acquiredStream;
                acquiredStream = null;
                video.srcObject = cameraStream;
                video.hidden = false;
                startCamera.hidden = true;
                stopCamera.hidden = false;
                scanning = true;
                setCameraStatus('Camera active. Hold the ticket QR code inside the frame.');
                await video.play();

                if (!isCurrentGeneration()) {
                    return;
                }

                const detectCode = async () => {
                    if (!scanning || !isCurrentGeneration()) {
                        return;
                    }

                    try {
                        const codes = await detector.detect(video);

                        if (!scanning || !isCurrentGeneration()) {
                            return;
                        }

                        const value = codes.find((code) => typeof code.rawValue === 'string')?.rawValue;

                        if (value && codeInput && checkInForm) {
                            codeInput.value = value;
                            stopCheckInCamera();
                            setCameraStatus('QR code detected. Submitting check-in.');
                            checkInForm.requestSubmit();
                            return;
                        }
                    } catch (error) {
                        if (!isCurrentGeneration()) {
                            return;
                        }

                        stopCheckInCamera();
                        setCameraStatus('The camera could not read this code. Enter the printed ticket number instead.');
                        return;
                    }

                    if (scanning && isCurrentGeneration()) {
                        scanFrame = requestAnimationFrame(detectCode);
                    }
                };

                scanFrame = requestAnimationFrame(detectCode);
            } catch (error) {
                acquiredStream?.getTracks().forEach((track) => track.stop());

                if (!isCurrentGeneration()) {
                    return;
                }

                stopCheckInCamera();
                const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
                setCameraStatus(denied
                    ? 'Camera permission was not granted. Enter the printed ticket number instead.'
                    : 'The camera is unavailable. Enter the printed ticket number instead.');
            } finally {
                if (generation === cameraGeneration) {
                    cameraStarting = false;
                    startCamera.disabled = false;
                }
            }
        });

        stopCamera?.addEventListener('click', () => {
            stopCheckInCamera();
            setCameraStatus('Camera stopped. You can enter the printed ticket number.');
        });

        window.addEventListener('pagehide', stopCheckInCamera);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopCheckInCamera();
            }
        });
    }
}
