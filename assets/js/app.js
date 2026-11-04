/**
 * LASU FCIT CMS Client Application Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle (Single Authoritative Handler)
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (mobileMenuBtn && sidebar) {
        let isSidebarOpen = false;

        const setSidebarState = (open) => {
            isSidebarOpen = open;
            if (isSidebarOpen) {
                sidebar.classList.remove('-translate-x-full');
                if (sidebarBackdrop) sidebarBackdrop.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                sidebar.classList.add('-translate-x-full');
                if (sidebarBackdrop) sidebarBackdrop.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };

        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            setSidebarState(!isSidebarOpen);
        });

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', () => {
                if (isSidebarOpen) setSidebarState(false);
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isSidebarOpen) {
                setSidebarState(false);
            }
        });

        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (isSidebarOpen && window.innerWidth < 768) {
                    setSidebarState(false);
                }
            });
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
                // Close any other open dropdowns first
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.add('hidden');
                    }
                });
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
