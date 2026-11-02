document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const surface = editor.querySelector('[contenteditable="true"]');
    const input = editor.querySelector('[data-rich-editor-input]');
    if (!surface || !input) return;

    const sync = () => {
        input.value = surface.innerHTML;
    };

    surface.addEventListener('input', sync);
    editor.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            surface.focus();
            const command = button.dataset.command;
            const value = button.dataset.value || null;
            if (command === 'createLink') {
                const url = window.prompt('Enter the link URL');
                if (!url) return;
                document.execCommand(command, false, url);
            } else {
                document.execCommand(command, false, value);
            }
            sync();
        });
    });

    editor.closest('form')?.addEventListener('submit', sync);
});