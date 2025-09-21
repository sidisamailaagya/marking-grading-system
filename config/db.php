<?php
// config/db.php
// Database configuration with sensible defaults; override via environment variables if needed.

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    // Change the default DB name below to your actual database name if different
    define('DB_NAME', getenv('ma') ?: 'marking_grading');
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Get a connected MySQLi instance.
 * Throws mysqli_sql_exception on error (PHP 8+).
 */
function get_mysqli(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}