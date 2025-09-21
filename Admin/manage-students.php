<?php

declare(strict_types=1);
session_start();

/**
 * Auth and DB
 * Adjust paths if your includes live elsewhere.
 */
require_once __DIR__ . '/../includes/auth.php';
if (function_exists('require_admin')) {
    require_admin();
}
require_once __DIR__ . '/../includes/connect.php';

/**
 * Find a mysqli connection exposed by includes/connect.php
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

/**
 * CSRF Token
 */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/**
 * Flash messages
 */
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/**
 * Modal state
 */
$openModal = false;
$modalMode = 'create';
$old = [];
$formErrors = [];

/**
 * DB connect
 */
$mysqli = db_connect_auto();
if (!$mysqli) {
    $flashError = 'Database connection not available. Please configure includes/connect.php.';
}

/**
 * Dictionaries
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

function fetch_levels(mysqli $db): array
{
    $rows = [];
    if ($res = $db->query("SELECT level_id, level_name FROM levels ORDER BY level_name+0 ASC, level_name ASC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

/**
 * Validation
 */
function validate_student_post(array $src, array $levelsDict): array
{
    $errors = [];
    $student_id = trim((string)($src['student_id'] ?? ''));
    $full_name  = trim((string)($src['full_name'] ?? ''));
    $matric_no  = trim((string)($src['matric_no'] ?? ''));
    $email      = trim((string)($src['email'] ?? ''));
    $faculty_id = trim((string)($src['faculty_id'] ?? ''));
    $dept_id    = trim((string)($src['dept_id'] ?? ''));
    $level      = trim((string)($src['level'] ?? ''));
    $enrolled   = trim((string)($src['enrolled'] ?? ''));

    if ($full_name === '') $errors['full_name'] = 'Full name is required';
    if ($matric_no === '') $errors['matric_no'] = 'Matric number is required';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email address';
    }
    if ($faculty_id === '' || !ctype_digit($faculty_id)) $errors['faculty_id'] = 'Select a valid faculty';
    if ($dept_id === '' || !ctype_digit($dept_id)) $errors['dept_id'] = 'Select a valid department';
    if ($level === '' || !ctype_digit($level) || !isset($levelsDict[(int)$level])) {
        $errors['level'] = 'Select a valid level';
    }
    if ($enrolled !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrolled)) {
        $errors['enrolled'] = 'Invalid enrolled date';
    }

    $data = [
        'student_id' => $student_id,
        'full_name'  => $full_name,
        'matric_no'  => $matric_no,
        'email'      => $email,
        'faculty_id' => $faculty_id === '' ? null : (int)$faculty_id,
        'dept_id'    => $dept_id === '' ? null : (int)$dept_id,
        'level'      => $level === '' ? null : (int)$level,
        'enrolled'   => $enrolled, // YYYY-MM-DD or ''
    ];

    return [$data, $errors];
}

/**
 * Unique check
 */
function matric_exists(mysqli $db, string $matric_no, ?int $exclude_id = null): bool
{
    $sql = "SELECT student_id FROM students WHERE matric_no = ?" . ($exclude_id ? " AND student_id <> ?" : "") . " LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return true;
    if ($exclude_id) $stmt->bind_param('si', $matric_no, $exclude_id);
    else $stmt->bind_param('s', $matric_no);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}

/**
 * CRUD
 */
