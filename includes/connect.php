<?php

declare(strict_types=1);

// Database credentials
$DB_HOST = '127.0.0.1'; // or 'localhost'
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'marking_grading';

// Create mysqli connection in a standardized $conn variable
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_errno) {
    // Keep the message generic in production; you can log details to a file if needed.
    http_response_code(500);
    exit('Database connection failed.');
}

// Use a modern charset
$conn->set_charset('utf8mb4');

// Backward-compatibility alias for existing code that uses $connect
$connect = $conn;

// Optional helper to fetch the connection if you prefer functional access
if (!function_exists('db')) {
    function db(): mysqli
    {
        global $conn;
        return $conn;
    }
}
