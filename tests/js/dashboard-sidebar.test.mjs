import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/dashboard-sidebar.js', import.meta.url), 'utf8');

class ElementStub {
    constructor(document) {
        this.attributes = new Map();
        this.classNames = new Set();
        this.classList = {
            add: (name) => this.classNames.add(name),
            contains: (name) => this.classNames.has(name),
            remove: (name) => this.classNames.delete(name),
            toggle: (name, force) => force ? this.classNames.add(name) : this.classNames.delete(name),
        };
        this.document = document;
        this.hidden = false;
        this.inert = false;
        this.listeners = new Map();
    }

    addEventListener(type, callback) { this.listeners.set(type, callback); }
    click() { this.listeners.get('click')?.({ target: this }); }
    focus() { this.document.activeElement = this; }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    hasAttribute(name) { return this.attributes.has(name); }
    removeAttribute(name) { this.attributes.delete(name); }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
}

function createHarness() {
    const documentListeners = new Map();
    const windowListeners = new Map();
    const mediaListeners = new Map();
    const document = {
        activeElement: null,
        body: { style: { overflow: '' } },
        addEventListener(type, callback) { documentListeners.set(type, callback); },
    };
    const sidebar = new ElementStub(document);
    const overlay = new ElementStub(document);
    const open = new ElementStub(document);
    const close = new ElementStub(document);
    const link = new ElementStub(document);
    const logout = new ElementStub(document);
    const main = new ElementStub(document);
    overlay.hidden = true;
    open.setAttribute('aria-expanded', 'false');
    sidebar.querySelectorAll = () => [close, link, logout];
    document.querySelector = (selector) => ({
        '[data-dashboard-sidebar]': sidebar,
        '[data-dashboard-overlay]': overlay,
        '[data-dashboard-open]': open,
        '[data-dashboard-close]': close,
        '[data-dashboard-main]': main,
    }[selector] ?? null);
    let desktop = false;
    const media = {
        get matches() { return desktop; },
        addEventListener(type, callback) { if (type === 'change') mediaListeners.set('desktop', callback); },
    };
    const sandbox = {
        document,
        window: {
            addEventListener(type, callback) { windowListeners.set(type, callback); },
            matchMedia() { return media; },
        },
    };
    vm.runInNewContext(source, sandbox, { filename: 'dashboard-sidebar.js' });

    return {
        close,
        document,
        link,
        logout,
        main,
        open,
        overlay,
        sidebar,
        keydown(key, shiftKey = false) {
            let prevented = false;
            documentListeners.get('keydown')?.({ key, shiftKey, preventDefault() { prevented = true; } });
            return prevented;
        },
        desktop(value) { desktop = value; mediaListeners.get('desktop')?.({ matches: value }); },
        pagehide(persisted = true) { windowListeners.get('pagehide')?.({ persisted }); },
        pageshow(persisted = true) { windowListeners.get('pageshow')?.({ persisted }); },
    };
}

test('mobile sidebar makes the background inert, exposes dialog semantics, and traps focus', () => {
    const harness = createHarness();
    harness.open.click();

    assert.equal(harness.sidebar.classList.contains('is-open'), true);
    assert.equal(harness.sidebar.getAttribute('role'), 'dialog');
    assert.equal(harness.sidebar.getAttribute('aria-modal'), 'true');
    assert.equal(harness.main.inert, true);
    assert.equal(harness.main.hasAttribute('inert'), true);
    assert.equal(harness.overlay.hidden, false);
    assert.equal(harness.document.activeElement, harness.close);

    harness.logout.focus();
    assert.equal(harness.keydown('Tab'), true);
    assert.equal(harness.document.activeElement, harness.close);
    harness.close.focus();
    assert.equal(harness.keydown('Tab', true), true);
    assert.equal(harness.document.activeElement, harness.logout);
});

test('Escape closes the sidebar, restores the trigger, and removes modal state', () => {
    const harness = createHarness();
    harness.open.click();
    harness.keydown('Escape');

    assert.equal(harness.sidebar.classList.contains('is-open'), false);
    assert.equal(harness.sidebar.hasAttribute('role'), false);
    assert.equal(harness.sidebar.hasAttribute('aria-modal'), false);
    assert.equal(harness.main.inert, false);
    assert.equal(harness.main.hasAttribute('inert'), false);
    assert.equal(harness.document.body.style.overflow, '');
    assert.equal(harness.document.activeElement, harness.open);
});

test('desktop changes and bfcache lifecycle clean up all mobile sidebar state', () => {
    const desktop = createHarness();
    desktop.open.click();
    desktop.desktop(true);
    assert.equal(desktop.sidebar.getAttribute('aria-hidden'), null);
    assert.equal(desktop.main.inert, false);
    assert.equal(desktop.overlay.hidden, true);

    const cached = createHarness();
    cached.open.click();
    cached.pagehide();
    assert.equal(cached.main.inert, false);
    cached.pageshow();
    assert.equal(cached.sidebar.classList.contains('is-open'), false);
    assert.equal(cached.open.getAttribute('aria-expanded'), 'false');
});