function student_create(mysqli $db, array $d): array
{
    if (matric_exists($db, $d['matric_no'])) {
        return [false, 'Matric number already exists'];
    }

    // Insert without password column (let it use its default value)
    $sql = "INSERT INTO students (matric_no, full_name, email, faculty_id, dept_id, level, created_at)
          VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $created_at = $d['enrolled'] ? ($d['enrolled'] . ' 00:00:00') : date('Y-m-d H:i:s');

    // Bind params: s s s i i i s (7 params, removed password)
    $stmt->bind_param(
        'sssiiss',
        $d['matric_no'],
        $d['full_name'],
        $d['email'],
        $d['faculty_id'],
        $d['dept_id'],
        $d['level'],
        $created_at
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
function student_update(mysqli $db, array $d): array
{
    if (empty($d['student_id']) || !ctype_digit((string)$d['student_id'])) {
        return [false, 'Invalid student ID'];
    }
    $id = (int)$d['student_id'];
    if (matric_exists($db, $d['matric_no'], $id)) {
        return [false, 'Matric number already exists'];
    }

    $sql = "UPDATE students
          SET matric_no = ?, full_name = ?, email = ?, faculty_id = ?, dept_id = ?, level = ?
          WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $stmt->bind_param(
        'sssiisi',
        $d['matric_no'],
        $d['full_name'],
        $d['email'],
        $d['faculty_id'],
        $d['dept_id'],
        $d['level'],
        $id
    );

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return [true, $affected];
}

function student_delete(mysqli $db, string $student_id): array
{
    if ($student_id === '' || !ctype_digit((string)$student_id)) {
        return [false, 'Invalid student ID'];
    }
    $id = (int)$student_id;

    $sql = "DELETE FROM students WHERE student_id = ?";
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
 * Listing with search/filters/pagination
 */
function students_query(mysqli $db, string $q, ?int $faculty_id, ?int $dept_id, ?int $level, int $offset, int $limit): array
{
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = "(s.full_name LIKE ? OR s.matric_no LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
    }
    if ($faculty_id) {
        $where[] = "s.faculty_id = ?";
        $params[] = $faculty_id;
        $types .= 'i';
    }
    if ($dept_id) {
        $where[] = "s.dept_id = ?";
        $params[] = $dept_id;
        $types .= 'i';
    }
    if ($level) {
        $where[] = "s.level = ?";
        $params[] = $level;
        $types .= 'i';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
            s.student_id, s.matric_no, s.full_name, s.email,
            s.faculty_id, f.faculty_name,
            s.dept_id, d.dept_name,
            s.level, l.level_name,
            s.created_at
          FROM students s
          LEFT JOIN faculties f ON f.faculty_id = s.faculty_id
          LEFT JOIN departments d ON d.dept_id = s.dept_id
          LEFT JOIN levels l ON l.level_id = s.level
          $whereSql
          ORDER BY s.created_at DESC, s.student_id DESC
          LIMIT ?, ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $types2 = $types . 'ii';
    $params2 = $params;
    $params2[] = $offset;
    $params2[] = $limit;

    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function students_count(mysqli $db, string $q, ?int $faculty_id, ?int $dept_id, ?int $level): int
{
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = "(s.full_name LIKE ? OR s.matric_no LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
    }
    if ($faculty_id) {
        $where[] = "s.faculty_id = ?";
        $params[] = $faculty_id;
        $types .= 'i';
    }
    if ($dept_id) {
        $where[] = "s.dept_id = ?";
        $params[] = $dept_id;
        $types .= 'i';
    }
    if ($level) {
        $where[] = "s.level = ?";
        $params[] = $level;
        $types .= 'i';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT COUNT(*) AS c FROM students s $whereSql";
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    if ($row = $res->fetch_assoc()) $count = (int)$row['c'];
    $stmt->close();
    return $count;
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
        // Load levels dict for validation
        $levelsList = fetch_levels($mysqli);
        $levelsDict = [];
        foreach ($levelsList as $lv) $levelsDict[(int)$lv['level_id']] = $lv['level_name'];

        if ($op === 'create' || $op === 'update') {
            [$data, $errs] = validate_student_post($_POST, $levelsDict);
            if (!empty($errs)) {
                $formErrors = $errs;
                $old = $data;
                $openModal = true;
                $modalMode = $op;
            } else {
                if ($op === 'create') {
                    [$ok, $result] = student_create($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Student added successfully.';
                        header('Location: manage-students.php');
                        exit;
                    } else {
                        $flashError = $result;
                        $formErrors = ['_general' => $result];
                        $old = $data;
                        $openModal = true;
                        $modalMode = 'create';
                    }
                } else {
                    [$ok, $result] = student_update($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Student updated successfully.';
                        header('Location: manage-students.php');
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
            $student_id = $_POST['student_id'] ?? '';
            [$ok, $result] = student_delete($mysqli, $student_id);
            if ($ok) {
                $_SESSION['flash_success'] = 'Student deleted successfully.';
                header('Location: manage-students.php');
                exit;
            } else {
                $flashError = $result;
            }
        }
    }
}

/**
 * Load dictionaries and list data (GET filters)
 */
$faculties   = $mysqli ? fetch_faculties($mysqli)   : [];
$departments = $mysqli ? fetch_departments($mysqli) : [];
$levels      = $mysqli ? fetch_levels($mysqli)      : [];

$q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterFac  = isset($_GET['faculty_id']) && ctype_digit((string)$_GET['faculty_id']) ? (int)$_GET['faculty_id'] : null;
$filterDept = isset($_GET['dept_id'])    && ctype_digit((string)$_GET['dept_id'])    ? (int)$_GET['dept_id']    : null;
$filterLvl  = isset($_GET['level'])      && ctype_digit((string)$_GET['level'])      ? (int)$_GET['level']      : null;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$total    = $mysqli ? students_count($mysqli, $q, $filterFac, $filterDept, $filterLvl) : 0;
$students = $mysqli ? students_query($mysqli, $q, $filterFac, $filterDept, $filterLvl, $offset, $perPage) : [];

/**
 * Pagination helpers
 */
$totalPages = max(1, (int)ceil($total / $perPage));
$from = $total ? ($offset + 1) : 0;
$to   = min($offset + $perPage, $total);

function build_query(array $params): string
{
    $base = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null) unset($base[$k]);
        else $base[$k] = $v;
    }
    return http_build_query($base);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Manage Students - Marking & Grading System</title>
    <!-- HTML5 Shim and Respond.js IE11 support of HTML5 elements and media queries -->
    <!--[if lt IE 11]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Professional Student Management System" />
    <meta name="keywords" content="students, management, education, dashboard">
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

        /* Navigation Styles */
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

        /* Header Styles */
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

        /* Content */
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

        .students-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Action Bar */
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

        .action-form {
            display: flex;
            gap: .6rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input,
        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #4a5568;
        }

        .search-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }

        .btn {
            padding: 0.75rem 1.2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
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

        .btn-muted {
            background: #edf2f7;
            color: #2d3748;
        }

        /* Table */
        .students-table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.2rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .table-stats {
            font-size: 0.9rem;
            opacity: 0.95;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #4a5568;
            font-weight: 700;
            padding: 0.9rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .action-buttons-table {
            display: flex;
            gap: .4rem;
        }

        .btn-sm {
            padding: .5rem .6rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: #edf2f7;
            color: #2d3748;
        }

        .btn-sm:hover {
            background: #e2e8f0;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
        }

        .pagination-info {
            color: #718096;
            font-size: .9rem;
        }

        .pagination {
            display: flex;
            gap: .4rem;
            margin-left: auto;
        }

        .page-btn {
            padding: .45rem .7rem;
            border: 1px solid #e2e8f0;
            background: white;
            color: #4a5568;
            border-radius: 6px;
            cursor: pointer;
        }

        .page-btn.active,
        .page-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn .2s ease-out;
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
            border-radius: 14px;
            padding: 1.2rem 1.2rem 1rem;
            width: min(600px, 92vw);
            max-height: 90vh;
            overflow: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: .6rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }

        .modal-title {
            font-weight: 800;
            color: #2d3748;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.6rem;
            color: #718096;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #f56565;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: .35rem;
            font-weight: 700;
            color: #4a5568;
            font-size: .9rem;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: .6rem .7rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, .1);
        }

        .modal-actions {
            display: flex;
            gap: .6rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .students-container {
                padding: 1rem;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .action-form {
                width: 100%;
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
                    <li class="nav-item pcoded-menu-caption"><label>Navigation</label></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-home"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
                    <li class="nav-item"><a href="manage-students.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span><span class="pcoded-mtext">Manage Students</span></a></li>
                    <li class="nav-item"><a href="manage-lecturers.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span><span class="pcoded-mtext">Manage Lecturers</span></a></li>
                    <li class="nav-item"><a href="manage-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Manage Courses</span></a></li>
                    <li class="nav-item"><a href="grading-scale.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Grading Scale</span></a></li>
                    <li class="nav-item"><a href="system-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-cog"></i></span><span class="pcoded-mtext">System Settings</span></a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- [ navigation menu ] end -->

    <!-- [ Header ] start -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light">
        <div class="m-header">
            <a class="mobile-menu" id="mobile-collapse" href="#!"><span></span></a>
            <a href="#!" class="b-brand">
                <h3 class="text-primary mb-0">MGS Admin</h3>
            </a>
            <a href="#!" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="container-fluid">
                    <div class="page-content text-center">
                        <h1 class="page-title">Manage Students</h1>
                        <p class="page-subtitle">Register, edit, and organize student records. Only existing students can create accounts later.</p>
                    </div>
                </div>
            </div>

            <!-- Students Content -->
            <div class="students-container">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" style="background: rgba(72,187,120,.12); color:#2f855a; border: 1px solid rgba(72,187,120,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;"><?= h($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-error" style="background: rgba(245,101,101,.12); color:#c53030; border: 1px solid rgba(245,101,101,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;"><?= h($flashError) ?></div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <form class="action-form" method="get" action="">
                        <input type="text" name="q" value="<?= h($q) ?>" class="search-input" placeholder="Search by name or matric no...">
                        <select name="faculty_id" id="filterFaculty" class="filter-select">
                            <option value="">All Faculties</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?= (int)$f['faculty_id'] ?>" <?= ($filterFac && $filterFac === (int)$f['faculty_id']) ? 'selected' : '' ?>><?= h($f['faculty_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="dept_id" id="filterDept" class="filter-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['dept_id'] ?>" data-faculty="<?= (int)$d['faculty_id'] ?>" <?= ($filterDept && $filterDept === (int)$d['dept_id']) ? 'selected' : '' ?>><?= h($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="level" class="filter-select">
                            <option value="">All Levels</option>
                            <?php foreach ($levels as $lv): ?>
                                <option value="<?= (int)$lv['level_id'] ?>" <?= ($filterLvl && $filterLvl === (int)$lv['level_id']) ? 'selected' : '' ?>><?= h($lv['level_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-muted">Apply</button>
                        <?php if ($q !== '' || $filterFac || $filterDept || $filterLvl): ?>
                            <a href="manage-students.php" class="btn btn-muted">Reset</a>
                        <?php endif; ?>
                    </form>
                    <div class="action-buttons">
                        <button class="btn btn-primary" id="openAddStudent" type="button">
                            <i class="fas fa-plus"></i>
                            Add Student
                        </button>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="students-table-container">
                    <div class="table-header">
                        <h3 class="table-title">Student Records</h3>
                        <div class="table-stats">
                            <?= $total ? "Showing $from-$to of $total students" : "No students found" ?>
                        </div>
                    </div>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Matric No</th>
                                <th>Email</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Enrolled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" class="small" style="color:#718096; padding: 1rem;">No students match your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><?= h($s['full_name']) ?></td>
                                        <td><strong><?= h($s['matric_no']) ?></strong></td>
                                        <td><?= h($s['email']) ?></td>
                                        <td><?= h($s['faculty_name']) ?></td>
                                        <td><?= h($s['dept_name']) ?></td>
                                        <td><?= h($s['level_name'] ?: (string)$s['level']) ?></td>
                                        <td><?= h($s['created_at']) ?></td>
                                        <td>
                                            <div class="action-buttons-table">
                                                <button type="button" class="btn-sm js-edit-student"
                                                    title="Edit"
                                                    data-student='<?= h(json_encode([
                                                                        'student_id' => (string)$s['student_id'],
                                                                        'matric_no'  => (string)$s['matric_no'],
                                                                        'full_name'  => (string)$s['full_name'],
                                                                        'email'      => (string)$s['email'],
                                                                        'faculty_id' => (string)$s['faculty_id'],
                                                                        'dept_id'    => (string)$s['dept_id'],
                                                                        'level'      => (string)$s['level'],
                                                                        'enrolled'   => substr((string)$s['created_at'], 0, 10),
                                                                    ], JSON_UNESCAPED_UNICODE)) ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" style="display:inline" onsubmit="return confirm('Delete student <?= h($s['matric_no']) ?>?');">
                                                    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                                                    <input type="hidden" name="op" value="delete">
                                                    <input type="hidden" name="student_id" value="<?= h((string)$s['student_id']) ?>">
                                                    <button type="submit" class="btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination-container">
                        <div class="pagination-info"><?= $total ? "Showing $from to $to of $total entries" : "0 entries" ?></div>
                        <div class="pagination">
                            <?php
                            // Simple pagination buttons: Prev, numbered window, Next
                            $window = 3;
                            $start = max(1, $page - $window);
                            $end   = min($totalPages, $page + $window);
                            $prevQ = build_query(['page' => max(1, $page - 1)]);
                            $nextQ = build_query(['page' => min($totalPages, $page + 1)]);
                            ?>
                            <a class="page-btn<?= $page <= 1 ? ' disabled' : '' ?>" href="?<?= h($prevQ) ?>">Previous</a>
                            <?php for ($p = $start; $p <= $end; $p++): $qstr = build_query(['page' => $p]); ?>
                                <a class="page-btn<?= $p === $page ? ' active' : '' ?>" href="?<?= h($qstr) ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <a class="page-btn<?= $page >= $totalPages ? ' disabled' : '' ?>" href="?<?= h($nextQ) ?>">Next</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Add/Edit Student Modal -->
    <div id="studentModal" class="modal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"><?= $modalMode === 'update' ? 'Edit Student' : 'Add New Student' ?></h2>
                <button class="close-btn" id="studentClose" aria-label="Close">&times;</button>
            </div>
            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error" style="background: rgba(245,101,101,.12); color:#c53030; border: 1px solid rgba(245,101,101,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;">
                    <?php foreach ($formErrors as $msg): if (is_string($msg)): ?>
                            <div><?= h($msg) ?></div>
                    <?php endif;
                    endforeach; ?>
                </div>
            <?php endif; ?>
            <form id="studentForm" method="post" action="" novalidate>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                <input type="hidden" name="op" id="opField" value="<?= $modalMode === 'update' ? 'update' : 'create' ?>">
                <input type="hidden" name="student_id" id="editingStudentId" value="<?= h($old['student_id'] ?? '') ?>">

                <div class="form-group">
                    <label class="form-label" for="fullName">Full Name</label>
                    <input type="text" class="form-input" name="full_name" id="fullName" required value="<?= h($old['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="matricNo">Matric No</label>
                    <input type="text" class="form-input" name="matric_no" id="matricNo" required value="<?= h($old['matric_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email (optional)</label>
                    <input type="email" class="form-input" name="email" id="email" placeholder="student@example.com" value="<?= h($old['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="facultyId">Faculty</label>
                    <select class="form-select" name="faculty_id" id="facultyId" required>
                        <option value="">Select Faculty</option>
                        <?php foreach ($faculties as $f): ?>
                            <option value="<?= (int)$f['faculty_id'] ?>" <?= (isset($old['faculty_id']) && (int)$old['faculty_id'] === (int)$f['faculty_id']) ? 'selected' : '' ?>><?= h($f['faculty_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="deptId">Department</label>
                    <select class="form-select" name="dept_id" id="deptId" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['dept_id'] ?>" data-faculty="<?= (int)$d['faculty_id'] ?>" <?= (isset($old['dept_id']) && (int)$old['dept_id'] === (int)$d['dept_id']) ? 'selected' : '' ?>><?= h($d['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="level">Level</label>
                    <select class="form-select" name="level" id="level" required>
                        <option value="">Select Level</option>
                        <?php foreach ($levels as $lv): ?>
                            <option value="<?= (int)$lv['level_id'] ?>" <?= (isset($old['level']) && (int)$old['level'] === (int)$lv['level_id']) ? 'selected' : '' ?>><?= h($lv['level_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="enrolled">Enrolled (Date)</label>
                    <input type="date" class="form-input" name="enrolled" id="enrolled" value="<?= h($old['enrolled'] ?? '') ?>">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-muted" id="studentCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="studentSave">Save Student</button>
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
            const openBtn = document.getElementById('openAddStudent');
            const modal = document.getElementById('studentModal');
            const closeBtn = document.getElementById('studentClose');
            const cancelBtn = document.getElementById('studentCancel');
            const titleEl = document.getElementById('modalTitle');
            const form = document.getElementById('studentForm');
            const opField = document.getElementById('opField');

            // Inputs
            const input = {
                id: document.getElementById('editingStudentId'),
                fullName: document.getElementById('fullName'),
                matric: document.getElementById('matricNo'),
                email: document.getElementById('email'),
                fac: document.getElementById('facultyId'),
                dept: document.getElementById('deptId'),
                level: document.getElementById('level'),
                enrolled: document.getElementById('enrolled')
            };

            function showModal() {
                modal.classList.add('show');
                setTimeout(() => input.fullName && input.fullName.focus(), 120);
            }

            function hideModal() {
                modal.classList.remove('show');
            }

            function setMode(mode, data) {
                opField.value = mode === 'update' ? 'update' : 'create';
                titleEl.textContent = mode === 'update' ? 'Edit Student' : 'Add New Student';
                if (data) {
                    input.id.value = data.student_id || '';
                    input.fullName.value = data.full_name || '';
                    input.matric.value = data.matric_no || '';
                    input.email.value = data.email || '';
                    input.fac.value = data.faculty_id || '';
                    filterDepartmentsSelect();
                    input.dept.value = data.dept_id || '';
                    input.level.value = data.level || '';
                    input.enrolled.value = data.enrolled || '';
                } else {
                    form.reset();
                    input.id.value = '';
                    filterDepartmentsSelect();
                }
            }

            function filterDepartmentsSelect() {
                const fac = input.fac.value;
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
            input.fac.addEventListener('change', filterDepartmentsSelect);

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
            document.querySelectorAll('.js-edit-student').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const data = JSON.parse(btn.getAttribute('data-student') || '{}');
                        setMode('update', data);
                        showModal();
                    } catch (e) {
                        console.error('Bad student data', e);
                    }
                });
            });

            // Filter departments in the filter bar too
            const filterFaculty = document.getElementById('filterFaculty');
            const filterDept = document.getElementById('filterDept');

            function filterDeptBar() {
                const f = filterFaculty.value;
                Array.from(filterDept.options).forEach(opt => {
                    if (opt.value === '') {
                        opt.hidden = false;
                        return;
                    }
                    const fac = opt.getAttribute('data-faculty');
                    opt.hidden = (f && fac !== f);
                    if (opt.hidden && opt.selected) opt.selected = false;
                });
            }
            if (filterFaculty && filterDept) {
                filterDeptBar();
                filterFaculty.addEventListener('change', filterDeptBar);
            }

            // Auto-open modal when server-side validation failed
            <?php if (!empty($openModal)): ?>
                setMode('<?= $modalMode === 'update' ? 'update' : 'create' ?>', <?= json_encode($old ?? [], JSON_UNESCAPED_UNICODE) ?>);
                showModal();
            <?php endif; ?>
        })();
    </script>
</body>

</html>