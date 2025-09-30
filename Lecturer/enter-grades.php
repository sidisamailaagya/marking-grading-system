<?php

declare(strict_types=1);
session_start();

// Authentication and database connection
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/connect.php';

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'lecturer') {
  header('Location: ../login.php');
  exit;
}

/**
 * Find a mysqli connection
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

// Get database connection
$mysqli = db_connect_auto();
if (!$mysqli) {
  die('Database connection failed. Please check your configuration.');
}

// Get lecturer ID from session
$lecturer_id = (int)$_SESSION['uid'];

/**
 * Calculate letter grade based on percentage
 */
function calculate_grade(float $percentage): string
{
  if ($percentage >= 70) return 'A';
  if ($percentage >= 60) return 'B';
  if ($percentage >= 50) return 'C';
  if ($percentage >= 45) return 'D';
  if ($percentage >= 40) return 'E';
  return 'F';
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');

  if ($_GET['action'] === 'get_courses') {
    // Get lecturer's assigned courses
    $sql = "SELECT DISTINCT
                c.course_id,
                c.course_code,
                c.course_name
            FROM course_assignments ca
            INNER JOIN courses c ON ca.course_id = c.course_id
            WHERE ca.lecturer_id = ?
            ORDER BY c.course_code";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      echo json_encode(['error' => 'Failed to prepare courses query: ' . $mysqli->error]);
      exit;
    }

    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
      $courses[] = [
        'code' => $row['course_code'],
        'title' => $row['course_name']
      ];
    }
    $stmt->close();

    echo json_encode($courses);
    exit;
  }

  if ($_GET['action'] === 'get_students' && isset($_GET['course'])) {
    $course_code = $_GET['course'];

    try {
      // First, get students for this course
      $sql = "SELECT 
                  s.student_id,
                  s.matric_no,
                  s.full_name
              FROM course_assignments ca
              INNER JOIN courses c ON ca.course_id = c.course_id
              INNER JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
              WHERE ca.lecturer_id = ? AND c.course_code = ?
              ORDER BY s.full_name";

      $stmt = $mysqli->prepare($sql);
      if (!$stmt) {
        throw new Exception('Failed to prepare students query: ' . $mysqli->error);
      }

      $stmt->bind_param('is', $lecturer_id, $course_code);
      $stmt->execute();
      $result = $stmt->get_result();

      $students = [];
      while ($row = $result->fetch_assoc()) {
        $student_data = [
          'student_id' => $row['student_id'],
          'name' => $row['full_name'],
          'matric' => $row['matric_no'],
          'assignment_score' => 0,
          'test_score' => 0,
          'project_score' => 0,
          'exam_score' => 0,
          'discipline_score' => 0,
          'punctuality_score' => 0,
          'teamwork_score' => 0,
          'remarks' => '',
          'final_score' => 0,
          'grade_letter' => 'F'
        ];

        // Now try to get existing grades for this student
        $grade_sql = "SELECT 
                          assignment_score, test_score, project_score, exam_score,
                          discipline_score, punctuality_score, teamwork_score,
                          remarks, final_score, grade_letter
                        FROM results r
                        INNER JOIN courses c ON r.course_id = c.course_id
                        WHERE r.student_id = ? AND c.course_code = ?";

        $grade_stmt = $mysqli->prepare($grade_sql);
        if ($grade_stmt) {
          $grade_stmt->bind_param('is', $row['student_id'], $course_code);
          $grade_stmt->execute();
          $grade_result = $grade_stmt->get_result();
          $grade_row = $grade_result->fetch_assoc();

          if ($grade_row) {
            $student_data['assignment_score'] = $grade_row['assignment_score'] ?? 0;
            $student_data['test_score'] = $grade_row['test_score'] ?? 0;
            $student_data['project_score'] = $grade_row['project_score'] ?? 0;
            $student_data['exam_score'] = $grade_row['exam_score'] ?? 0;
            $student_data['discipline_score'] = $grade_row['discipline_score'] ?? 0;
            $student_data['punctuality_score'] = $grade_row['punctuality_score'] ?? 0;
            $student_data['teamwork_score'] = $grade_row['teamwork_score'] ?? 0;
            $student_data['remarks'] = $grade_row['remarks'] ?? '';
            $student_data['final_score'] = $grade_row['final_score'] ?? 0;
            $student_data['grade_letter'] = $grade_row['grade_letter'] ?? 'F';
          }

          $grade_stmt->close();
        }

        $students[] = $student_data;
      }
      $stmt->close();

      echo json_encode($students);
    } catch (Exception $e) {
      echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
  }

  if ($_GET['action'] === 'save_grades' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['course_code']) || !isset($input['grades'])) {
      echo json_encode(['success' => false, 'message' => 'Invalid input data']);
      exit;
    }

    $course_code = $input['course_code'];
    $grades = $input['grades'];

    try {
      // Get course_id
      $course_sql = "SELECT course_id FROM courses WHERE course_code = ?";
      $course_stmt = $mysqli->prepare($course_sql);
      if (!$course_stmt) {
        throw new Exception('Failed to prepare course query: ' . $mysqli->error);
      }

      $course_stmt->bind_param('s', $course_code);
      $course_stmt->execute();
      $course_result = $course_stmt->get_result();
      $course_row = $course_result->fetch_assoc();
      $course_stmt->close();

      if (!$course_row) {
        throw new Exception('Course not found');
      }

      $course_id = $course_row['course_id'];
      $saved_count = 0;

      // Begin transaction
      $mysqli->begin_transaction();

      foreach ($grades as $grade) {
        $student_id = (int)$grade['student_id'];

        // Validate and clamp scores to their maximum limits
        $assignment_score = max(0, min(20, (float)$grade['assignment_score']));
        $test_score = max(0, min(20, (float)$grade['test_score']));
        $project_score = max(0, min(20, (float)$grade['project_score']));
        $exam_score = max(0, min(40, (float)$grade['exam_score']));
        $discipline_score = max(0, min(100, (float)$grade['discipline_score']));
        $punctuality_score = max(0, min(100, (float)$grade['punctuality_score']));
        $teamwork_score = max(0, min(100, (float)$grade['teamwork_score']));

        $remarks = $grade['remarks'] ?? '';
        $final_score = (float)$grade['final_score'];
        $grade_letter = calculate_grade($final_score);

        // Check if result already exists
        $check_sql = "SELECT result_id FROM results WHERE student_id = ? AND course_id = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param('ii', $student_id, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $existing = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
          // Update existing record
          $update_sql = "UPDATE results SET 
                        assignment_score = ?, test_score = ?, project_score = ?, exam_score = ?,
                        discipline_score = ?, punctuality_score = ?, teamwork_score = ?,
                        remarks = ?, final_score = ?, grade_letter = ?, updated_at = NOW()
                        WHERE student_id = ? AND course_id = ?";
          $update_stmt = $mysqli->prepare($update_sql);
          $update_stmt->bind_param(
            'dddddddssdii',
            $assignment_score,
            $test_score,
            $project_score,
            $exam_score,
            $discipline_score,
            $punctuality_score,
            $teamwork_score,
            $remarks,
            $final_score,
            $grade_letter,
            $student_id,
            $course_id
          );
          $update_stmt->execute();
          $update_stmt->close();
        } else {
          // Insert new record
          $insert_sql = "INSERT INTO results 
                        (student_id, course_id, assignment_score, test_score, project_score, exam_score,
                         discipline_score, punctuality_score, teamwork_score, remarks, final_score, grade_letter, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
          $insert_stmt = $mysqli->prepare($insert_sql);
          $insert_stmt->bind_param(
            'iidddddddsds',
            $student_id,
            $course_id,
            $assignment_score,
            $test_score,
            $project_score,
            $exam_score,
            $discipline_score,
            $punctuality_score,
            $teamwork_score,
            $remarks,
            $final_score,
            $grade_letter
          );
          $insert_stmt->execute();
          $insert_stmt->close();
        }

        $saved_count++;
      }

      // Commit transaction
      $mysqli->commit();
      echo json_encode(['success' => true, 'message' => "Successfully saved grades for {$saved_count} students"]);
    } catch (Exception $e) {
      // Rollback transaction
      $mysqli->rollback();
      echo json_encode(['success' => false, 'message' => 'Error saving grades: ' . $e->getMessage()]);
    }

    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Enter Grades - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Enter academic and behavioral scores, auto-calc final grade" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      --radius: 14px;
      --transition: all .25s ease;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }

    .pcoded-navbar {
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
      border-right: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.12);
    }

    .pcoded-header {
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
    }

    .page-hero {
      background: linear-gradient(135deg, rgba(102, 126, 234, .92), rgba(118, 75, 162, .92));
      color: #fff;
      padding: 2rem 0;
      border-radius: 0 0 26px 26px;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .page-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      opacity: .15;
      background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent);
    }

    .container {
      padding: 0 1.25rem;
    }

    .main {
      max-width: 1400px;
      margin: 0 auto;
      padding: 1.25rem;
    }

    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      padding: 1.25rem;
    }

    .card:hover {
      box-shadow: var(--card-shadow-hover);
    }

    .toolbar {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .select,
    .input {
      padding: .65rem .8rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      transition: var(--transition);
    }

    .select:focus,
    .input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .input.score:invalid,
    .input.score.error {
      border-color: var(--danger-color);
      box-shadow: 0 0 0 3px rgba(245, 101, 101, 0.12);
    }

    .input.score.warning {
      border-color: var(--warning-color);
      box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.12);
    }

    .btn {
      padding: .68rem 1rem;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea, #5a67d8);
      color: #fff;
    }

    .btn-outline {
      background: #fff;
      color: #4a5568;
      border: 1px solid #e2e8f0;
    }

    .btn-success {
      background: linear-gradient(135deg, #48bb78, #38a169);
      color: #fff;
    }

    .controls {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      align-items: center;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: .65rem;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
    }

    th {
      background: #f8fafc;
      color: #475569;
      font-weight: 800;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    .center {
      text-align: center;
    }

    .small {
      font-size: .85rem;
      color: #718096;
    }

    .badge {
      padding: .18rem .5rem;
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 800;
    }

    .badge.good {
      background: rgba(72, 187, 120, .12);
      color: #2f855a;
    }

    .badge.warn {
      background: rgba(237, 137, 54, .12);
      color: #9c4221;
    }

    .badge.bad {
      background: rgba(245, 101, 101, .12);
      color: #9b2c2c;
    }

    .right {
      text-align: right;
    }

    .loading {
      text-align: center;
      padding: 2rem;
      color: #718096;
    }

    .alert {
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      animation: slideIn 0.3s ease-out;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .score-tooltip {
      position: relative;
    }

    .score-tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #333;
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      z-index: 1000;
      opacity: 0.9;
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Lecturer Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-cog"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
          <li class="nav-item"><a href="../logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
      <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
      <a href="#" class="b-brand">
        <h3 class="text-primary mb-0">Lecturer Portal</h3>
      </a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>Enter Grades</h1>
          <p>Input academic and behavioral scores; the system auto-calculates final grade</p>
        </div>
      </div>

      <div class="main container">
        <div id="alertContainer"></div>

        <div class="card" style="margin-bottom:1rem;">
          <div class="toolbar">
            <div class="small">Course:</div>
            <select id="courseSelect" class="select">
              <option value="">Loading courses...</option>
            </select>
            <div class="small">Weights:</div>
            <div class="small">Assignments 20% · Tests 20% · Project 20% · Exam 40%</div>
            <div class="small">Behavior factor:</div>
            <select id="behaviorWeight" class="select">
              <option value="0">0% (ignore)</option>
              <option value="5">5%</option>
              <option value="10" selected>10%</option>
            </select>
            <button class="btn btn-outline" onclick="fillZeros()"><i class="fas fa-eraser"></i> Zero All</button>
            <button class="btn btn-success" onclick="saveAll()" id="saveBtn"><i class="fas fa-save"></i> Save All</button>
          </div>
        </div>

        <div class="card" style="overflow:auto;">
          <div id="tableContainer">
            <div class="loading"><i class="fas fa-spinner fa-spin"></i> Select a course to view students</div>
          </div>
          <div class="small" style="margin-top:.5rem;">Note: Final = Academics (A20+T20+P20+E40) adjusted by behavior weight. Grade scale: A≥70, B≥60, C≥50, D≥45, E≥40, F&lt;40.</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let courses = [];
    let currentStudents = [];

    function qparam(key) {
      const u = new URL(window.location.href);
      return u.searchParams.get(key) || '';
    }

    async function loadCourses() {
      try {
        const response = await fetch('?action=get_courses');
        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        courses = data;

        const sel = document.getElementById('courseSelect');
        sel.innerHTML = '<option value="">Select a course...</option>';

        courses.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.code;
          opt.textContent = `${c.code} — ${c.title}`;
          sel.appendChild(opt);
        });

        // Pre-select course from URL parameter
        const q = qparam('course');
        if (q && courses.find(c => c.code === q)) {
          sel.value = q;
          loadStudents(q);
        }

      } catch (error) {
        console.error('Error loading courses:', error);
        showAlert('Error loading courses: ' + error.message, 'error');
      }
    }

    async function loadStudents(courseCode) {
      if (!courseCode) {
        document.getElementById('tableContainer').innerHTML = '<div class="loading">Select a course to view students</div>';
        return;
      }

      document.getElementById('tableContainer').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading students...</div>';

      try {
        const response = await fetch(`?action=get_students&course=${encodeURIComponent(courseCode)}`);
        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        currentStudents = data;
        renderTable();
      } catch (error) {
        console.error('Error loading students:', error);
        document.getElementById('tableContainer').innerHTML = '<div class="loading">Error loading students: ' + error.message + '</div>';
      }
    }

    function inputCell(cls, max, value = 0) {
      const tooltip = `Max: ${max} points`;
      return `<input type="number" class="input score ${cls} score-tooltip" min="0" max="${max}" step="0.5" value="${value}" style="width:90px;" data-max="${max}" data-tooltip="${tooltip}">`;
    }

    function renderTable() {
      if (currentStudents.length === 0) {
        document.getElementById('tableContainer').innerHTML = '<div class="loading">No students enrolled in this course.</div>';
        return;
      }

      const tableHtml = `
        <table id="gradesTable">
          <thead>
            <tr>
              <th>Student</th>
              <th>Matric No</th>
              <th class="center">Assign. (20)</th>
              <th class="center">Test (20)</th>
              <th class="center">Project (20)</th>
              <th class="center">Exam (40)</th>
              <th class="center">Discipline</th>
              <th class="center">Punctuality</th>
              <th class="center">Teamwork</th>
              <th>Remarks</th>
              <th class="center">Final %</th>
              <th class="center">Grade</th>
            </tr>
          </thead>
          <tbody id="gradesBody"></tbody>
        </table>
      `;

      document.getElementById('tableContainer').innerHTML = tableHtml;

      const body = document.getElementById('gradesBody');
      body.innerHTML = '';

      currentStudents.forEach(s => {
        const tr = document.createElement('tr');
        tr.dataset.studentId = s.student_id;
        tr.innerHTML = `
          <td>${s.name}</td>
          <td>${s.matric}</td>
          <td class="center">${inputCell('a', 20, s.assignment_score)}</td>
          <td class="center">${inputCell('t', 20, s.test_score)}</td>
          <td class="center">${inputCell('p', 20, s.project_score)}</td>
          <td class="center">${inputCell('e', 40, s.exam_score)}</td>
          <td class="center">${inputCell('bd', 100, s.discipline_score)}</td>
          <td class="center">${inputCell('bp', 100, s.punctuality_score)}</td>
          <td class="center">${inputCell('bt', 100, s.teamwork_score)}</td>
          <td><input type="text" class="input remark" placeholder="Optional remarks..." value="${s.remarks}" style="min-width:220px;"></td>
          <td class="center final">${s.final_score}</td>
          <td class="center grade"><span class="badge ${getGradeBadgeClass(s.grade_letter)}">${s.grade_letter}</span></td>`;
        body.appendChild(tr);
      });

      attachRecalc();
      recalcAll();
    }

    function attachRecalc() {
      document.querySelectorAll('#gradesBody .score').forEach(inp => {
        inp.addEventListener('input', function() {
          validateInput(this);
          recalcAll();
        });

        inp.addEventListener('blur', function() {
          validateInput(this);
          recalcAll();
        });
      });

      document.getElementById('behaviorWeight').addEventListener('change', recalcAll);
    }

    function validateInput(input) {
      const maxVal = parseFloat(input.getAttribute('data-max'));
      const currentVal = parseFloat(input.value);

      // Remove previous validation classes
      input.classList.remove('error', 'warning');

      if (isNaN(currentVal) || currentVal < 0) {
        input.value = 0;
        input.classList.add('warning');
      } else if (currentVal > maxVal) {
        input.value = maxVal;
        input.classList.add('error');
        showAlert(`Score cannot exceed ${maxVal} points`, 'error');
      }
    }

    function clamp(v, min, max) {
      const num = parseFloat(v) || 0;
      return Math.max(min, Math.min(max, num));
    }

    function recalcAll() {
      const bWeight = Number(document.getElementById('behaviorWeight').value) || 0;

      document.querySelectorAll('#gradesBody tr').forEach(tr => {
        const get = sel => {
          const input = tr.querySelector(sel);
          return input ? parseFloat(input.value) || 0 : 0;
        };

        // Get scores with proper clamping to their specific maximums
        const a = clamp(get('.a'), 0, 20); // Assignment max 20
        const t = clamp(get('.t'), 0, 20); // Test max 20
        const p = clamp(get('.p'), 0, 20); // Project max 20
        const e = clamp(get('.e'), 0, 40); // Exam max 40
        const bd = clamp(get('.bd'), 0, 100); // Discipline max 100
        const bp = clamp(get('.bp'), 0, 100); // Punctuality max 100
        const bt = clamp(get('.bt'), 0, 100); // Teamwork max 100

        // Update the input values if they were clamped
        const aInput = tr.querySelector('.a');
        const tInput = tr.querySelector('.t');
        const pInput = tr.querySelector('.p');
        const eInput = tr.querySelector('.e');
        const bdInput = tr.querySelector('.bd');
        const bpInput = tr.querySelector('.bp');
        const btInput = tr.querySelector('.bt');

        if (aInput && parseFloat(aInput.value) !== a) aInput.value = a;
        if (tInput && parseFloat(tInput.value) !== t) tInput.value = t;
        if (pInput && parseFloat(pInput.value) !== p) pInput.value = p;
        if (eInput && parseFloat(eInput.value) !== e) eInput.value = e;
        if (bdInput && parseFloat(bdInput.value) !== bd) bdInput.value = bd;
        if (bpInput && parseFloat(bpInput.value) !== bp) bpInput.value = bp;
        if (btInput && parseFloat(btInput.value) !== bt) btInput.value = bt;

        const academic = a + t + p + e; // out of 100
        const behaviorAvg = (bd + bp + bt) / 3; // 0..100
        const final = Math.round(academic * (100 - bWeight) / 100 + behaviorAvg * (bWeight / 100));
        const grade = gradeLetter(final);

        tr.querySelector('.final').textContent = final;
        const gcell = tr.querySelector('.grade');
        gcell.innerHTML = gradeBadge(grade);
      });
    }

    function gradeLetter(x) {
      if (x >= 70) return 'A';
      if (x >= 60) return 'B';
      if (x >= 50) return 'C';
      if (x >= 45) return 'D';
      if (x >= 40) return 'E';
      return 'F';
    }

    function getGradeBadgeClass(grade) {
      if (grade === 'A' || grade === 'B') return 'good';
      if (grade === 'C' || grade === 'D') return 'warn';
      return 'bad';
    }

    function gradeBadge(g) {
      const cls = getGradeBadgeClass(g);
      return `<span class="badge ${cls}">${g}</span>`;
    }

    function fillZeros() {
      document.querySelectorAll('#gradesBody .score').forEach(i => {
        i.value = 0;
        i.classList.remove('error', 'warning');
      });
      recalcAll();
    }

    async function saveAll() {
      const courseCode = document.getElementById('courseSelect').value;
      if (!courseCode) {
        showAlert('Please select a course first.', 'error');
        return;
      }

      const saveBtn = document.getElementById('saveBtn');
      const originalText = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      saveBtn.disabled = true;

      try {
        const grades = [];
        document.querySelectorAll('#gradesBody tr').forEach(tr => {
          const studentId = tr.dataset.studentId;
          const get = sel => Number(tr.querySelector(sel)?.value) || 0;
          const getText = sel => tr.querySelector(sel)?.value || '';

          grades.push({
            student_id: studentId,
            assignment_score: get('.a'),
            test_score: get('.t'),
            project_score: get('.p'),
            exam_score: get('.e'),
            discipline_score: get('.bd'),
            punctuality_score: get('.bp'),
            teamwork_score: get('.bt'),
            remarks: getText('.remark'),
            final_score: Number(tr.querySelector('.final').textContent)
          });
        });

        const response = await fetch('?action=save_grades', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            course_code: courseCode,
            grades: grades
          })
        });

        const result = await response.json();

        if (result.success) {
          showAlert(result.message, 'success');
        } else {
          showAlert(result.message, 'error');
        }

      } catch (error) {
        console.error('Error saving grades:', error);
        showAlert('Error saving grades. Please try again.', 'error');
      } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
      }
    }

    function showAlert(message, type) {
      const container = document.getElementById('alertContainer');
      const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

      const alert = document.createElement('div');
      alert.className = `alert ${alertClass}`;
      alert.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> ${message}</span>
          <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>
      `;

      container.innerHTML = '';
      container.appendChild(alert);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        if (alert.parentElement) {
          alert.remove();
        }
      }, 5000);
    }

    // Event listeners
    document.getElementById('courseSelect').addEventListener('change', function() {
      loadStudents(this.value);
    });

    // Initialize on page load
    window.addEventListener('load', () => {
      loadCourses();
    });
  </script>
</body>

</html>
</qodoArtifact>