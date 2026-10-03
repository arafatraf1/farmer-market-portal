/**
 * Farmer Market Portal - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // User menu dropdown toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenuDropdown = document.getElementById('userMenuDropdown');

    if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
                userMenuDropdown.classList.remove('show');
            }
        });
    }

    // Auto-dismiss flash alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Modal close triggers
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Image preview for file upload inputs
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const previewEl = document.getElementById(previewId);
            if (previewEl && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewEl.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
});

// Global Modal helpers
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Global Toast Notification
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? '#15803d' : (type === 'error' ? '#dc2626' : '#2563eb');
    toast.style.cssText = `background:${bg};color:#fff;padding:12px 20px;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.2);font-weight:600;font-size:14px;pointer-events:auto;animation:fadeInDown 0.2s ease-out;display:flex;align-items:center;gap:10px;`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}
