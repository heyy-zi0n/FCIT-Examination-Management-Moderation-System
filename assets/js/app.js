/**
 * LASU FCIT EMMS — Client Application Logic
 * Accessible navigation, dialogs, confirmations, form loading states, and upload feedback.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle (Single Authoritative Handler with ARIA state)
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (mobileMenuBtn && sidebar) {
        let isSidebarOpen = false;

        const setSidebarState = (open) => {
            isSidebarOpen = open;
            mobileMenuBtn.setAttribute('aria-expanded', String(isSidebarOpen));
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
                mobileMenuBtn.focus();
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

    // 2. Dropdown Toggles (Notifications & Profile with ARIA + Escape dismissal)
    const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');

    const closeAllDropdowns = () => {
        dropdownToggles.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    };

    dropdownToggles.forEach(toggle => {
        const targetId = toggle.getAttribute('data-dropdown-toggle');
        const targetMenu = document.getElementById(targetId);
        if (targetMenu) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const wasOpen = !targetMenu.classList.contains('hidden');
                closeAllDropdowns();
                if (!wasOpen) {
                    targetMenu.classList.remove('hidden');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });

            targetMenu.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    });

    document.addEventListener('click', closeAllDropdowns);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });

    // 3. Modal & Native <dialog> Manager
    const attachBackdropDismiss = (dialog) => {
        if (dialog.dataset.backdropBound) return;
        dialog.dataset.backdropBound = '1';
        if (!('closedBy' in HTMLDialogElement.prototype)) {
            const isOutside = (e) => {
                const r = dialog.getBoundingClientRect();
                return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
            };
            let pressedOutside = false;
            dialog.addEventListener('pointerdown', (e) => { pressedOutside = isOutside(e); });
            dialog.addEventListener('pointerup', (e) => {
                if (pressedOutside && isOutside(e)) dialog.close();
                pressedOutside = false;
            });
        }
    };

    document.querySelectorAll('dialog[closedby="any"]').forEach(attachBackdropDismiss);

    let lastActiveElement = null;

    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        if (modal.tagName === 'DIALOG') {
            attachBackdropDismiss(modal);
            if (!modal.open) {
                modal.showModal();
            }
            return;
        }

        lastActiveElement = document.activeElement;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        const firstFocusable = modal.querySelector('input:not([type="hidden"]), select, textarea, button:not([aria-label="Close"])');
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 20);
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        if (modal.tagName === 'DIALOG') {
            if (modal.open) {
                modal.close();
            }
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
            lastActiveElement = null;
        }
    };

    // Close legacy div modals on Escape or backdrop click
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('div[id$="-modal"]:not(.hidden)').forEach(m => {
                window.closeModal(m.id);
            });
        }
    });
    document.querySelectorAll('div[id$="-modal"]').forEach(m => {
        m.addEventListener('click', (e) => {
            if (e.target === m) window.closeModal(m.id);
        });
    });

    // 4. Institutional Accessible Confirmation Dialog (replaces raw window.confirm)
    let confirmDialog = document.getElementById('ems-confirm-dialog');
    if (!confirmDialog) {
        confirmDialog = document.createElement('dialog');
        confirmDialog.id = 'ems-confirm-dialog';
        confirmDialog.setAttribute('closedby', 'any');
        confirmDialog.setAttribute('aria-labelledby', 'ems-confirm-title');
        confirmDialog.setAttribute('aria-describedby', 'ems-confirm-desc');
        confirmDialog.className = 'w-full max-w-md rounded-2xl shadow-2xl p-0 overflow-hidden';
        confirmDialog.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden text-slate-800">
                <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
                    <h2 id="ems-confirm-title" class="text-sm font-bold tracking-wide">Confirm Action</h2>
                    <button type="button" data-confirm-cancel aria-label="Cancel and close dialog" class="p-1 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p id="ems-confirm-desc" class="text-sm text-slate-600 leading-relaxed"></p>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" data-confirm-cancel autofocus class="px-4 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition">
                            Cancel
                        </button>
                        <button type="button" id="ems-confirm-accept" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-lasu-navy hover:bg-lasu-navy-light shadow transition">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(confirmDialog);
        attachBackdropDismiss(confirmDialog);
    }

    let pendingConfirmationCallback = null;
    confirmDialog.querySelectorAll('[data-confirm-cancel]').forEach(btn => {
        btn.addEventListener('click', () => {
            confirmDialog.returnValue = 'cancel';
            confirmDialog.close();
        });
    });
    const confirmAcceptBtn = document.getElementById('ems-confirm-accept');
    if (confirmAcceptBtn) {
        confirmAcceptBtn.addEventListener('click', () => {
            confirmDialog.returnValue = 'confirm';
            confirmDialog.close();
        });
    }
    confirmDialog.addEventListener('close', () => {
        const accepted = confirmDialog.returnValue === 'confirm';
        confirmDialog.returnValue = '';
        if (accepted && typeof pendingConfirmationCallback === 'function') {
            const fn = pendingConfirmationCallback;
            pendingConfirmationCallback = null;
            fn();
        } else {
            pendingConfirmationCallback = null;
        }
    });

    // Intercept clicks on submit buttons with [data-confirm-title] or [data-confirm-message]
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('button[data-confirm-message]');
        if (!trigger || trigger.dataset.confirmedBypass === '1') return;

        const form = trigger.form;
        if (form && !form.checkValidity()) {
            // Let native HTML5 required-field validation run first
            return;
        }

        e.preventDefault();
        const title = trigger.getAttribute('data-confirm-title') || 'Confirm Governance Action';
        const message = trigger.getAttribute('data-confirm-message') || 'Are you sure you want to proceed with this action?';
        const confirmLabel = trigger.getAttribute('data-confirm-label') || 'Confirm & Proceed';
        const variant = trigger.getAttribute('data-confirm-variant') || 'primary';

        document.getElementById('ems-confirm-title').textContent = title;
        document.getElementById('ems-confirm-desc').textContent = message;
        confirmAcceptBtn.textContent = confirmLabel;

        confirmAcceptBtn.className = 'px-5 py-2.5 rounded-xl text-xs font-bold shadow transition ';
        if (variant === 'danger') {
            confirmAcceptBtn.className += 'bg-rose-600 hover:bg-rose-700 text-white';
        } else if (variant === 'success') {
            confirmAcceptBtn.className += 'bg-emerald-600 hover:bg-emerald-700 text-white';
        } else if (variant === 'gold') {
            confirmAcceptBtn.className += 'bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy';
        } else {
            confirmAcceptBtn.className += 'bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold';
        }

        pendingConfirmationCallback = () => {
            trigger.dataset.confirmedBypass = '1';
            trigger.click();
        };

        confirmDialog.showModal();
    });

    // 5. Submit Button Loading State Protection (prevents duplicate POSTs while preserving button name/value)
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            const submitter = e.submitter || form.querySelector('button[type="submit"]');
            if (!submitter || submitter.dataset.submitting === '1') {
                if (submitter && submitter.dataset.submitting === '1') {
                    e.preventDefault();
                }
                return;
            }

            const loadingText = submitter.getAttribute('data-loading-text');
            if (!loadingText) return;

            submitter.dataset.submitting = '1';

            // Preserve submitter name/value in POST body before disabling the button
            if (submitter.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value || '1';
                form.appendChild(hidden);
            }

            submitter.disabled = true;
            submitter.classList.add('opacity-75', 'cursor-wait');
            submitter.innerHTML = `
                <svg class="w-4 h-4 animate-spin inline-block mr-1.5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span>${loadingText}</span>
            `;
        });
    });

    // 6. File Upload Input Feedback ([data-file-feedback="target-id"])
    document.querySelectorAll('input[type="file"][data-file-feedback]').forEach(input => {
        input.addEventListener('change', () => {
            const targetId = input.getAttribute('data-file-feedback');
            const maxMb = parseFloat(input.getAttribute('data-max-mb') || '20');
            const target = document.getElementById(targetId);
            if (!target) return;

            if (!input.files || !input.files.length) {
                target.textContent = '';
                target.className = 'hidden';
                return;
            }

            const file = input.files[0];
            const sizeMb = file.size / (1024 * 1024);
            const formattedSize = sizeMb >= 1
                ? `${sizeMb.toFixed(2)} MB`
                : `${Math.max(1, Math.round(file.size / 1024))} KB`;

            if (sizeMb > maxMb) {
                target.textContent = `Selected "${file.name}" (${formattedSize}) exceeds the ${maxMb} MB limit.`;
                target.className = 'mt-2.5 px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold block';
            } else {
                target.textContent = `Selected: ${file.name} (${formattedSize})`;
                target.className = 'mt-2.5 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold block truncate';
            }
        });
    });

    // 7. Auto-dismiss ONLY non-critical status alerts (.auto-dismiss-alert) after 6 seconds
    const autoAlerts = document.querySelectorAll('.auto-dismiss-alert');
    autoAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 6000);
    });
});
