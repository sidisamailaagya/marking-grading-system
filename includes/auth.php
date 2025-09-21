<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // 1) Explicit override via constant or environment variable (most reliable)
    if (defined('APP_BASE_PATH')) {
        $base = rtrim((string)APP_BASE_PATH, '/');
        return $cached = $base === '' ? '' : $base;
    }
    $envBase = getenv('APP_BASE_PATH');
    if ($envBase !== false) {
        $base = rtrim((string)$envBase, '/');
        return $cached = $base === '' ? '' : $base;
    }

    // 2) Simple detection based on SCRIPT_NAME
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptName = str_replace('\\', '/', $scriptName);
    
    // For a script like "/Grading system/authenticate.php" or "/Grading system/Admin/dashboard.php"
    // Extract the first directory segment
    if (preg_match('#^/([^/]+)/#', $scriptName, $matches)) {
        $firstSegment = $matches[1];
        // If it doesn't look like a PHP file, it's probably our app folder
        if (!str_ends_with($firstSegment, '.php')) {
            return $cached = '/' . $firstSegment;
        }
    }

    // Root by default
    return $cached = '';
}

/**
 * Build a URL path that is safe whether the app is in a subfolder or at root.
 * Pass relative paths like "Admin/dashboard.php" or "/Admin/dashboard.php".
 */
function url_for(string $path): string
{
    $base = rtrim(app_base_path(), '/');
    $normalized = '/' . ltrim($path, '/');
    $full = $base . $normalized;
    return $full !== '' ? $full : '/';
}

/**
 * Determine scheme+host origin, honoring HTTPS and common proxy headers.
 * Example: http://localhost or https://example.com
 */
function server_origin(): string
{
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $scheme . '://' . $host;
}

/**
 * Absolute URL for a given app-relative path.
 * Example: absolute_url_for('login.php') => http://localhost/Grading system/login.php
 */
function absolute_url_for(string $path): string
{
    $pathPart = url_for($path);
    // Ensure path starts with a single slash
    if ($pathPart === '' || $pathPart[0] !== '/') {
        $pathPart = '/' . ltrim($pathPart, '/');
    }
    return server_origin() . $pathPart;
}

/** Send a Location header to an absolute URL and exit (avoids relative resolution issues). */
function redirect(string $path): void
{
    $absolute = absolute_url_for($path);
    // Debug header to verify where we are redirecting
    header('X-Debug-Redirect: ' . $absolute);
    header('Location: ' . $absolute);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['uid'], $_SESSION['role']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function dashboard_path_for_role(?string $role): string
{
    switch ($role) {
        case 'admin':
            return 'Admin/dashboard.php';
        case 'student':
            return 'Student/dashboard.php';
        case 'lecturer':
            return 'Lecturer/dashboard.php';
        default:
            return 'login.php';
    }
}

function redirect_if_authenticated(): void
{
    if (is_logged_in()) {
        redirect(dashboard_path_for_role(current_role()));
    }
}

function require_role(string $role): void
{
    if (!is_logged_in() || current_role() !== $role) {
        $_SESSION['error'] = 'Please sign in with proper privileges to access that page.';
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function require_student(): void
{
    require_role('student');
}

function require_lecturer(): void
{
    require_role('lecturer');
}