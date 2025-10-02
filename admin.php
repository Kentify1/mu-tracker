<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vip_admin_enhancements.php';

// Check if user is admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: login');
    exit;
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_licenses':
            echo json_encode(getAllLicenses());
            exit;

        case 'create_license':
            $license_key = $_POST['license_key'] ?? '';
            $license_type = $_POST['license_type'] ?? 'regular';
            $max_uses = intval($_POST['max_uses'] ?? 1);
            $expires_at = $_POST['expires_at'] ?? null;
            echo json_encode(createLicense($license_key, $max_uses, $expires_at, $license_type));
            exit;

        case 'update_license':
            $id = intval($_POST['id'] ?? 0);
            $license_key = $_POST['license_key'] ?? '';
            $license_type = $_POST['license_type'] ?? 'regular';
            $max_uses = intval($_POST['max_uses'] ?? 1);
            $expires_at = $_POST['expires_at'] ?? null;
            echo json_encode(updateLicense($id, $license_key, $max_uses, $expires_at, $license_type));
            exit;

        case 'delete_license':
            $id = intval($_POST['id'] ?? 0);
            echo json_encode(deleteLicense($id));
            exit;

        case 'get_users':
            echo json_encode(getAllUsers());
            exit;

        case 'update_user_role':
            $user_id = intval($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'regular';
            echo json_encode(updateUserRole($user_id, $role));
            exit;

        case 'toggle_user_status':
            $user_id = intval($_POST['user_id'] ?? 0);
            echo json_encode(toggleUserStatus($user_id));
            exit;

        case 'get_activity_logs':
            $limit = intval($_POST['limit'] ?? 50);
            echo json_encode(getActivityLogs($limit));
            exit;

        case 'get_all_characters':
            echo json_encode(getAllCharactersAdmin());
            exit;

        case 'delete_character_admin':
            $character_id = intval($_POST['character_id'] ?? 0);
            echo json_encode(deleteCharacterAdmin($character_id));
            exit;

        case 'get_user_characters':
            $user_id = intval($_POST['user_id'] ?? 0);
            echo json_encode(getUserCharacters($user_id));
            exit;

        case 'get_system_health':
            echo json_encode(getSystemHealthMetrics());
            exit;

        case 'get_advanced_user_analytics':
            echo json_encode(getAdvancedUserAnalytics());
            exit;

        case 'bulk_user_operation':
            $operation = $_POST['operation'] ?? '';
            $userIds = $_POST['user_ids'] ?? [];
            $params = $_POST['params'] ?? [];
            echo json_encode(performBulkUserOperation($operation, $userIds, $params));
            exit;
    }
}

