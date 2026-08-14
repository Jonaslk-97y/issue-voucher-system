/**
 * Issue Voucher System - JavaScript
 * Main application JavaScript file
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Auto-dismiss alerts after 5 seconds
    autoDismissAlerts();
    
    // Form validation enhancements
    enhanceFormValidation();
    
    // Date field helpers
    initializeDateHelpers();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

/**
 * Auto-dismiss alert messages after 5 seconds
 */
function autoDismissAlerts() {
    var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
}

/**
 * Enhance form validation with real-time feedback
 */
function enhanceFormValidation() {
    var forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Real-time validation feedback
    var inputs = document.querySelectorAll('input[required], select[required], textarea[required]');
    inputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });
}

/**
 * Initialize date helpers with min/max dates
 */
function initializeDateHelpers() {
    var dateInputs = document.querySelectorAll('input[type="date"]');
    var today = new Date().toISOString().split('T')[0];
    
    dateInputs.forEach(function(input) {
        // Set max date to today for past dates
        if (input.id === 'issue_date' || input.id === 'purchase_date') {
            input.setAttribute('max', today);
        }
        
        // Set min date to today for future dates
        if (input.id === 'expected_return_date') {
            input.setAttribute('min', today);
        }
    });
}

/**
 * Search functionality with debounce
 */
function debounceSearch(inputId, tableId) {
    var input = document.getElementById(inputId);
    var table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    var debounceTimer;
    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            filterTable(input.value.toLowerCase(), table);
        }, 300);
    });
}

/**
 * Filter table rows based on search term
 */
function filterTable(searchTerm, table) {
    var rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

/**
 * Confirm action with custom message
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Format currency for Namibia (NAD)
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-NA', {
        style: 'currency',
        currency: 'NAD',
        minimumFractionDigits: 2
    }).format(amount);
}

/**
 * Format date to Namibia format (dd-mm-yyyy)
 */
function formatNamibiaDate(dateString) {
    var date = new Date(dateString);
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();
    return day + '-' + month + '-' + year;
}

/**
 * Validate Namibian phone numbers
 */
function validateNamibiaPhone(phone) {
    // Namibia phone format: +264 or 0 followed by 81, 85, 88, etc.
    var regex = /^(\+264|0)(81|82|83|84|85|88|27|60|61)\d{7}$/;
    return regex.test(phone.replace(/\s/g, ''));
}

/**
 * Print voucher with proper formatting
 */
function printVoucher(voucherId) {
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Voucher #${voucherId}</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    .no-print { display: none; }
                    .voucher-header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    .voucher-footer { border-top: 2px solid #333; padding-top: 10px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="voucher-header">
                    <h2>Issue Voucher System - Namibia</h2>
                    <p>Voucher #: ${voucherId}</p>
                </div>
                <div id="voucherContent">
                    <!-- Content will be loaded via AJAX -->
                    <p class="text-center">Loading voucher data...</p>
                </div>
                <div class="voucher-footer text-muted">
                    <p>Printed on: ${new Date().toLocaleString('en-NA')}</p>
                    <p>This is a system-generated document</p>
                </div>
                <button onclick="window.print()" class="btn btn-primary no-print">Print</button>
                <button onclick="window.close()" class="btn btn-secondary no-print">Close</button>
            </body>
        </html>
    `);
    
    // Load voucher content via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', `view.php?id=${voucherId}&format=print`, true);
    xhr.onload = function() {
        var content = printWindow.document.getElementById('voucherContent');
        if (xhr.status === 200) {
            content.innerHTML = xhr.responseText;
        } else {
            content.innerHTML = '<p class="text-danger">Error loading voucher data.</p>';
        }
    };
    xhr.send();
}

/**
 * Export data to CSV (Namibian format)
 */
function exportToCSV(data, filename) {
    // Add BOM for UTF-8
    var csvContent = '\uFEFF' + data;
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Calculate days between dates (Namibia timezone)
 */
function daysBetween(date1, date2) {
    var oneDay = 24 * 60 * 60 * 1000;
    var d1 = new Date(date1);
    var d2 = new Date(date2);
    // Adjust for Namibia timezone (UTC+2)
    d1.setHours(0, 0, 0, 0);
    d2.setHours(0, 0, 0, 0);
    return Math.round(Math.abs((d1 - d2) / oneDay));
}

/**
 * Check if asset is overdue based on expected return date
 */
function checkOverdue(expectedReturnDate) {
    var today = new Date();
    var expected = new Date(expectedReturnDate);
    expected.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    return today > expected;
}

// Export functions for use in inline scripts
window.formatNamibiaDate = formatNamibiaDate;
window.formatCurrency = formatCurrency;
window.validateNamibiaPhone = validateNamibiaPhone;
window.printVoucher = printVoucher;
window.exportToCSV = exportToCSV;
window.daysBetween = daysBetween;
window.checkOverdue = checkOverdue;
window.confirmAction = confirmAction;
window.debounceSearch = debounceSearch;