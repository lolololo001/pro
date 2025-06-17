/**
 * Universal Confirmation System for School Admin
 * Handles edit and delete confirmations across all pages
 */

// Global variables
let confirmationModal = null;
let editModal = null;
let currentAction = null;
let currentEntityId = null;
let currentEntityType = null;
let currentDeleteButton = null;
let currentEditButton = null;
let currentForm = null;

// Initialize the confirmation system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeConfirmationSystem();
    initializeEditDeleteButtons();
});

/**
 * Initialize the confirmation modal system
 */
function initializeConfirmationSystem() {
    // Create confirmation modal if it doesn't exist
    if (!document.getElementById('universalConfirmationModal')) {
        createConfirmationModal();
    }
    
    // Create edit modal if it doesn't exist
    if (!document.getElementById('universalEditModal')) {
        createEditModal();
    }
}

/**
 * Create the universal confirmation modal
 */
function createConfirmationModal() {
    const modalHTML = `
        <div id="universalConfirmationModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Confirm Action</h2>
                    <span class="close-modal" onclick="closeUniversalConfirmation()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="universalConfirmationMessage" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.5;"></div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn" onclick="closeUniversalConfirmation()" style="background-color: #6c757d; color: white; padding: 0.75rem 1.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" id="universalConfirmButton" class="btn btn-danger" style="padding: 0.75rem 1.5rem;">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Create the universal edit modal
 */
function createEditModal() {
    const modalHTML = `
        <div id="universalEditModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h2><i class="fas fa-edit"></i> <span id="editModalTitle">Edit</span></h2>
                    <span class="close-modal" onclick="closeUniversalEdit()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="universalEditFormContainer">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Initialize edit and delete buttons across all pages
 */
function initializeEditDeleteButtons() {
    // Initialize delete buttons with comprehensive selectors
    const deleteButtons = document.querySelectorAll(`
        a[href*="action=delete"],
        a[onclick*="delete"],
        .btn-icon.delete,
        .btn-delete,
        button[data-action="delete"],
        .delete-btn,
        [data-delete-id]
    `);

    deleteButtons.forEach(button => {
        // Remove existing event listeners to prevent duplicates
        button.removeEventListener('click', handleDeleteClick);

        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleDeleteClick(this);
        });
    });

    // Initialize edit buttons with comprehensive selectors
    const editButtons = document.querySelectorAll(`
        a[href*="edit_"],
        .btn-icon.edit,
        .btn-edit,
        button[data-action="edit"],
        .edit-btn,
        [data-edit-id]
    `);

    editButtons.forEach(button => {
        // Remove existing event listeners to prevent duplicates
        button.removeEventListener('click', handleEditClick);

        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleEditClick(this);
        });
    });

    // Initialize form submissions for updates
    const updateForms = document.querySelectorAll('form[data-confirm-update="true"]');
    updateForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this);
        });
    });
}

/**
 * Handle delete button clicks
 */
function handleDeleteClick(button) {
    const href = button.getAttribute('href') || '';
    const onclick = button.getAttribute('onclick') || '';
    const dataId = button.getAttribute('data-id') || button.getAttribute('data-delete-id');
    const dataType = button.getAttribute('data-type') || button.getAttribute('data-entity-type');
    const entityName = button.getAttribute('data-name') || button.getAttribute('title') || '';

    // Extract entity type and ID
    let entityType = dataType || 'item';
    let entityId = dataId;

    // Try to extract from href if not found in data attributes
    if (href && !entityId) {
        if (href.includes('students.php')) entityType = 'student';
        else if (href.includes('teachers.php')) entityType = 'teacher';
        else if (href.includes('classes.php')) entityType = 'class';
        else if (href.includes('departments.php')) entityType = 'department';
        else if (href.includes('parents.php')) entityType = 'parent';
        else if (href.includes('bursars.php')) entityType = 'bursar';
        else if (href.includes('payments.php')) entityType = 'payment';

        const idMatch = href.match(/[?&]id=(\d+)/);
        if (idMatch) entityId = idMatch[1];
    }

    // Try to extract from onclick if still not found
    if (onclick && !entityId) {
        const idMatch = onclick.match(/\((\d+)\)/);
        if (idMatch) entityId = idMatch[1];
    }

    // Try to extract from closest table row
    if (!entityId) {
        const row = button.closest('tr');
        if (row) {
            entityId = row.getAttribute('data-id') || row.id.replace(/.*-(\d+)$/, '$1');
        }
    }

    if (entityId) {
        showDeleteConfirmation(entityType, entityId, href, onclick, entityName);
    } else {
        console.error('Could not determine entity ID for delete action');
        showUniversalAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: Could not identify item to delete');
    }
}

