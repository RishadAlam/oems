import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/analytics-charts.js', import.meta.url), 'utf8');

function harness({ chartAvailable = true, reducedMotion = false } = {}) {
    const destroyed = [];
    const created = [];
    const listeners = new Map();
    const observer = { disconnectCalls: 0, observeCalls: 0 };
    const payload = {
        timeline: {
            labels: ['2026-08-01', '2026-08-02'],
            events: [1, 0], registrations: [3, 2], attendance: [1, 1],
            payments: { BDT: ['12.30', '0.00'] },
        },
        categories: { labels: ['Technology'], registrations: [5] },
    };
    const canvases = [{ dataset: { analyticsChart: 'timeline' } }, { dataset: { analyticsChart: 'categories' } }];
    const status = { textContent: '' };
    class ChartStub {
        constructor(canvas, config) { this.canvas = canvas; this.config = config; created.push(this); }
        destroy() { destroyed.push(this); }
    }
    class MutationObserverStub {
        constructor(callback) { this.callback = callback; }
        observe() { observer.observeCalls += 1; observer.instance = this; }
        disconnect() { observer.disconnectCalls += 1; }
    }
    const sandbox = {
        console,
        Chart: chartAvailable ? ChartStub : undefined,
        MutationObserver: MutationObserverStub,
        document: {
            documentElement: {},
            querySelector(selector) {
                if (selector === '#analytics-chart-data') return { textContent: JSON.stringify(payload) };
                if (selector === '[data-analytics-chart-status]') return status;
                return null;
            },
            querySelectorAll(selector) { return selector === '[data-analytics-chart]' ? canvases : []; },
        },
        getComputedStyle() {
            return { getPropertyValue(name) { return ({ '--accent': '#2457f5', '--ink-muted': '#667085', '--line': '#d0d5dd' })[name] ?? ''; } };
        },
        matchMedia() { return { matches: reducedMotion }; },
        addEventListener(type, callback) { listeners.set(type, callback); },
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox);
    return { created, destroyed, listeners, observer, status };
}

test('creates aggregate charts with responsive theme-aware reduced-motion options', () => {
    const result = harness({ reducedMotion: true });
    assert.equal(result.created.length, 2);
    assert.equal(result.created[0].config.options.responsive, true);
    assert.equal(result.created[0].config.options.animation, false);
    assert.deepEqual(Array.from(result.created[0].config.data.datasets, (dataset) => dataset.label), ['Events', 'Registrations', 'Attendance']);
    assert.equal('yMoney' in result.created[0].config.options.scales, false);
    assert.equal(result.created[0].config.options.scales.x.ticks.autoSkip, true);
    assert.equal(result.created[0].config.options.scales.x.ticks.maxTicksLimit, 8);
    assert.equal(result.created[1].config.options.indexAxis, 'y');
    assert.equal(result.created[1].config.options.scales.x.ticks.precision, 0);
    assert.equal(result.created[1].config.data.datasets[0].maxBarThickness, 28);
    assert.match(result.status.textContent, /source tables remain available/i);
    assert.equal(result.observer.observeCalls, 1);
});

test('keeps tables usable without Chart and cleans up across page lifecycle', () => {
    const unavailable = harness({ chartAvailable: false });
    assert.match(unavailable.status.textContent, /tables remain available/i);

    const available = harness();
    available.listeners.get('pagehide')?.();
    assert.equal(available.destroyed.length, 2);
    assert.equal(available.observer.disconnectCalls, 1);
    available.listeners.get('pageshow')?.({ persisted: true });
    assert.equal(available.created.length, 4);
});
