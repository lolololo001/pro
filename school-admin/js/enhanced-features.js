/**
 * Enhanced Features for School Admin System
 * Implements confirmation dialogs for delete and update actions
 * Adds advanced search functionality with attractive design
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize enhanced search functionality
    initializeEnhancedSearch();
    
    // Initialize confirmation dialogs for delete actions
    initializeDeleteConfirmations();
    
    // Initialize confirmation dialogs for update actions
    initializeUpdateConfirmations();
});

/**
 * Initialize enhanced search functionality
 */
function initializeEnhancedSearch() {
    // Create search container for students table if it doesn't exist
    createStudentSearchContainer();
    
    // Add event listeners to search inputs
    const searchInputs = document.querySelectorAll('.enhanced-search-input');
    
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-table');
        const table = document.getElementById(tableId);
        
        if (table) {
            // Add keyup event listener for real-time search
            input.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase().trim();
                performEnhancedSearch(table, searchTerm);
            });
            
            // Add clear button functionality
            const clearBtn = input.parentElement.querySelector('.search-clear-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    input.focus();
                    performEnhancedSearch(table, '');
                });
            }
        }
    });
}

/**
 * Create search container for students table
 */
function createStudentSearchContainer() {
    const studentsCard = document.querySelector('.card:has(.data-table)');
    
    if (studentsCard) {
        const cardHeader = studentsCard.querySelector('.card-header');
        const cardBody = studentsCard.querySelector('.card-body');
        
        // Check if search container already exists
        if (!cardBody.querySelector('.enhanced-search-container')) {
            // Create search container
            const searchContainer = document.createElement('div');
            searchContainer.className = 'enhanced-search-container';
            searchContainer.innerHTML = `
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="enhanced-search-input" data-table="students-table" placeholder="Search students by name, reg number, class, etc...">
                    <button type="button" class="search-clear-btn" title="Clear search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-results-info">
                    <span class="results-count"></span>
                </div>
            `;
            
            // Add search container to card body before the table
            const tableResponsive = cardBody.querySelector('.table-responsive');
            if (tableResponsive) {
                cardBody.insertBefore(searchContainer, tableResponsive);
                
                // Add ID to the table for easier reference
                const table = tableResponsive.querySelector('.data-table');
                if (table) {
                    table.id = 'students-table';
                }
            }
        }
    }
}

/**
 * Perform enhanced search on table
 * @param {HTMLElement} table - The table element to search in
 * @param {string} searchTerm - The search term
 */
