<?php
/**
 * Universal Modals for Edit and Delete Confirmations
 * Include this file in all admin pages that need edit/delete functionality
 */
?>

<!-- Universal Confirmation Modal -->
<div id="universalConfirmationModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; margin: 10% auto;">
        <div class="modal-header" style="border-bottom: 1px solid #dee2e6; padding: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336;"></i>
                Confirm Action
            </h3>
        </div>
        
        <div class="modal-body" style="padding: 2rem; text-align: center;">
            <div id="universalConfirmationMessage">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
        
        <div class="modal-footer" style="padding: 1.5rem; border-top: 1px solid #dee2e6; display: flex; gap: 1rem; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeUniversalConfirmation()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" id="universalConfirmButton" class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Universal Edit Modal -->
<div id="universalEditModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; margin: 5% auto; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="border-bottom: 1px solid #dee2e6; padding: 1.5rem; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
            <h3 id="editModalTitle" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-edit"></i>
                Edit Item
            </h3>
            <button type="button" class="close" onclick="closeUniversalEdit()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; margin-left: auto;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body" style="padding: 0;">
            <div id="universalEditFormContainer">
                <!-- Dynamic form content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<!-- Universal Success/Error Alert Container -->
<div id="universalAlertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 10000; pointer-events: none;">
    <!-- Dynamic alerts will be inserted here -->
</div>

<style>
/* Universal Modal Styles */
.modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: #fefefe;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    animation: slideInDown 0.3s ease;
    overflow: hidden;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h3 {
    font-weight: 600;
}

.close {
    opacity: 0.7;
    transition: opacity 0.2s;
}

.close:hover {
    opacity: 1;
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #0056b3;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #545b62;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #c82333;
    transform: translateY(-1px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #1e7e34;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

/* Universal Alert Styles */
.universal-alert {
    pointer-events: auto;
    min-width: 300px;
    max-width: 500px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-bottom: 1rem;
}

/* Loading Spinner */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: #007bff;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-content {
        margin: 5% auto;
        max-width: 95%;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .universal-alert {
        min-width: auto;
        max-width: 90%;
        right: 5%;
    }
}
</style>

<script>
// Initialize universal confirmation system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Load the universal confirmation script if not already loaded
    if (typeof initializeEditDeleteButtons === 'undefined') {
        const script = document.createElement('script');
        script.src = 'js/universal-confirmation.js';
        script.onload = function() {
            initializeEditDeleteButtons();
        };
        document.head.appendChild(script);
    } else {
        initializeEditDeleteButtons();
    }
});

// Re-initialize when new content is added dynamically
function reinitializeUniversalConfirmation() {
    if (typeof initializeEditDeleteButtons === 'function') {
        initializeEditDeleteButtons();
    }
}
</script>
