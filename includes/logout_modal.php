<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2><i class="fas fa-sign-out-alt"></i> Logout Confirmation</h2>
            <span class="close-modal" onclick="closeModal('logoutConfirmModal')">&times;</span>
        </div>
        <div class="modal-body">
            <p>Do you really want to logout?</p>
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logoutConfirmModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmLogoutBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Close modal function
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                }
            };;
</script>