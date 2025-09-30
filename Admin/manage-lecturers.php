<?php

declare(strict_types=1);
session_start();

// Auth and DB
require_once __DIR__ . '/../includes/auth.php';
if (function_exists('require_admin')) {
    require_admin();
}
require_once __DIR__ . '/../includes/connect.php';

/**
 * Find mysqli connection
 */
function db_connect_auto(): ?mysqli
{
    foreach (['conn', 'con', 'mysqli'] as $var) {
        if (isset($GLOBALS[$var]) && $GLOBALS[$var] instanceof mysqli) {
            return $GLOBALS[$var];
        }
    }
    foreach (['db', 'db_connect'] as $fn) {
        if (function_exists($fn)) {
            $maybe = $fn();
            if ($maybe instanceof mysqli) return $maybe;
        }
    }
    return null;
}

function h(?string $s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Modal state
$openModal = false;
$modalMode = 'create';
$old = [];
$formErrors = [];

// DB
$mysqli = db_connect_auto();
if (!$mysqli) {
    $flashError = 'Database connection not available. Please configure includes/connect.php.';
}

/**
 * Load dictionaries
 */
function fetch_faculties(mysqli $db): array
{
    $rows = [];
    if ($res = $db->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

function fetch_departments(mysqli $db): array
{
    $rows = [];
    if ($res = $db->query("SELECT dept_id, faculty_id, dept_name FROM departments ORDER BY dept_name ASC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

/**
 * Validation
 */
function validate_lecturer_post(array $src): array
{
    $errors = [];

    $lecturer_id   = trim((string)($src['lecturer_id'] ?? ''));
    $lecturer_name = trim((string)($src['lecturer_name'] ?? ''));
    $staff_id      = trim((string)($src['staff_id'] ?? ''));
    $email         = trim((string)($src['email'] ?? ''));
    $password      = trim((string)($src['password'] ?? ''));
    $faculty_id    = trim((string)($src['faculty_id'] ?? ''));
    $dept_id       = trim((string)($src['dept_id'] ?? ''));
    $status        = trim((string)($src['status'] ?? ''));
    $notes         = trim((string)($src['notes'] ?? ''));

    if ($lecturer_name === '') $errors['lecturer_name'] = 'Lecturer name is required';
    if ($staff_id === '') $errors['staff_id'] = 'Staff ID is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required';
    }
    if ($lecturer_id === '' && $password === '') {
        $errors['password'] = 'Password is required for new lecturers';
    }
    if ($faculty_id === '' || !ctype_digit($faculty_id)) $errors['faculty_id'] = 'Select a valid faculty';
    if ($dept_id === '' || !ctype_digit($dept_id)) $errors['dept_id'] = 'Select a valid department';
    if (!in_array($status, ['Active', 'Inactive'], true)) $errors['status'] = 'Invalid status';

    $data = [
        'lecturer_id'   => $lecturer_id,
        'lecturer_name' => $lecturer_name,
        'staff_id'      => $staff_id,
        'email'         => $email,
        'password'      => $password,
        'faculty_id'    => $faculty_id === '' ? null : (int)$faculty_id,
        'dept_id'       => $dept_id === '' ? null : (int)$dept_id,
        'status'        => $status,
        'notes'         => $notes,
    ];

    return [$data, $errors];
}

/**
 * Check unique constraints
 */
function staff_id_exists(mysqli $db, string $staff_id, ?int $exclude_id = null): bool
{
    $sql = "SELECT lecturer_id FROM lecturers WHERE staff_no = ?" . ($exclude_id ? " AND lecturer_id <> ?" : "") . " LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return true;
    if ($exclude_id) $stmt->bind_param('si', $staff_id, $exclude_id);
    else $stmt->bind_param('s', $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}
function email_exists(mysqli $db, string $email, ?int $exclude_id = null): bool
{
    $sql = "SELECT lecturer_id FROM lecturers WHERE email = ?" . ($exclude_id ? " AND lecturer_id <> ?" : "") . " LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return true;
    if ($exclude_id) $stmt->bind_param('si', $email, $exclude_id);
    else $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}

/**
 * CRUD Operations
 */
/**
 * CRUD Operations - FIXED VERSION
 */
function lecturer_create(mysqli $db, array $d): array
{
    if (staff_id_exists($db, $d['staff_id'])) {
        return [false, 'Staff ID already exists'];
    }
    if (email_exists($db, $d['email'])) {
        return [false, 'Email already exists'];
    }

    $sql = "INSERT INTO lecturers (lecturer_name, staff_no, email, password, faculty_id, dept_id, created_at)
          VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $hashedPassword = password_hash($d['password'], PASSWORD_DEFAULT);

    // FIXED: Parameter types should be 'ssssii' (4 strings + 2 integers)
    $stmt->bind_param(
        'ssssii',  // ✅ Correct: string, string, string, string, int, int
        $d['lecturer_name'],
        $d['staff_id'],
        $d['email'],
        $hashedPassword,  // This is a string (hashed password)
        $d['faculty_id'],
        $d['dept_id']
    );

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    return [true, $newId];
}

function lecturer_update(mysqli $db, array $d): array
{
    if (empty($d['lecturer_id']) || !ctype_digit((string)$d['lecturer_id'])) {
        return [false, 'Invalid lecturer ID'];
    }
    $id = (int)$d['lecturer_id'];

    if (staff_id_exists($db, $d['staff_id'], $id)) {
        return [false, 'Staff ID already exists'];
    }
    if (email_exists($db, $d['email'], $id)) {
        return [false, 'Email already exists'];
    }

    // Update with or without password
    if ($d['password'] !== '') {
        $sql = "UPDATE lecturers 
            SET lecturer_name = ?, staff_no = ?, email = ?, password = ?, faculty_id = ?, dept_id = ?
            WHERE lecturer_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [false, 'Failed to prepare statement'];

        $hashedPassword = password_hash($d['password'], PASSWORD_DEFAULT);

        // FIXED: 4 strings + 3 integers = 'ssssiii'
        $stmt->bind_param(
            'ssssiii',  // ✅ Correct: string, string, string, string, int, int, int
            $d['lecturer_name'],
            $d['staff_id'],
            $d['email'],
            $hashedPassword,  // This is a string (hashed password)
            $d['faculty_id'],
            $d['dept_id'],
            $id
        );
    } else {
        $sql = "UPDATE lecturers 
            SET lecturer_name = ?, staff_no = ?, email = ?, faculty_id = ?, dept_id = ?
            WHERE lecturer_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [false, 'Failed to prepare statement'];

        // FIXED: 3 strings + 3 integers = 'sssiii'
        $stmt->bind_param(
            'sssiii',  // ✅ Correct: string, string, string, int, int, int
            $d['lecturer_name'],
            $d['staff_id'],
            $d['email'],
            $d['faculty_id'],
            $d['dept_id'],
            $id
        );
    }

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return [true, $affected];
}
function lecturer_delete(mysqli $db, string $lecturer_id): array
{
    if ($lecturer_id === '' || !ctype_digit((string)$lecturer_id)) {
        return [false, 'Invalid lecturer ID'];
    }
    $id = (int)$lecturer_id;

    $sql = "DELETE FROM lecturers WHERE lecturer_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return [true, $affected];
}

/**
 * List lecturers with search/filters
 */
/**
 * List lecturers with search/filters
 */
function lecturers_query(mysqli $db, string $q, ?int $dept_id, ?string $status): array
{
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        // Try different possible column names for lecturer name
        $where[] = "(l.lecturer_name LIKE ? OR l.staff_no LIKE ? OR l.email LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
    }
    if ($dept_id) {
        $where[] = "l.dept_id = ?";
        $params[] = $dept_id;
        $types .= 'i';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Use the actual column names from your table
    $sql = "SELECT
            l.lecturer_id, 
            l.lecturer_name, 
            l.staff_no as staff_id, 
            l.email,
            l.faculty_id, 
            f.faculty_name,
            l.dept_id, 
            d.dept_name,
            'Active' as status, 
            '' as notes, 
            l.created_at
          FROM lecturers l
          LEFT JOIN faculties f ON f.faculty_id = l.faculty_id
          LEFT JOIN departments d ON d.dept_id = l.dept_id
          $whereSql
          ORDER BY l.created_at DESC, l.lecturer_id DESC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    } else {
        $res = $db->query($sql);
        if (!$res) return [];
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $res->free();
        return $rows;
    }
}
/**
 * Handle POST
 */
if ($mysqli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $flashError = 'Invalid CSRF token.';
    } else {
        if ($op === 'create' || $op === 'update') {
            [$data, $errs] = validate_lecturer_post($_POST);
            if (!empty($errs)) {
                $formErrors = $errs;
                $old = $data;
                $openModal = true;
                $modalMode = $op;
            } else {
                if ($op === 'create') {
                    [$ok, $result] = lecturer_create($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Lecturer added successfully.';
                        header('Location: manage-lecturers.php');
                        exit;
                    } else {
                        $flashError = $result;
                        $formErrors = ['_general' => $result];
                        $old = $data;
                        $openModal = true;
                        $modalMode = 'create';
                    }
                } else {
                    [$ok, $result] = lecturer_update($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Lecturer updated successfully.';
                        header('Location: manage-lecturers.php');
                        exit;
                    } else {
                        $flashError = $result;
                        $formErrors = ['_general' => $result];
                        $old = $data;
                        $openModal = true;
                        $modalMode = 'update';
                    }
                }
            }
        } elseif ($op === 'delete') {
            $lecturer_id = $_POST['lecturer_id'] ?? '';
            [$ok, $result] = lecturer_delete($mysqli, $lecturer_id);
            if ($ok) {
                $_SESSION['flash_success'] = 'Lecturer deleted successfully.';
                header('Location: manage-lecturers.php');
                exit;
            } else {
                $flashError = $result;
            }
        }
    }
}

/**
 * Load data for rendering
 */
$faculties   = $mysqli ? fetch_faculties($mysqli)   : [];
$departments = $mysqli ? fetch_departments($mysqli) : [];

$q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterDept = isset($_GET['dept_id']) && ctype_digit((string)$_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

$lecturers = $mysqli ? lecturers_query($mysqli, $q, $filterDept, $filterStatus) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Manage Lecturers - Marking & Grading System</title>
    <!-- HTML5 Shim and Respond.js IE11 support of HTML5 elements and media queries -->
    <!--[if lt IE 11]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Lecturer management for the Marking & Grading System" />
    <meta name="keywords" content="lecturers, teachers, management, assign courses, education">
    <meta name="author" content="Marking & Grading System" />
    <!-- Favicon icon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --info-color: #4299e1;
            --light-bg: #f8fafc;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation */
        .pcoded-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            transition: var(--transition);
            border-radius: 8px;
            margin: 2px 8px;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white !important;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white !important;
        }

        /* Header */
        .pcoded-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .page-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .page-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            animation: slideInDown 1s ease-out;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            animation: slideInUp 1s ease-out 0.2s both;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Main */
        .pcoded-main-container {
            background: transparent;
        }

        .pcoded-content {
            background: var(--light-bg);
            min-height: calc(100vh - 70px);
            border-radius: 30px 0 0 0;
            padding: 0;
            margin-top: 0;
        }

        .lecturers-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Action bar */
        .action-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            max-width: 600px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: #f8fafc;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #4a5568;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-muted {
            background: #edf2f7;
            color: #2d3748;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .table-stats {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: var(--transition);
            vertical-align: top;
        }

        tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }

        .status-inactive {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger-color);
        }

        .action-buttons-table {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background: rgba(66, 153, 225, 0.1);
            color: var(--info-color);
        }

        .btn-edit:hover {
            background: var(--info-color);
            color: white;
            transform: scale(1.1);
        }

        .btn-delete {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
            transform: scale(1.1);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            max-width: 650px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--card-shadow-hover);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d3748;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #718096;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--danger-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .alert {
            padding: 10px 12px;
            border-radius: 8px;
            margin: 10px 0;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(72, 187, 120, .12);
            color: #2f855a;
            border: 1px solid rgba(72, 187, 120, .25);
        }

        .alert-error {
            background: rgba(245, 101, 101, .12);
            color: #c53030;
            border: 1px solid rgba(245, 101, 101, .25);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .lecturers-container {
                padding: 1rem;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                max-width: none;
            }

            .page-title {
                font-size: 2rem;
            }

            th,
            td {
                padding: 0.75rem;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="">
    <!-- [ navigation menu ] start -->
    <nav class="pcoded-navbar menu-light">
        <div class="navbar-wrapper">
            <div class="navbar-content scroll-div">
                <ul class="nav pcoded-inner-navbar">
                    <li class="nav-item pcoded-menu-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-home"></i></span>
                            <span class="pcoded-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-students.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span>
                            <span class="pcoded-mtext">Manage Students</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-lecturers.php" class="nav-link active">
                            <span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span>
                            <span class="pcoded-mtext">Manage Lecturers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-courses.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-book"></i></span>
                            <span class="pcoded-mtext">Manage Courses</span>
                        </a>
                    </li>

                    <li class="nav-item"><a href="assign-courses.php" class=""><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Assign Courses</span></a></li>
                    <li class="nav-item">
                        <a href="grading-scale.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                            <span class="pcoded-mtext">Grading Scale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span>
                            <span class="pcoded-mtext">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- [ navigation menu ] end -->

    <!-- [ Header ] start -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light">
        <div class="m-header">
            <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
            <a href="#" class="b-brand">
                <h3 class="text-primary mb-0">MGS Admin</h3>
            </a>
            <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="container-fluid">
                    <div class="page-content">
                        <h1 class="page-title">Manage Lecturers</h1>
                        <p class="page-subtitle">Add, edit, and manage lecturer accounts with login credentials</p>
                    </div>
                </div>
            </div>

            <div class="lecturers-container">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success"><?= h($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-error"><?= h($flashError) ?></div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <form class="search-container" method="get" action="">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input name="q" value="<?= h($q) ?>" type="text" class="search-input" placeholder="Search lecturers by name, ID, or email...">
                        </div>
                        <select name="dept_id" class="filter-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['dept_id'] ?>" <?= ($filterDept && $filterDept === (int)$d['dept_id']) ? 'selected' : '' ?>><?= h($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <button type="submit" class="btn btn-muted">Apply</button>
                        <?php if ($q !== '' || $filterDept || $filterStatus !== ''): ?>
                            <a href="manage-lecturers.php" class="btn btn-muted">Reset</a>
                        <?php endif; ?>
                    </form>
                    <div class="action-buttons">
                        <button class="btn btn-primary" id="openAddLecturer">
                            <i class="fas fa-user-plus"></i>
                            Add Lecturer
                        </button>
                    </div>
                </div>

                <!-- Lecturers Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Lecturers</h3>
                        <div class="table-stats"><?= count($lecturers) ?> total</div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="min-width: 180px;">Lecturer</th>
                                    <th>Email</th>
                                    <th>Faculty</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lecturers)): ?>
                                    <tr>
                                        <td colspan="7" style="color:#718096; padding: 1rem;">No lecturers found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lecturers as $lec): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;color:#2d3748"><?= h($lec['lecturer_name']) ?></div>
                                                <div style="font-size:0.85rem;color:#718096"><?= h($lec['staff_id']) ?></div>
                                            </td>
                                            <td><?= h($lec['email']) ?></td>
                                            <td><?= h($lec['faculty_name']) ?></td>
                                            <td><?= h($lec['dept_name']) ?></td>
                                            <td>
                                                <span class="status-badge <?= $lec['status'] === 'Active' ? 'status-active' : 'status-inactive' ?>">
                                                    <?= h($lec['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= h($lec['created_at']) ?></td>
                                            <td>
                                                <div class="action-buttons-table">
                                                    <button type="button" class="btn-sm btn-edit js-edit-lecturer" title="Edit"
                                                        data-lecturer='<?= h(json_encode([
                                                                            'lecturer_id'   => (string)$lec['lecturer_id'],
                                                                            'lecturer_name' => (string)$lec['lecturer_name'],
                                                                            'staff_id'      => (string)$lec['staff_id'],
                                                                            'email'         => (string)$lec['email'],
                                                                            'faculty_id'    => (string)$lec['faculty_id'],
                                                                            'dept_id'       => (string)$lec['dept_id'],
                                                                            'status'        => (string)$lec['status'],
                                                                            'notes'         => (string)$lec['notes'],
                                                                        ], JSON_UNESCAPED_UNICODE)) ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete lecturer <?= h($lec['lecturer_name']) ?>?');">
                                                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                                                        <input type="hidden" name="op" value="delete">
                                                        <input type="hidden" name="lecturer_id" value="<?= h((string)$lec['lecturer_id']) ?>">
                                                        <button type="submit" class="btn-sm btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Add/Edit Lecturer Modal -->
    <div id="lecturerModal" class="modal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h2 id="lecturerModalTitle" class="modal-title"><?= $modalMode === 'update' ? 'Edit Lecturer' : 'Add New Lecturer' ?></h2>
                <button class="close-btn" id="lecturerClose" aria-label="Close">&times;</button>
            </div>
            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error" style="margin-top:0;">
                    <?php foreach ($formErrors as $msg): if (is_string($msg)): ?>
                            <div><?= h($msg) ?></div>
                    <?php endif;
                    endforeach; ?>
                </div>
            <?php endif; ?>
            <form id="lecturerForm" method="post" action="" novalidate>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                <input type="hidden" name="op" id="opField" value="<?= $modalMode === 'update' ? 'update' : 'create' ?>">
                <input type="hidden" name="lecturer_id" id="editingLecturerId" value="<?= h($old['lecturer_id'] ?? '') ?>">

                <div class="form-grid">
                    <div>
                        <label class="form-label" for="lecturerName">Full Name</label>
                        <input id="lecturerName" name="lecturer_name" class="form-input" required placeholder="e.g., Dr. Sarah Johnson" value="<?= h($old['lecturer_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="staffId">Staff ID</label>
                        <input id="staffId" name="staff_id" class="form-input" required placeholder="e.g., LEC-2024-001" value="<?= h($old['staff_id'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="lecturerEmail">Email</label>
                        <input id="lecturerEmail" name="email" type="email" class="form-input" required placeholder="name@university.edu" value="<?= h($old['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="lecturerPassword">Password <?= $modalMode === 'update' ? '(leave blank to keep current)' : '' ?></label>
                        <input id="lecturerPassword" name="password" type="password" class="form-input" <?= $modalMode === 'create' ? 'required' : '' ?> placeholder="Enter login password">
                    </div>
                    <div>
                        <label class="form-label" for="lecturerFaculty">Faculty</label>
                        <select id="lecturerFaculty" name="faculty_id" class="form-select" required>
                            <option value="">Select Faculty</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?= (int)$f['faculty_id'] ?>" <?= (isset($old['faculty_id']) && (int)$old['faculty_id'] === (int)$f['faculty_id']) ? 'selected' : '' ?>><?= h($f['faculty_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="lecturerDept">Department</label>
                        <select id="lecturerDept" name="dept_id" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['dept_id'] ?>" data-faculty="<?= (int)$d['faculty_id'] ?>" <?= (isset($old['dept_id']) && (int)$old['dept_id'] === (int)$d['dept_id']) ? 'selected' : '' ?>><?= h($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="lecturerStatus">Status</label>
                        <select id="lecturerStatus" name="status" class="form-select" required>
                            <option value="Active" <?= (isset($old['status']) && $old['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= (isset($old['status']) && $old['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="form-label" for="lecturerNotes">Notes (optional)</label>
                        <textarea id="lecturerNotes" name="notes" class="form-textarea" rows="3" placeholder="Specialization, office hours, etc."><?= h($old['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="lecturerCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Lecturer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/vendor-all.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/ripple.js"></script>
    <script src="assets/js/pcoded.min.js"></script>

    <script>
        (function() {
            const openBtn = document.getElementById('openAddLecturer');
            const modal = document.getElementById('lecturerModal');
            const closeBtn = document.getElementById('lecturerClose');
            const cancelBtn = document.getElementById('lecturerCancel');
            const titleEl = document.getElementById('lecturerModalTitle');
            const form = document.getElementById('lecturerForm');
            const opField = document.getElementById('opField');

            // Inputs
            const input = {
                id: document.getElementById('editingLecturerId'),
                name: document.getElementById('lecturerName'),
                staffId: document.getElementById('staffId'),
                email: document.getElementById('lecturerEmail'),
                password: document.getElementById('lecturerPassword'),
                faculty: document.getElementById('lecturerFaculty'),
                dept: document.getElementById('lecturerDept'),
                status: document.getElementById('lecturerStatus'),
                notes: document.getElementById('lecturerNotes')
            };

            function showModal() {
                modal.classList.add('show');
                setTimeout(() => input.name && input.name.focus(), 120);
            }

            function hideModal() {
                modal.classList.remove('show');
            }

            function setMode(mode, data) {
                opField.value = mode === 'update' ? 'update' : 'create';
                titleEl.textContent = mode === 'update' ? 'Edit Lecturer' : 'Add New Lecturer';
                if (data) {
                    input.id.value = data.lecturer_id || '';
                    input.name.value = data.lecturer_name || '';
                    input.staffId.value = data.staff_id || '';
                    input.email.value = data.email || '';
                    input.password.value = ''; // Never pre-fill password
                    input.faculty.value = data.faculty_id || '';
                    filterDepartments();
                    input.dept.value = data.dept_id || '';
                    input.status.value = data.status || 'Active';
                    input.notes.value = data.notes || '';
                } else {
                    form.reset();
                    input.id.value = '';
                    input.status.value = 'Active';
                    filterDepartments();
                }
            }

            function filterDepartments() {
                const fac = input.faculty.value;
                Array.from(input.dept.options).forEach(opt => {
                    if (opt.value === '') {
                        opt.hidden = false;
                        return;
                    }
                    const f = opt.getAttribute('data-faculty');
                    const show = !fac || f === fac;
                    opt.hidden = !show;
                    if (!show && opt.selected) opt.selected = false;
                });
            }
            input.faculty.addEventListener('change', filterDepartments);

            if (openBtn) openBtn.addEventListener('click', () => {
                setMode('create');
                showModal();
            });
            if (closeBtn) closeBtn.addEventListener('click', hideModal);
            if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
            window.addEventListener('click', (e) => {
                if (e.target === modal) hideModal();
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') hideModal();
            });

            // Edit buttons
            document.querySelectorAll('.js-edit-lecturer').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const data = JSON.parse(btn.getAttribute('data-lecturer') || '{}');
                        setMode('update', data);
                        showModal();
                    } catch (e) {
                        console.error('Bad lecturer data', e);
                    }
                });
            });

            // Auto-open modal when server-side validation failed
            <?php if (!empty($openModal)): ?>
                setMode('<?= $modalMode === 'update' ? 'update' : 'create' ?>', <?= json_encode($old ?? [], JSON_UNESCAPED_UNICODE) ?>);
                showModal();
            <?php endif; ?>

            // Initialize
            filterDepartments();
        })();
    </script>
</body>

</html>