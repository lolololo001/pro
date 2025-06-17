<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="modal">
    <div class="modal-content" style="max-width: 500px; background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);">
        <div class="modal-header" style="background: linear-gradient(135deg, #00704a, #2563eb); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
            <h2 style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-sign-out-alt"></i> Logout Confirmation
            </h2>
            <span class="close-modal" onclick="closeModal('logoutConfirmModal')" style="cursor: pointer; font-size: 1.5rem; padding: 0.5rem; border-radius: 50%; transition: background 0.3s; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 2rem;">
            <p style="margin: 0 0 1.5rem 0; font-size: 1.1rem; color: #374151;">Do you really want to logout from your account?</p>
            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logoutConfirmModal')" style="padding: 0.75rem 1.5rem; border-radius: 6px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="handleLogout()" style="padding: 0.75rem 1.5rem; border-radius: 6px;">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.1);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal {
    animation: fadeIn 0.3s ease;
}

.modal-content {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
    // Close modal function
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
    };

    // Handle logout function (if not already defined)
    if (typeof handleLogout !== 'function') {
        window.handleLogout = function() {
            closeModal('logoutConfirmModal');
            window.location.href = '../logout.php';
        };
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target.id);
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const visibleModal = document.querySelector('.modal[style*="display: block"]');
            if (visibleModal) {
                closeModal(visibleModal.id);
            }
        }
    });
</script>