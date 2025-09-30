<?php

declare(strict_types=1);
session_start();

/**
 * Auth and DB
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
 * Create missing tables if they don't exist
 */
if ($mysqli) {
    // Check if levels table exists, if not create it
    $result = $mysqli->query("SHOW TABLES LIKE 'levels'");
    if ($result->num_rows == 0) {
        // Create levels table only if it doesn't exist
        $mysqli->query("CREATE TABLE levels (
            level_id INT PRIMARY KEY AUTO_INCREMENT,
            level_name VARCHAR(10) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert default levels for new table
        $mysqli->query("INSERT INTO levels (level_name) VALUES
            ('100'), ('200'), ('300'), ('400'), ('500'), ('600')");
    } else {
        // Table exists, just insert missing levels if any
        $mysqli->query("INSERT IGNORE INTO levels (level_name) VALUES
            ('100'), ('200'), ('300'), ('400'), ('500'), ('600')");
    }

    // Check if course_assignments table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'course_assignments'");
    if ($result->num_rows == 0) {
        // Create course_assignments table with session column
        $mysqli->query("CREATE TABLE course_assignments (
            assignment_id INT PRIMARY KEY AUTO_INCREMENT,
            lecturer_id INT NOT NULL,
            course_id INT NOT NULL,
            dept_id INT NOT NULL,
            level_id INT NOT NULL,
            session VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (lecturer_id, course_id, dept_id, level_id, session),
            INDEX idx_lecturer (lecturer_id),
            INDEX idx_course (course_id),
            INDEX idx_dept (dept_id),
            INDEX idx_level (level_id),
            INDEX idx_session (session)
        )");
    } else {
        // Table exists, check if it has session column
        $columns = $mysqli->query("SHOW COLUMNS FROM course_assignments LIKE 'session'");
        if ($columns->num_rows == 0) {
            // Add session column if it doesn't exist
            $mysqli->query("ALTER TABLE course_assignments ADD COLUMN session VARCHAR(50) NOT NULL DEFAULT '2023/2024'");

            // Check if session_id column exists and migrate data
            $sessionIdColumns = $mysqli->query("SHOW COLUMNS FROM course_assignments LIKE 'session_id'");
            if ($sessionIdColumns->num_rows > 0) {
                // Migrate data from session_id to session (if academic_sessions table exists)
                $sessionTableExists = $mysqli->query("SHOW TABLES LIKE 'academic_sessions'");
                if ($sessionTableExists->num_rows > 0) {
                    $mysqli->query("UPDATE course_assignments ca 
                                   LEFT JOIN academic_sessions s ON s.session_id = ca.session_id 
                                   SET ca.session = COALESCE(s.session_name, '2023/2024')");
                }

                // Drop the old session_id column and related constraints
                $mysqli->query("ALTER TABLE course_assignments DROP FOREIGN KEY IF EXISTS fk_assignment_session");
                $mysqli->query("ALTER TABLE course_assignments DROP COLUMN session_id");
            }

            // Update unique constraint to include session instead of session_id
            $mysqli->query("ALTER TABLE course_assignments DROP INDEX IF EXISTS unique_assignment");
            $mysqli->query("ALTER TABLE course_assignments ADD UNIQUE KEY unique_assignment (lecturer_id, course_id, dept_id, level_id, session)");

            // Add index for session
            $mysqli->query("ALTER TABLE course_assignments ADD INDEX idx_session (session)");
        }
    }
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

function fetch_lecturers(mysqli $db): array
{
    $rows = [];
    if ($res = $db->query("SELECT l.lecturer_id, l.lecturer_name, l.staff_no, l.faculty_id, l.dept_id, 
                                  f.faculty_name, d.dept_name 
                           FROM lecturers l 
                           LEFT JOIN faculties f ON f.faculty_id = l.faculty_id 
                           LEFT JOIN departments d ON d.dept_id = l.dept_id 
                           ORDER BY l.lecturer_name ASC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

function fetch_courses(mysqli $db): array
{
    $rows = [];
    if ($res = $db->query("SELECT c.course_id, c.course_name, c.course_code, c.dept_id, c.level_id,
                                  d.dept_name, l.level_name
                           FROM courses c 
                           LEFT JOIN departments d ON d.dept_id = c.dept_id
                           LEFT JOIN levels l ON l.level_id = c.level_id
                           ORDER BY c.course_code ASC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

function fetch_sessions(mysqli $db): array
{
    $rows = [];

    // Check if course_assignments table exists and has session column
    $tableExists = $db->query("SHOW TABLES LIKE 'course_assignments'");
    if ($tableExists->num_rows == 0) {
        return $rows; // Return empty array if table doesn't exist
    }

    $columnExists = $db->query("SHOW COLUMNS FROM course_assignments LIKE 'session'");
    if ($columnExists->num_rows == 0) {
        return $rows; // Return empty array if session column doesn't exist
    }

    // Query distinct sessions
    if ($res = $db->query("SELECT DISTINCT session FROM course_assignments WHERE session IS NOT NULL AND session != '' ORDER BY session DESC")) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

/**
 * Validation
 */
function validate_assignment_post(array $src): array
{
    $errors = [];
    $assignment_id = trim((string)($src['assignment_id'] ?? ''));
    $lecturer_id   = trim((string)($src['lecturer_id'] ?? ''));
    $course_id     = trim((string)($src['course_id'] ?? ''));
    $dept_id       = trim((string)($src['dept_id'] ?? ''));
    $level_id      = trim((string)($src['level_id'] ?? ''));
    $session       = trim((string)($src['session'] ?? ''));

    if ($lecturer_id === '' || !ctype_digit($lecturer_id)) $errors['lecturer_id'] = 'Select a valid lecturer';
    if ($course_id === '' || !ctype_digit($course_id)) $errors['course_id'] = 'Select a valid course';
    if ($dept_id === '' || !ctype_digit($dept_id)) $errors['dept_id'] = 'Select a valid department';
    if ($level_id === '' || !ctype_digit($level_id)) $errors['level_id'] = 'Select a valid level';
    if ($session === '') $errors['session'] = 'Enter a valid session (e.g., 2023/2024)';

    $data = [
        'assignment_id' => $assignment_id,
        'lecturer_id'   => $lecturer_id === '' ? null : (int)$lecturer_id,
        'course_id'     => $course_id === '' ? null : (int)$course_id,
        'dept_id'       => $dept_id === '' ? null : (int)$dept_id,
        'level_id'      => $level_id === '' ? null : (int)$level_id,
        'session'       => $session,
    ];

    return [$data, $errors];
}

/**
 * Business Logic Validation
 */
function validate_lecturer_department_match(mysqli $db, int $lecturer_id, int $dept_id): bool
{
    $sql = "SELECT l.dept_id, l.faculty_id, d.faculty_id as dept_faculty_id 
            FROM lecturers l 
            JOIN departments d ON d.dept_id = ? 
            WHERE l.lecturer_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param('ii', $dept_id, $lecturer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) return false;

    // Lecturer can teach in their own department OR any department in their faculty
    return ($row['dept_id'] == $dept_id) || ($row['faculty_id'] == $row['dept_faculty_id']);
}

function assignment_exists(mysqli $db, int $lecturer_id, int $course_id, int $dept_id, int $level_id, string $session, ?int $exclude_id = null): bool
{
    $sql = "SELECT assignment_id FROM course_assignments 
            WHERE lecturer_id = ? AND course_id = ? AND dept_id = ? AND level_id = ? AND session = ?"
        . ($exclude_id ? " AND assignment_id <> ?" : "") . " LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return true;

    if ($exclude_id) {
        $stmt->bind_param('iiiisi', $lecturer_id, $course_id, $dept_id, $level_id, $session, $exclude_id);
    } else {
        $stmt->bind_param('iiiis', $lecturer_id, $course_id, $dept_id, $level_id, $session);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}

/**
 * CRUD Operations
 */
function assignment_create(mysqli $db, array $d): array
{
    // Validate lecturer-department relationship
    if (!validate_lecturer_department_match($db, $d['lecturer_id'], $d['dept_id'])) {
        return [false, 'Lecturer can only be assigned to courses in their faculty'];
    }

    // Check for duplicate assignment
    if (assignment_exists($db, $d['lecturer_id'], $d['course_id'], $d['dept_id'], $d['level_id'], $d['session'])) {
        return [false, 'This assignment already exists'];
    }

    $sql = "INSERT INTO course_assignments (lecturer_id, course_id, dept_id, level_id, session, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $stmt->bind_param('iiiis', $d['lecturer_id'], $d['course_id'], $d['dept_id'], $d['level_id'], $d['session']);

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    return [true, $newId];
}

function assignment_update(mysqli $db, array $d): array
{
    if (empty($d['assignment_id']) || !ctype_digit((string)$d['assignment_id'])) {
        return [false, 'Invalid assignment ID'];
    }
    $id = (int)$d['assignment_id'];

    // Validate lecturer-department relationship
    if (!validate_lecturer_department_match($db, $d['lecturer_id'], $d['dept_id'])) {
        return [false, 'Lecturer can only be assigned to courses in their faculty'];
    }

    // Check for duplicate assignment (excluding current)
    if (assignment_exists($db, $d['lecturer_id'], $d['course_id'], $d['dept_id'], $d['level_id'], $d['session'], $id)) {
        return [false, 'This assignment already exists'];
    }

    $sql = "UPDATE course_assignments 
            SET lecturer_id = ?, course_id = ?, dept_id = ?, level_id = ?, session = ?
            WHERE assignment_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [false, 'Failed to prepare statement'];

    $stmt->bind_param('iiiisi', $d['lecturer_id'], $d['course_id'], $d['dept_id'], $d['level_id'], $d['session'], $id);

    if (!$stmt->execute()) {
        $err = 'DB error: ' . $db->error;
        $stmt->close();
        return [false, $err];
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return [true, $affected];
}

function assignment_delete(mysqli $db, string $assignment_id): array
{
    if ($assignment_id === '' || !ctype_digit((string)$assignment_id)) {
        return [false, 'Invalid assignment ID'];
    }
    $id = (int)$assignment_id;

    $sql = "DELETE FROM course_assignments WHERE assignment_id = ?";
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
function assignments_query(mysqli $db, string $q, ?int $lecturer_id, ?int $course_id, ?int $dept_id, ?int $level_id, ?string $session, int $offset, int $limit): array
{
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = "(l.lecturer_name LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
    }
    if ($lecturer_id) {
        $where[] = "ca.lecturer_id = ?";
        $params[] = $lecturer_id;
        $types .= 'i';
    }
    if ($course_id) {
        $where[] = "ca.course_id = ?";
        $params[] = $course_id;
        $types .= 'i';
    }
    if ($dept_id) {
        $where[] = "ca.dept_id = ?";
        $params[] = $dept_id;
        $types .= 'i';
    }
    if ($level_id) {
        $where[] = "ca.level_id = ?";
        $params[] = $level_id;
        $types .= 'i';
    }
    if ($session) {
        $where[] = "ca.session LIKE ?";
        $params[] = "%$session%";
        $types .= 's';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
            ca.assignment_id, ca.lecturer_id, ca.course_id, ca.dept_id, ca.level_id, ca.session,
            l.lecturer_name, l.staff_no,
            c.course_name, c.course_code,
            d.dept_name,
            lv.level_name,
            ca.created_at
          FROM course_assignments ca
          LEFT JOIN lecturers l ON l.lecturer_id = ca.lecturer_id
          LEFT JOIN courses c ON c.course_id = ca.course_id
          LEFT JOIN departments d ON d.dept_id = ca.dept_id
          LEFT JOIN levels lv ON lv.level_id = ca.level_id
          $whereSql
          ORDER BY ca.created_at DESC, ca.assignment_id DESC
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

function assignments_count(mysqli $db, string $q, ?int $lecturer_id, ?int $course_id, ?int $dept_id, ?int $level_id, ?string $session): int
{
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = "(l.lecturer_name LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
        $params[] = $like;
        $types .= 's';
    }
    if ($lecturer_id) {
        $where[] = "ca.lecturer_id = ?";
        $params[] = $lecturer_id;
        $types .= 'i';
    }
    if ($course_id) {
        $where[] = "ca.course_id = ?";
        $params[] = $course_id;
        $types .= 'i';
    }
    if ($dept_id) {
        $where[] = "ca.dept_id = ?";
        $params[] = $dept_id;
        $types .= 'i';
    }
    if ($level_id) {
        $where[] = "ca.level_id = ?";
        $params[] = $level_id;
        $types .= 'i';
    }
    if ($session) {
        $where[] = "ca.session LIKE ?";
        $params[] = "%$session%";
        $types .= 's';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT COUNT(*) AS c FROM course_assignments ca
            LEFT JOIN lecturers l ON l.lecturer_id = ca.lecturer_id
            LEFT JOIN courses c ON c.course_id = ca.course_id
            LEFT JOIN departments d ON d.dept_id = ca.dept_id
            $whereSql";
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
        if ($op === 'create' || $op === 'update') {
            [$data, $errs] = validate_assignment_post($_POST);
            if (!empty($errs)) {
                $formErrors = $errs;
                $old = $data;
                $openModal = true;
                $modalMode = $op;
            } else {
                if ($op === 'create') {
                    [$ok, $result] = assignment_create($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Course assignment created successfully.';
                        header('Location: assign-courses.php');
                        exit;
                    } else {
                        $flashError = $result;
                        $formErrors = ['_general' => $result];
                        $old = $data;
                        $openModal = true;
                        $modalMode = 'create';
                    }
                } else {
                    [$ok, $result] = assignment_update($mysqli, $data);
                    if ($ok) {
                        $_SESSION['flash_success'] = 'Course assignment updated successfully.';
                        header('Location: assign-courses.php');
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
            $assignment_id = $_POST['assignment_id'] ?? '';
            [$ok, $result] = assignment_delete($mysqli, $assignment_id);
            if ($ok) {
                $_SESSION['flash_success'] = 'Course assignment deleted successfully.';
                header('Location: assign-courses.php');
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
$lecturers   = $mysqli ? fetch_lecturers($mysqli)   : [];
$courses     = $mysqli ? fetch_courses($mysqli)     : [];
$sessions    = $mysqli ? fetch_sessions($mysqli)    : [];

$q             = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterLec     = isset($_GET['lecturer_id']) && ctype_digit((string)$_GET['lecturer_id']) ? (int)$_GET['lecturer_id'] : null;
$filterCourse  = isset($_GET['course_id'])   && ctype_digit((string)$_GET['course_id'])   ? (int)$_GET['course_id']   : null;
$filterDept    = isset($_GET['dept_id'])     && ctype_digit((string)$_GET['dept_id'])     ? (int)$_GET['dept_id']     : null;
$filterLevel   = isset($_GET['level_id'])    && ctype_digit((string)$_GET['level_id'])    ? (int)$_GET['level_id']    : null;
$filterSession = isset($_GET['session'])     ? trim((string)$_GET['session'])             : null;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$total       = $mysqli ? assignments_count($mysqli, $q, $filterLec, $filterCourse, $filterDept, $filterLevel, $filterSession) : 0;
$assignments = $mysqli ? assignments_query($mysqli, $q, $filterLec, $filterCourse, $filterDept, $filterLevel, $filterSession, $offset, $perPage) : [];

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
    <title>Assign Courses - Marking & Grading System</title>
    <!-- HTML5 Shim and Respond.js IE11 support of HTML5 elements and media queries -->
    <!--[if lt IE 11]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Professional Course Assignment Management System" />
    <meta name="keywords" content="courses, assignments, lecturers, education, dashboard">
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

        .assignments-container {
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
        .assignments-table-container {
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

        .form-input:disabled,
        .form-select:disabled {
            background: #f1f5f9;
            color: #718096;
            cursor: not-allowed;
        }

        .modal-actions {
            display: flex;
            gap: .6rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .assignments-container {
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
                    <li class="nav-item"><a href="manage-students.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span><span class="pcoded-mtext">Manage Students</span></a></li>
                    <li class="nav-item"><a href="manage-lecturers.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span><span class="pcoded-mtext">Manage Lecturers</span></a></li>
                    <li class="nav-item"><a href="manage-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Manage Courses</span></a></li>
                    <li class="nav-item"><a href="assign-courses.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user-tie"></i></span><span class="pcoded-mtext">Assign Courses</span></a></li>
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
                        <h1 class="page-title">Course Assignments</h1>
                        <p class="page-subtitle">Assign lecturers to courses for specific departments, levels, and academic sessions.</p>
                    </div>
                </div>
            </div>

            <!-- Assignments Content -->
            <div class="assignments-container">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" style="background: rgba(72,187,120,.12); color:#2f855a; border: 1px solid rgba(72,187,120,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;"><?= h($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-error" style="background: rgba(245,101,101,.12); color:#c53030; border: 1px solid rgba(245,101,101,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;"><?= h($flashError) ?></div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <form class="action-form" method="get" action="">
                        <input type="text" name="q" value="<?= h($q) ?>" class="search-input" placeholder="Search lecturer, course...">
                        <select name="lecturer_id" class="filter-select">
                            <option value="">All Lecturers</option>
                            <?php foreach ($lecturers as $l): ?>
                                <option value="<?= (int)$l['lecturer_id'] ?>" <?= ($filterLec && $filterLec === (int)$l['lecturer_id']) ? 'selected' : '' ?>><?= h($l['lecturer_name']) ?> (<?= h($l['staff_no']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select name="course_id" class="filter-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= (int)$c['course_id'] ?>" <?= ($filterCourse && $filterCourse === (int)$c['course_id']) ? 'selected' : '' ?>><?= h($c['course_code']) ?> - <?= h($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="dept_id" class="filter-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['dept_id'] ?>" <?= ($filterDept && $filterDept === (int)$d['dept_id']) ? 'selected' : '' ?>><?= h($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="session" value="<?= h($filterSession ?? '') ?>" class="search-input" placeholder="Session (e.g., 2023/2024)">
                        <button type="submit" class="btn btn-muted">Apply</button>
                        <?php if ($q !== '' || $filterLec || $filterCourse || $filterDept || $filterLevel || $filterSession): ?>
                            <a href="assign-courses.php" class="btn btn-muted">Reset</a>
                        <?php endif; ?>
                    </form>
                    <div class="action-buttons">
                        <button class="btn btn-primary" id="openAddAssignment" type="button">
                            <i class="fas fa-plus"></i>
                            Assign Course
                        </button>
                    </div>
                </div>

                <!-- Assignments Table -->
                <div class="assignments-table-container">
                    <div class="table-header">
                        <h3 class="table-title">Course Assignments</h3>
                        <div class="table-stats">
                            <?= $total ? "Showing $from-$to of $total assignments" : "No assignments found" ?>
                        </div>
                    </div>
                    <table class="assignments-table">
                        <thead>
                            <tr>
                                <th>Lecturer</th>
                                <th>Course</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Session</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="7" class="small" style="color:#718096; padding: 1rem;">No assignments match your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $a): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($a['lecturer_name']) ?></strong><br>
                                            <small style="color:#718096;"><?= h($a['staff_no']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= h($a['course_code']) ?></strong><br>
                                            <small style="color:#718096;"><?= h($a['course_name']) ?></small>
                                        </td>
                                        <td><?= h($a['dept_name']) ?></td>
                                        <td><?= h($a['level_name']) ?></td>
                                        <td><?= h($a['session']) ?></td>
                                        <td><?= h($a['created_at']) ?></td>
                                        <td>
                                            <div class="action-buttons-table">
                                                <button type="button" class="btn-sm js-edit-assignment"
                                                    title="Edit"
                                                    data-assignment='<?= h(json_encode([
                                                                            'assignment_id' => (string)$a['assignment_id'],
                                                                            'lecturer_id'   => (string)$a['lecturer_id'],
                                                                            'course_id'     => (string)$a['course_id'],
                                                                            'dept_id'       => (string)$a['dept_id'],
                                                                            'level_id'      => (string)$a['level_id'],
                                                                            'session'       => (string)$a['session'],
                                                                        ], JSON_UNESCAPED_UNICODE)) ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this assignment?');">
                                                    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                                                    <input type="hidden" name="op" value="delete">
                                                    <input type="hidden" name="assignment_id" value="<?= h((string)$a['assignment_id']) ?>">
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

    <!-- Add/Edit Assignment Modal -->
    <div id="assignmentModal" class="modal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"><?= $modalMode === 'update' ? 'Edit Assignment' : 'Assign Course' ?></h2>
                <button class="close-btn" id="assignmentClose" aria-label="Close">&times;</button>
            </div>
            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error" style="background: rgba(245,101,101,.12); color:#c53030; border: 1px solid rgba(245,101,101,.25); padding: .8rem 1rem; border-radius: 10px; margin-bottom: 12px;">
                    <?php foreach ($formErrors as $msg): if (is_string($msg)): ?>
                            <div><?= h($msg) ?></div>
                    <?php endif;
                    endforeach; ?>
                </div>
            <?php endif; ?>
            <form id="assignmentForm" method="post" action="" novalidate>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                <input type="hidden" name="op" id="opField" value="<?= $modalMode === 'update' ? 'update' : 'create' ?>">
                <input type="hidden" name="assignment_id" id="editingAssignmentId" value="<?= h($old['assignment_id'] ?? '') ?>">

                <div class="form-group">
                    <label class="form-label" for="lecturerId">Lecturer</label>
                    <select class="form-select" name="lecturer_id" id="lecturerId" required>
                        <option value="">Select Lecturer</option>
                        <?php foreach ($lecturers as $l): ?>
                            <option value="<?= (int)$l['lecturer_id'] ?>"
                                data-faculty="<?= (int)$l['faculty_id'] ?>"
                                data-dept="<?= (int)$l['dept_id'] ?>"
                                <?= (isset($old['lecturer_id']) && (int)$old['lecturer_id'] === (int)$l['lecturer_id']) ? 'selected' : '' ?>>
                                <?= h($l['lecturer_name']) ?> (<?= h($l['staff_no']) ?>) - <?= h($l['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="courseId">Course</label>
                    <select class="form-select" name="course_id" id="courseId" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['course_id'] ?>"
                                data-dept="<?= (int)$c['dept_id'] ?>"
                                data-level="<?= (int)$c['level_id'] ?>"
                                data-dept-name="<?= h($c['dept_name']) ?>"
                                data-level-name="<?= h($c['level_name']) ?>"
                                <?= (isset($old['course_id']) && (int)$old['course_id'] === (int)$c['course_id']) ? 'selected' : '' ?>>
                                <?= h($c['course_code']) ?> - <?= h($c['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="deptId">Department <small>(Auto-populated from course)</small></label>
                    <input type="text" class="form-input" id="deptDisplay" readonly placeholder="Select a course first">
                    <input type="hidden" name="dept_id" id="deptId" value="<?= h($old['dept_id'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="levelId">Level <small>(Auto-populated from course)</small></label>
                    <input type="text" class="form-input" id="levelDisplay" readonly placeholder="Select a course first">
                    <input type="hidden" name="level_id" id="levelId" value="<?= h($old['level_id'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="sessionInput">Academic Session</label>
                    <input type="text" class="form-input" name="session" id="sessionInput"
                        placeholder="e.g., 2023/2024"
                        value="<?= h($old['session'] ?? '') ?>" required>
                    <small style="color: #718096; font-size: 0.8rem;">Enter the academic session (e.g., 2023/2024, 2024/2025)</small>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-muted" id="assignmentCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignmentSave">Save Assignment</button>
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
            const openBtn = document.getElementById('openAddAssignment');
            const modal = document.getElementById('assignmentModal');
            const closeBtn = document.getElementById('assignmentClose');
            const cancelBtn = document.getElementById('assignmentCancel');
            const titleEl = document.getElementById('modalTitle');
            const form = document.getElementById('assignmentForm');
            const opField = document.getElementById('opField');

            // Inputs
            const input = {
                id: document.getElementById('editingAssignmentId'),
                lecturer: document.getElementById('lecturerId'),
                course: document.getElementById('courseId'),
                dept: document.getElementById('deptId'),
                deptDisplay: document.getElementById('deptDisplay'),
                level: document.getElementById('levelId'),
                levelDisplay: document.getElementById('levelDisplay'),
                session: document.getElementById('sessionInput')
            };

            // Course selection handler
            input.course.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const deptId = selectedOption.getAttribute('data-dept');
                    const levelId = selectedOption.getAttribute('data-level');
                    const deptName = selectedOption.getAttribute('data-dept-name');
                    const levelName = selectedOption.getAttribute('data-level-name');

                    // Auto-populate department and level
                    input.dept.value = deptId;
                    input.deptDisplay.value = deptName;
                    input.level.value = levelId;
                    input.levelDisplay.value = levelName;
                } else {
                    // Clear fields if no course selected
                    input.dept.value = '';
                    input.deptDisplay.value = '';
                    input.level.value = '';
                    input.levelDisplay.value = '';
                }
            });

            function showModal() {
                modal.classList.add('show');
                setTimeout(() => input.lecturer && input.lecturer.focus(), 120);
            }

            function hideModal() {
                modal.classList.remove('show');
            }

            function setMode(mode, data) {
                opField.value = mode === 'update' ? 'update' : 'create';
                titleEl.textContent = mode === 'update' ? 'Edit Assignment' : 'Assign Course';
                if (data) {
                    input.id.value = data.assignment_id || '';
                    input.lecturer.value = data.lecturer_id || '';
                    input.course.value = data.course_id || '';
                    input.dept.value = data.dept_id || '';
                    input.level.value = data.level_id || '';
                    input.session.value = data.session || '';

                    // Trigger course change to populate display fields
                    if (data.course_id) {
                        const courseOption = input.course.querySelector(`option[value="${data.course_id}"]`);
                        if (courseOption) {
                            input.deptDisplay.value = courseOption.getAttribute('data-dept-name') || '';
                            input.levelDisplay.value = courseOption.getAttribute('data-level-name') || '';
                        }
                    }
                } else {
                    form.reset();
                    input.id.value = '';
                    input.dept.value = '';
                    input.deptDisplay.value = '';
                    input.level.value = '';
                    input.levelDisplay.value = '';
                }
            }

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
            document.querySelectorAll('.js-edit-assignment').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const data = JSON.parse(btn.getAttribute('data-assignment') || '{}');
                        setMode('update', data);
                        showModal();
                    } catch (e) {
                        console.error('Bad assignment data', e);
                    }
                });
            });

            // Auto-open modal when server-side validation failed
            <?php if (!empty($openModal)): ?>
                setMode('<?= $modalMode === 'update' ? 'update' : 'create' ?>', <?= json_encode($old ?? [], JSON_UNESCAPED_UNICODE) ?>);
                showModal();
            <?php endif; ?>
        })();
    </script>
</body>

</html>
</qodoArtifact>