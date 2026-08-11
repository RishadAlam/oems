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