/**
 * Handle edit button clicks
 */
function handleEditClick(button) {
    const href = button.getAttribute('href') || '';
    const onclick = button.getAttribute('onclick') || '';
    const dataId = button.getAttribute('data-id') || button.getAttribute('data-edit-id');
    const dataType = button.getAttribute('data-type') || button.getAttribute('data-entity-type');
    const entityName = button.getAttribute('data-name') || button.getAttribute('title') || '';

    // Extract entity type and ID
    let entityType = dataType || 'item';
    let entityId = dataId;

    // Try to extract from href if not found in data attributes
    if (href && !entityId) {
        if (href.includes('edit_student.php') || href.includes('students.php')) entityType = 'student';
        else if (href.includes('edit_teacher.php') || href.includes('teachers.php')) entityType = 'teacher';
        else if (href.includes('edit_class.php') || href.includes('classes.php')) entityType = 'class';
        else if (href.includes('edit_department.php') || href.includes('departments.php')) entityType = 'department';
        else if (href.includes('edit_parent.php') || href.includes('parents.php')) entityType = 'parent';
        else if (href.includes('edit_bursar.php') || href.includes('bursars.php')) entityType = 'bursar';
        else if (href.includes('edit_payment.php') || href.includes('payments.php')) entityType = 'payment';

        const idMatch = href.match(/[?&]id=(\d+)/);
        if (idMatch) entityId = idMatch[1];
    }

    // Try to extract from onclick if still not found
    if (onclick && !entityId) {
        const idMatch = onclick.match(/\((\d+)\)/);
        if (idMatch) entityId = idMatch[1];
    }

    // Try to extract from closest table row
    if (!entityId) {
        const row = button.closest('tr');
        if (row) {
            entityId = row.getAttribute('data-id') || row.id.replace(/.*-(\d+)$/, '$1');
        }
    }

    if (entityId) {
        // Check if this should open a modal or redirect
        if (button.hasAttribute('data-modal') || button.classList.contains('modal-edit') ||
            button.hasAttribute('data-edit-modal') || entityType === 'student' ||
            entityType === 'teacher' || entityType === 'parent') {
            showEditModal(entityType, entityId, entityName);
        } else {
            // For simple redirects, navigate directly
            if (href) {
                window.location.href = href;
            } else {
                showEditModal(entityType, entityId, entityName);
            }
        }
    } else {
        console.error('Could not determine entity ID for edit action');
        showUniversalAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: Could not identify item to edit');
    }
}

/**
 * Show delete confirmation dialog
 */
function showDeleteConfirmation(entityType, entityId, href, onclick, entityName = '') {
    currentAction = 'delete';
    currentEntityType = entityType;
    currentEntityId = entityId;

    const modal = document.getElementById('universalConfirmationModal');
    const message = document.getElementById('universalConfirmationMessage');
    const confirmBtn = document.getElementById('universalConfirmButton');

    // Create entity display name
    const displayName = entityName ? `"${entityName}"` : `this ${entityType}`;

    // Set confirmation message
    message.innerHTML = `
        <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 2rem; margin-bottom: 1rem;"></i>
        <p style="margin: 0; font-weight: 500;">Are you sure you want to delete ${displayName}?</p>
        <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">This action cannot be undone and will permanently remove all associated data.</p>
    `;

    // Reset confirm button styling for delete
    confirmBtn.className = 'btn btn-danger';
    confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';

    // Set confirm button action
    confirmBtn.onclick = function() {
        executeDelete(entityType, entityId, href, onclick);
    };

    // Show modal
    modal.style.display = 'block';

    // Add shake animation to modal for emphasis
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            modalContent.style.animation = '';
        }, 500);
    }
}

