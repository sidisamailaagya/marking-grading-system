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
  if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
  }
  die('Database connection failed. Please check your configuration.');
}

// Get lecturer ID from session
$lecturer_id = (int)$_SESSION['uid'];

/**
 * Get students enrolled in lecturer's courses
 */
function get_lecturer_students(mysqli $db, int $lecturer_id): array
{
  $sql = "SELECT DISTINCT
                s.student_id,
                s.matric_no,
                s.full_name,
                s.level,
                d.dept_name
            FROM course_assignments ca
            INNER JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE ca.lecturer_id = ?
            ORDER BY s.full_name";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log("Failed to prepare students query: " . $db->error);
    return [];
  }

  $stmt->bind_param('i', $lecturer_id);
  if (!$stmt->execute()) {
    error_log("Failed to execute students query: " . $stmt->error);
    $stmt->close();
    return [];
  }
  
  $result = $stmt->get_result();
  $students = [];
  
  while ($row = $result->fetch_assoc()) {
    $students[] = [
      'student_id' => $row['student_id'],
      'matric_no' => $row['matric_no'],
      'full_name' => $row['full_name'],
      'level' => $row['level'],
      'dept_name' => $row['dept_name'] ?? 'Unknown Department'
    ];
  }
  $stmt->close();
  return $students;
}

/**
 * Get student's performance history
 */
function get_student_performance_history(mysqli $db, int $student_id, int $lecturer_id): array
{
  $sql = "SELECT 
                c.course_code,
                c.course_name,
                r.assignment_score,
                r.test_score,
                r.project_score,
                r.exam_score,
                r.discipline_score,
                r.punctuality_score,
                r.teamwork_score,
                r.final_score,
                r.grade_letter,
                r.remarks,
                r.created_at
            FROM results r
            INNER JOIN courses c ON r.course_id = c.course_id
            WHERE r.student_id = ?
            ORDER BY r.created_at DESC";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log("Failed to prepare performance history query: " . $db->error);
    return [];
  }

  $stmt->bind_param('i', $student_id);
  if (!$stmt->execute()) {
    error_log("Failed to execute performance history query: " . $stmt->error);
    $stmt->close();
    return [];
  }
  
  $result = $stmt->get_result();
  $history = [];
  
  while ($row = $result->fetch_assoc()) {
    // Calculate academic percentage (without behavior)
    $academic_total = ($row['assignment_score'] ?? 0) + ($row['test_score'] ?? 0) + 
                     ($row['project_score'] ?? 0) + ($row['exam_score'] ?? 0);
    
    // Calculate behavior adjustment
    $behavior_avg = (($row['discipline_score'] ?? 0) + ($row['punctuality_score'] ?? 0) + 
                    ($row['teamwork_score'] ?? 0)) / 3;
    $behavior_adjustment = round($behavior_avg * 0.1); // 10% behavior weight
    
    $history[] = [
      'course_code' => $row['course_code'],
      'course_name' => $row['course_name'],
      'session_name' => 'Current Session',
      'academic_score' => round($academic_total),
      'behavior_adjustment' => $behavior_adjustment,
      'final_score' => $row['final_score'] ?? 0,
      'grade_letter' => $row['grade_letter'] ?? 'F',
      'assignment_score' => $row['assignment_score'] ?? 0,
      'test_score' => $row['test_score'] ?? 0,
      'project_score' => $row['project_score'] ?? 0,
      'exam_score' => $row['exam_score'] ?? 0,
      'discipline_score' => $row['discipline_score'] ?? 0,
      'punctuality_score' => $row['punctuality_score'] ?? 0,
      'teamwork_score' => $row['teamwork_score'] ?? 0,
      'remarks' => $row['remarks'] ?? '',
      'created_at' => $row['created_at']
    ];
  }
  $stmt->close();
  return $history;
}

/**
 * Get student basic information
 */
function get_student_info(mysqli $db, int $student_id): ?array
{
  $sql = "SELECT 
                s.student_id,
                s.matric_no,
                s.full_name,
                s.level,
                d.dept_name
            FROM students s
            LEFT JOIN departments d ON s.dept_id = d.dept_id
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
}

/**
 * Calculate performance insights
 */
