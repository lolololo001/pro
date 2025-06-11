/**
 * Search functionality for admin pages
 * This script adds search functionality to data tables in the admin dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality for all data tables
    initializeSearch();
});

/**
 * Initialize search functionality for all data tables
 */
function initializeSearch() {
    const searchContainers = document.querySelectorAll('.search-container');
    
    searchContainers.forEach(container => {
        const searchInput = container.querySelector('.search-input');
        const tableId = container.getAttribute('data-table');
        const table = document.getElementById(tableId);
        
        if (searchInput && table) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show empty state if no results
                const visibleRows = table.querySelectorAll('tbody tr[style=""]');
                const emptyState = document.querySelector(`#${tableId}-empty-search`);
                
                if (emptyState) {
                    if (visibleRows.length === 0 && searchTerm !== '') {
                        emptyState.style.display = 'block';
                    } else {
                        emptyState.style.display = 'none';
                    }
                }
            });
        }
    });
}

/**
 * Clear search input and reset table
 * @param {string} tableId - ID of the table to reset
 */
function clearSearch(tableId) {
    const searchContainer = document.querySelector(`.search-container[data-table="${tableId}"]`);
    const searchInput = searchContainer.querySelector('.search-input');
    
    if (searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('keyup'));
    }
}