/**
 * Show edit modal
 */
function showEditModal(entityType, entityId, entityName = '') {
    currentAction = 'edit';
    currentEntityType = entityType;
    currentEntityId = entityId;

    const modal = document.getElementById('universalEditModal');
    const title = document.getElementById('editModalTitle');
    const container = document.getElementById('universalEditFormContainer');

    // Set title with entity name if available
    const displayTitle = entityName ?
        `Edit ${entityType.charAt(0).toUpperCase() + entityType.slice(1)}: ${entityName}` :
        `Edit ${entityType.charAt(0).toUpperCase() + entityType.slice(1)}`;
    title.textContent = displayTitle;

    // Show loading with better styling
    container.innerHTML = `
        <div class="loading-spinner" style="text-align: center; padding: 3rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #007bff; margin-bottom: 1rem;"></i>
            <p style="margin: 0; color: #666;">Loading ${entityType} information...</p>
        </div>
    `;

    // Show modal
    modal.style.display = 'block';

    // Load edit form
    fetch(`get_edit_form.php?type=${entityType}&id=${entityId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;

            // The form submit handler is now added within the form HTML itself
            // No need to add it here as it's included in the get_edit_form.php response
        })
        .catch(error => {
            console.error('Error loading edit form:', error);
            container.innerHTML = `
                <div class="alert alert-danger" style="margin: 2rem;">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Error loading form:</strong> ${error.message}
                    <br><br>
                    <button class="btn btn-secondary" onclick="closeUniversalEdit()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        });
}

/**
 * Show update confirmation dialog
 */
function showUpdateConfirmation(entityType, entityId, entityName = '', formData = null) {
    currentAction = 'update';
    currentEntityType = entityType;
    currentEntityId = entityId;
    currentForm = formData;

    const modal = document.getElementById('universalConfirmationModal');
    const message = document.getElementById('universalConfirmationMessage');
    const confirmBtn = document.getElementById('universalConfirmButton');

    // Create entity display name
    const displayName = entityName ? `"${entityName}"` : `this ${entityType}`;

    // Set confirmation message
    message.innerHTML = `
        <i class="fas fa-edit" style="color: #007bff; font-size: 2rem; margin-bottom: 1rem;"></i>
        <p style="margin: 0; font-weight: 500;">Are you sure you want to update ${displayName}?</p>
        <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">This will save all changes you have made to the ${entityType} information.</p>
    `;

    // Reset confirm button styling for update
    confirmBtn.className = 'btn btn-primary';
    confirmBtn.innerHTML = '<i class="fas fa-save"></i> Update';

    // Set confirm button action
    confirmBtn.onclick = function() {
        executeUpdate(entityType, entityId, formData);
    };

    // Show modal
    modal.style.display = 'block';
}

/**
 * Show edit confirmation
 */
function showEditConfirmation(form) {
    const modal = document.getElementById('universalConfirmationModal');
    const message = document.getElementById('universalConfirmationMessage');
    const confirmBtn = document.getElementById('universalConfirmButton');
    
    // Set confirmation message
    message.innerHTML = `
        <i class="fas fa-edit" style="color: #007bff; font-size: 2rem; margin-bottom: 1rem;"></i>
        <p style="margin: 0; font-weight: 500;">Are you sure you want to update this ${currentEntityType}?</p>
        <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">The changes will be saved immediately.</p>
    `;
    
    // Change confirm button to primary color for edit
    confirmBtn.className = 'btn btn-primary';
    confirmBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    
    // Set confirm button action
    confirmBtn.onclick = function() {
        executeEdit(form);
    };
    
    // Show modal
    modal.style.display = 'block';
}

/**
 * Execute delete action
 */
function executeDelete(entityType, entityId, href, onclick) {
    const confirmBtn = document.getElementById('universalConfirmButton');
    const originalContent = confirmBtn.innerHTML;

    // Show loading state
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    confirmBtn.disabled = true;

    // Determine delete method
    if (href && href.includes('action=delete')) {
        // Use existing URL-based delete for backward compatibility
        window.location.href = href;
        return;
    }

    // Use universal AJAX delete handler
    const deleteEndpoint = 'delete_handler.php';

    // Create form data
    const formData = new FormData();
    formData.append('type', entityType);
    formData.append('id', entityId);

    // Send delete request
    fetch(deleteEndpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        closeUniversalConfirmation();

        if (data.success) {
            showUniversalAlert('success', `<i class="fas fa-check-circle"></i> ${data.message}`);

            // Try to remove row from table with various ID patterns
            const possibleRowIds = [
                `${entityType}-row-${entityId}`,
                `${entityType}-${entityId}`,
                `row-${entityId}`,
                `${entityType}_${entityId}`
            ];

            let rowRemoved = false;
            for (const rowId of possibleRowIds) {
                const row = document.getElementById(rowId);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        // Update any counters or statistics
                        updatePageStatistics(entityType, 'delete');
                    }, 300);
                    rowRemoved = true;
                    break;
                }
            }

            // If no row was found, try to remove from table by finding the row with delete button
            if (!rowRemoved && currentDeleteButton) {
                const row = currentDeleteButton.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        updatePageStatistics(entityType, 'delete');
                    }, 300);
                    rowRemoved = true;
                }
            }

            // If still no row removed, reload page after delay
            if (!rowRemoved) {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            showUniversalAlert('danger', `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        closeUniversalConfirmation();
        showUniversalAlert('danger', `<i class="fas fa-exclamation-circle"></i> An error occurred while deleting the ${entityType}: ${error.message}`);
    })
    .finally(() => {
        confirmBtn.innerHTML = originalContent;
        confirmBtn.disabled = false;
    });
}

/**
 * Execute edit action
 */
function executeEdit(form) {
    const confirmBtn = document.getElementById('universalConfirmButton');
    const originalContent = confirmBtn.innerHTML;
    
    // Show loading state
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    confirmBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(form);
    
    // Determine edit endpoint
    const editEndpoint = `edit_${currentEntityType}.php?id=${currentEntityId}`;
    
    // Send edit request
    fetch(editEndpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        closeUniversalConfirmation();
        closeUniversalEdit();
        showUniversalAlert('success', `<i class="fas fa-check-circle"></i> ${currentEntityType.charAt(0).toUpperCase() + currentEntityType.slice(1)} updated successfully!`);
        
        // Reload page after short delay
        setTimeout(() => window.location.reload(), 1500);
    })
    .catch(error => {
        console.error('Error:', error);
        showUniversalAlert('danger', `<i class="fas fa-exclamation-circle"></i> Error updating ${currentEntityType}: ${error.message}`);
    })
    .finally(() => {
        confirmBtn.innerHTML = originalContent;
        confirmBtn.disabled = false;
        confirmBtn.className = 'btn btn-danger'; // Reset to default
    });
}

/**
 * Execute update action
 */
function executeUpdate(entityType, entityId, formData) {
    const confirmBtn = document.getElementById('universalConfirmButton');
    const originalContent = confirmBtn.innerHTML;

    // Show loading state
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    confirmBtn.disabled = true;

    // Use universal AJAX update handler
    const updateEndpoint = 'update_handler.php';

    // Add entity type and id to form data
    if (formData instanceof FormData) {
        formData.append('type', entityType);
        formData.append('id', entityId);
    } else {
        // If formData is not FormData, create new FormData
        const newFormData = new FormData();
        newFormData.append('type', entityType);
        newFormData.append('id', entityId);

        // Add all form fields if formData is an object
        if (formData && typeof formData === 'object') {
            for (const [key, value] of Object.entries(formData)) {
                newFormData.append(key, value);
            }
        }
        formData = newFormData;
    }

    // Send update request
    fetch(updateEndpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        closeUniversalConfirmation();
        closeUniversalEdit();

        if (data.success) {
            showUniversalAlert('success', `<i class="fas fa-check-circle"></i> ${data.message}`);

            // Try to update the row in the table if it exists
            updateTableRow(entityType, entityId, formData);

            // If no row update was possible, reload page after delay
            setTimeout(() => {
                if (document.querySelector(`[data-id="${entityId}"]`)) {
                    // Row exists, no need to reload
                } else {
                    window.location.reload();
                }
            }, 1500);
        } else {
            showUniversalAlert('danger', `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        closeUniversalConfirmation();
        showUniversalAlert('danger', `<i class="fas fa-exclamation-circle"></i> An error occurred while updating the ${entityType}: ${error.message}`);
    })
    .finally(() => {
        confirmBtn.innerHTML = originalContent;
        confirmBtn.disabled = false;
    });
}

