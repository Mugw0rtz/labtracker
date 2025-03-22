<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCSS)) { echo $extraCSS; } ?>
</head>
<body>
    <div class="wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <img src="/assets/logo.svg" alt="<?php echo APP_NAME; ?>" height="30">
                    <?php echo APP_NAME; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tools.php') ? 'active' : ''; ?>" href="/tools.php">Tools</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'borrow.php') ? 'active' : ''; ?>" href="/borrow.php">Borrow</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'return.php') ? 'active' : ''; ?>" href="/return.php">Return</a>
                            </li>
                            
                            <?php if (isStaff()): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="/reports.php">Reports</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>" href="/notifications.php">
                                        Notifications
                                        <span class="notification-badge" id="notificationBadge"></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (isAdmin()): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="/settings.php">Settings</a>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['user_name']; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="/settings.php?tab=profile">Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/logout.php">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>" href="/login.php">Login</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Mobile Scanner Menu (Fixed Bottom) -->
        <?php if (isLoggedIn()): ?>
        <div class="d-md-none scan-menu">
            <div class="container">
                <div class="row">
                    <div class="col-6 text-center py-2">
                        <a href="/borrow.php" class="btn btn-outline-light btn-scan <?php echo (basename($_SERVER['PHP_SELF']) == 'borrow.php') ? 'active' : ''; ?>">
                            <i class="fas fa-qrcode"></i> Borrow
                        </a>
                    </div>
                    <div class="col-6 text-center py-2">
                        <a href="/return.php" class="btn btn-outline-light btn-scan <?php echo (basename($_SERVER['PHP_SELF']) == 'return.php') ? 'active' : ''; ?>">
                            <i class="fas fa-qrcode"></i> Return
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="container py-4">
            <?php 
            // Display alert messages if any
            if (isset($_SESSION['alert_message']) && isset($_SESSION['alert_type'])) {
                echo getAlert($_SESSION['alert_message'], $_SESSION['alert_type']);
                unset($_SESSION['alert_message']);
                unset($_SESSION['alert_type']);
            }
            ?>