// Get dashboard statistics
$stats = getAdminStats();
$currentUser = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MU Tracker - Admin Panel</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="index" class="nav-link">Back to App</a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user"></i>
                        <span class="hidden-xs"><?= htmlspecialchars($currentUser['username']) ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <span class="dropdown-item dropdown-header">Admin Panel</span>
                        <div class="dropdown-divider"></div>
                        <a href="index" class="dropdown-item">
                            <i class="fas fa-arrow-left mr-2"></i> Back to App
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->
            <a href="#" class="brand-link">
                <img src="https://adminlte.io/themes/v3/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
                <span class="brand-text font-weight-light">MU Tracker Admin</span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <img src="https://adminlte.io/themes/v3/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
                    </div>
                    <div class="info">
                        <a href="#" class="d-block"><?= htmlspecialchars($currentUser['username']) ?></a>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <li class="nav-item">
                            <a href="#" class="nav-link active" onclick="showSection('dashboard')">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('licenses')">
                                <i class="nav-icon fas fa-key"></i>
                                <p>License Keys</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('users')">
                                <i class="nav-icon fas fa-users"></i>
                                <p>User Management</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('characters')">
                                <i class="nav-icon fas fa-gamepad"></i>
                                <p>Characters</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('activity')">
                                <i class="nav-icon fas fa-history"></i>
                                <p>Activity Logs</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('system')">
                                <i class="nav-icon fas fa-server"></i>
                                <p>System Health</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('analytics')">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Advanced Analytics</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0" id="page-title">Dashboard</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active" id="breadcrumb-active">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Dashboard Section -->
                    <div id="dashboard-section" class="admin-section">
                        <!-- Info boxes -->
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Users</span>
                                        <span class="info-box-number"><?= $stats['total_users'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-success elevation-1"><i class="fas fa-gamepad"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Characters</span>
                                        <span class="info-box-number"><?= $stats['total_characters'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="clearfix hidden-md-up"></div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-key"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Active Licenses</span>
                                        <span class="info-box-number"><?= $stats['active_licenses'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-crown"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">VIP Users</span>
                                        <span class="info-box-number"><?= $stats['vip_users'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Tools Row -->
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-file-alt"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">System Logs</span>
                                        <span class="info-box-number">
                                            <a href="log_viewer" class="text-white" style="text-decoration: none;">
                                                <i class="fas fa-search"></i> View
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-dark elevation-1"><i class="fas fa-bug"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Error Testing</span>
                                        <span class="info-box-number">
                                            <a href="test_errors" class="text-white" style="text-decoration: none;">
                                                <i class="fas fa-vial"></i> Test
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity and User Roles Chart -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-clock mr-1"></i>
                                            Recent Activity
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div id="recent-activity" style="max-height: 400px; overflow-y: auto;">
                                            <!-- Activity logs will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-chart-pie mr-1"></i>
                                            User Roles Distribution
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="roleChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Licenses Section -->
                    <div id="licenses-section" class="admin-section" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">License Key Management</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showCreateLicenseModal()">
                                        <i class="fas fa-plus"></i> Create License
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="licenses-table" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>License Key</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Uses</th>
                                                <th>Max Uses</th>
                                                <th>Expires</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Licenses will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Section -->
                    <div id="users-section" class="admin-section" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">User Management</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="users-table" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Characters</th>
                                                <th>Last Login</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Users will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Characters Section -->
                    <div id="characters-section" class="admin-section" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Character Management</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="loadAllCharacters()">
                                        <i class="fas fa-sync"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="characters-table" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Character</th>
                                                <th>Owner</th>
                                                <th>Level</th>
                                                <th>Resets</th>
                                                <th>Status</th>
                                                <th>Last Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Characters will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Logs Section -->
                    <div id="activity-section" class="admin-section" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Activity Logs</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="loadActivityLogs()">
                                        <i class="fas fa-sync"></i> Refresh
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm" onclick="exportActivityLogs()">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" id="activitySearch" placeholder="Search logs..." onkeyup="filterActivityLogs()">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" id="userFilter" onchange="filterActivityLogs()">
                                            <option value="">All Users</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" id="actionFilter" onchange="filterActivityLogs()">
                                            <option value="">All Actions</option>
                                            <option value="User login">Login</option>
                                            <option value="User logout">Logout</option>
                                            <option value="User registration">Registration</option>
                                            <option value="Character added">Character Added</option>
                                            <option value="Character removed">Character Removed</option>
                                            <option value="Characters refreshed">Characters Refreshed</option>
                                            <option value="Created license key">License Created</option>
                                            <option value="Updated license key">License Updated</option>
                                            <option value="Deleted license key">License Deleted</option>
                                            <option value="Updated user role">User Role Updated</option>
                                            <option value="User activated">User Activated</option>
                                            <option value="User deactivated">User Deactivated</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" class="form-control" id="dateFilter" onchange="filterActivityLogs()">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" id="limitFilter" onchange="filterActivityLogs()">
                                            <option value="50">50 records</option>
                                            <option value="100">100 records</option>
                                            <option value="250">250 records</option>
                                            <option value="500">500 records</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table id="activity-table" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Activity logs will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Health Section -->
                    <div id="system-section" class="admin-section" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">System Health Monitoring</h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="loadSystemHealth()">
                                                <i class="fas fa-sync"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row" id="systemHealthMetrics">
                                            <!-- System health metrics will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Analytics Section -->
                    <div id="analytics-section" class="admin-section" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Advanced User Analytics</h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="loadAdvancedAnalytics()">
                                                <i class="fas fa-sync"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Registration Trends Chart -->
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <h5>User Registration Trends (Last 30 Days)</h5>
                                                <canvas id="registrationChart" width="400" height="100"></canvas>
                                            </div>
                                        </div>

                                        <!-- User Engagement Table -->
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <h5>Top User Engagement</h5>
                                                <div class="table-responsive">
                                                    <table id="engagement-table" class="table table-bordered table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Username</th>
                                                                <th>Role</th>
                                                                <th>Characters</th>
                                                                <th>Resets Gained (30d)</th>
                                                                <th>Active Days</th>
                                                                <th>Days Since Registration</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!-- User engagement data will be loaded here -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- License Statistics -->
                                        <div class="row">
                                            <div class="col-12">
                                                <h5>License Usage Statistics</h5>
                                                <canvas id="licenseChart" width="400" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="float-right d-none d-sm-block">
                <b>Version</b> 1.0.0
            </div>
            <strong>Copyright &copy; 2024 <a href="#">MU Tracker</a>.</strong> All rights reserved.
        </footer>
    </div>

    <!-- License Modal -->
    <div class="modal fade" id="licenseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="licenseModalTitle">Create License</h4>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="licenseForm">
                        <input type="hidden" id="licenseId" name="id">
                        <div class="form-group">
                            <label for="licenseKey">License Key</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="licenseKey" name="license_key" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateLicenseKey()">
                                        <i class="fas fa-sync"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="licenseType">License Type</label>
                            <select class="form-control" id="licenseType" name="license_type" required>
                                <option value="regular">Regular</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="maxUses">Max Uses</label>
                            <input type="number" class="form-control" id="maxUses" name="max_uses" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="expiresAt">Expires At (optional)</label>
                            <input type="datetime-local" class="form-control" id="expiresAt" name="expires_at">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveLicense()">Save License</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Global variables
        let currentSection = 'dashboard';
        let roleChart = null;
        let allActivityLogs = [];
        let filteredActivityLogs = [];

        // Initialize admin panel
        $(document).ready(function() {
            showSection('dashboard');
            loadDashboardData();
        });

        // Navigation functions
        function showSection(section) {
            // Hide all sections
            $('.admin-section').hide();

            // Remove active class from nav links
            $('.nav-link').removeClass('active');

            // Show selected section
            $('#' + section + '-section').show();

            // Add active class to nav link
            $('a[onclick="showSection(\'' + section + '\')"]').addClass('active');

            // Update page title and breadcrumb
            const titles = {
                'dashboard': 'Dashboard',
                'licenses': 'License Keys',
                'users': 'User Management',
                'characters': 'Characters',
                'activity': 'Activity Logs',
                'system': 'System Health',
                'analytics': 'Advanced Analytics'
            };

            $('#page-title').text(titles[section]);
            $('#breadcrumb-active').text(titles[section]);

            currentSection = section;

            // Load section data
            switch (section) {
                case 'dashboard':
                    loadDashboardData();
                    break;
                case 'licenses':
                    loadLicenses();
                    break;
                case 'users':
                    loadUsers();
                    break;
                case 'characters':
                    loadAllCharacters();
                    break;
                case 'activity':
                    loadActivityLogs();
                    break;
                case 'system':
                    loadSystemHealth();
                    break;
                case 'analytics':
                    loadAdvancedAnalytics();
                    break;
            }
        }

        // Dashboard functions
        function loadDashboardData() {
            loadRecentActivity();
            loadRoleChart();
        }

        function loadRecentActivity() {
            $.post('', {
                action: 'get_activity_logs',
                limit: 10
            }).done(function(data) {
                if (data.success) {
                    const container = $('#recent-activity');
                    container.html(data.data.map(log => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>${log.user}</strong> ${log.action}
                            <br><small class="text-muted">${log.details}</small>
                        </div>
                        <small class="text-muted">${new Date(log.timestamp).toLocaleString()}</small>
                    </div>
                `).join(''));
                }
            });
        }

        function loadRoleChart() {
            $.post('', {
                action: 'get_users'
            }).done(function(data) {
                if (data.success && data.data && data.data.length > 0) {
                    const roles = data.data.reduce((acc, user) => {
                        const role = user.user_role || 'regular';
                        acc[role] = (acc[role] || 0) + 1;
                        return acc;
                    }, {});

                    const ctx = document.getElementById('roleChart').getContext('2d');
                    if (roleChart) roleChart.destroy();

                    if (Object.keys(roles).length > 0) {
                        roleChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(roles).map(role => role.charAt(0).toUpperCase() + role.slice(1)),
                                datasets: [{
                                    data: Object.values(roles),
                                    backgroundColor: [
                                        '#ffc107', // Regular - Yellow
                                        '#17a2b8', // VIP - Teal
                                        '#28a745', // Admin - Green
                                        '#6c757d', // Other - Gray
                                        '#dc3545' // Additional - Red
                                    ],
                                    borderWidth: 2,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            padding: 20,
                                            usePointStyle: true
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
            });
        }

        // License management functions
        function loadLicenses() {
            $.post('', {
                action: 'get_licenses'
            }).done(function(data) {
                if (data.success) {
                    const tbody = $('#licenses-table tbody');
                    tbody.html(data.data.map(license => `
                    <tr>
                        <td>${license.id}</td>
                        <td><code>${license.license_key}</code></td>
                        <td>
                            <span class="badge ${license.license_type === 'vip' ? 'badge-warning' : 'badge-info'}">
                                ${license.license_type === 'vip' ? 'VIP' : 'Regular'}
                            </span>
                        </td>
                        <td>
                            <span class="badge ${license.is_used ? 'badge-danger' : 'badge-success'}">
                                ${license.is_used ? 'Used' : 'Available'}
                            </span>
                        </td>
                        <td>${license.current_uses}</td>
                        <td>${license.max_uses}</td>
                        <td>${license.expires_at ? new Date(license.expires_at).toLocaleDateString() : 'Never'}</td>
                        <td>${new Date(license.created_at).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editLicense(${license.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLicense(${license.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join(''));
                }
            });
        }

        function showCreateLicenseModal() {
            $('#licenseModalTitle').text('Create License');
            $('#licenseForm')[0].reset();
            $('#licenseId').val('');
            // Generate a default license key
            generateLicenseKey();
            $('#licenseModal').modal('show');
        }

        function editLicense(id) {
            // Find the license data from the table
            const licenseRow = $(`#licenses-table tbody tr`).filter(function() {
                return $(this).find('td:first').text() == id;
            });

            if (licenseRow.length > 0) {
                const cells = licenseRow.find('td');
                const licenseKey = cells.eq(1).find('code').text();
                const licenseType = cells.eq(2).find('.badge').text().toLowerCase();
                const maxUses = cells.eq(5).text();

                // Populate the form
                $('#licenseId').val(id);
                $('#licenseKey').val(licenseKey);
                $('#licenseType').val(licenseType);
                $('#maxUses').val(maxUses);

                // Set modal title
                $('#licenseModalTitle').text('Edit License');

                $('#licenseModal').modal('show');
            }
        }

        function generateLicenseKey() {
            const licenseType = $('#licenseType').val();
            const year = new Date().getFullYear();
            const timestamp = Date.now().toString().slice(-6);
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

            let prefix = 'MUTRACK';
            if (licenseType === 'vip') {
                prefix = 'VIP';
            }

            const generatedKey = `${prefix}-${year}-${timestamp}${random}`;
            $('#licenseKey').val(generatedKey);
        }

        // Auto-generate license key when type changes (only for new licenses)
        $(document).on('change', '#licenseType', function() {
            if (!$('#licenseId').val()) { // Only for new licenses, not when editing
                generateLicenseKey();
            }
        });

        function saveLicense() {
            const form = $('#licenseForm')[0];
            const formData = new FormData(form);
            const action = formData.get('id') ? 'update_license' : 'create_license';

            $.post('', {
                action: action,
                ...Object.fromEntries(formData)
            }).done(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    $('#licenseModal').modal('hide');
                    loadLicenses();
                } else {
                    showToast(data.message, 'error');
                }
            });
        }

        function deleteLicense(id) {
            if (confirm('Are you sure you want to delete this license?')) {
                $.post('', {
                    action: 'delete_license',
                    id: id
                }).done(function(data) {
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadLicenses();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            }
        }

        // User management functions
        function loadUsers() {
            $.post('', {
                action: 'get_users'
            }).done(function(data) {
                if (data.success) {
                    const tbody = $('#users-table tbody');
                    tbody.html(data.data.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.username}</td>
                        <td>${user.email}</td>
                        <td>
                            <span class="badge badge-${user.user_role === 'admin' ? 'danger' : user.user_role === 'vip' ? 'warning' : 'secondary'}">
                                ${user.user_role.toUpperCase()}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-${user.is_active ? 'success' : 'danger'}">
                                ${user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>${user.character_count || 0}</td>
                        <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <div class="btn-group">
                                <select class="form-control form-control-sm" onchange="updateUserRole(${user.id}, this.value)">
                                    <option value="regular" ${user.user_role === 'regular' ? 'selected' : ''}>Regular</option>
                                    <option value="vip" ${user.user_role === 'vip' ? 'selected' : ''}>VIP</option>
                                    <option value="admin" ${user.user_role === 'admin' ? 'selected' : ''}>Admin</option>
                                </select>
                                <button class="btn btn-sm btn-${user.is_active ? 'warning' : 'success'}" onclick="toggleUserStatus(${user.id})">
                                    <i class="fas fa-${user.is_active ? 'ban' : 'check'}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join(''));
                }
            });
        }

        function updateUserRole(userId, role) {
            $.post('', {
                action: 'update_user_role',
                user_id: userId,
                role: role
            }).done(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                } else {
                    showToast(data.message, 'error');
                }
            });
        }

        function toggleUserStatus(userId) {
            $.post('', {
                action: 'toggle_user_status',
                user_id: userId
            }).done(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                } else {
                    showToast(data.message, 'error');
                }
            });
        }

        // Character management functions
        function loadAllCharacters() {
            $.post('', {
                action: 'get_all_characters'
            }).done(function(data) {
                if (data.success) {
                    const tbody = $('#characters-table tbody');
                    tbody.html(data.data.map(character => `
                    <tr>
                        <td>${character.id}</td>
                        <td>
                            <strong>${character.name}</strong><br>
                            <small class="text-muted">${character.class}</small>
                        </td>
                        <td>${character.username}</td>
                        <td>${character.level}</td>
                        <td>${character.resets}</td>
                        <td>
                            <span class="badge badge-${character.status === 'Online' ? 'success' : 'secondary'}">
                                ${character.status}
                            </span>
                        </td>
                        <td>${new Date(character.last_updated).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deleteCharacterAdmin(${character.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join(''));
                }
            });
        }

        function deleteCharacterAdmin(characterId) {
            if (confirm('Are you sure you want to delete this character?')) {
                $.post('', {
                    action: 'delete_character_admin',
                    character_id: characterId
                }).done(function(data) {
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadAllCharacters();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            }
        }

        // Activity logs functions
        function loadActivityLogs() {
            const limit = $('#limitFilter').val() || 50;
            $.post('', {
                action: 'get_activity_logs',
                limit: limit
            }).done(function(data) {
                if (data.success) {
                    allActivityLogs = data.data;
                    filteredActivityLogs = [...allActivityLogs];
                    populateUserFilter();
                    displayActivityLogs();
                }
            });
        }

        function populateUserFilter() {
            const userFilter = $('#userFilter');
            const uniqueUsers = [...new Set(allActivityLogs.map(log => log.user))];
            userFilter.html('<option value="">All Users</option>' +
                uniqueUsers.map(user => `<option value="${user}">${user}</option>`).join(''));
        }

        function filterActivityLogs() {
            const search = $('#activitySearch').val().toLowerCase();
            const user = $('#userFilter').val();
            const action = $('#actionFilter').val();
            const date = $('#dateFilter').val();

            filteredActivityLogs = allActivityLogs.filter(log => {
                const matchesSearch = !search ||
                    log.user.toLowerCase().includes(search) ||
                    log.action.toLowerCase().includes(search) ||
                    log.details.toLowerCase().includes(search) ||
                    log.ip_address.toLowerCase().includes(search);

                const matchesUser = !user || log.user === user;
                const matchesAction = !action || log.action === action;
                const matchesDate = !date || log.timestamp.startsWith(date);

                return matchesSearch && matchesUser && matchesAction && matchesDate;
            });

            displayActivityLogs();
        }

        function displayActivityLogs() {
            const tbody = $('#activity-table tbody');
            tbody.html(filteredActivityLogs.map(log => `
            <tr>
                <td>${new Date(log.timestamp).toLocaleString()}</td>
                <td><span class="badge badge-primary">${log.user}</span></td>
                <td><span class="badge badge-${getActionBadgeColor(log.action)}">${log.action}</span></td>
                <td>${log.details}</td>
                <td><code>${log.ip_address}</code></td>
            </tr>
        `).join(''));
        }

        function getActionBadgeColor(action) {
            const colors = {
                'User login': 'success',
                'User logout': 'secondary',
                'User registration': 'info',
                'Character added': 'primary',
                'Character removed': 'danger',
                'Characters refreshed': 'warning',
                'Created license key': 'success',
                'Updated license key': 'warning',
                'Deleted license key': 'danger',
                'Updated user role': 'info',
                'User activated': 'success',
                'User deactivated': 'danger'
            };
            return colors[action] || 'secondary';
        }

        function exportActivityLogs() {
            const csvContent = [
                ['Timestamp', 'User', 'Action', 'Details', 'IP Address'],
                ...filteredActivityLogs.map(log => [
                    new Date(log.timestamp).toLocaleString(),
                    log.user,
                    log.action,
                    log.details,
                    log.ip_address
                ])
            ].map(row => row.map(field => `"${field}"`).join(',')).join('\n');

            const blob = new Blob([csvContent], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `activity_logs_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Toast notification functions
        function showToast(message, type = 'info', title = '', duration = 5000) {
            const toastClass = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            } [type] || 'bg-info';

            const toast = $(`
            <div class="toast ${toastClass}" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <div class="toast-header">
                    <strong class="mr-auto">${title || 'Notification'}</strong>
                    <button type="button" class="ml-2 mb-1 close" data-dismiss="toast">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="toast-body text-white">
                    ${message}
                </div>
            </div>
        `);

            $('body').append(toast);
            toast.toast({
                delay: duration
            });
            toast.toast('show');

            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // System Health Functions
        function loadSystemHealth() {
            $.post('', {
                action: 'get_system_health'
            }).done(function(data) {
                if (data.success) {
                    displaySystemHealth(data.data);
                } else {
                    showToast('Failed to load system health data', 'error');
                }
            });
        }

        function displaySystemHealth(metrics) {
            const healthHtml = `
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-database"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Database Size</span>
                            <span class="info-box-number">${metrics.database.total_size_mb} MB</span>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${Math.min(metrics.database.total_size_mb / 100, 100)}%"></div>
                            </div>
                            <span class="progress-description">${metrics.database.total_rows} total rows</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Active Users</span>
                            <span class="info-box-number">${metrics.users.active_24h}</span>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: ${(metrics.users.active_24h / metrics.users.total_users) * 100}%"></div>
                            </div>
                            <span class="progress-description">${metrics.users.active_7d} active this week</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-gamepad"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Characters Updated</span>
                            <span class="info-box-number">${metrics.characters.updated_24h}</span>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: ${(metrics.characters.updated_24h / metrics.characters.total_characters) * 100}%"></div>
                            </div>
                            <span class="progress-description">Last 24 hours</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-${metrics.errors.recent_errors > 10 ? 'danger' : 'success'}"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">System Errors</span>
                            <span class="info-box-number">${metrics.errors.recent_errors}</span>
                            <div class="progress">
                                <div class="progress-bar bg-${metrics.errors.recent_errors > 10 ? 'danger' : 'success'}" style="width: ${Math.min(metrics.errors.recent_errors * 10, 100)}%"></div>
                            </div>
                            <span class="progress-description">Log size: ${metrics.errors.log_file_size_kb} KB</span>
                        </div>
                    </div>
                </div>
            `;
            $('#systemHealthMetrics').html(healthHtml);
        }

        // Advanced Analytics Functions
        let registrationChart = null;
        let licenseChart = null;

        function loadAdvancedAnalytics() {
            $.post('', {
                action: 'get_advanced_user_analytics'
            }).done(function(data) {
                if (data.success) {
                    displayAdvancedAnalytics(data.data);
                } else {
                    showToast('Failed to load advanced analytics', 'error');
                }
            });
        }

        function displayAdvancedAnalytics(analytics) {
            // Registration trends chart
            const regCtx = document.getElementById('registrationChart').getContext('2d');
            if (registrationChart) registrationChart.destroy();

            const regLabels = analytics.registration_trends.map(item => item.date);
            const regData = analytics.registration_trends.map(item => item.registrations);
            const vipRegData = analytics.registration_trends.map(item => item.vip_registrations);

            registrationChart = new Chart(regCtx, {
                type: 'line',
                data: {
                    labels: regLabels,
                    datasets: [{
                        label: 'Total Registrations',
                        data: regData,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'VIP Registrations',
                        data: vipRegData,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // User engagement table
            const engagementHtml = analytics.user_engagement.slice(0, 20).map(user => `
                <tr>
                    <td><span class="badge badge-${user.user_role === 'admin' ? 'danger' : user.user_role === 'vip' ? 'warning' : 'secondary'}">${user.username}</span></td>
                    <td>${user.user_role.toUpperCase()}</td>
                    <td>${user.character_count}</td>
                    <td><strong>${user.total_resets_gained}</strong></td>
                    <td>${user.active_days}</td>
                    <td>${user.days_since_registration}</td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewUserDetails(${user.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            $('#engagement-table tbody').html(engagementHtml);

            // License statistics chart
            const licCtx = document.getElementById('licenseChart').getContext('2d');
            if (licenseChart) licenseChart.destroy();

            const licenseLabels = analytics.license_statistics.map(item => item.license_type.toUpperCase());
            const totalLicenses = analytics.license_statistics.map(item => item.total_licenses);
            const usedLicenses = analytics.license_statistics.map(item => item.used_licenses);

            licenseChart = new Chart(licCtx, {
                type: 'bar',
                data: {
                    labels: licenseLabels,
                    datasets: [{
                        label: 'Total Licenses',
                        data: totalLicenses,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Used Licenses',
                        data: usedLicenses,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function viewUserDetails(userId) {
            // Implementation for viewing user details
            showToast(`Viewing details for user ID: ${userId}`, 'info');
        }
    </script>
</body>

</html>