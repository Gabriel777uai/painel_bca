document.addEventListener('DOMContentLoaded', () => {
    const btnToggle = document.getElementById('btn-toggle-sidebar');
    const btnClose = document.getElementById('btn-close-sidebar');
    const sidebar = document.querySelector('.sidebar-nav');
    
    // Create backdrop element dynamically if it doesn't exist
    let backdrop = document.getElementById('sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sidebar-backdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    if (btnToggle && sidebar && backdrop) {
        btnToggle.addEventListener('click', () => {
            sidebar.classList.add('show-sidebar');
            backdrop.classList.add('show');
        });
    }

    const closeMenu = () => {
        if (sidebar && backdrop) {
            sidebar.classList.remove('show-sidebar');
            backdrop.classList.remove('show');
        }
    };

    if (btnClose) {
        btnClose.addEventListener('click', closeMenu);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMenu);
    }
});