/**
 * Update table row with new data
 */
function updateTableRow(entityType, entityId, formData) {
    // Try to find the table row
    const possibleRowIds = [
        `${entityType}-row-${entityId}`,
        `${entityType}-${entityId}`,
        `row-${entityId}`,
        `${entityType}_${entityId}`
    ];

    let row = null;
    for (const rowId of possibleRowIds) {
        row = document.getElementById(rowId);
        if (row) break;
    }

    // If no row found by ID, try to find by data attribute
    if (!row) {
        row = document.querySelector(`tr[data-id="${entityId}"]`);
    }

    if (row) {
        // Update specific cells based on entity type
        if (entityType === 'student') {
            updateStudentRow(row, formData);
        } else if (entityType === 'teacher') {
            updateTeacherRow(row, formData);
        } else if (entityType === 'parent') {
            updateParentRow(row, formData);
        }

        // Add visual feedback
        row.style.transition = 'background-color 0.3s ease';
        row.style.backgroundColor = '#d4edda';
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 2000);
    }
}

/**
 * Update student table row
 */
function updateStudentRow(row, formData) {
    const cells = row.querySelectorAll('td');

    // Update name (usually first cell after checkbox)
    const nameCell = cells[1] || cells[0];
    if (nameCell && formData.get('first_name') && formData.get('last_name')) {
        const nameElement = nameCell.querySelector('.student-name, .name');
        if (nameElement) {
            nameElement.textContent = `${formData.get('first_name')} ${formData.get('last_name')}`;
        }
    }

    // Update email if visible
    const emailElement = row.querySelector('.email, [data-field="email"]');
    if (emailElement && formData.get('email')) {
        emailElement.textContent = formData.get('email');
    }

    // Update class if visible
    const classElement = row.querySelector('.class, [data-field="class"]');
    if (classElement && formData.get('class_name')) {
        classElement.textContent = formData.get('class_name');
    }
}

