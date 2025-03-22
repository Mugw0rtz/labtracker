/**
 * Laboratory Tool Management System
 * Main JavaScript file
 */

// Document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add active class to current nav item based on URL
    highlightActiveNavItem();
    
    // Handle form validation
    setupFormValidation();
    
    // Setup AJAX CSRF token for all requests
    setupAjaxCsrf();
});

// Highlight active nav item based on current URL
function highlightActiveNavItem() {
    const currentLocation = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentLocation || 
            (href !== '/' && currentLocation.startsWith(href))) {
            link.classList.add('active');
        }
    });
}

// Set up client-side form validation
function setupFormValidation() {
    // Get all forms with the 'needs-validation' class
    const forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// Set up AJAX CSRF token for all jQuery AJAX requests
function setupAjaxCsrf() {
    if (typeof $ !== 'undefined') {
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                // Only send the token to relative URLs
                if (!/^(https?:)?\/\//i.test(settings.url)) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (csrfToken) {
                        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    }
                }
            }
        });
    }
}

// Format date helper function
function formatDate(dateString) {
    if (!dateString) return '';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Format date with time helper function
function formatDateTime(dateString) {
    if (!dateString) return '';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Get status label helper function
function getStatusLabel(status) {
    const labels = {
        'available': 'Available',
        'borrowed': 'Borrowed',
        'maintenance': 'In Maintenance',
        'missing': 'Missing',
        'inactive': 'Inactive',
        'active': 'Active',
        'damaged': 'Damaged',
        'archived': 'Archived',
        'overdue': 'Overdue',
        'reserved': 'Reserved'
    };
    
    return labels[status] || status.charAt(0).toUpperCase() + status.slice(1);
}

// Get status color class helper function
function getStatusColorClass(status) {
    const classes = {
        'available': 'success',
        'borrowed': 'primary',
        'maintenance': 'warning',
        'missing': 'danger',
        'inactive': 'secondary',
        'active': 'success',
        'damaged': 'danger',
        'archived': 'dark',
        'overdue': 'danger',
        'reserved': 'info'
    };
    
    return classes[status] || 'secondary';
}

// Show alert message
function showAlert(message, type = 'info', container = null) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    if (container) {
        document.querySelector(container).innerHTML = alertHtml;
    } else {
        // Add at the top of the main content area
        const main = document.querySelector('main');
        const firstChild = main.firstChild;
        
        // Create a div for the alert
        const alertContainer = document.createElement('div');
        alertContainer.innerHTML = alertHtml;
        
        // Insert at the top
        main.insertBefore(alertContainer, firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertContainer.querySelector('.alert').classList.remove('show');
            setTimeout(() => alertContainer.remove(), 150);
        }, 5000);
    }
}

// Copy text to clipboard
function copyToClipboard(text) {
    // Create a temporary input element
    const input = document.createElement('input');
    input.setAttribute('value', text);
    document.body.appendChild(input);
    
    // Select the text
    input.select();
    
    // Copy the text
    document.execCommand('copy');
    
    // Remove the temporary element
    document.body.removeChild(input);
    
    // Show feedback
    showAlert('Copied to clipboard!', 'success');
}

// Handle AJAX form submission
function submitFormAjax(formId, successCallback, errorCallback) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Create FormData object
        const formData = new FormData(form);
        
        // Convert FormData to URL-encoded string if needed
        const formUrlEncoded = new URLSearchParams(formData).toString();
        
        // Send AJAX request
        fetch(form.action, {
            method: form.method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formUrlEncoded
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof successCallback === 'function') {
                    successCallback(data);
                } else {
                    showAlert(data.message || 'Operation completed successfully', 'success');
                    
                    // If redirect URL is provided, redirect after a short delay
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }
                }
            } else {
                if (typeof errorCallback === 'function') {
                    errorCallback(data);
                } else {
                    showAlert(data.message || 'An error occurred', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An unexpected error occurred. Please try again.', 'danger');
            
            if (typeof errorCallback === 'function') {
                errorCallback({ success: false, message: 'An unexpected error occurred' });
            }
        });
    });
}

// Load QR Code scanner
function initQRScanner(elementId, resultCallback) {
    const element = document.getElementById(elementId);
    if (!element) return null;
    
    try {
        const scanner = new QrScanner(element, result => {
            if (typeof resultCallback === 'function') {
                resultCallback(result);
            }
        }, {
            highlightScanRegion: true,
            highlightCodeOutline: true,
        });
        
        return scanner;
    } catch (error) {
        console.error('QR Scanner initialization error:', error);
        return null;
    }
}

// Print QR code
function printQRCode(qrUrl, toolName) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>QR Code - ${toolName}</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; }
                    .container { margin: 20px auto; max-width: 400px; }
                    img { max-width: 100%; height: auto; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>${toolName}</h2>
                    <img src="${qrUrl}" alt="QR Code">
                    <p>Scan this QR code to borrow or return the tool.</p>
                </div>
                <script>
                    window.onload = function() { window.print(); }
                </script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Check for new notifications
function checkNotifications() {
    fetch('/api/notifications.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('notificationBadge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Generate tool code
function generateToolCode() {
    const prefix = 'TOOL';
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `${prefix}-${random}`;
}
