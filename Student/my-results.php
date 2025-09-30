<?php

declare(strict_types=1);
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Check if user is logged in and is a student
if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'student') {
  header('Location: ../login.php');
  exit;
}

// Simple database connection
try {
  require_once __DIR__ . '/../includes/connect.php';
} catch (Exception $e) {
  if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
  }
  die('Database connection failed: ' . $e->getMessage());
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
  if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
  }
  die('Database connection failed. Please check your configuration.');
}

// Get student ID from session
$student_id = (int)$_SESSION['uid'];

/**
 * Calculate grade letter based on final score (5-point system)
 */
function calculate_grade_letter(float $score): string
{
  if ($score >= 70) return 'A';
  if ($score >= 60) return 'B';
  if ($score >= 50) return 'C';
  if ($score >= 45) return 'D';
  if ($score >= 40) return 'E';
  return 'F';
}

/**
 * Get student information with real department data
 */
function get_student_info(mysqli $db, int $student_id): ?array
{
  try {
    $sql = "SELECT 
                  s.student_id,
                  s.full_name,
                  s.matric_no,
                  s.level,
                  s.dept_id,
                  COALESCE(d.dept_name, 'Unknown Department') as dept_name,
                  COALESCE(d.faculty_id, 0) as faculty_id,
                  COALESCE(f.faculty_name, 'Unknown Faculty') as faculty_name
              FROM students s
              LEFT JOIN departments d ON s.dept_id = d.dept_id
              LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
              WHERE s.student_id = ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare student info query: " . $db->error);
      return null;
    }

    $stmt->bind_param('i', $student_id);
    if (!$stmt->execute()) {
      error_log("Failed to execute student info query: " . $stmt->error);
      $stmt->close();
      return null;
    }

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    return $student;
  } catch (Exception $e) {
    error_log("Error in get_student_info: " . $e->getMessage());
    return null;
  }
}

/**
 * Get available courses filtered by student's faculty, department, and level
 */
function get_available_courses(mysqli $db, int $faculty_id, int $dept_id, int $level): array
{
  try {
    $sql = "SELECT 
                  c.course_id,
                  c.course_code,
                  c.course_name,
                  COALESCE(c.credit_unit, 3) as credit_unit,
                  COALESCE(c.semester, 'First') as semester,
                  'Current Session' as session_name,
                  COALESCE(l.lecturer_name, 'Not Assigned') as lecturer_name
              FROM courses c
              INNER JOIN course_assignments ca ON c.course_id = ca.course_id
              LEFT JOIN lecturers l ON ca.lecturer_id = l.lecturer_id
              WHERE c.faculty_id = ? 
                AND c.dept_id = ? 
                AND c.level_id = ?
                AND c.status = 'active'
              ORDER BY c.course_code";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare courses query: " . $db->error);
      return [];
    }

    $stmt->bind_param('iii', $faculty_id, $dept_id, $level);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
      $courses[] = [
        'course_id' => (int)$row['course_id'],
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'credit_unit' => (int)$row['credit_unit'],
        'semester' => $row['semester'],
        'session_name' => $row['session_name'],
        'lecturer_name' => $row['lecturer_name'] ?? 'Not Assigned'
      ];
    }
    $stmt->close();
    return $courses;
  } catch (Exception $e) {
    error_log("Error in get_available_courses: " . $e->getMessage());
    return [];
  }
}
/**
 * Get student's results - simplified
 */