/**
 * Update teacher table row
 */
function updateTeacherRow(row, formData) {
    const cells = row.querySelectorAll('td');

    // Update name
    const nameCell = cells[1] || cells[0];
    if (nameCell && formData.get('name')) {
        const nameElement = nameCell.querySelector('.teacher-name, .name');
        if (nameElement) {
            nameElement.textContent = formData.get('name');
        }
    }

    // Update email if visible
    const emailElement = row.querySelector('.email, [data-field="email"]');
    if (emailElement && formData.get('email')) {
        emailElement.textContent = formData.get('email');
    }

    // Update subject if visible
    const subjectElement = row.querySelector('.subject, [data-field="subject"]');
    if (subjectElement && formData.get('subject')) {
        subjectElement.textContent = formData.get('subject');
    }
}

/**
 * Update parent table row
 */
function updateParentRow(row, formData) {
    const cells = row.querySelectorAll('td');

    // Update name
    const nameCell = cells[1] || cells[0];
    if (nameCell) {
        const nameElement = nameCell.querySelector('.parent-name, .name');
        if (nameElement && formData.get('first_name') && formData.get('last_name')) {
            nameElement.textContent = `${formData.get('first_name')} ${formData.get('last_name')}`;
        }
    }

    // Update email if visible
    const emailElement = row.querySelector('.email, [data-field="email"]');
    if (emailElement && formData.get('email')) {
        emailElement.textContent = formData.get('email');
    }

    // Update phone if visible
    const phoneElement = row.querySelector('.phone, [data-field="phone"]');
    if (phoneElement && formData.get('phone')) {
        phoneElement.textContent = formData.get('phone');
    }
}

