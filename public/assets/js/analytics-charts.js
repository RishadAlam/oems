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
        if (status) status.textContent = 'Charts are unavailable. The data tables remain available.';
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
            muted: value('--ink-muted', '#667085'),
            line: value('--line', '#d0d5dd'),
            palette: ['#2457f5', '#0f9f7f', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#dc2626'],
        };
    };

    const options = (palette) => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 240 },
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { color: palette.muted, usePointStyle: true } },
            tooltip: { enabled: true },
        },
        scales: {
            x: { ticks: { color: palette.muted }, grid: { color: palette.line } },
            y: { beginAtZero: true, ticks: { color: palette.muted, precision: 0 }, grid: { color: palette.line } },
        },
    });

    const timelineConfig = (palette) => {
        const timeline = payload.timeline || {};
        const datasets = [
            { label: 'Events', data: timeline.events || [], borderColor: palette.palette[0], backgroundColor: palette.palette[0] + '33', tension: 0.25 },
            { label: 'Registrations', data: timeline.registrations || [], borderColor: palette.palette[1], backgroundColor: palette.palette[1] + '33', tension: 0.25 },
            { label: 'Attendance', data: timeline.attendance || [], borderColor: palette.palette[2], backgroundColor: palette.palette[2] + '33', tension: 0.25 },
        ];
        Object.entries(timeline.payments || {}).forEach(([currency, values], index) => {
            datasets.push({
                label: `Verified payments (${currency})`,
                data: Array.isArray(values) ? values.map((value) => Number(value) || 0) : [],
                borderColor: palette.palette[(index + 3) % palette.palette.length],
                backgroundColor: palette.palette[(index + 3) % palette.palette.length] + '33',
                borderDash: [5, 4],
                tension: 0.25,
                yAxisID: 'yMoney',
            });
        });

        const chartOptions = options(palette);
        chartOptions.scales.yMoney = {
            beginAtZero: true,
            position: 'right',
            ticks: { color: palette.muted },
            grid: { drawOnChartArea: false },
        };

        return { type: 'line', data: { labels: timeline.labels || [], datasets }, options: chartOptions };
    };

    const categoryConfig = (palette) => {
        const categories = payload.categories || {};
        const chartOptions = options(palette);
        chartOptions.indexAxis = 'y';

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
                }],
            },
            options: chartOptions,
        };
    };

    const create = () => {
        destroy();
        if (typeof window.Chart !== 'function') {
            if (status) status.textContent = 'Charts are unavailable. The data tables remain available.';
            return;
        }
        const palette = colors();
        canvases.forEach((canvas) => {
            const config = canvas.dataset.analyticsChart === 'categories'
                ? categoryConfig(palette)
                : timelineConfig(palette);
            instances.push(new window.Chart(canvas, config));
        });
        if (status) status.textContent = 'Interactive charts loaded. The same values remain available in the tables.';
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
