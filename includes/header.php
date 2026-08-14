<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>">
            <i class="bi bi-box-arrow-right"></i> <?php echo APP_NAME; ?>
        </a>
        <?php if (isLoggedIn()): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/modules/dashboard/">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-file-text"></i> Vouchers
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/vouchers/create.php">
                            <i class="bi bi-plus-circle"></i> Create New</a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/vouchers/list.php">
                            <i class="bi bi-list"></i> View All</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-laptop"></i> Assets
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/assets/add.php">
                            <i class="bi bi-plus-circle"></i> Add Asset</a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/assets/list.php">
                            <i class="bi bi-list"></i> Manage Assets</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/modules/reports/">
                        <i class="bi bi-bar-chart"></i> Reports
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container"></main>