<?php
require_once __DIR__ . '/auth.php';

// Logout user
$result = $auth->logout();

// Redirect to login page
header('Location: login');
exit;
