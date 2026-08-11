import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8');

function loadFormApi() {
    const media = { matches: false, addEventListener() {} };
    const sandbox = {
        console,
        document: {
            documentElement: { dataset: {} },
            hidden: false,
            addEventListener() {},
            querySelector() { return null; },
            querySelectorAll() { return []; },
        },
        matchMedia() { return media; },
        setTimeout,
        clearTimeout,
        requestAnimationFrame() { return 1; },
        cancelAnimationFrame() {},
        addEventListener() {},
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox, { filename: 'app.js' });

    return sandbox.OEMSForms ?? null;
}

function control(overrides = {}) {
    return {
        dataset: { formLabel: 'Email address' },
        files: [],
        id: 'email',
        name: 'email',
        type: 'email',
        value: '',
        validity: {
            badInput: false,
            patternMismatch: false,
            rangeOverflow: false,
            rangeUnderflow: false,
            stepMismatch: false,
            tooLong: false,
            tooShort: false,
            typeMismatch: false,
            valid: true,
            valueMissing: false,
        },
        ...overrides,
    };
}

class EventTargetStub {
    constructor() {
        this.attributes = new Map();
        this.dataset = {};
        this.hidden = false;
        this.listeners = new Map();
        this.textContent = '';
    }

    addEventListener(type, callback) {
        const callbacks = this.listeners.get(type) ?? [];
        callbacks.push(callback);
        this.listeners.set(type, callbacks);
    }

    dispatch(type, detail = {}) {
        const event = {
            defaultPrevented: false,
            preventDefault() { this.defaultPrevented = true; },
            target: this,
            ...detail,
        };
        for (const callback of this.listeners.get(type) ?? []) callback(event);
        return event;
    }

    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
}

test('required controls receive a specific actionable message', () => {
    const api = loadFormApi();
    const input = control({
        validity: { ...control().validity, valid: false, valueMissing: true },
    });

    assert.equal(api?.messageFor(input), 'Enter email address.');
});

test('format and range failures use the field label and valid boundary', () => {
    const api = loadFormApi();
    const invalidEmail = control({
        validity: { ...control().validity, valid: false, typeMismatch: true },
    });
    const capacity = control({
        dataset: { formLabel: 'Maximum capacity' },
        max: '100000',
        min: '1',
        type: 'number',
        validity: { ...control().validity, valid: false, rangeUnderflow: true },
    });

    assert.equal(api?.messageFor(invalidEmail), 'Enter a valid email address.');
    assert.equal(api?.messageFor(capacity), 'Maximum capacity must be at least 1.');
});

test('confirmation and schedule rules compare against the related field', () => {
    const api = loadFormApi();
    const fields = new Map([
        ['password', control({ name: 'password', type: 'password', value: 'correct horse' })],
        ['start_date', control({ name: 'start_date', type: 'datetime-local', value: '2026-08-12T18:00' })],
    ]);
    const form = { elements: { namedItem: (name) => fields.get(name) ?? null } };
    const confirmation = control({
        dataset: { formLabel: 'Password confirmation', matchField: 'password' },
        name: 'password_confirmation',
        type: 'password',
        value: 'different value',
    });
    const end = control({
        dataset: { afterField: 'start_date', formLabel: 'End date' },
        name: 'end_date',
        type: 'datetime-local',
        value: '2026-08-12T17:00',
    });

    assert.equal(api?.messageFor(confirmation, form), 'Password confirmation must match password.');
    assert.equal(api?.messageFor(end, form), 'End date must be after start date.');
});

test('paired coordinates and file policies reject incomplete or unsafe input', () => {
    const api = loadFormApi();
    const longitude = control({ name: 'longitude', type: 'number', value: '' });
    const form = { elements: { namedItem: (name) => name === 'longitude' ? longitude : null } };
    const latitude = control({
        dataset: { formLabel: 'Latitude', pairedWith: 'longitude' },
        name: 'latitude',
        type: 'number',
        value: '23.8103',
    });
    const gallery = control({
        accept: 'image/jpeg,image/png,image/webp',
        dataset: { formLabel: 'Gallery images', maxBytes: '5242880', maxFiles: '6' },
        files: [{ name: 'agenda.pdf', size: 8000, type: 'application/pdf' }],
        name: 'gallery_images[]',
        type: 'file',
    });

    assert.equal(api?.messageFor(latitude, form), 'Enter both latitude and longitude, or leave both blank.');
    assert.equal(api?.messageFor(gallery, form), 'Gallery images must use JPEG, PNG, or WebP files.');
});

test('valid state-changing submission locks only its submitter with specific progress copy', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const submitter = new EventTargetStub();
    submitter.dataset = { submitLabel: 'Saving event…' };
    submitter.disabled = false;
    submitter.textContent = 'Save event';
    form.checkValidity = () => true;
    form.dataset = { formKind: 'entry' };
    form.method = 'post';

    api?.enhanceForm(form);
    form.dispatch('submit', { submitter });

    assert.equal(submitter.disabled, true);
    assert.equal(submitter.textContent, 'Saving event…');
    assert.equal(form.getAttribute('aria-busy'), 'true');
});

test('field errors appear after blur and clear as soon as the value is corrected', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const error = new EventTargetStub();
    const input = new EventTargetStub();
    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.querySelector = (selector) => selector === '[data-client-error-for="email"]' ? error : null;
    form.querySelectorAll = () => [input];
    input.dataset.formLabel = 'Email address';
    input.form = form;
    input.id = 'email';
    input.name = 'email';
    input.type = 'email';
    input.value = 'not-an-email';
    input.validity = { ...control().validity, valid: false, typeMismatch: true };
    input.matches = () => true;
    input.setCustomValidity = () => {};
    error.hidden = true;

    api?.enhanceForm(form);
    form.dispatch('focusout', { target: input });

    assert.equal(input.getAttribute('aria-invalid'), 'true');
    assert.equal(error.hidden, false);
    assert.equal(error.textContent, 'Enter a valid email address.');

    input.value = 'person@example.com';
    input.validity = { ...control().validity, valid: true };
    form.dispatch('input', { target: input });

    assert.equal(input.getAttribute('aria-invalid'), null);
    assert.equal(error.hidden, true);
});

test('invalid submission prevents native bubbles and focuses the form error summary', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const summary = new EventTargetStub();
    const error = new EventTargetStub();
    const input = new EventTargetStub();
    summary.focusCalls = 0;
    summary.focus = () => { summary.focusCalls += 1; };
    summary.hidden = true;
    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.querySelector = (selector) => ({
        '[data-form-error-summary]': summary,
        '[data-client-error-for="email"]': error,
    }[selector] ?? null);
    form.querySelectorAll = () => [input];
    input.dataset.formLabel = 'Email address';
    input.form = form;
    input.id = 'email';
    input.name = 'email';
    input.type = 'email';
    input.value = '';
    input.validity = { ...control().validity, valid: false, valueMissing: true };
    input.matches = () => true;
    input.setCustomValidity = () => {};

    api?.enhanceForm(form);
    const event = form.dispatch('invalid', { target: input });

    assert.equal(event.defaultPrevented, true);
    assert.equal(summary.hidden, false);
    assert.equal(summary.focusCalls, 1);
});
