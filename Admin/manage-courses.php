<?php
declare(strict_types=1);
session_start();

// Auth (adjust path if needed)
require_once __DIR__ . '/../includes/auth.php';
if (function_exists('require_admin')) {
  require_admin();
}

// DB include (adjust path if needed)
require_once __DIR__ . '/../includes/connect.php';

/**
 * Locate a mysqli connection from common includes.
 */
function db_connect_auto(): ?mysqli {
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

  // Optional fallback via env
  $host = getenv('DB_HOST');
  $user = getenv('DB_USER');
  $pass = getenv('DB_PASS');
  $name = getenv('DB_NAME');
  if ($host && $user && $name) {
    $m = @new mysqli($host, $user, $pass, $name);
    if ($m->connect_errno === 0) return $m;
  }
  return null;
}

function h(?string $s): string {
  return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF token
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Modal state for validation errors
$openModal = false;
$modalMode = 'create';
$old = [];
$formErrors = [];

// DB connect
$mysqli = db_connect_auto();
if (!$mysqli) {
  // Keep page usable even without DB; show message only.
  $flashError = 'Database connection not available. Please configure includes/connect.php or environment variables.';
}

/**
 * Validate and normalize course input from POST
 * Returns [array $data, array $errors]
 */
function validate_course_from_post(array $src): array {
  $errors = [];

  $allowedLevels = ['100', '200', '300', '400', '500'];
  $allowedSemesters = ['First', 'Second'];
  $allowedStatus = ['Active', 'Inactive'];

  $course_id   = isset($src['course_id']) ? trim((string)$src['course_id']) : '';
  $course_code = isset($src['course_code']) ? trim((string)$src['course_code']) : '';
  $course_name = isset($src['course_name']) ? trim((string)$src['course_name']) : '';
  $credit_unit = isset($src['credit_unit']) ? (string)$src['credit_unit'] : '';
  $level       = isset($src['level']) ? trim((string)$src['level']) : '';
  $faculty     = isset($src['faculty_name']) ? trim((string)$src['faculty_name']) : '';
  $dept        = isset($src['dept_name']) ? trim((string)$src['dept_name']) : '';
  $semester    = isset($src['semester']) ? trim((string)$src['semester']) : '';
  $status      = isset($src['status']) ? trim((string)$src['status']) : '';

  if ($course_code === '') $errors['course_code'] = 'Course code is required';
  if ($course_name === '') $errors['course_name'] = 'Course title is required';
  if ($credit_unit === '' || !is_numeric($credit_unit)) {
    $errors['credit_unit'] = 'Credit unit must be a number';
  }
  if (!in_array($level, $allowedLevels, true)) $errors['level'] = 'Invalid level';
  if ($faculty === '') $errors['faculty_name'] = 'Faculty is required';
  if ($dept === '') $errors['dept_name'] = 'Department is required';
  if (!in_array($semester, $allowedSemesters, true)) $errors['semester'] = 'Invalid semester';
  if (!in_array($status, $allowedStatus, true)) $errors['status'] = 'Invalid status';

  $data = [
    'course_id'     => $course_id,
    'course_code'   => $course_code,
    'course_name'   => $course_name,
    'credit_unit'   => $credit_unit === '' ? null : (float)$credit_unit,
    'level'         => $level, // string like '100'
    'faculty_name'  => $faculty,
    'dept_name'     => $dept,
    'semester'      => $semester,
    'status'        => $status,
  ];

  return [$data, $errors];
}

/**
 * Look up or create a faculty by name; returns [true, faculty_id] or [false, msg]
 */
function ensure_faculty_id(mysqli $db, string $faculty_name): array {
  $faculty_name_trim = trim($faculty_name);
  if ($faculty_name_trim === '') return [false, 'Faculty name empty'];

  // Try find
  $sql = "SELECT faculty_id FROM faculties WHERE LOWER(faculty_name) = LOWER(?) LIMIT 1";
  if ($stmt = $db->prepare($sql)) {
    $stmt->bind_param('s', $faculty_name_trim);
    if ($stmt->execute() && ($res = $stmt->get_result()) && ($row = $res->fetch_assoc())) {
      $id = (int)$row['faculty_id'];
      $stmt->close();
      return [true, $id];
    }
    $stmt->close();
  } else {
    return [false, 'Failed to prepare faculty lookup'];
  }

  // Insert new
  $ins = "INSERT INTO faculties (faculty_name) VALUES (?)";
  if ($stmt = $db->prepare($ins)) {
    $stmt->bind_param('s', $faculty_name_trim);
    if (!$stmt->execute()) {
      $err = 'DB error creating faculty: ' . $db->error;
      $stmt->close();
      return [false, $err];
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    return [true, $newId];
  }
  return [false, 'Failed to prepare faculty insert'];
}

/**
 * Look up or create a department by name within a faculty; returns [true, dept_id] or [false, msg]
 */
function ensure_department_id(mysqli $db, int $faculty_id, string $dept_name): array {
  $dept_name_trim = trim($dept_name);
  if ($dept_name_trim === '') return [false, 'Department name empty'];
  if ($faculty_id <= 0) return [false, 'Invalid faculty ID'];

  // Try find
  $sql = "SELECT dept_id FROM departments WHERE faculty_id = ? AND LOWER(dept_name) = LOWER(?) LIMIT 1";
  if ($stmt = $db->prepare($sql)) {
    $stmt->bind_param('is', $faculty_id, $dept_name_trim);
    if ($stmt->execute() && ($res = $stmt->get_result()) && ($row = $res->fetch_assoc())) {
      $id = (int)$row['dept_id'];
      $stmt->close();
      return [true, $id];
    }
    $stmt->close();
  } else {
    return [false, 'Failed to prepare department lookup'];
  }

  // Insert new
  $ins = "INSERT INTO departments (faculty_id, dept_name) VALUES (?, ?)";
  if ($stmt = $db->prepare($ins)) {
    $stmt->bind_param('is', $faculty_id, $dept_name_trim);
    if (!$stmt->execute()) {
      $err = 'DB error creating department: ' . $db->error;
      $stmt->close();
      return [false, $err];
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    return [true, $newId];
  }
  return [false, 'Failed to prepare department insert'];
}

/**
 * Fetch all courses (joined with faculty/department names)
 */
function courses_all(mysqli $db): array {
  $rows = [];
  $sql = "SELECT
            c.course_id,
            c.course_code,
            c.course_name,
            c.credit_unit,
            c.level_id AS level,
            f.faculty_name,
            d.dept_name,
            c.semester,
            c.status
          FROM courses AS c
          LEFT JOIN faculties AS f ON c.faculty_id = f.faculty_id
          LEFT JOIN departments AS d ON c.dept_id = d.dept_id
          ORDER BY c.course_code ASC";
  if ($res = $db->query($sql)) {
    while ($row = $res->fetch_assoc()) {
      $rows[] = $row;
    }
    $res->free();
  }
  return $rows;
}

/**
 * Create a course (maps faculty_name/dept_name to IDs; stores level to level_id)
 * Note: No lecturer dependency; insert without lecturer_id.
 */
function course_create(mysqli $db, array $d): array {
  // Resolve Faculty
  [$okF, $faculty_id_val] = ensure_faculty_id($db, (string)$d['faculty_name']);
  if (!$okF) return [false, is_string($faculty_id_val) ? $faculty_id_val : 'Faculty lookup failed'];

  // Resolve Department
  [$okD, $dept_id_val] = ensure_department_id($db, (int)$faculty_id_val, (string)$d['dept_name']);
  if (!$okD) return [false, is_string($dept_id_val) ? $dept_id_val : 'Department lookup failed'];

  // Insert without lecturer_id (it stays NULL or default until assigned elsewhere)
  $sql = "INSERT INTO courses (
            course_code, course_name, credit_unit, level_id, faculty_id, dept_id, semester, status
          ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?
          )";
  $stmt = $db->prepare($sql);
  if (!$stmt) return [false, 'Failed to prepare statement'];

  $credit   = (float)$d['credit_unit'];
  $level_id = (int)$d['level'];
  $fac_id   = (int)$faculty_id_val;
  $dep_id   = (int)$dept_id_val;

  // Types: s s d i i i s s  (8 params)
  $stmt->bind_param(
    'ssdiiiss',
    $d['course_code'],
    $d['course_name'],
    $credit,
    $level_id,
    $fac_id,
    $dep_id,
    $d['semester'],
    $d['status']
  );

  if (!$stmt->execute()) {
    $err = $db->errno === 1062 ? 'Course code already exists' : ('DB error: ' . $db->error);
    $stmt->close();
    return [false, $err];
  }
  $newId = (string)$stmt->insert_id;
  $stmt->close();
  return [true, $newId];
}

/**
 * Update a course (no lecturer dependency)
 */
function course_update(mysqli $db, array $d): array {
  if (empty($d['course_id']) || !ctype_digit((string)$d['course_id'])) {
    return [false, 'Invalid course ID'];
  }

  // Resolve Faculty
  [$okF, $faculty_id_val] = ensure_faculty_id($db, (string)$d['faculty_name']);
  if (!$okF) return [false, is_string($faculty_id_val) ? $faculty_id_val : 'Faculty lookup failed'];

  // Resolve Department
  [$okD, $dept_id_val] = ensure_department_id($db, (int)$faculty_id_val, (string)$d['dept_name']);
  if (!$okD) return [false, is_string($dept_id_val) ? $dept_id_val : 'Department lookup failed'];

  $sql = "UPDATE courses
            SET course_code = ?, course_name = ?, credit_unit = ?, level_id = ?, faculty_id = ?, dept_id = ?, semester = ?, status = ?
          WHERE course_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) return [false, 'Failed to prepare statement'];

  $credit   = (float)$d['credit_unit'];
  $level_id = (int)$d['level'];
  $fac_id   = (int)$faculty_id_val;
  $dep_id   = (int)$dept_id_val;
  $id       = (int)$d['course_id'];

  // Types: s s d i i i s s i
  $stmt->bind_param(
    'ssdiiissi',
    $d['course_code'],
    $d['course_name'],
    $credit,
    $level_id,
    $fac_id,
    $dep_id,
    $d['semester'],
    $d['status'],
    $id
  );

  if (!$stmt->execute()) {
    $err = $db->errno === 1062 ? 'Course code already exists' : ('DB error: ' . $db->error);
    $stmt->close();
    return [false, $err];
  }
  $affected = $stmt->affected_rows;
  $stmt->close();
  return [true, $affected];
}

/**
 * Delete a course
 */
function course_delete(mysqli $db, string $course_id): array {
  $id = (int)$course_id;
  $sql = "DELETE FROM courses WHERE course_id = ?";
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

// Handle POST actions
if ($mysqli && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $op = $_POST['op'] ?? '';
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    $flashError = 'Invalid CSRF token.';
  } else {
    if ($op === 'create' || $op === 'update') {
      [$data, $errs] = validate_course_from_post($_POST);
      if (!empty($errs)) {
        $formErrors = $errs;
        $old = $data;
        $openModal = true;
        $modalMode = $op;
      } else {
        if ($op === 'create') {
          [$ok, $result] = course_create($mysqli, $data);
          if ($ok) {
            $_SESSION['flash_success'] = 'Course created successfully.';
            header('Location: manage-courses.php');
            exit;
          } else {
            $flashError = $result;
            $formErrors = ['_general' => $result];
            $old = $data;
            $openModal = true;
            $modalMode = 'create';
          }
        } else { // update
          [$ok, $result] = course_update($mysqli, $data);
          if ($ok) {
            $_SESSION['flash_success'] = 'Course updated successfully.';
            header('Location: manage-courses.php');
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
      $course_id = $_POST['course_id'] ?? '';
      if ($course_id === '' || !ctype_digit((string)$course_id)) {
        $flashError = 'Invalid course ID for deletion.';
      } else {
        [$ok, $result] = course_delete($mysqli, $course_id);
        if ($ok) {
          $_SESSION['flash_success'] = 'Course deleted successfully.';
          header('Location: manage-courses.php');
          exit;
        } else {
          $flashError = $result;
        }
      }
    }
  }
}

// Load courses for rendering
$courses = $mysqli ? courses_all($mysqli) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Courses - Marking & Grading System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Add and manage courses" />
  <meta name="keywords" content="courses, subjects">
  <meta name="author" content="Marking & Grading System" />
  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

  <!-- Theme CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet">

  <style>
    :root {
      --primary:#667eea; --primary-2:#5a67d8; --light:#f8fafc; --muted:#718096;
      --card-radius:12px; --shadow:0 10px 25px rgba(0,0,0,.08);
    }
    body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Segoe UI Emoji"; }
    .page-wrap { background: var(--light); min-height: calc(100vh - 70px); border-radius: 30px 0 0 0; }
    .page-header {
      background: linear-gradient(135deg, rgba(102,126,234,.95), rgba(118,75,162,.95));
      color: #fff; padding: 28px 0; margin-bottom: 18px; border-radius: 0 0 24px 24px;
      box-shadow: 0 20px 30px rgba(0,0,0,.08);
    }
    .page-header h1 { font-size: 1.8rem; font-weight: 800; margin: 0; }
    .page-header p { margin: 4px 0 0; opacity: .95; }

    .actions-bar {
      background:#fff; border-radius: var(--card-radius); box-shadow: var(--shadow);
      padding: 14px 16px; display:flex; justify-content: space-between; align-items:center; gap: 10px; margin: 12px 0 18px;
    }
    .btn { border:0; border-radius:10px; padding:.7rem 1rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; }
    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--primary-2)); color:#fff;
      box-shadow: 0 10px 24px rgba(102,126,234,.28);
    }
    .btn-muted { background:#edf2f7; color:#2d3748; }
    .btn-icon { padding:.45rem .65rem; border-radius:8px; }

    .card { background:#fff; border-radius: var(--card-radius); box-shadow: var(--shadow); overflow:hidden; }
    .card-header { display:flex; justify-content: space-between; align-items:center; padding: 14px 16px; border-bottom: 1px solid #edf2f7; }
    .card-title { font-weight:800; color:#2d3748; }
    .card-body { padding: 14px 16px 18px; }

    table { width:100%; border-collapse: collapse; font-size:.95rem; }
    th, td { padding:.8rem .7rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    thead th { background:#f8fafc; color:#4a5568; font-weight:800; }
    tbody tr:hover { background:#fafcff; }
    .badge {
      display:inline-block; padding:.18rem .5rem; border-radius:999px; font-weight:700; font-size:.78rem;
      background:#edf2f7; color:#2d3748;
    }
    .badge.green { background: rgba(72,187,120,.12); color:#2f855a; }
    .badge.gray { background:#edf2f7; color:#2d3748; }

    /* Modal */
    .modal-overlay { position: fixed; inset:0; display:none; place-items:center; background: rgba(2,6,23,.62); backdrop-filter: blur(4px); z-index: 1050; }
    .modal { width:min(720px, 92vw); background:#fff; border-radius: 16px; overflow:hidden; transform: translateY(8px) scale(.985); opacity:.98; transition: transform .18s ease, opacity .18s ease; }
    .modal.show { transform: translateY(0) scale(1); opacity:1; }
    .modal-header { background: linear-gradient(135deg, var(--primary), #764ba2); color:#fff; padding: 14px 16px; display:flex; align-items:center; justify-content:space-between; }
    .modal-title { font-weight:800; }
    .modal-close { width:36px; height:36px; border-radius:10px; border:none; cursor:pointer; background: rgba(255,255,255,.16); color:#fff; }
    .modal-body { padding: 14px 16px; }
    .modal-footer { padding: 14px 16px; border-top:1px solid #edf2f7; display:flex; justify-content:flex-end; gap: 10px; background:#f9fafb; }

    .grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .field { display:flex; flex-direction:column; gap: 6px; }
    .label { font-size:.8rem; color:#4a5568; font-weight:800; text-transform: uppercase; letter-spacing:.04em; }
    .input, .select, .textarea { width:100%; padding:.7rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#f8fafc; outline: none; transition: all .15s ease; font-size:.95rem; }
    .input:focus, .select:focus, .textarea:focus { border-color: var(--primary); background:#fff; box-shadow: 0 0 0 3px rgba(102,126,234,.12); }
    .actions-col { white-space: nowrap; }

    .toolbar { display:flex; gap:6px; flex-wrap: wrap; }
    .small { font-size:.85rem; color: var(--muted); }

    .alert { padding: 12px 14px; border-radius: 10px; margin: 10px 0; font-weight: 600; }
    .alert-success { background: rgba(72,187,120,.12); color:#2f855a; border: 1px solid rgba(72,187,120,.25); }
    .alert-error { background: rgba(245, 101, 101,.12); color:#c53030; border: 1px solid rgba(245,101,101,.25); }

    @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }

    /* FIX: prevent Bootstrap .modal {display:none} from hiding our custom modal */
    .modal-overlay .modal { display: block !important; position: relative !important; max-height: 90vh; overflow: hidden; }
    .modal-overlay { z-index: 2000 !important; }
    .modal-body { max-height: calc(90vh - 120px); overflow-y: auto; }
  </style>
</head>
<body>
  <!-- [ navigation menu ] start -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Navigation</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-home"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="manage-students.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span><span class="pcoded-mtext">Manage Students</span></a></li>
          <li class="nav-item"><a href="manage-lecturers.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span><span class="pcoded-mtext">Manage Lecturers</span></a></li>
          <li class="nav-item"><a href="manage-courses.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Manage Courses</span></a></li>

          <li class="nav-item"><a href="assign-courses.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Assign Courses</span></a></li>
          
          <li class="nav-item"><a href="grading-scale.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Grading Scale</span></a></li>
          <li class="nav-item"><a href="../logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
        </ul>
      </div>
    </div>
  </nav>
  <!-- [ navigation menu ] end -->

  <!-- [ Header ] start -->
  <header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
      <a class="mobile-menu" id="mobile-collapse" href="#!"><span></span></a>
      <a href="#!" class="b-brand"><h3 class="text-primary mb-0">MGS Admin</h3></a>
      <a href="#!" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>
  <!-- [ Header ] end -->

  <!-- [ Main Content ] start -->
  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-header">
        <div class="container-fluid">
          <h1>Manage Courses</h1>
          <p class="small text-secondary">Manage courses and assign them to lecturers.</p>Add new courses and manage existing ones. Assignment to lecturers happens on a separate page.</p>
        </div>
      </div>

      <div class="page-wrap">
        <div class="container-fluid" style="max-width: 1400px; padding: 18px;">
          <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= h($flashSuccess) ?></div>
          <?php endif; ?>
          <?php if ($flashError): ?>
            <div class="alert alert-error"><?= h($flashError) ?></div>
          <?php endif; ?>

          <!-- Actions Bar -->
          <div class="actions-bar">
            <div class="toolbar">
              <button class="btn btn-primary" id="openAddCourse" type="button"><i class="fas fa-plus"></i> Add Course</button>
            </div>
            <div class="small">Tip: Keep course codes unique (e.g., CSC101, MTH201)</div>
          </div>

          <!-- Courses Table -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">Registered Courses</div>
              <div class="small" id="courseCount"><?= (int)count($courses) ?> total</div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table aria-label="Courses table">
                  <thead>
                    <tr>
                      <th style="min-width:120px;">Course Code</th>
                      <th>Title</th>
                      <th style="width:100px;">Units</th>
                      <th>Faculty</th>
                      <th>Department</th>
                      <th style="width:110px;">Level</th>
                      <th style="width:120px;">Semester</th>
                      <th style="width:110px;">Status</th>
                      <th class="actions-col" style="width:180px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="coursesTbody">
                    <?php if (empty($courses)): ?>
                      <tr><td colspan="9" class="small">No courses found. Click "Add Course" to create one.</td></tr>
                    <?php else: ?>
                      <?php foreach ($courses as $c): ?>
                        <tr>
                          <td><strong><?= h($c['course_code']) ?></strong></td>
                          <td><?= h($c['course_name']) ?></td>
                          <td><?= h((string)$c['credit_unit']) ?></td>
                          <td><?= h($c['faculty_name']) ?></td>
                          <td><?= h($c['dept_name']) ?></td>
                          <td><span class="badge"><?= h((string)$c['level']) ?></span></td>
                          <td><?= h($c['semester']) ?></td>
                          <td><span class="badge <?= ($c['status'] === 'Active' ? 'green' : 'gray') ?>"><?= h($c['status']) ?></span></td>
                          <td class="actions-col">
                            <button
                              type="button"
                              class="btn btn-muted btn-icon js-edit-course"
                              title="Edit"
                              data-course='<?= h(json_encode([
                                'course_id'     => (string)$c['course_id'],
                                'course_code'   => (string)$c['course_code'],
                                'course_name'   => (string)$c['course_name'],
                                'credit_unit'   => (string)$c['credit_unit'],
                                'level'         => (string)$c['level'],
                                'faculty_name'  => (string)$c['faculty_name'],
                                'dept_name'     => (string)$c['dept_name'],
                                'semester'      => (string)$c['semester'],
                                'status'        => (string)$c['status'],
                              ], JSON_UNESCAPED_UNICODE)) ?>'
                            >
                              <i class="fas fa-pen-to-square"></i>
                            </button>

                            <form method="post" style="display:inline" onsubmit="return confirm('Delete course <?= h($c['course_code']) ?>? This action cannot be undone.');">
                              <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                              <input type="hidden" name="op" value="delete">
                              <input type="hidden" name="course_id" value="<?= h((string)$c['course_id']) ?>">
                              <button type="submit" class="btn btn-muted btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- End Courses Table -->
        </div>
      </div>
    </div>
  </div>
  <!-- [ Main Content ] end -->

  <!-- Add/Edit Course Modal -->
  <div class="modal-overlay" id="courseOverlay" aria-hidden="true">
    <div class="modal" id="courseModal" role="dialog" aria-modal="true" aria-labelledby="courseModalTitle" tabindex="-1">
      <div class="modal-header">
        <div class="modal-title" id="courseModalTitle">Add Course</div>
        <button class="modal-close" id="courseClose" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>

      <form id="courseForm" method="post" action="" novalidate>
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="op" id="opField" value="<?= $modalMode === 'update' ? 'update' : 'create' ?>">
        <input type="hidden" name="course_id" id="editingCourseId" value="<?= h($old['course_id'] ?? '') ?>">

        <div class="modal-body">
          <?php if (!empty($formErrors)): ?>
            <div class="alert alert-error" style="margin-top:0;">
              <?php foreach ($formErrors as $msg): ?>
                <div><?= h($msg) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="grid">
            <div class="field">
              <label class="label" for="courseCode">Course Code</label>
              <input class="input" name="course_code" id="courseCode" type="text" placeholder="e.g., CSC101" maxlength="20" required value="<?= h($old['course_code'] ?? '') ?>">
            </div>
            <div class="field">
              <label class="label" for="courseTitle">Course Title</label>
              <input class="input" name="course_name" id="courseTitle" type="text" placeholder="e.g., Introduction to Programming" required value="<?= h($old['course_name'] ?? '') ?>">
            </div>
            <div class="field">
              <label class="label" for="creditUnit">Credit Unit</label>
              <input class="input" name="credit_unit" id="creditUnit" type="number" min="0" step="0.5" placeholder="e.g., 3" required value="<?= h(isset($old['credit_unit']) ? (string)$old['credit_unit'] : '') ?>">
            </div>
            <div class="field">
              <label class="label" for="levelId">Level</label>
              <select class="select" name="level" id="levelId" required>
                <option value="">Select level</option>
                <?php foreach (['100','200','300','400','500'] as $lvl): ?>
                  <option value="<?= $lvl ?>" <?= (isset($old['level']) && $old['level'] === $lvl) ? 'selected' : '' ?>><?= $lvl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="label" for="facultyName">Faculty</label>
              <input class="input" name="faculty_name" id="facultyName" type="text" placeholder="e.g., Science" required value="<?= h($old['faculty_name'] ?? '') ?>">
            </div>
            <div class="field">
              <label class="label" for="deptName">Department</label>
              <input class="input" name="dept_name" id="deptName" type="text" placeholder="e.g., Computer Science" required value="<?= h($old['dept_name'] ?? '') ?>">
            </div>
            <div class="field">
              <label class="label" for="semester">Semester</label>
              <select class="select" name="semester" id="semester" required>
                <option value="">Select semester</option>
                <option value="First"  <?= (isset($old['semester']) && $old['semester'] === 'First')  ? 'selected' : '' ?>>First</option>
                <option value="Second" <?= (isset($old['semester']) && $old['semester'] === 'Second') ? 'selected' : '' ?>>Second</option>
              </select>
            </div>
            <div class="field">
              <label class="label" for="status">Status</label>
              <select class="select" name="status" id="status" required>
                <option value="">Select status</option>
                <option value="Active"   <?= (isset($old['status']) && $old['status'] === 'Active')   ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= (isset($old['status']) && $old['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-muted" id="courseCancel" type="button">Cancel</button>
          <button class="btn btn-primary" id="courseSave" type="submit"><i class="fas fa-floppy-disk"></i> Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/vendor-all.min.js"></script>
  <script src="assets/js/plugins/bootstrap.min.js"></script>
  <script src="assets/js/ripple.js"></script>
  <script src="assets/js/pcoded.min.js"></script>

  <script>
    (function(){
      const openBtn = document.getElementById('openAddCourse');
      const overlay = document.getElementById('courseOverlay');
      const modal   = document.getElementById('courseModal');
      const closeBtn = document.getElementById('courseClose');
      const cancelBtn = document.getElementById('courseCancel');
      const titleEl = document.getElementById('courseModalTitle');
      const form = document.getElementById('courseForm');
      const opField = document.getElementById('opField');

      // Inputs
      const input = {
        id: document.getElementById('editingCourseId'),
        code: document.getElementById('courseCode'),
        name: document.getElementById('courseTitle'),
        unit: document.getElementById('creditUnit'),
        level: document.getElementById('levelId'),
        faculty: document.getElementById('facultyName'),
        dept: document.getElementById('deptName'),
        semester: document.getElementById('semester'),
        status: document.getElementById('status'),
      };

      function showModal() {
        if (!overlay || !modal) return;
        overlay.style.display = 'grid';
        overlay.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(()=> { if (modal) modal.classList.add('show'); });
        setTimeout(()=> { if (input.code) input.code.focus(); }, 120);
      }
      function hideModal() {
        if (!overlay || !modal) return;
        modal.classList.remove('show');
        setTimeout(()=>{
          overlay.style.display = 'none';
          overlay.setAttribute('aria-hidden','true');
        }, 150);
      }
      function setMode(mode, data) {
        if (!opField || !titleEl) return;
        opField.value = mode === 'update' ? 'update' : 'create';
        titleEl.textContent = mode === 'update' ? 'Edit Course' : 'Add Course';

        if (data) {
          input.id.value = data.course_id || '';
          input.code.value = data.course_code || '';
          input.name.value = data.course_name || '';
          input.unit.value = data.credit_unit || '';
          input.level.value = data.level || '';
          input.faculty.value = data.faculty_name || '';
          input.dept.value = data.dept_name || '';
          input.semester.value = data.semester || '';
          input.status.value = data.status || '';
        } else if (form) {
          form.reset();
          input.id.value = '';
        }
      }

      if (openBtn) openBtn.addEventListener('click', () => { setMode('create'); showModal(); });

      document.querySelectorAll('.js-edit-course').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          try {
            const data = JSON.parse(btn.getAttribute('data-course') || '{}');
            setMode('update', data);
            showModal();
          } catch (e) {
            console.error('Bad course data', e);
          }
        });
      });

      if (closeBtn) closeBtn.addEventListener('click', hideModal);
      if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
      if (overlay) overlay.addEventListener('click', (e)=>{ if (e.target === overlay) hideModal(); });
      window.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && overlay && overlay.style.display === 'grid') hideModal(); });

      // If server-side validation failed, auto-open modal with old values.
      <?php if (!empty($openModal)): ?>
        setMode('<?= $modalMode === 'update' ? 'update' : 'create' ?>', <?= json_encode($old ?? [], JSON_UNESCAPED_UNICODE) ?>);
        showModal();
      <?php endif; ?>
    })();
  </script>
</body>
</html>