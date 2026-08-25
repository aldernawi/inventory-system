document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-app-shell]');

    if (! shell) return;

    const open = () => shell.classList.add('sidebar-open');
    const close = () => shell.classList.remove('sidebar-open');

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => button.addEventListener('click', open));
    document.querySelectorAll('[data-sidebar-close], [data-sidebar-overlay], [data-sidebar-link]').forEach((element) => element.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
});
