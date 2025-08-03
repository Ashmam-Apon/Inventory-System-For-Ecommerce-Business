// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile navigation toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
            }
        });
    }
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Table row selection
    const selectAllCheckbox = document.querySelector('#selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-select');
    
    if (selectAllCheckbox && rowCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('.row-select:checked').length;
                selectAllCheckbox.checked = checkedCount === rowCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
            });
        });
    }
    
    // Live search functionality
    const searchInput = document.querySelector('#searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 300);
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Dynamic form fields for booking items
    const addItemButton = document.querySelector('#addItem');
    const itemsContainer = document.querySelector('#itemsContainer');
    
    if (addItemButton && itemsContainer) {
        let itemCount = itemsContainer.children.length;
        
        addItemButton.addEventListener('click', function() {
            const newItem = createItemRow(itemCount);
            itemsContainer.appendChild(newItem);
            itemCount++;
            updateTotalAmount();
        });
        
        // Remove item functionality
        itemsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                if (itemsContainer.children.length > 1) {
                    e.target.closest('.item-row').remove();
                    updateTotalAmount();
                }
            }
        });
        
        // Update amount when quantity or product changes
        itemsContainer.addEventListener('change', function(e) {
            if (e.target.name && (e.target.name.includes('product_id') || e.target.name.includes('quantity'))) {
                updateItemAmount(e.target.closest('.item-row'));
                updateTotalAmount();
            }
        });
    }
});

// Search functionality
function performSearch(query) {
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(query.toLowerCase());
        row.style.display = matches ? '' : 'none';
    });
    
    // Update no results message
    const visibleRows = document.querySelectorAll('tbody tr[style=""], tbody tr:not([style])');
    const noResultsRow = document.querySelector('.no-results');
    
    if (visibleRows.length === 0 && query.trim() !== '') {
        if (!noResultsRow) {
            const tbody = document.querySelector('tbody');
            const tr = document.createElement('tr');
            tr.className = 'no-results';
            tr.innerHTML = `<td colspan="100%" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                No results found for "${query}"
            </td>`;
            tbody.appendChild(tr);
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

// Form validation
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        const value = field.value.trim();
        const errorElement = field.parentNode.querySelector('.field-error');
        
        if (!value) {
            isValid = false;
            field.classList.add('error');
            
            if (!errorElement) {
                const error = document.createElement('div');
                error.className = 'field-error';
                error.textContent = 'This field is required';
                error.style.color = 'var(--error-color)';
                error.style.fontSize = '0.875rem';
                error.style.marginTop = '4px';
                field.parentNode.appendChild(error);
            }
        } else {
            field.classList.remove('error');
            if (errorElement) {
                errorElement.remove();
            }
        }
    });
    
    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (field.value && !emailRegex.test(field.value)) {
            isValid = false;
            field.classList.add('error');
        }
    });
    
    // Phone validation - more flexible regex
    const phoneFields = form.querySelectorAll('input[type="tel"]');
    phoneFields.forEach(field => {
        // Allow digits, spaces, dashes, parentheses, and plus sign
        const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
        if (field.value && !phoneRegex.test(field.value.trim())) {
            isValid = false;
            field.classList.add('error');
            
            const errorElement = field.parentNode.querySelector('.field-error');
            if (!errorElement) {
                const error = document.createElement('div');
                error.className = 'field-error';
                error.textContent = 'Please enter a valid phone number';
                error.style.color = 'var(--error-color)';
                error.style.fontSize = '0.875rem';
                error.style.marginTop = '4px';
                field.parentNode.appendChild(error);
            }
        }
    });
    
    return isValid;
}

// Create new item row for booking form
function createItemRow(index) {
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Product</label>
                <select name="items[${index}][product_id]" class="form-select" required>
                    <option value="">Select Product</option>
                    ${getProductOptions()}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="number" name="items[${index}][quantity]" class="form-input" min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Unit Price</label>
                <input type="number" name="items[${index}][unit_price]" class="form-input" step="0.01" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Amount</label>
                <div style="display: flex; align-items: end; gap: 10px;">
                    <input type="number" name="items[${index}][amount]" class="form-input item-amount" step="0.01" readonly>
                    <button type="button" class="btn btn-error btn-sm remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    return div;
}

// Get product options (this would be populated from server data)
function getProductOptions() {
    // This would normally be populated from PHP/server data
    return '';
}

// Update item amount based on quantity and unit price
function updateItemAmount(itemRow) {
    const productSelect = itemRow.querySelector('select[name*="product_id"]');
    const quantityInput = itemRow.querySelector('input[name*="quantity"]');
    const unitPriceInput = itemRow.querySelector('input[name*="unit_price"]');
    const amountInput = itemRow.querySelector('input[name*="amount"]');
    
    if (productSelect.value && window.productPrices) {
        const productPrice = window.productPrices[productSelect.value];
        unitPriceInput.value = productPrice || 0;
    }
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    const amount = quantity * unitPrice;
    
    amountInput.value = amount.toFixed(2);
}

// Update total amount for booking
function updateTotalAmount() {
    const amountInputs = document.querySelectorAll('.item-amount');
    let total = 0;
    
    amountInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    const totalAmountInput = document.querySelector('#totalAmount');
    if (totalAmountInput) {
        totalAmountInput.value = total.toFixed(2);
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
}

function showLoading(element) {
    const original = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    element.disabled = true;
    
    return function hideLoading() {
        element.innerHTML = original;
        element.disabled = false;
    };
}

// AJAX helper
function makeRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = Object.assign(defaults, options);
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

// Chart initialization (if Chart.js is loaded)
function initializeCharts() {
    if (typeof Chart === 'undefined') return;
    
    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: window.chartData?.revenueLabels || [],
                datasets: [{
                    label: 'Revenue',
                    data: window.chartData?.revenueData || [],
                    borderColor: 'rgb(37, 99, 235)',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Status distribution chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: window.chartData?.statusLabels || [],
                datasets: [{
                    data: window.chartData?.statusData || [],
                    backgroundColor: [
                        '#f59e0b',
                        '#10b981',
                        '#ef4444',
                        '#2563eb',
                        '#6b7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initializeCharts, 100);
});
