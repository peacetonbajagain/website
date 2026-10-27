(() => {
    const root = document.documentElement;

    const syncControls = () => {
        const dark = root.dataset.theme === 'dark';
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', String(dark));
            button.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} theme`);
            button.textContent = dark ? 'Light mode' : 'Dark mode';
        });
    };

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            try {
                localStorage.setItem('travo-theme', root.dataset.theme);
            } catch (error) {
                // The selected theme still applies for the current page.
            }
            syncControls();
        });
    });

    syncControls();
})();