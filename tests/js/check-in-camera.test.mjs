import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8');
const detectedValue = '/organizer/check-in?token=' + 'a'.repeat(64);
const flush = (milliseconds = 0) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const deferred = () => {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });

    return { promise, resolve };
};

class ElementStub {
    constructor() {
        this.hidden = false;
        this.disabled = false;
        this.dataset = {};
        this.attributes = new Map();
        this.listeners = new Map();
        this.classList = { contains: () => false, toggle: () => {}, add: () => {}, remove: () => {} };
        this.style = {};
        this.textContent = '';
        this.value = '';
        this.srcObject = null;
    }

    addEventListener(type, callback) { this.listeners.set(type, callback); }
    click() { this.listeners.get('click')?.({ preventDefault() {} }); }
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
    querySelector() { return null; }
    querySelectorAll() { return []; }
    closest() { return null; }
    focus() {}
    async play() {}
}

function createHarness({ acquire, detect }) {
    const panel = new ElementStub();
    const start = new ElementStub();
    const stop = new ElementStub();
    const video = new ElementStub();
    video.hidden = true;
    const status = new ElementStub();
    const input = new ElementStub();
    const form = new ElementStub();
    let submitted = false;
    let mediaRequests = 0;
    let stoppedTracks = 0;
    let detectCalls = 0;
    const windowListeners = new Map();
    const documentListeners = new Map();
    const stream = { getTracks: () => [{ stop: () => { stoppedTracks += 1; } }] };

    form.requestSubmit = () => { submitted = true; };
    panel.querySelector = (selector) => ({
        '[data-check-in-camera-start]': start,
        '[data-check-in-camera-stop]': stop,
        '[data-check-in-video]': video,
        '[data-check-in-camera-status]': status,
    }[selector] ?? null);

    const sandbox = {
        console,
        setTimeout,
        clearTimeout,
        localStorage: { getItem: () => null, setItem: () => {} },
        matchMedia: () => ({ matches: false, addEventListener() {} }),
        addEventListener: (type, callback) => { windowListeners.set(type, callback); },
        document: {
            hidden: false,
            documentElement: { dataset: {} },
            body: { style: {} },
            querySelector: (selector) => ({
                '[data-check-in-camera]': panel,
                '[data-check-in-form]': form,
                '#ticket-code': input,
            }[selector] ?? null),
            querySelectorAll: () => [],
            addEventListener: (type, callback) => { documentListeners.set(type, callback); },
        },
        navigator: {
            mediaDevices: {
                getUserMedia: async () => {
                    mediaRequests += 1;
                    return acquire ? acquire(stream) : stream;
                },
            },
        },
        BarcodeDetector: class {
            static async getSupportedFormats() { return ['qr_code']; }
            async detect() {
                detectCalls += 1;
                return detect ? detect() : [{ rawValue: detectedValue }];
            }
        },
        requestAnimationFrame: (callback) => setTimeout(callback, 0),
        cancelAnimationFrame: (handle) => clearTimeout(handle),
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox, { filename: 'app.js' });

    return {
        start,
        stop,
        video,
        input,
        stream,
        submitted: () => submitted,
        mediaRequests: () => mediaRequests,
        stoppedTracks: () => stoppedTracks,
        detectCalls: () => detectCalls,
        pagehide: () => windowListeners.get('pagehide')?.(),
    };
}

{
    const camera = createHarness({});
    camera.start.click();
    await flush(40);

    if (camera.mediaRequests() !== 1 || !camera.submitted() || camera.input.value !== detectedValue || camera.stoppedTracks() !== 1) {
        throw new Error('Camera scan must request local media, submit the detected value, and stop every track.');
    }
}

{
    const acquisition = deferred();
    const camera = createHarness({ acquire: () => acquisition.promise });
    camera.start.click();
    camera.start.click();
    await flush();
    camera.stop.click();
    acquisition.resolve(camera.stream);
    await flush(20);

    if (camera.mediaRequests() !== 1 || camera.stoppedTracks() !== 1 || camera.video.srcObject !== null || camera.submitted()) {
        throw new Error('Stopping during pending acquisition must cancel one start and stop the late stream.');
    }
}

{
    const detection = deferred();
    const camera = createHarness({ detect: () => detection.promise });
    camera.start.click();
    await flush(20);

    if (camera.detectCalls() !== 1) {
        throw new Error('Camera must begin detection before the lifecycle cancellation probe.');
    }

    camera.pagehide();
    detection.resolve([{ rawValue: detectedValue }]);
    await flush(20);

    if (camera.stoppedTracks() !== 1 || camera.video.srcObject !== null || camera.submitted()) {
        throw new Error('Page hide during pending detection must stop media and prevent submission.');
    }
}
