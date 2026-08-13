(function () {
    const payloadElement = document.querySelector('#analytics-chart-data');
    const canvases = Array.from(document.querySelectorAll('[data-analytics-chart]'));
    const status = document.querySelector('[data-analytics-chart-status]');
    const instances = [];
    let payload = null;
    let observer = null;

    if (!payloadElement || canvases.length === 0) {
        return;
    }

    try {
        payload = JSON.parse(payloadElement.textContent || '{}');
    } catch (error) {
        if (status) status.textContent = 'Charts are unavailable. Exact source tables remain available.';
        return;
    }

    const destroy = () => {
        while (instances.length > 0) instances.pop().destroy();
    };

    const colors = () => {
        const styles = window.getComputedStyle(document.documentElement);
        const value = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;

        return {
            accent: value('--accent', '#2457f5'),
            success: value('--success', '#147a52'),
            warning: value('--warning', '#8a5a05'),
            muted: value('--ink-muted', '#667085'),
            line: value('--line', '#d0d5dd'),
        };
    };

    const options = (palette) => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 240 },
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { color: palette.muted, usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16 } },
            tooltip: { enabled: true },
        },
        scales: {
            x: {
                ticks: { color: palette.muted, autoSkip: true, maxTicksLimit: 8, maxRotation: 0, minRotation: 0 },
                grid: { color: palette.line },
            },
            y: { beginAtZero: true, ticks: { color: palette.muted, precision: 0 }, grid: { color: palette.line } },
        },
    });

    const timelineConfig = (palette) => {
        const timeline = payload.timeline || {};
        const datasets = [
            { label: 'Events', data: timeline.events || [], borderColor: palette.accent, backgroundColor: palette.accent + '1f', tension: 0.22 },
            { label: 'Registrations', data: timeline.registrations || [], borderColor: palette.success, backgroundColor: palette.success + '1f', tension: 0.22 },
            { label: 'Attendance', data: timeline.attendance || [], borderColor: palette.warning, backgroundColor: palette.warning + '1f', tension: 0.22 },
        ];

        datasets.forEach((dataset) => {
            dataset.borderWidth = 2;
            dataset.pointRadius = 2;
            dataset.pointHoverRadius = 4;
            dataset.fill = false;
        });

        return { type: 'line', data: { labels: timeline.labels || [], datasets }, options: options(palette) };
    };

    const categoryConfig = (palette) => {
        const categories = payload.categories || {};
        const chartOptions = options(palette);
        chartOptions.indexAxis = 'y';
        chartOptions.interaction = { intersect: false, mode: 'nearest', axis: 'y' };
        chartOptions.plugins.legend.display = false;
        chartOptions.scales.x.beginAtZero = true;
        chartOptions.scales.x.ticks.precision = 0;
        chartOptions.scales.y.grid.display = false;

        return {
            type: 'bar',
            data: {
                labels: categories.labels || [],
                datasets: [{
                    label: 'Registrations',
                    data: categories.registrations || [],
                    borderColor: palette.accent,
                    backgroundColor: palette.accent + '99',
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 28,
                }],
            },
            options: chartOptions,
        };
    };

    const create = () => {
        destroy();
        if (typeof window.Chart !== 'function') {
            if (status) status.textContent = 'Charts are unavailable. Exact source tables remain available.';
            return;
        }
        const palette = colors();
        canvases.forEach((canvas) => {
            const config = canvas.dataset.analyticsChart === 'categories'
                ? categoryConfig(palette)
                : timelineConfig(palette);
            instances.push(new window.Chart(canvas, config));
        });
        if (status) status.textContent = 'Interactive charts ready. Exact source tables remain available.';
    };

    create();
    if (typeof window.MutationObserver === 'function') {
        observer = new window.MutationObserver(() => create());
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }
    window.addEventListener('pagehide', () => {
        destroy();
        observer?.disconnect();
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        create();
        if (observer) observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    });
}());