function calculate_insights(array $history): array
{
  if (empty($history)) {
    return [
      'predicted_grade' => 'F',
      'trend' => 'No Data',
      'strength' => 'Unknown',
      'weakness' => 'Unknown',
      'average_score' => 0
    ];
  }

  // Calculate average final score
  $total_scores = array_sum(array_column($history, 'final_score'));
  $average_score = round($total_scores / count($history));

  // Predict grade based on average
  $predicted_grade = 'F';
  if ($average_score >= 70) $predicted_grade = 'A';
  elseif ($average_score >= 60) $predicted_grade = 'B';
  elseif ($average_score >= 50) $predicted_grade = 'C';
  elseif ($average_score >= 45) $predicted_grade = 'D';
  elseif ($average_score >= 40) $predicted_grade = 'E';

  // Analyze trend (last 3 courses)
  $recent_courses = array_slice($history, 0, 3);
  $trend = 'Stable';
  if (count($recent_courses) >= 2) {
    $first_score = end($recent_courses)['final_score'];
    $last_score = $recent_courses[0]['final_score'];
    $difference = $last_score - $first_score;
    
    if ($difference > 5) $trend = 'Improving';
    elseif ($difference < -5) $trend = 'Declining';
  }

  // Find strength and weakness
  $component_averages = [
    'Assignment' => array_sum(array_column($history, 'assignment_score')) / count($history),
    'Test' => array_sum(array_column($history, 'test_score')) / count($history),
    'Project' => array_sum(array_column($history, 'project_score')) / count($history),
    'Exam' => array_sum(array_column($history, 'exam_score')) / count($history),
    'Discipline' => array_sum(array_column($history, 'discipline_score')) / count($history),
    'Punctuality' => array_sum(array_column($history, 'punctuality_score')) / count($history),
    'Teamwork' => array_sum(array_column($history, 'teamwork_score')) / count($history)
  ];

  // Convert to percentages for fair comparison
  $component_percentages = [
    'Assignment' => ($component_averages['Assignment'] / 20) * 100,
    'Test' => ($component_averages['Test'] / 20) * 100,
    'Project' => ($component_averages['Project'] / 20) * 100,
    'Exam' => ($component_averages['Exam'] / 40) * 100,
    'Discipline' => $component_averages['Discipline'],
    'Punctuality' => $component_averages['Punctuality'],
    'Teamwork' => $component_averages['Teamwork']
  ];

  $strength = array_keys($component_percentages, max($component_percentages))[0];
  $weakness = array_keys($component_percentages, min($component_percentages))[0];

  return [
    'predicted_grade' => $predicted_grade,
    'trend' => $trend,
    'strength' => $strength,
    'weakness' => $weakness,
    'average_score' => $average_score
  ];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_students') {
      $students = get_lecturer_students($mysqli, $lecturer_id);
      echo json_encode($students);
      exit;
    }

    if ($_GET['action'] === 'get_student_performance' && isset($_GET['student_id'])) {
      $student_id = (int)$_GET['student_id'];
      
      if ($student_id <= 0) {
        echo json_encode(['error' => 'Invalid student ID']);
        exit;
      }
      
      $student_info = get_student_info($mysqli, $student_id);
      if (!$student_info) {
        echo json_encode(['error' => 'Student not found']);
        exit;
      }

      $history = get_student_performance_history($mysqli, $student_id, $lecturer_id);
      $insights = calculate_insights($history);

      echo json_encode([
        'student' => $student_info,
        'history' => $history,
        'insights' => $insights
      ]);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
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
  <title>View Student Performance - Lecturer | Marking & Grading System</title>
  <meta name="description" content="View detailed student performance and predicted grade" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2; --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1; --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15); --radius:14px; --transition: all .25s ease; }
    body { font-family:'Inter',sans-serif; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
    .pcoded-navbar{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-right:1px solid rgba(0,0,0,0.05); box-shadow:0 0 30px rgba(0,0,0,0.12); }
    .pcoded-header{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-bottom:1px solid rgba(0,0,0,0.05); box-shadow:0 6px 20px rgba(0,0,0,0.06); }
    .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
    .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .container{ padding: 0 1.25rem; }
    .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
    .grid{ display:grid; grid-template-columns: 1.1fr 1fr; gap:1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; transition:var(--transition); }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .profile{ display:flex; gap:1rem; align-items:center; }
    .avatar{ width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.3rem; }
    .muted{ color:#718096; font-size:.9rem; }
    .tag{ padding:.2rem .55rem; border-radius:999px; background:#edf2f7; color:#4a5568; font-weight:700; font-size:.8rem; display:inline-block; margin:.2rem .2rem 0 0; }
    .tag.strength{ background:rgba(72,187,120,.12); color:#2f855a; }
    .tag.weakness{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .tag.improving{ background:rgba(72,187,120,.12); color:#2f855a; }
    .tag.declining{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .tag.stable{ background:rgba(66,153,225,.12); color:#2c5282; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
    .center{ text-align:center; }
    .right{ text-align:right; }
    .select{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; width:100%; }
    .select:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .loading{ text-align:center; padding:2rem; color:#718096; }
    .no-data{ text-align:center; padding:2rem; color:#718096; font-style:italic; }
    .grade-badge{ padding:.3rem .6rem; border-radius:999px; font-weight:800; font-size:.9rem; }
    .grade-A{ background:rgba(72,187,120,.12); color:#2f855a; }
    .grade-B{ background:rgba(66,153,225,.12); color:#2c5282; }
    .grade-C{ background:rgba(237,137,54,.12); color:#9c4221; }
    .grade-D{ background:rgba(237,137,54,.15); color:#9c4221; }
    .grade-E{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .grade-F{ background:rgba(245,101,101,.15); color:#9b2c2c; }
    .predicted-grade{ font-size:2.4rem; font-weight:900; color:#2d3748; margin:.5rem 0; }
    .toolbar{ display:flex; gap:.5rem; align-items:center; margin-bottom:1rem; }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); text-decoration:none; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .btn:hover{ transform:translateY(-1px); }
    .alert{ padding:1rem; border-radius:10px; margin-bottom:1rem; animation:slideIn 0.3s ease-out; }
    .alert-info{ background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }

    @keyframes slideIn {
      from { opacity:0; transform:translateY(-10px); }
      to { opacity:1; transform:translateY(0); }
    }

    @media (max-width: 768px) {
      .grid{ grid-template-columns: 1fr; }
      .toolbar{ flex-direction:column; align-items:stretch; }
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
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
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
      <a href="#" class="b-brand"><h3 class="text-primary mb-0">Lecturer Portal</h3></a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>View Student Performance</h1>
          <p>Detailed breakdown, insights, and predicted grades</p>
        </div>
      </div>

      <div class="main container">
        <div class="card" style="margin-bottom:1rem;">
          <div class="toolbar">
            <div class="muted">Select Student:</div>
            <select id="studentSelect" class="select" style="max-width:400px;">
              <option value="">Loading students...</option>
            </select>
            <a href="enter-grades.php" class="btn btn-outline"><i class="fas fa-pen-to-square"></i> Enter Grades</a>
            <a href="reports-analytics.php" class="btn btn-outline"><i class="fas fa-chart-line"></i> View Reports</a>
          </div>
        </div>

        <div id="performanceContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Select a student to view performance</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let students = [];
    let currentStudent = null;

    async function loadStudents() {
      try {
        const response = await fetch('?action=get_students');
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        students = data;
        
        const select = document.getElementById('studentSelect');
        select.innerHTML = '<option value="">Select a student...</option>';
        
        students.forEach(student => {
          const option = document.createElement('option');
          option.value = student.student_id;
          option.textContent = `${student.full_name} (${student.matric_no})`;
          select.appendChild(option);
        });
        
      } catch (error) {
        console.error('Error loading students:', error);
        document.getElementById('studentSelect').innerHTML = '<option value="">Error loading students</option>';
      }
    }

    async function loadStudentPerformance(studentId) {
      if (!studentId) {
        document.getElementById('performanceContent').innerHTML = '<div class="loading">Select a student to view performance</div>';
        return;
      }

      document.getElementById('performanceContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading student performance...</div>';

      try {
        const response = await fetch(`?action=get_student_performance&student_id=${studentId}`);
        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        currentStudent = data;
        renderPerformance();

      } catch (error) {
        console.error('Error loading student performance:', error);
        document.getElementById('performanceContent').innerHTML = '<div class="no-data">Error loading student performance: ' + error.message + '</div>';
      }
    }

    function renderPerformance() {
      if (!currentStudent) return;

      const { student, history, insights } = currentStudent;
      
      // Generate initials for avatar
      const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      const content = `
        <div class="grid">
          <div class="card">
            <div class="profile">
              <div class="avatar">${initials}</div>
              <div>
                <div style="font-weight:800;color:#2d3748;">${escapeHtml(student.full_name)}</div>
                <div class="muted">Matric: ${escapeHtml(student.matric_no)} · Level: ${student.level} · ${escapeHtml(student.dept_name)}</div>
              </div>
            </div>
            <div style="margin-top:1rem;">
              <div class="muted" style="margin-bottom:.4rem;">Course Performance History</div>
              ${renderHistoryTable(history)}
            </div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Performance Insights</div>
            <div class="muted">Predicted Grade (next assessment)</div>
            <div class="predicted-grade grade-${insights.predicted_grade}">${insights.predicted_grade}</div>
            <div class="muted" style="margin:.5rem 0 1rem;">Based on ${history.length} course${history.length !== 1 ? 's' : ''} and performance trends.</div>
            <div>
              <div class="tag strength">Strength: ${insights.strength}</div>
              <div class="tag weakness">Weakness: ${insights.weakness}</div>
              <div class="tag ${insights.trend.toLowerCase()}">Trend: ${insights.trend}</div>
            </div>
            <div style="margin-top:1rem;">
              <div class="muted">Average Score: <strong>${insights.average_score}%</strong></div>
            </div>
            ${renderRecommendations(insights)}
          </div>
        </div>
        
        ${renderCurrentCourseComponents(history)}
      `;

      document.getElementById('performanceContent').innerHTML = content;
    }

    function renderHistoryTable(history) {
      if (history.length === 0) {
        return '<div class="no-data">No performance history available for this student.</div>';
      }

      let tableHtml = `
        <table>
          <thead>
            <tr>
              <th>Course</th>
              <th>Session</th>
              <th>Academic %</th>
              <th>Behavior Adj.</th>
              <th>Final %</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
      `;

      history.forEach(record => {
        tableHtml += `
          <tr>
            <td>${escapeHtml(record.course_code)}</td>
            <td>${escapeHtml(record.session_name)}</td>
            <td class="center">${record.academic_score}%</td>
            <td class="center">+${record.behavior_adjustment}%</td>
            <td class="center"><strong>${record.final_score}%</strong></td>
            <td class="center"><span class="grade-badge grade-${record.grade_letter}">${record.grade_letter}</span></td>
          </tr>
        `;
      });

      tableHtml += '</tbody></table>';
      return tableHtml;
    }

    function renderCurrentCourseComponents(history) {
      if (history.length === 0) {
        return '';
      }

      // Get the most recent course for component breakdown
      const latestCourse = history[0];

      return `
        <div class="card" style="margin-top:1rem;">
          <div class="muted" style="margin-bottom:.5rem;">Latest Course Components (${escapeHtml(latestCourse.course_code)})</div>
          <table>
            <thead>
              <tr><th>Component</th><th>Weight</th><th>Score</th><th>Percentage</th></tr>
            </thead>
            <tbody>
              <tr>
                <td>Assignments</td>
                <td>20%</td>
                <td>${latestCourse.assignment_score}/20</td>
                <td class="center">${Math.round((latestCourse.assignment_score / 20) * 100)}%</td>
              </tr>
              <tr>
                <td>Tests</td>
                <td>20%</td>
                <td>${latestCourse.test_score}/20</td>
                <td class="center">${Math.round((latestCourse.test_score / 20) * 100)}%</td>
              </tr>
              <tr>
                <td>Project</td>
                <td>20%</td>
                <td>${latestCourse.project_score}/20</td>
                <td class="center">${Math.round((latestCourse.project_score / 20) * 100)}%</td>
              </tr>
              <tr>
                <td>Exam</td>
                <td>40%</td>
                <td>${latestCourse.exam_score}/40</td>
                <td class="center">${Math.round((latestCourse.exam_score / 40) * 100)}%</td>
              </tr>
              <tr style="border-top: 2px solid #e2e8f0;">
                <td><strong>Behavior Scores</strong></td>
                <td>10%</td>
                <td>-</td>
                <td>-</td>
              </tr>
              <tr>
                <td>&nbsp;&nbsp;Discipline</td>
                <td>-</td>
                <td>${latestCourse.discipline_score}/100</td>
                <td class="center">${latestCourse.discipline_score}%</td>
              </tr>
              <tr>
                <td>&nbsp;&nbsp;Punctuality</td>
                <td>-</td>
                <td>${latestCourse.punctuality_score}/100</td>
                <td class="center">${latestCourse.punctuality_score}%</td>
              </tr>
              <tr>
                <td>&nbsp;&nbsp;Teamwork</td>
                <td>-</td>
                <td>${latestCourse.teamwork_score}/100</td>
                <td class="center">${latestCourse.teamwork_score}%</td>
              </tr>
            </tbody>
          </table>
          ${latestCourse.remarks ? `<div class="muted" style="margin-top:.5rem;"><strong>Remarks:</strong> ${escapeHtml(latestCourse.remarks)}</div>` : ''}
        </div>
      `;
    }

    function renderRecommendations(insights) {
      let recommendations = [];
      
      if (insights.trend === 'Declining') {
        recommendations.push('Consider additional support or tutoring');
      } else if (insights.trend === 'Improving') {
        recommendations.push('Student shows positive progress');
      }
      
      if (insights.average_score < 50) {
        recommendations.push('Requires immediate academic intervention');
      } else if (insights.average_score >= 70) {
        recommendations.push('Excellent performance - consider advanced challenges');
      }
      
      if (recommendations.length === 0) {
        return '';
      }
      
      return `
        <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid #e2e8f0;">
          <div class="muted" style="margin-bottom:.5rem;">Recommendations:</div>
          ${recommendations.map(rec => `<div class="alert alert-info" style="margin:.25rem 0; padding:.5rem;"><i class="fas fa-lightbulb"></i> ${rec}</div>`).join('')}
        </div>
      `;
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Event listeners
    document.getElementById('studentSelect').addEventListener('change', function() {
      loadStudentPerformance(this.value);
    });

    // Initialize on page load
    window.addEventListener('load', () => {
      loadStudents();
    });
  </script>
</body>
</html>