function performEnhancedSearch(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const cells = row.querySelectorAll('td');
        
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
            
            // Highlight matching text if search term is not empty
            if (searchTerm !== '') {
                highlightMatchingText(cells, searchTerm);
            } else {
                // Remove highlighting
                cells.forEach(cell => {
                    cell.innerHTML = cell.innerHTML.replace(/<mark class="search-highlight">([^<]+)<\/mark>/g, '$1');
                });
            }
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update results count
    const resultsInfo = document.querySelector('.search-results-info .results-count');
    if (resultsInfo) {
        if (searchTerm === '') {
            resultsInfo.textContent = '';
        } else {
            resultsInfo.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''} found`;
        }
    }
    
    // Show/hide empty state
    const tableContainer = table.closest('.table-responsive');
    let emptyState = tableContainer.nextElementSibling;
    
    // If next element is not empty state, create it
    if (!emptyState || !emptyState.classList.contains('search-empty-state')) {
        emptyState = document.createElement('div');
        emptyState.className = 'search-empty-state';
        emptyState.innerHTML = `
            <div class="empty-icon"><i class="fas fa-search"></i></div>
            <div class="empty-text">No matching students found</div>
            <p>Try adjusting your search term or clear it to see all students.</p>
        `;
        emptyState.style.display = 'none';
        tableContainer.parentNode.insertBefore(emptyState, tableContainer.nextSibling);
    }
    
    // Show empty state if no results and search term is not empty
    if (visibleCount === 0 && searchTerm !== '') {
        tableContainer.style.display = 'none';
        emptyState.style.display = 'block';
    } else {
        tableContainer.style.display = '';
        emptyState.style.display = 'none';
    }
}

/**
 * Highlight matching text in table cells
 * @param {NodeList} cells - The table cells
 * @param {string} searchTerm - The search term to highlight
 */
function highlightMatchingText(cells, searchTerm) {
    cells.forEach(cell => {
        // Skip cells with complex content (like action buttons)
        if (cell.querySelector('.action-btns')) return;
        
        // Get the current HTML content
        let html = cell.innerHTML;
        
        // Remove existing highlights first
        html = html.replace(/<mark class="search-highlight">([^<]+)<\/mark>/g, '$1');
        
        // Create a case-insensitive regular expression for the search term
        const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
        
        // Replace matches with highlighted version
        html = html.replace(regex, '<mark class="search-highlight">$1</mark>');
        
        // Update the cell content
        cell.innerHTML = html;
    });
}

/**
 * Escape special characters in string for use in RegExp
 * @param {string} string - The string to escape
 * @returns {string} - Escaped string
 */
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Initialize confirmation dialogs for delete actions
 */
function initializeDeleteConfirmations() {
    // Find all delete buttons that don't already have event listeners
    const deleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="action=delete"], a[href*="action=delete"]');
    
    deleteButtons.forEach(button => {
        // Remove the original href
        const href = button.getAttribute('href');
        button.setAttribute('data-href', href);
        button.setAttribute('href', 'javascript:void(0)');
        
        // Add click event listener if not already added
        if (!button.hasAttribute('data-confirmation-initialized')) {
            button.setAttribute('data-confirmation-initialized', 'true');
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the entity type and ID from the href
                const entityType = getEntityTypeFromUrl(href);
                const entityId = getEntityIdFromUrl(href);
                
                // Show confirmation dialog
                showDeleteConfirmation(entityType, entityId, href);
            });
        }
    });
}

/**
 * Initialize confirmation dialogs for update actions
 */
function initializeUpdateConfirmations() {
    // Find all forms that submit updates
    const updateForms = document.querySelectorAll('form[action*="edit_"], form[action*="update_"]');
    
    updateForms.forEach(form => {
        // Add submit event listener if not already added
        if (!form.hasAttribute('data-confirmation-initialized')) {
            form.setAttribute('data-confirmation-initialized', 'true');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get the entity type from the form action
                const entityType = getEntityTypeFromUrl(form.getAttribute('action'));
                
                // Show confirmation dialog
                showUpdateConfirmation(entityType, form);
            });
        }
    });
}

/**
 * Show delete confirmation dialog
 * @param {string} entityType - The type of entity being deleted
 * @param {string|number} entityId - The ID of the entity being deleted
 * @param {string} href - The original href to navigate to if confirmed
 */
function showDeleteConfirmation(entityType, entityId, href) {
    // Format entity type for display
    const formattedType = formatEntityType(entityType);
    
    // Get or create confirmation modal
    const modal = getOrCreateConfirmationModal();
    
    // Set modal content
    const title = modal.querySelector('#confirmModalTitle');
    const message = modal.querySelector('#confirmModalMessage');
    const confirmBtn = modal.querySelector('#confirmActionButton');
    
    title.innerHTML = `<i class="fas fa-trash"></i> Delete ${formattedType}`;
    message.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #f44336;"></i> Are you sure you want to delete this ${formattedType.toLowerCase()}? This action cannot be undone.`;
    
    // Set confirm button style and text
    confirmBtn.className = 'btn btn-danger';
    confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    
    // Set confirm button action
    confirmBtn.onclick = function() {
        window.location.href = href;
    };
    
    // Show the modal
    modal.style.display = 'block';
}

/**
 * Show update confirmation dialog
 * @param {string} entityType - The type of entity being updated
 * @param {HTMLFormElement} form - The form being submitted
 */
function showUpdateConfirmation(entityType, form) {
    // Format entity type for display
    const formattedType = formatEntityType(entityType);
    
    // Get or create confirmation modal
    const modal = getOrCreateConfirmationModal();
    
    // Set modal content
    const title = modal.querySelector('#confirmModalTitle');
    const message = modal.querySelector('#confirmModalMessage');
    const confirmBtn = modal.querySelector('#confirmActionButton');
    
    title.innerHTML = `<i class="fas fa-edit"></i> Update ${formattedType}`;
    message.innerHTML = `Are you sure you want to update this ${formattedType.toLowerCase()}?`;
    
    // Set confirm button style and text
    confirmBtn.className = 'btn btn-primary';
    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Update';
    
    // Set confirm button action
    confirmBtn.onclick = function() {
        closeConfirmationModal();
        form.submit();
    };
    
    // Show the modal
    modal.style.display = 'block';
}

/**
 * Get or create confirmation modal
 * @returns {HTMLElement} - The confirmation modal element
 */
function getOrCreateConfirmationModal() {
    let modal = document.getElementById('enhancedConfirmationModal');
    
    if (!modal) {
        // Create the modal
        const modalHtml = `
            <div id="enhancedConfirmationModal" class="modal">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2 id="confirmModalTitle"><i class="fas fa-question-circle"></i> Confirmation</h2>
                        <span class="close-modal" onclick="closeConfirmationModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p id="confirmModalMessage">Are you sure you want to perform this action?</p>
                        <div class="form-actions" style="margin-top: 1.5rem;">
                            <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-danger" id="confirmActionButton">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer.firstElementChild);
        
        modal = document.getElementById('enhancedConfirmationModal');
        
        // Add close on outside click
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeConfirmationModal();
            }
        });
    }
    
    return modal;
}

/**
 * Close the confirmation modal
 */
function closeConfirmationModal() {
    const modal = document.getElementById('enhancedConfirmationModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Get entity type from URL
 * @param {string} url - The URL to extract entity type from
 * @returns {string} - The entity type
 */
function getEntityTypeFromUrl(url) {
    if (!url) return 'item';
    
    if (url.includes('student')) return 'student';
    if (url.includes('teacher')) return 'teacher';
    if (url.includes('parent')) return 'parent';
    if (url.includes('class')) return 'class';
    if (url.includes('department')) return 'department';
    if (url.includes('bursar')) return 'bursar';
    if (url.includes('payment')) return 'payment';
    
    return 'item';
}

/**
 * Get entity ID from URL
 * @param {string} url - The URL to extract entity ID from
 * @returns {string} - The entity ID
 */
function getEntityIdFromUrl(url) {
    if (!url) return '';
    
    const idMatch = url.match(/[?&]id=([^&]+)/);
    return idMatch ? idMatch[1] : '';
}

/**
 * Format entity type for display
 * @param {string} entityType - The entity type to format
 * @returns {string} - The formatted entity type
 */
function formatEntityType(entityType) {
    if (!entityType) return 'Item';
    
    // Capitalize first letter
    return entityType.charAt(0).toUpperCase() + entityType.slice(1);
}