/**
 * Close confirmation modal
 */
function closeUniversalConfirmation() {
    const modal = document.getElementById('universalConfirmationModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentAction = null;
    currentEntityId = null;
    currentEntityType = null;
}

/**
 * Close edit modal
 */
function closeUniversalEdit() {
    const modal = document.getElementById('universalEditModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentAction = null;
    currentEntityId = null;
    currentEntityType = null;
}

/**
 * Show universal alert message
 */
function showUniversalAlert(type, message) {
    // Remove any existing alerts
    const existingAlerts = document.querySelectorAll('.universal-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} universal-alert`;
    alertDiv.innerHTML = message;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease;
    `;
    
    // Add to page
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 300);
    }, 5000);
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const confirmationModal = document.getElementById('universalConfirmationModal');
    const editModal = document.getElementById('universalEditModal');

    if (event.target === confirmationModal) {
        closeUniversalConfirmation();
    } else if (event.target === editModal) {
        closeUniversalEdit();
    }
});

/**
 * Update page statistics after CRUD operations
 */
function updatePageStatistics(entityType, operation) {
    // Update stat cards if they exist
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        const statInfo = card.querySelector('.stat-info h3');
        if (statInfo) {
            const currentCount = parseInt(statInfo.textContent) || 0;

            // Determine if this card should be updated
            const cardClasses = card.className;
            let shouldUpdate = false;

            if (operation === 'delete') {
                if (cardClasses.includes('all-') || cardClasses.includes('total-')) {
                    shouldUpdate = true;
                } else if (cardClasses.includes(entityType)) {
                    shouldUpdate = true;
                }
            } else if (operation === 'add') {
                if (cardClasses.includes('all-') || cardClasses.includes('total-')) {
                    shouldUpdate = true;
                } else if (cardClasses.includes(entityType)) {
                    shouldUpdate = true;
                }
            }

            if (shouldUpdate) {
                const newCount = operation === 'delete' ?
                    Math.max(0, currentCount - 1) :
                    currentCount + 1;

                // Animate the count change
                statInfo.style.transition = 'all 0.3s ease';
                statInfo.style.transform = 'scale(1.2)';
                statInfo.style.color = operation === 'delete' ? '#dc3545' : '#28a745';

                setTimeout(() => {
                    statInfo.textContent = newCount;
                    setTimeout(() => {
                        statInfo.style.transform = 'scale(1)';
                        statInfo.style.color = '';
                    }, 150);
                }, 150);
            }
        }
    });

    // Update table row count if visible
    const tableInfo = document.querySelector('.table-info, .showing-entries');
    if (tableInfo) {
        const text = tableInfo.textContent;
        const matches = text.match(/(\d+)/g);
        if (matches && matches.length > 0) {
            const currentTotal = parseInt(matches[matches.length - 1]);
            const newTotal = operation === 'delete' ?
                Math.max(0, currentTotal - 1) :
                currentTotal + 1;

            tableInfo.textContent = text.replace(/\d+(?=\s*(?:entries|items|records))/g, newTotal);
        }
    }
}

/**
 * Initialize confirmation system on page load
 */
function initializeUniversalConfirmation() {
    // Initialize edit and delete buttons
    initializeEditDeleteButtons();

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // ESC key to close modals
        if (e.key === 'Escape') {
            closeUniversalConfirmation();
            closeUniversalEdit();
        }

        // Enter key to confirm in confirmation modal
        if (e.key === 'Enter') {
            const confirmModal = document.getElementById('universalConfirmationModal');
            if (confirmModal && confirmModal.style.display === 'block') {
                const confirmBtn = document.getElementById('universalConfirmButton');
                if (confirmBtn && !confirmBtn.disabled) {
                    confirmBtn.click();
                }
            }
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeUniversalConfirmation);
} else {
    initializeUniversalConfirmation();
}
