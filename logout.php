<?php
// logout.php - Universal logout for all user types (admin, lecturer, student)
declare(strict_types=1);

session_start();

// Optional: Log the logout action
if (isset($_SESSION['role'], $_SESSION['name'])) {
    error_log("User logout - Role: " . $_SESSION['role'] . ", Name: " . $_SESSION['name']);
}

// Clear all session variables
$_SESSION = [];

// Delete session cookie if cookies are used
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, 
        $params['path'], 
        $params['domain'], 
        $params['secure'], 
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Use the auth.php redirect function if available, otherwise use direct redirect
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
    redirect('login.php');
} else {
    // Fallback redirect for your "Grading system" folder structure
    header('Location: /Grading system/login.php');
    exit;
}
?>