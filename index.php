<?php
/**
 * Home Page
 * Landing page for the application
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Welcome';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <div class="text-center text-lg-start">
                <h1 class="display-4 fw-bold mb-4">Laboratory Tool Management System</h1>
                <p class="lead mb-4">An efficient way to manage laboratory tools with QR-based borrowing and returning, real-time inventory tracking, and automated notifications.</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <a href="login.php" class="btn btn-primary btn-lg px-4 me-md-2">Login</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mt-5 mt-lg-0">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center">
                        <i class="fas fa-tools fa-4x mb-3 text-primary"></i>
                        <h2 class="card-title mb-4">Key Features</h2>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-icon bg-primary bg-gradient text-white rounded-3 me-3">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div>
                                    <h5>QR Integration</h5>
                                    <p class="text-muted">Efficient check-in and check-out using QR codes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-icon bg-primary bg-gradient text-white rounded-3 me-3">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div>
                                    <h5>Mobile Ready</h5>
                                    <p class="text-muted">Borrow and return tools from any device</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-icon bg-primary bg-gradient text-white rounded-3 me-3">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div>
                                    <h5>Notifications</h5>
                                    <p class="text-muted">Automated alerts for deadlines and maintenance</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-icon bg-primary bg-gradient text-white rounded-3 me-3">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div>
                                    <h5>Reports</h5>
                                    <p class="text-muted">Generate detailed inventory reports</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-5">
        <div class="col-md-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-3x text-primary mb-3"></i>
                    <h3>Real-time Inventory</h3>
                    <p>Monitor tool availability and location in real-time with detailed tracking.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h3>User Management</h3>
                    <p>Control who can borrow specific tools with user roles and permissions.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-history fa-3x text-primary mb-3"></i>
                    <h3>Maintenance Tracking</h3>
                    <p>Schedule and track tool maintenance to ensure equipment reliability.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-5 mb-5">
        <div class="col-12 text-center">
            <h2 class="mb-4">How It Works</h2>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="circle-icon bg-primary mb-3">
                    <span>1</span>
                </div>
                <h4>Scan Tool QR Code</h4>
                <p>Use your mobile device to scan the QR code on the tool</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="circle-icon bg-primary mb-3">
                    <span>2</span>
                </div>
                <h4>Borrow the Tool</h4>
                <p>Confirm borrowing details and duration</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="circle-icon bg-primary mb-3">
                    <span>3</span>
                </div>
                <h4>Use the Tool</h4>
                <p>Receive notifications before return deadline</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="circle-icon bg-primary mb-3">
                    <span>4</span>
                </div>
                <h4>Return the Tool</h4>
                <p>Scan QR code again to check-in the tool</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
