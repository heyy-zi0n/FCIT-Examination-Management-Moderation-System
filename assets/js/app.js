/**
 * LASU FCIT CMS Client Application Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            if (sidebarBackdrop) sidebarBackdrop.classList.toggle('hidden');
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            if (sidebar) sidebar.classList.add('-translate-x-full');
            sidebarBackdrop.classList.add('hidden');
        });
    }

    // Dropdown toggles (User profile, Notifications)
    const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');
    dropdownToggles.forEach(toggle => {
        const targetId = toggle.getAttribute('data-dropdown-toggle');
        const targetMenu = document.getElementById(targetId);
        if (targetMenu) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                targetMenu.classList.toggle('hidden');
            });
        }
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });
    });

    // Modal Manager
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
    };

    // Auto-dismiss alerts after 6 seconds
    const autoAlerts = document.querySelectorAll('.auto-dismiss-alert');
    autoAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });
});
