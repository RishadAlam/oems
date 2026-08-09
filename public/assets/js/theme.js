(function () {
    let saved = null;

    try {
        saved = window.localStorage.getItem('oems-theme');
    } catch (error) {
        // The system preference remains available when storage is blocked.
    }

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.theme = ['light', 'dark'].includes(saved)
        ? saved
        : (prefersDark ? 'dark' : 'light');
}());
