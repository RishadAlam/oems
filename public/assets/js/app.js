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

document.querySelectorAll('[data-auto-submit]').forEach((control) => {
    control.form?.querySelector('[data-auto-submit-fallback]')?.setAttribute('hidden', '');
    control.addEventListener('change', () => control.form?.requestSubmit());
});

document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('[data-flash-message]')?.remove();
    });
});

document.querySelectorAll('[data-notification-status-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const row = form.closest('[data-notification-row]');

        row?.classList.add('notification-row--updating');
        row?.setAttribute('aria-busy', 'true');
    });
});

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

const OEMSForms = (() => {
    const fieldLabel = (control) => control?.dataset?.formLabel
        || control?.labels?.[0]?.textContent?.replace(/\s+Optional\s*$/i, '').trim()
        || control?.name?.replaceAll?.('_', ' ')
        || 'This field';

    const lowerFirst = (value) => value === '' ? value : value.charAt(0).toLowerCase() + value.slice(1);
    const hasValue = (control) => String(control?.value ?? '').trim() !== '';

    const readableFieldName = (name) => String(name ?? '')
        .replace(/\[\]$/, '')
        .replaceAll('_', ' ')
        .trim();

    const relatedControl = (form, name) => form?.elements?.namedItem?.(name) ?? null;

    const acceptedTypeNames = (accept) => {
        const labels = {
            'image/jpeg': 'JPEG',
            'image/png': 'PNG',
            'image/webp': 'WebP',
        };

        return String(accept ?? '')
            .split(',')
            .map((type) => type.trim().toLowerCase())
            .filter(Boolean)
            .map((type) => labels[type] ?? type.replace(/^\./, '').toUpperCase());
    };

    const formatList = (items) => {
        if (items.length <= 1) return items[0] ?? '';
        if (items.length === 2) return `${items[0]} or ${items[1]}`;
        return `${items.slice(0, -1).join(', ')}, or ${items.at(-1)}`;
    };

    const fileSelectionText = (control) => {
        const names = Array.from(control?.files ?? [])
            .map((file) => String(file?.name ?? '').trim())
            .filter(Boolean);

        if (names.length === 0) return 'No file selected.';
        if (names.length === 1) return `Selected: ${names[0]}`;

        const shown = names.slice(0, 3);
        const remaining = names.length - shown.length;
        const suffix = remaining > 0 ? `, and ${remaining} more` : '';
        return `${names.length} files selected: ${shown.join(', ')}${suffix}.`;
    };

    const fileMessage = (control, label) => {
        const files = Array.from(control?.files ?? []);
        const maximumFiles = Number.parseInt(control?.dataset?.maxFiles ?? '', 10);
        const maximumBytes = Number.parseInt(control?.dataset?.maxBytes ?? '', 10);
        const allowedTypes = String(control?.accept ?? '')
            .split(',')
            .map((type) => type.trim().toLowerCase())
            .filter(Boolean);

        if (Number.isFinite(maximumFiles) && files.length > maximumFiles) {
            return `${label} allows up to ${maximumFiles} files.`;
        }

        if (allowedTypes.length > 0 && files.some((file) => !allowedTypes.includes(String(file.type ?? '').toLowerCase()))) {
            return `${label} must use ${formatList(acceptedTypeNames(control.accept))} files.`;
        }

        if (Number.isFinite(maximumBytes) && files.some((file) => Number(file.size ?? 0) > maximumBytes)) {
            const megabytes = maximumBytes / (1024 * 1024);
            return `${label} files must be ${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB or smaller.`;
        }

        return '';
    };

    const messageFor = (control, form = control?.form ?? null) => {
        const label = fieldLabel(control);
        const lowerLabel = lowerFirst(label);
        const matchField = control?.dataset?.matchField;
        const afterField = control?.dataset?.afterField;
        const beforeOrEqualField = control?.dataset?.beforeOrEqualField;
        const pairedWith = control?.dataset?.pairedWith;
        const maxWhenField = control?.dataset?.maxWhenField;
        const maxWhenValue = control?.dataset?.maxWhenValue;
        const maxWhen = control?.dataset?.maxWhen;

        if (control?.type === 'file') {
            const message = fileMessage(control, label);
            if (message !== '') return message;
        }

        if (pairedWith) {
            const related = relatedControl(form, pairedWith);
            if (hasValue(control) !== hasValue(related)) {
                return `Enter both ${lowerLabel} and ${readableFieldName(pairedWith)}, or leave both blank.`;
            }
        }

        if (matchField && hasValue(control)) {
            const related = relatedControl(form, matchField);
            if (related && control.value !== related.value) {
                return `${label} must match ${readableFieldName(matchField)}.`;
            }
        }

        if (afterField && hasValue(control)) {
            const related = relatedControl(form, afterField);
            if (hasValue(related) && control.value <= related.value) {
                return `${label} must be after ${readableFieldName(afterField)}.`;
            }
        }

        if (beforeOrEqualField && hasValue(control)) {
            const related = relatedControl(form, beforeOrEqualField);
            if (hasValue(related) && control.value > related.value) {
                return `${label} must be before or equal to ${readableFieldName(beforeOrEqualField)}.`;
            }
        }

        if (maxWhenField && maxWhen !== undefined && hasValue(control)) {
            const related = relatedControl(form, maxWhenField);
            if (related?.value === maxWhenValue && Number(control.value) > Number(maxWhen)) {
                return `${label} must be no more than ${maxWhen} for ${maxWhenValue}.`;
            }
        }

        const validity = control?.validity ?? {};
        if (validity.valueMissing && control?.type === 'checkbox') return `Select ${lowerLabel} to continue.`;
        if (validity.valueMissing && control?.type === 'radio') return `Choose ${lowerLabel}.`;
        if (validity.valueMissing) return `Enter ${lowerLabel}.`;
        if (validity.typeMismatch && control?.type === 'email') return `Enter a valid ${lowerLabel}.`;
        if (validity.typeMismatch && control?.type === 'url') return `Enter a valid HTTP or HTTPS URL for ${lowerLabel}.`;
        if (validity.tooShort) return `${label} must be at least ${control.minLength} characters.`;
        if (validity.tooLong) return `${label} must be no more than ${control.maxLength} characters.`;
        if (validity.rangeUnderflow) return `${label} must be at least ${control.min}.`;
        if (validity.rangeOverflow) return `${label} must be no more than ${control.max}.`;
        if (validity.stepMismatch) return `Enter a valid increment for ${lowerLabel}.`;
        if (validity.patternMismatch || validity.badInput) return `Enter a valid ${lowerLabel}.`;
        if (validity.valid === false) return control.validationMessage || `Check ${lowerLabel}.`;

        return '';
    };

    const isValidatableControl = (candidate) => candidate?.matches?.(
        'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea',
    ) === true;

    const clientErrorFor = (form, control) => {
        const key = control.id || String(control.name ?? '').replace(/[^a-zA-Z0-9_-]+/g, '-');
        if (!key) return null;

        let error = form.querySelector?.(`[data-client-error-for="${key}"]`) ?? null;
        if (error || typeof document.createElement !== 'function') return error;

        error = document.createElement('p');
        error.id = `${key}-client-error`;
        error.className = 'field-error';
        error.dataset.clientErrorFor = key;
        error.hidden = true;
        error.setAttribute('role', 'alert');

        const group = control.closest?.('.field-group, .form-field');
        if (group?.append) group.append(error);
        else control.insertAdjacentElement?.('afterend', error);

        return error;
    };

    const updateDescription = (control, errorId, add) => {
        if (!errorId) return;
        const ids = new Set(String(control.getAttribute?.('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
        if (add) ids.add(errorId);
        else ids.delete(errorId);

        if (ids.size > 0) control.setAttribute?.('aria-describedby', Array.from(ids).join(' '));
        else control.removeAttribute?.('aria-describedby');
    };

    const validateControl = (form, control) => {
        control.setCustomValidity?.('');
        const message = messageFor(control, form);
        const error = clientErrorFor(form, control);

        if (message !== '') {
            control.setCustomValidity?.(message);
            control.setAttribute?.('aria-invalid', 'true');
            if (error) {
                error.textContent = message;
                error.hidden = false;
                updateDescription(control, error.id, true);
            }
            return false;
        }

        control.removeAttribute?.('aria-invalid');
        if (error) {
            error.textContent = '';
            error.hidden = true;
            updateDescription(control, error.id, false);
        }
        return true;
    };

    const ensureErrorSummary = (form) => {
        let summary = form.querySelector?.('[data-form-error-summary]') ?? null;
        if (summary || typeof document.createElement !== 'function') return summary;

        summary = document.createElement('section');
        summary.className = 'form-error-summary';
        summary.dataset.formErrorSummary = '';
        summary.dataset.clientGenerated = 'true';
        summary.tabIndex = -1;
        summary.setAttribute('role', 'alert');

        const heading = document.createElement('div');
        heading.className = 'form-error-summary__heading';

        const icon = document.createElement('i');
        icon.className = 'ph ph-warning-circle';
        icon.setAttribute('aria-hidden', 'true');

        const copy = document.createElement('div');
        const title = document.createElement('h2');
        title.textContent = 'Check the highlighted fields';
        const guidance = document.createElement('p');
        guidance.textContent = 'Correct the highlighted information, then submit the form again.';
        copy.append(title, guidance);
        heading.append(icon, copy);
        summary.append(heading);
        form.prepend?.(summary);

        return summary;
    };

    const ensureClientErrorList = (summary) => {
        if (!summary) return null;
        let list = summary.querySelector?.('[data-client-error-list]') ?? null;
        if (list || typeof document.createElement !== 'function') return list;

        list = document.createElement('ul');
        list.dataset.clientErrorList = '';
        summary.append?.(list);
        return list;
    };

    const controlTargetId = (control) => {
        const existing = String(control?.id ?? '').trim();
        if (existing !== '') return existing;

        const generated = String(control?.name ?? 'field')
            .replace(/\[\]$/, '')
            .replace(/[^a-zA-Z0-9_-]+/g, '-');
        if (generated !== '') control?.setAttribute?.('id', generated);
        return generated;
    };

    const updateFileSelectionStatus = (form, control) => {
        if (control?.type !== 'file') return;
        const target = controlTargetId(control);
        if (target === '') return;

        let status = form.querySelector?.(`[data-file-status-for="${target}"]`) ?? null;
        const hasFiles = Array.from(control.files ?? []).length > 0;
        if (!status && !hasFiles) return;

        if (!status && typeof document.createElement === 'function') {
            status = document.createElement('p');
            status.id = `${target}-file-status`;
            status.className = 'field-help field-file-status';
            status.dataset.fileStatusFor = target;
            status.setAttribute('aria-live', 'polite');
            control.insertAdjacentElement?.('afterend', status);
        }

        if (!status) return;
        status.textContent = fileSelectionText(control);
        status.hidden = !hasFiles;
        updateDescription(control, status.id, hasFiles);
    };

    const updateErrorSummary = (form, invalidControls, focus = false) => {
        const summary = invalidControls.length > 0
            ? ensureErrorSummary(form)
            : form.querySelector?.('[data-form-error-summary]') ?? null;
        if (!summary) return;

        const list = ensureClientErrorList(summary);
        const serverTargets = new Set(
            Array.from(summary.querySelectorAll?.('a[href^="#"]') ?? [])
                .filter((link) => link.dataset?.clientErrorLink === undefined)
                .map((link) => link.getAttribute?.('href'))
                .filter(Boolean),
        );
        const entries = [];

        invalidControls.forEach((control) => {
            const target = controlTargetId(control);
            if (target === '' || serverTargets.has(`#${target}`) || typeof document.createElement !== 'function') return;

            const item = document.createElement('li');
            const link = document.createElement('a');
            link.dataset.clientErrorLink = '';
            link.setAttribute('href', `#${target}`);
            link.textContent = `${fieldLabel(control)}: ${messageFor(control, form)}`;
            item.append?.(link);
            entries.push(item);
        });

        list?.replaceChildren?.(...entries);
        if (list) list.hidden = entries.length === 0;

        const hasErrors = invalidControls.length > 0 || serverTargets.size > 0;
        summary.hidden = !hasErrors;
        if (focus && hasErrors) summary.focus?.({ preventScroll: true });
    };

    const invalidControlsFromState = (form) => Array.from(
        form.querySelectorAll?.('input, select, textarea') ?? [],
    ).filter((control) => isValidatableControl(control) && control.getAttribute?.('aria-invalid') === 'true');

    const clearServerErrorForControl = (form, control) => {
        const target = controlTargetId(control);
        const summary = form.querySelector?.('[data-form-error-summary]') ?? null;
        if (summary && target !== '') {
            Array.from(summary.querySelectorAll?.('a[href^="#"]') ?? []).forEach((link) => {
                if (link.dataset?.clientErrorLink !== undefined || link.getAttribute?.('href') !== `#${target}`) return;
                link.closest?.('li')?.remove?.();
            });
        }

        String(control.getAttribute?.('aria-describedby') ?? '')
            .split(/\s+/)
            .filter(Boolean)
            .forEach((id) => {
                const described = form.querySelector?.(`#${id}`) ?? null;
                if (!described?.classList?.contains?.('field-error') || described.dataset?.clientErrorFor !== undefined) return;
                described.hidden = true;
                updateDescription(control, id, false);
            });
    };

    const validateForm = (form) => {
        const controls = Array.from(form.querySelectorAll?.('input, select, textarea') ?? [])
            .filter(isValidatableControl);
        const invalidControls = [];

        controls.forEach((control) => {
            if (!validateControl(form, control)) invalidControls.push(control);
        });

        return invalidControls;
    };

    const enhanceForm = (form) => {
        if (!form || form.dataset?.formEnhanced === 'true') return;
        form.dataset.formEnhanced = 'true';
        const touched = new WeakSet();

        form.addEventListener('focusout', (event) => {
            const control = event.target;
            if (!isValidatableControl(control)) return;
            touched.add(control);
            validateControl(form, control);
            if (form.dataset?.validationAttempted === 'true') {
                updateErrorSummary(form, invalidControlsFromState(form));
            }
        });

        const revalidateEditedControl = (event) => {
            const control = event.target;
            if (!isValidatableControl(control)) return;
            updateFileSelectionStatus(form, control);
            clearServerErrorForControl(form, control);
            if (touched.has(control) || control.getAttribute?.('aria-invalid') === 'true') {
                validateControl(form, control);
            }

            form.querySelectorAll?.('input, select, textarea').forEach((dependent) => {
                if (dependent === control) return;
                const dependsOnEditedField = [
                    dependent.dataset?.matchField,
                    dependent.dataset?.afterField,
                    dependent.dataset?.beforeOrEqualField,
                    dependent.dataset?.pairedWith,
                    dependent.dataset?.maxWhenField,
                ].includes(control.name);

                if (dependsOnEditedField
                    && (touched.has(dependent) || dependent.getAttribute?.('aria-invalid') === 'true')) {
                    validateControl(form, dependent);
                }
            });

            if (form.dataset?.validationAttempted === 'true'
                || form.querySelector?.('[data-form-error-summary]')) {
                updateErrorSummary(form, invalidControlsFromState(form));
            }
        };

        form.addEventListener('input', revalidateEditedControl);
        form.addEventListener('change', revalidateEditedControl);

        form.addEventListener('invalid', (event) => {
            const control = event.target;
            if (!isValidatableControl(control)) return;
            event.preventDefault();
            touched.add(control);
            form.dataset.validationAttempted = 'true';
            const invalidControls = validateForm(form);
            updateErrorSummary(form, invalidControls, invalidControls[0] === control);
        }, true);

        form.addEventListener('submit', (event) => {
            if (String(form.method ?? 'get').toLowerCase() === 'get') return;

            if (form.dataset?.submitting === 'true') {
                event.preventDefault();
                return;
            }

            const invalidControls = validateForm(form);
            if (invalidControls.length > 0 || form.checkValidity?.() === false) {
                event.preventDefault();
                form.dataset.validationAttempted = 'true';
                updateErrorSummary(form, invalidControls, true);
                return;
            }

            updateErrorSummary(form, []);

            const confirmation = form.dataset?.confirm;
            if (confirmation && !window.confirm(confirmation)) {
                event.preventDefault();
                return;
            }

            const submitter = event.submitter;
            form.dataset.submitting = 'true';
            form.setAttribute?.('aria-busy', 'true');

            if (!submitter || submitter.disabled) return;

            submitter.setAttribute?.('aria-busy', 'true');
            submitter.dataset.submitLocked = 'true';
            submitter.disabled = true;

            const progress = submitter.dataset?.submitLabel;
            if (!progress) return;

            const text = submitter.querySelector?.('[data-submit-text], span');
            if (text) text.textContent = progress;
            else submitter.textContent = progress;
        });
    };

    const resetSubmissionState = (form) => {
        if (!form) return;
        delete form.dataset?.submitting;
        form.removeAttribute?.('aria-busy');
        form.querySelectorAll?.('[data-submit-locked="true"]').forEach((submitter) => {
            submitter.disabled = false;
            submitter.removeAttribute?.('aria-busy');
            delete submitter.dataset?.submitLocked;
        });
    };

    return { enhanceForm, fileSelectionText, messageFor, resetSubmissionState };
})();

window.OEMSForms = OEMSForms;
document.querySelectorAll('form[data-form-kind]').forEach((form) => OEMSForms.enhanceForm(form));
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-form-kind]').forEach((form) => OEMSForms.resetSubmissionState(form));
});