function get_student_results(mysqli $db, int $student_id): array
{
  try {
    // Check if results table exists
    $result = $db->query("SHOW TABLES LIKE 'results'");
    if ($result->num_rows === 0) {
      return []; // Return empty if table doesn't exist
    }

    $sql = "SELECT 
                  r.result_id,
                  r.course_id,
                  COALESCE(r.assignment_score, 0) as assignment_score,
                  COALESCE(r.test_score, 0) as test_score,
                  COALESCE(r.project_score, 0) as project_score,
                  COALESCE(r.exam_score, 0) as exam_score,
                  COALESCE(r.discipline_score, 0) as discipline_score,
                  COALESCE(r.punctuality_score, 0) as punctuality_score,
                  COALESCE(r.teamwork_score, 0) as teamwork_score,
                  COALESCE(r.final_score, 0) as final_score,
                  r.grade_letter,
                  r.remarks,
                  r.created_at,
                  c.course_code,
                  c.course_name,
                  COALESCE(c.credit_unit, 3) as credit_unit,
                  COALESCE(c.semester, 'First') as semester,
                  'Current Session' as session_name,
                  COALESCE(l.lecturer_name, 'Unknown Lecturer') as lecturer_name
              FROM results r
              INNER JOIN courses c ON r.course_id = c.course_id
              LEFT JOIN lecturers l ON c.lecturer_id = l.lecturer_id
              WHERE r.student_id = ?
              ORDER BY r.created_at DESC";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare results query: " . $db->error);
      return [];
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
      $final_score = (float)($row['final_score'] ?? 0);
      $calculated_grade = calculate_grade_letter($final_score);

      $results[] = [
        'result_id' => (int)$row['result_id'],
        'course_id' => (int)$row['course_id'],
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'credit_unit' => (int)$row['credit_unit'],
        'semester' => $row['semester'],
        'session_name' => $row['session_name'],
        'assignment_score' => (float)$row['assignment_score'],
        'test_score' => (float)$row['test_score'],
        'project_score' => (float)$row['project_score'],
        'exam_score' => (float)$row['exam_score'],
        'discipline_score' => (float)$row['discipline_score'],
        'punctuality_score' => (float)$row['punctuality_score'],
        'teamwork_score' => (float)$row['teamwork_score'],
        'final_score' => $final_score,
        'grade_letter' => $calculated_grade,
        'remarks' => $row['remarks'],
        'lecturer_name' => $row['lecturer_name'],
        'created_at' => $row['created_at']
      ];
    }
    $stmt->close();
    return $results;
  } catch (Exception $e) {
    error_log("Error in get_student_results: " . $e->getMessage());
    return [];
  }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  // Clean output buffer
  if (ob_get_level()) {
    ob_clean();
  }

  header('Content-Type: application/json');

  try {
    if ($_GET['action'] === 'get_student_data') {
      $student_info = get_student_info($mysqli, $student_id);
      if (!$student_info) {
        echo json_encode(['error' => 'Student not found']);
        exit;
      }

      // Get courses filtered by student's faculty, department, and level
      $available_courses = get_available_courses(
        $mysqli,
        (int)$student_info['faculty_id'],
        (int)$student_info['dept_id'],
        (int)$student_info['level']
      );

      $registrations = []; // Simplified - no registrations for now
      $results = get_student_results($mysqli, $student_id);
      $sessions = [[
        'session_id' => 1,
        'session_name' => 'Current Session',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+1 year')),
        'is_active' => true
      ]];

      echo json_encode([
        'student' => $student_info,
        'available_courses' => $available_courses,
        'registrations' => $registrations,
        'results' => $results,
        'sessions' => $sessions
      ]);
      exit;
    }

    if ($_GET['action'] === 'register_course' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      echo json_encode(['success' => false, 'message' => 'Course registration not available yet']);
      exit;
    }

    if ($_GET['action'] === 'unregister_course' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      echo json_encode(['success' => false, 'message' => 'Course unregistration not available yet']);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
  } catch (Exception $e) {
    error_log("My Results Error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
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
  <title>My Results | Student Portal</title>
  <meta name="description" content="View grades and behavioral breakdown by subject" />
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

    .muted {
      color: #64748b;
      font-weight: 600;
    }

    .input,
    .select {
      padding: .65rem .8rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
    }

    .input:focus,
    .select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .btn {
      padding: .55rem .9rem;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      transition: var(--transition);
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

    .btn-ghost {
      background: transparent;
      color: #4a5568;
      border: 1px dashed #e2e8f0;
    }

    .btn[disabled] {
      opacity: .6;
      cursor: not-allowed;
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

    .badge.info {
      background: rgba(66, 153, 225, .12);
      color: #2b6cb0;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-weight: 800;
      color: #334155;
      margin-bottom: .75rem;
    }

    .section-desc {
      color: #64748b;
      margin-bottom: .5rem;
    }

    .flex {
      display: flex;
      gap: .5rem;
      align-items: center;
      flex-wrap: wrap;
    }

    .right {
      text-align: right;
    }

    .nowrap {
      white-space: nowrap;
    }

    .num {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .empty {
      color: #94a3b8;
      font-style: italic;
      padding: .5rem 0;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
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

    .debug {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
      margin-bottom: 1rem;
      padding: 1rem;
      border-radius: 10px;
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

    @media (min-width: 1100px) {
      .cards-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Student Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-results.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
          <li class="nav-item"><a href="performance-history.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
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
        <h3 class="text-primary mb-0">Student Portal</h3>
      </a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>My Results & Course Information</h1>
          <p>View your academic results and department-specific courses (5-Point Grading System)</p>
        </div>
      </div>

      <div class="main container">
        <div id="alertContainer"></div>

        <div id="mainContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading your courses and results...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let studentData = null;
    let filteredResults = [];

    async function loadStudentData() {
      try {
        console.log('Loading student data...');
        const response = await fetch('?action=get_student_data');

        console.log('Response status:', response.status);

        // Check if response is ok
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get response text first to debug
        const responseText = await response.text();
        console.log('Response text:', responseText);

        // Try to parse as JSON
        let data;
        try {
          data = JSON.parse(responseText);
        } catch (parseError) {
          console.error('JSON Parse Error:', parseError);
          console.error('Response text:', responseText);
          throw new Error('Invalid JSON response from server. Check browser console for details.');
        }

        if (data.error) {
          throw new Error(data.error);
        }

        console.log('Student data loaded:', data);
        studentData = data;
        renderMainContent();
        applyFilters();

      } catch (error) {
        console.error('Error loading student data:', error);
        document.getElementById('mainContent').innerHTML = `
          <div class="debug">
            <h4>Debug Information</h4>
            <p><strong>Error:</strong> ${error.message}</p>
            <p><strong>Check browser console for more details</strong></p>
          </div>
          <div class="loading">Error loading data: ${error.message}</div>
        `;
      }
    }

    function renderMainContent() {
      if (!studentData) return;

      console.log('Rendering main content with data:', studentData);

      const content = `
        <div class="cards-grid">
          <!-- Student Info -->
          <div class="card">
            <div class="section-title"><i class="fas fa-user"></i> Student Information</div>
            <div class="section-desc">Your academic profile and department details</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
              <div><strong>Name:</strong> ${escapeHtml(studentData.student.full_name)}</div>
              <div><strong>Matric No:</strong> ${escapeHtml(studentData.student.matric_no)}</div>
              <div><strong>Level:</strong> ${studentData.student.level}</div>
              <div><strong>Department:</strong> ${escapeHtml(studentData.student.dept_name)}</div>
              <div><strong>Faculty:</strong> ${escapeHtml(studentData.student.faculty_name)}</div>
            </div>
          </div>

          <!-- Available Courses -->
          <div class="card">
            <div class="section-title"><i class="fas fa-book-open"></i> My Department Courses</div>
            <div class="section-desc">Courses for ${escapeHtml(studentData.student.dept_name)} - Level ${studentData.student.level} (${studentData.available_courses.length} courses found)</div>
            <div style="overflow:auto;">
              <table aria-label="Available courses">
                <thead>
                  <tr>
                    <th>Course</th>
                    <th>Lecturer</th>
                    <th>Session</th>
                    <th>Semester</th>
                    <th class="num">Credits</th>
                  </tr>
                </thead>
                <tbody id="availableCoursesBody">
                  <tr><td colspan="5" class="empty">Loading courses...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Results Filter -->
          <div class="card" style="margin-bottom:1rem;">
            <div class="toolbar">
              <div class="muted">Session:</div>
              <select id="sessionFilter" class="select">
                <option value="">All Sessions</option>
                ${studentData.sessions.map(session => `
                  <option value="${escapeHtml(session.session_name)}" ${session.is_active ? 'selected' : ''}>
                    ${escapeHtml(session.session_name)}
                  </option>
                `).join('')}
              </select>
              <div class="muted">Search:</div>
              <input id="searchFilter" class="input" placeholder="Course code or title" />
              <button class="btn btn-outline" onclick="applyFilters()"><i class="fas fa-filter"></i> Filter</button>
              <button class="btn btn-primary" onclick="exportResults()"><i class="fas fa-file-export"></i> Export</button>
            </div>
          </div>

          <!-- Results Table -->
          <div class="card" style="overflow:auto;">
            <div class="section-title"><i class="fas fa-graduation-cap"></i> My Results (5-Point Grading System)</div>
            <div class="section-desc">Your graded courses (${studentData.results.length} results found)</div>
            <table aria-label="My results table">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Session</th>
                  <th>Semester</th>
                  <th class="num">Credits</th>
                  <th class="num">Assign (20)</th>
                  <th class="num">Test (20)</th>
                  <th class="num">Project (20)</th>
                  <th class="num">Exam (40)</th>
                  <th class="num">Discipline</th>
                  <th class="num">Punctuality</th>
                  <th class="num">Teamwork</th>
                  <th>Lecturer Remark</th>
                  <th class="num">Final %</th>
                  <th>Grade</th>
                  <th class="num">Grade Points</th>
                </tr>
              </thead>
              <tbody id="resultsBody">
                <tr><td colspan="15" class="empty">No graded results yet.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      `;

      document.getElementById('mainContent').innerHTML = content;
      renderAvailableCourses();
    }

    function renderAvailableCourses() {
      if (!studentData) return;

      const body = document.getElementById('availableCoursesBody');

      if (studentData.available_courses.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="empty">No courses found for your department and level.</td></tr>';
        return;
      }

      body.innerHTML = '';
      studentData.available_courses.forEach(course => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${escapeHtml(course.course_code)}</strong> — ${escapeHtml(course.course_name)}</td>
          <td>${escapeHtml(course.lecturer_name)}</td>
          <td>${escapeHtml(course.session_name)}</td>
          <td>${escapeHtml(course.semester)}</td>
          <td class="num">${course.credit_unit}</td>
        `;
        body.appendChild(tr);
      });
    }

    function renderResultsTable() {
      const body = document.getElementById('resultsBody');

      if (filteredResults.length === 0) {
        body.innerHTML = '<tr><td colspan="15" class="empty">No graded results match your filters.</td></tr>';
        return;
      }

      body.innerHTML = '';
      filteredResults.forEach(result => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <strong>${escapeHtml(result.course_code)}</strong><br>
            <small class="muted">${escapeHtml(result.course_name)}</small>
          </td>
          <td>${escapeHtml(result.session_name)}</td>
          <td>${escapeHtml(result.semester)}</td>
          <td class="num">${result.credit_unit}</td>
          <td class="num">${result.assignment_score}</td>
          <td class="num">${result.test_score}</td>
          <td class="num">${result.project_score}</td>
          <td class="num">${result.exam_score}</td>
          <td class="num">${result.discipline_score}%</td>
          <td class="num">${result.punctuality_score}%</td>
          <td class="num">${result.teamwork_score}%</td>
          <td>${result.remarks ? escapeHtml(result.remarks) : '<em class="muted">No remarks</em>'}</td>
          <td class="num"><strong>${result.final_score}%</strong></td>
          <td>${gradeBadge(result.grade_letter)}</td>
          <td class="num"><strong>${getGradePoints(result.grade_letter)}</strong></td>
        `;
        body.appendChild(tr);
      });
    }

    function applyFilters() {
      if (!studentData) return;

      const sessionFilter = document.getElementById('sessionFilter')?.value || '';
      const searchFilter = document.getElementById('searchFilter')?.value.toLowerCase() || '';

      filteredResults = studentData.results.filter(result => {
        const matchesSession = !sessionFilter || result.session_name === sessionFilter;
        const matchesSearch = !searchFilter ||
          result.course_code.toLowerCase().includes(searchFilter) ||
          result.course_name.toLowerCase().includes(searchFilter);

        return matchesSession && matchesSearch;
      });

      renderResultsTable();
    }

    function exportResults() {
      if (!studentData || studentData.results.length === 0) {
        showAlert('No results to export', 'error');
        return;
      }

      // Create CSV content
      const headers = ['Course Code', 'Course Name', 'Session', 'Semester', 'Credits', 'Assignment', 'Test', 'Project', 'Exam', 'Discipline', 'Punctuality', 'Teamwork', 'Final Score', 'Grade', 'Grade Points', 'Remarks'];
      const csvContent = [
        headers.join(','),
        ...filteredResults.map(result => [
          result.course_code,
          `"${result.course_name}"`,
          `"${result.session_name}"`,
          result.semester,
          result.credit_unit,
          result.assignment_score,
          result.test_score,
          result.project_score,
          result.exam_score,
          result.discipline_score,
          result.punctuality_score,
          result.teamwork_score,
          result.final_score,
          result.grade_letter,
          getGradePoints(result.grade_letter),
          `"${result.remarks || ''}"`
        ].join(','))
      ].join('\n');

      // Download CSV
      const blob = new Blob([csvContent], {
        type: 'text/csv'
      });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `my_results_${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      showAlert('Results exported successfully', 'success');
    }

    // Utility functions
    function gradeBadge(grade) {
      const classes = {
        'A': 'good',
        'B': 'good',
        'C': 'warn',
        'D': 'warn',
        'E': 'bad',
        'F': 'bad'
      };
      return `<span class="badge ${classes[grade] || 'bad'}">${grade}</span>`;
    }

    function getGradePoints(grade) {
      const points = {
        'A': 5.0,
        'B': 4.0,
        'C': 3.0,
        'D': 2.0,
        'E': 1.0,
        'F': 0.0
      };
      return points[grade] || 0.0;
    }

    function showAlert(message, type) {
      const container = document.getElementById('alertContainer');
      const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
      const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';

      const alert = document.createElement('div');
      alert.className = `alert ${alertClass}`;
      alert.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span><i class="fas ${icon}"></i> ${message}</span>
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

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Event listeners
    document.addEventListener('change', function(e) {
      if (e.target.id === 'sessionFilter' || e.target.id === 'searchFilter') {
        applyFilters();
      }
    });

    document.addEventListener('input', function(e) {
      if (e.target.id === 'searchFilter') {
        // Debounce search
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(applyFilters, 300);
      }
    });

    // Initialize on page load
    window.addEventListener('load', () => {
      loadStudentData();
    });
  </script>
</body>

</html>
</qodoArtifact>