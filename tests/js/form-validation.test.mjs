import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8');

function loadFormApi(confirm = () => true) {
    const media = { matches: false, addEventListener() {} };
    const sandbox = {
        console,
        document: {
            documentElement: { dataset: {} },
            hidden: false,
            addEventListener() {},
            createElement(tagName) { return new EventTargetStub(tagName); },
            querySelector() { return null; },
            querySelectorAll() { return []; },
        },
        matchMedia() { return media; },
        setTimeout,
        clearTimeout,
        requestAnimationFrame() { return 1; },
        cancelAnimationFrame() {},
        addEventListener() {},
        confirm,
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
    constructor(tagName = 'div') {
        this.attributes = new Map();
        this.children = [];
        this.dataset = {};
        this.hidden = false;
        this.listeners = new Map();
        this.tagName = tagName.toUpperCase();
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
    append(...children) { this.children.push(...children); }
    prepend(...children) { this.children.unshift(...children); }
    querySelector() { return null; }
    querySelectorAll() { return []; }
    removeAttribute(name) { this.attributes.delete(name); }
    replaceChildren(...children) { this.children = [...children]; }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
}

test('required controls receive a specific actionable message', () => {
    const api = loadFormApi();
    const input = control({
        validity: { ...control().validity, valid: false, valueMissing: true },
    });

    assert.equal(api?.messageFor(input), 'Enter email address.');
});

test('required choices use selection language instead of text-entry language', () => {
    const api = loadFormApi();
    const checkbox = control({
        dataset: { formLabel: 'Terms and privacy' },
        type: 'checkbox',
        validity: { ...control().validity, valid: false, valueMissing: true },
    });
    const radio = control({
        dataset: { formLabel: 'Account type' },
        type: 'radio',
        validity: { ...control().validity, valid: false, valueMissing: true },
    });

    assert.equal(api?.messageFor(checkbox), 'Select terms and privacy to continue.');
    assert.equal(api?.messageFor(radio), 'Choose account type.');
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

test('first submission applies custom validation before any control is blurred', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const password = new EventTargetStub();
    const confirmation = new EventTargetStub();
    const error = new EventTargetStub();
    const summary = new EventTargetStub();
    const controls = new Map();

    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.elements = { namedItem: (name) => controls.get(name) ?? null };
    form.checkValidity = () => true;
    form.querySelectorAll = () => [password, confirmation];
    form.querySelector = (selector) => ({
        '[data-form-error-summary]': summary,
        '[data-client-error-for="password_confirmation"]': error,
    }[selector] ?? null);

    for (const input of [password, confirmation]) {
        input.form = form;
        input.matches = () => true;
        input.setCustomValidity = () => {};
        input.type = 'password';
        input.validity = { ...control().validity, valid: true };
    }
    password.dataset.formLabel = 'Password';
    password.id = 'password';
    password.name = 'password';
    password.value = 'first password';
    confirmation.dataset.formLabel = 'Password confirmation';
    confirmation.dataset.matchField = 'password';
    confirmation.id = 'password_confirmation';
    confirmation.name = 'password_confirmation';
    confirmation.value = 'different password';
    controls.set('password', password);
    controls.set('password_confirmation', confirmation);

    api?.enhanceForm(form);
    const event = form.dispatch('submit', { submitter: new EventTargetStub() });

    assert.equal(event.defaultPrevented, true);
    assert.equal(confirmation.getAttribute('aria-invalid'), 'true');
    assert.equal(error.textContent, 'Password confirmation must match password.');
});

test('first submission aggregates untouched schedule coordinate conditional and file rules', () => {
    const api = loadFormApi();
    const form = new EventTargetStub('form');
    const summary = new EventTargetStub('section');
    const list = new EventTargetStub('ul');
    const errors = new Map();
    const controls = new Map();
    const valid = control().validity;

    const makeControl = (name, type, value, dataset = {}) => {
        const input = new EventTargetStub(type === 'select-one' ? 'select' : 'input');
        input.dataset = { formLabel: name.replaceAll('_', ' '), ...dataset };
        input.form = form;
        input.id = name;
        input.name = name;
        input.type = type;
        input.value = value;
        input.files = [];
        input.validity = { ...valid, valid: true };
        input.matches = () => true;
        input.setCustomValidity = () => {};
        controls.set(name, input);
        errors.set(name, new EventTargetStub('p'));
        return input;
    };

    const start = makeControl('start_date', 'datetime-local', '2026-08-12T18:00');
    const end = makeControl('end_date', 'datetime-local', '2026-08-12T17:00', { afterField: 'start_date' });
    const latitude = makeControl('latitude', 'number', '23.8103', { pairedWith: 'longitude' });
    const longitude = makeControl('longitude', 'number', '');
    const discountType = makeControl('discount_type', 'select-one', 'percentage');
    const discountValue = makeControl('discount_value', 'number', '101', {
        maxWhenField: 'discount_type',
        maxWhenValue: 'percentage',
        maxWhen: '100',
    });
    const gallery = makeControl('gallery_images', 'file', '', { maxBytes: '5242880', maxFiles: '6' });
    gallery.accept = 'image/jpeg,image/png,image/webp';
    gallery.files = [{ name: 'unsafe.pdf', size: 1000, type: 'application/pdf' }];

    summary.querySelector = (selector) => selector === '[data-client-error-list]' ? list : null;
    summary.querySelectorAll = () => [];
    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.elements = { namedItem: (name) => controls.get(name) ?? null };
    form.checkValidity = () => true;
    form.querySelectorAll = () => Array.from(controls.values());
    form.querySelector = (selector) => {
        if (selector === '[data-form-error-summary]') return summary;
        const match = selector.match(/^\[data-client-error-for="(.+)"\]$/);
        return match ? errors.get(match[1]) ?? null : null;
    };

    api?.enhanceForm(form);
    const event = form.dispatch('submit', { submitter: new EventTargetStub('button') });

    assert.equal(event.defaultPrevented, true);
    for (const input of [end, latitude, discountValue, gallery]) {
        assert.equal(input.getAttribute('aria-invalid'), 'true');
    }
    for (const input of [start, longitude, discountType]) {
        assert.equal(input.getAttribute('aria-invalid'), null);
    }
    assert.equal(list.children.length, 4);
});

test('conditional numeric limits mirror organizer discount rules', () => {
    const api = loadFormApi();
    const type = control({ name: 'discount_type', type: 'select-one', value: 'percentage' });
    const form = { elements: { namedItem: (name) => name === 'discount_type' ? type : null } };
    const value = control({
        dataset: {
            formLabel: 'Discount value',
            maxWhenField: 'discount_type',
            maxWhenValue: 'percentage',
            maxWhen: '100',
        },
        name: 'discount_value',
        type: 'number',
        value: '101',
    });

    assert.equal(api?.messageFor(value, form), 'Discount value must be no more than 100 for percentage.');
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

test('file choosers report the selected filename or total without exposing a fake path', () => {
    const api = loadFormApi();

    assert.equal(api?.fileSelectionText(control({ type: 'file', files: [] })), 'No file selected.');
    assert.equal(api?.fileSelectionText(control({
        type: 'file',
        files: [{ name: 'event-banner.webp' }],
    })), 'Selected: event-banner.webp');
    assert.equal(api?.fileSelectionText(control({
        type: 'file',
        files: [
            { name: 'arrival.jpg' },
            { name: 'stage.png' },
            { name: 'audience.webp' },
            { name: 'closing.jpg' },
        ],
    })), '4 files selected: arrival.jpg, stage.png, audience.webp, and 1 more.');
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

test('a form-level lock cancels duplicate submissions even without a submitter', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const submitter = new EventTargetStub();
    form.checkValidity = () => true;
    form.querySelectorAll = () => [];
    form.dataset = { formKind: 'entry' };
    form.method = 'post';
    submitter.disabled = false;

    api?.enhanceForm(form);
    const first = form.dispatch('submit', { submitter });
    const duplicate = form.dispatch('submit', { submitter: null });

    assert.equal(first.defaultPrevented, false);
    assert.equal(duplicate.defaultPrevented, true);
});

test('destructive actions require confirmation before submission is locked', () => {
    let prompt = '';
    const api = loadFormApi((message) => {
        prompt = message;
        return false;
    });
    const form = new EventTargetStub();
    const submitter = new EventTargetStub();
    form.checkValidity = () => true;
    form.dataset = { confirm: 'Delete this event?', formKind: 'action' };
    form.method = 'post';
    submitter.disabled = false;

    api?.enhanceForm(form);
    const event = form.dispatch('submit', { submitter });

    assert.equal(prompt, 'Delete this event?');
    assert.equal(event.defaultPrevented, true);
    assert.equal(submitter.disabled, false);
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

test('invalid submission builds linked summary entries and hides them after correction', () => {
    const api = loadFormApi();
    const form = new EventTargetStub('form');
    const summary = new EventTargetStub('section');
    const list = new EventTargetStub('ul');
    const error = new EventTargetStub('p');
    const input = new EventTargetStub('input');

    summary.hidden = true;
    summary.querySelector = (selector) => selector === '[data-client-error-list]' ? list : null;
    summary.querySelectorAll = () => [];
    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.checkValidity = () => true;
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
    const event = form.dispatch('submit', { submitter: new EventTargetStub('button') });

    assert.equal(event.defaultPrevented, true);
    assert.equal(summary.hidden, false);
    assert.equal(list.children.length, 1);
    assert.equal(list.children[0].children[0].getAttribute('href'), '#email');
    assert.match(list.children[0].children[0].textContent, /Email address: Enter email address\./);

    input.value = 'person@example.test';
    input.validity = { ...control().validity, valid: true };
    form.dispatch('input', { target: input });

    assert.equal(list.children.length, 0);
    assert.equal(summary.hidden, true);
});

test('editing a source field revalidates a touched confirmation field', () => {
    const api = loadFormApi();
    const form = new EventTargetStub();
    const error = new EventTargetStub();
    const password = new EventTargetStub();
    const confirmation = new EventTargetStub();
    const controls = new Map();
    form.dataset.formKind = 'entry';
    form.method = 'post';
    form.elements = { namedItem: (name) => controls.get(name) ?? null };
    form.querySelector = (selector) => selector === '[data-client-error-for="password_confirmation"]' ? error : null;
    form.querySelectorAll = () => [password, confirmation];

    for (const input of [password, confirmation]) {
        input.form = form;
        input.matches = () => true;
        input.setCustomValidity = () => {};
        input.type = 'password';
        input.validity = { ...control().validity, valid: true };
    }
    password.dataset.formLabel = 'Password';
    password.id = 'password';
    password.name = 'password';
    password.value = 'first password';
    confirmation.dataset.formLabel = 'Password confirmation';
    confirmation.dataset.matchField = 'password';
    confirmation.id = 'password_confirmation';
    confirmation.name = 'password_confirmation';
    confirmation.value = 'second password';
    controls.set('password', password);
    controls.set('password_confirmation', confirmation);

    api?.enhanceForm(form);
    form.dispatch('focusout', { target: confirmation });
    assert.equal(confirmation.getAttribute('aria-invalid'), 'true');

    password.value = 'second password';
    form.dispatch('input', { target: password });

    assert.equal(confirmation.getAttribute('aria-invalid'), null);
    assert.equal(error.hidden, true);
});
