<?php
// Fixed authenticate.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/connect.php';

// Get database connection
$db = $conn;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please provide both username and password.';
    header('Location: login.php');
    exit;
}

// Password verification function
function check_password($input, $stored) {
    // Skip if stored password is empty or null
    if (empty($stored) || $stored === '0') {
        return false;
    }
    
    // Try hashed password first
    if (password_verify($input, $stored)) {
        return true;
    }
    
    // Try plaintext match
    return $input === $stored;
}

// Status check function - handles both string and integer status values
function is_active_status($status) {
    if ($status === null || $status === '') {
        return true; // null/empty status = active by default
    }
    
    // Convert to string for comparison
    $statusStr = strtolower((string)$status);
    
    // Check various active status values
    return in_array($statusStr, ['active', '1', 'enabled', 'approved']) || $status === 1;
}

$user = null;

// Try admin first (login by username OR email)
$sql = "SELECT admin_id, full_name, email, username, password, status FROM admins WHERE username = ? OR email = ? LIMIT 1";
if ($stmt = $db->prepare($sql)) {
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (check_password($password, $row['password'])) {
            if (is_active_status($row['status'])) {
                $user = [
                    'role' => 'admin',
                    'id' => (int)$row['admin_id'],
                    'name' => $row['full_name'],
                    'email' => $row['email'],
                    'username' => $row['username']
                ];
            }
        }
    }
    $stmt->close();
}

// Try lecturer if admin failed (login by email only) - FIXED VERSION
if (!$user) {
    // First check if status column exists
    $hasStatusColumn = false;
    $checkColumns = $db->query("SHOW COLUMNS FROM lecturers LIKE 'status'");
    if ($checkColumns && $checkColumns->num_rows > 0) {
        $hasStatusColumn = true;
    }
    
    // Build query based on whether status column exists
    if ($hasStatusColumn) {
        $sql = "SELECT lecturer_id, lecturer_name, email, password, status FROM lecturers WHERE email = ? LIMIT 1";
    } else {
        $sql = "SELECT lecturer_id, lecturer_name, email, password FROM lecturers WHERE email = ? LIMIT 1";
    }
    
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (check_password($password, $row['password'])) {
                // Check status only if column exists
                $statusOk = true;
                if ($hasStatusColumn) {
                    $statusOk = is_active_status($row['status']);
                }
                
                if ($statusOk) {
                    $user = [
                        'role' => 'lecturer',
                        'id' => (int)$row['lecturer_id'],
                        'name' => $row['lecturer_name'],
                        'email' => $row['email'],
                        'username' => $row['email']
                    ];
                }
            }
        }
        $stmt->close();
    }
}

// Try student if others failed (login by email only)
if (!$user) {
    $sql = "SELECT student_id, full_name, email, password FROM students WHERE email = ? LIMIT 1";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (check_password($password, $row['password'])) {
                $user = [
                    'role' => 'student',
                    'id' => (int)$row['student_id'],
                    'name' => $row['full_name'],
                    'email' => $row['email'],
                    'username' => $row['email']
                ];
            }
        }
        $stmt->close();
    }
}

if (!$user) {
    $_SESSION['error'] = 'Invalid credentials or inactive account.';
    $_SESSION['last_username'] = $username;
    header('Location: login.php');
    exit;
}

// Set session
$_SESSION['uid'] = $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];

// Update last_login_at if column exists
$table = $user['role'] === 'admin' ? 'admins' : ($user['role'] === 'lecturer' ? 'lecturers' : 'students');
$pk = $user['role'] === 'admin' ? 'admin_id' : ($user['role'] === 'lecturer' ? 'lecturer_id' : 'student_id');

$checkCol = $db->query("SHOW COLUMNS FROM $table LIKE 'last_login_at'");
if ($checkCol && $checkCol->num_rows > 0) {
    if ($stmt = $db->prepare("UPDATE $table SET last_login_at = NOW() WHERE $pk = ?")) {
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect based on role
switch ($user['role']) {
    case 'admin':
        $redirect = '/Grading system/Admin/dashboard.php';
        break;
    case 'lecturer':
        $redirect = '/Grading system/Lecturer/dashboard.php';
        break;
    case 'student':
        $redirect = '/Grading system/Student/dashboard.php';
        break;
    default:
        $redirect = '/Grading system/login.php';
}

header('Location: ' . $redirect);
exit;
?>