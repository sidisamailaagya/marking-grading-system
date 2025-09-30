<?php
declare(strict_types=1);
session_start();

// Authentication and database connection
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'student') {
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
 * Convert grade letter to grade points (5-point system)
 */
function get_grade_points(string $grade): float
{
  switch (strtoupper($grade)) {
    case 'A': return 5.0;
    case 'B': return 4.0;
    case 'C': return 3.0;
    case 'D': return 2.0;
    case 'E': return 1.0;
    case 'F': 
    default: return 0.0;
  }
}

/**
 * Get comprehensive student dashboard data
 */
function get_student_dashboard_data(mysqli $db, int $student_id): array
{
  try {
    // Get student basic info
    $student_info = get_student_info($db, $student_id);
    
    // Get academic statistics
    $academic_stats = get_academic_statistics($db, $student_id);
    
    // Get recent results
    $recent_results = get_recent_results($db, $student_id, 5);
    
    // Get performance trend
    $performance_trend = get_performance_trend($db, $student_id);
    
    // Get registered courses
    $registered_courses = get_registered_courses($db, $student_id);
    
    // Get notifications
    $notifications = generate_smart_notifications($db, $student_id, $academic_stats, $recent_results);
    
    // Get current session info
    $session_info = get_current_session($db);
    
    return [
      'student' => $student_info,
      'stats' => $academic_stats,
      'recent_results' => $recent_results,
      'trend' => $performance_trend,
      'courses' => $registered_courses,
      'notifications' => $notifications,
      'session' => $session_info
    ];
  } catch (Exception $e) {
    error_log("Error in get_student_dashboard_data: " . $e->getMessage());
    return [];
  }
}

/**
 * Get student information
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
    if (!$stmt) return null;

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
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
 * Get academic statistics
 */
function get_academic_statistics(mysqli $db, int $student_id): array
{
  try {
    $sql = "SELECT 
                  COUNT(*) as total_courses,
                  AVG(r.final_score) as average_score,
                  MAX(r.final_score) as highest_score,
                  MIN(r.final_score) as lowest_score,
                  SUM(c.credit_unit) as total_credits
              FROM results r
              INNER JOIN courses c ON r.course_id = c.course_id
              WHERE r.student_id = ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    // Calculate GPA
    $gpa_sql = "SELECT 
                      r.final_score,
                      c.credit_unit
                  FROM results r
                  INNER JOIN courses c ON r.course_id = c.course_id
                  WHERE r.student_id = ?";

    $stmt = $db->prepare($gpa_sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_points = 0;
    $total_units = 0;

    while ($row = $result->fetch_assoc()) {
      $grade = calculate_grade_letter((float)$row['final_score']);
      $points = get_grade_points($grade);
      $units = (int)$row['credit_unit'];
      
      $total_points += $points * $units;
      $total_units += $units;
    }
    $stmt->close();

    $gpa = $total_units > 0 ? $total_points / $total_units : 0.0;

    return [
      'total_courses' => (int)($stats['total_courses'] ?? 0),
      'average_score' => round((float)($stats['average_score'] ?? 0), 1),
      'highest_score' => (float)($stats['highest_score'] ?? 0),
      'lowest_score' => (float)($stats['lowest_score'] ?? 0),
      'total_credits' => (int)($stats['total_credits'] ?? 0),
      'gpa' => round($gpa, 2)
    ];
  } catch (Exception $e) {
    error_log("Error in get_academic_statistics: " . $e->getMessage());
    return [];
  }
}

/**
 * Get recent results
 */
function get_recent_results(mysqli $db, int $student_id, int $limit = 5): array
{
  try {
    $sql = "SELECT 
                  r.final_score,
                  r.created_at,
                  c.course_code,
                  c.course_name,
                  c.credit_unit
              FROM results r
              INNER JOIN courses c ON r.course_id = c.course_id
              WHERE r.student_id = ?
              ORDER BY r.created_at DESC
              LIMIT ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('ii', $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
      $final_score = (float)$row['final_score'];
      $grade = calculate_grade_letter($final_score);

      $results[] = [
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'final_score' => $final_score,
        'grade' => $grade,
        'credit_unit' => (int)$row['credit_unit'],
        'created_at' => $row['created_at']
      ];
    }
    $stmt->close();

    return $results;
  } catch (Exception $e) {
    error_log("Error in get_recent_results: " . $e->getMessage());
    return [];
  }
}

/**
 * Get performance trend
 */
function get_performance_trend(mysqli $db, int $student_id): array
{
  try {
    $sql = "SELECT 
                  r.final_score,
                  r.created_at
              FROM results r
              WHERE r.student_id = ?
              ORDER BY r.created_at DESC
              LIMIT 10";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $scores = [];
    while ($row = $result->fetch_assoc()) {
      $scores[] = (float)$row['final_score'];
    }
    $stmt->close();

    if (count($scores) < 2) {
      return ['trend' => 'stable', 'change' => 0];
    }

    $recent_avg = array_sum(array_slice($scores, 0, 3)) / min(3, count($scores));
    $older_avg = array_sum(array_slice($scores, 3, 3)) / min(3, count(array_slice($scores, 3)));

    $change = $recent_avg - $older_avg;
    $trend = 'stable';
    
    if ($change > 5) $trend = 'improving';
    elseif ($change < -5) $trend = 'declining';

    return [
      'trend' => $trend,
      'change' => round($change, 1),
      'recent_avg' => round($recent_avg, 1),
      'older_avg' => round($older_avg, 1)
    ];
  } catch (Exception $e) {
    error_log("Error in get_performance_trend: " . $e->getMessage());
    return ['trend' => 'stable', 'change' => 0];
  }
}

/**
 * Get registered courses
 */
function get_registered_courses(mysqli $db, int $student_id): array
{
  try {
    // Check if student_registrations table exists
    $result = $db->query("SHOW TABLES LIKE 'student_registrations'");
    if ($result->num_rows === 0) {
      return []; // Return empty if table doesn't exist
    }

    $sql = "SELECT 
                  c.course_code,
                  c.course_name,
                  c.credit_unit,
                  c.semester,
                  COALESCE(l.lecturer_name, 'Not Assigned') as lecturer_name,
                  CASE WHEN r.result_id IS NOT NULL THEN 1 ELSE 0 END as is_graded
              FROM student_registrations sr
              INNER JOIN courses c ON sr.course_id = c.course_id
              LEFT JOIN lecturers l ON c.lecturer_id = l.lecturer_id
              LEFT JOIN results r ON sr.student_id = r.student_id AND sr.course_id = r.course_id
              WHERE sr.student_id = ?
              ORDER BY c.course_code";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
      $courses[] = [
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'credit_unit' => (int)$row['credit_unit'],
        'semester' => $row['semester'],
        'lecturer_name' => $row['lecturer_name'],
        'is_graded' => (bool)$row['is_graded']
      ];
    }
    $stmt->close();

    return $courses;
  } catch (Exception $e) {
    error_log("Error in get_registered_courses: " . $e->getMessage());
    return [];
  }
}

/**
 * Generate smart notifications
 */
function generate_smart_notifications(mysqli $db, int $student_id, array $stats, array $recent_results): array
{
  $notifications = [];

  // GPA-based notifications
  if (isset($stats['gpa'])) {
    if ($stats['gpa'] >= 4.5) {
      $notifications[] = [
        'type' => 'success',
        'title' => 'Excellent Academic Performance!',
        'message' => "Your GPA of {$stats['gpa']} is outstanding. Keep up the excellent work!",
        'time' => 'Academic Alert',
        'icon' => 'fa-trophy'
      ];
    } elseif ($stats['gpa'] < 2.0) {
      $notifications[] = [
        'type' => 'warning',
        'title' => 'Academic Support Needed',
        'message' => "Your GPA of {$stats['gpa']} needs improvement. Consider seeking academic support.",
        'time' => 'Academic Alert',
        'icon' => 'fa-exclamation-triangle'
      ];
    }
  }

  // Recent performance notifications
  if (!empty($recent_results)) {
    $latest = $recent_results[0];
    if ($latest['final_score'] >= 70) {
      $notifications[] = [
        'type' => 'success',
        'title' => 'Great Result in ' . $latest['course_code'],
        'message' => "You scored {$latest['final_score']}% - Grade {$latest['grade']}!",
        'time' => date('M j, Y', strtotime($latest['created_at'])),
        'icon' => 'fa-star'
      ];
    } elseif ($latest['final_score'] < 50) {
      $notifications[] = [
        'type' => 'danger',
        'title' => 'Improvement Needed',
        'message' => "Your recent score in {$latest['course_code']} was {$latest['final_score']}%. Focus on this subject.",
        'time' => date('M j, Y', strtotime($latest['created_at'])),
        'icon' => 'fa-chart-line'
      ];
    }
  }

  // Course completion notifications
  if (isset($stats['total_courses'])) {
    if ($stats['total_courses'] >= 10) {
      $notifications[] = [
        'type' => 'info',
        'title' => 'Academic Progress',
        'message' => "You've completed {$stats['total_courses']} courses with {$stats['total_credits']} credit units.",
        'time' => 'Progress Update',
        'icon' => 'fa-graduation-cap'
      ];
    }
  }

  // Default welcome notification if no others
  if (empty($notifications)) {
    $notifications[] = [
      'type' => 'info',
      'title' => 'Welcome to Your Dashboard',
      'message' => 'Track your academic progress and view your results here.',
      'time' => 'System',
      'icon' => 'fa-info-circle'
    ];
  }

  return array_slice($notifications, 0, 4); // Limit to 4 notifications
}

/**
 * Get current academic session
 */
function get_current_session(mysqli $db): array
{
  try {
    // Check if academic_sessions table exists
    $result = $db->query("SHOW TABLES LIKE 'academic_sessions'");
    if ($result->num_rows === 0) {
      return [
        'session_name' => date('Y') . '/' . (date('Y') + 1),
        'semester' => date('n') <= 6 ? 'First' : 'Second',
        'is_active' => true
      ];
    }

    $sql = "SELECT session_name, start_date, end_date, is_active 
            FROM academic_sessions 
            WHERE is_active = 1 
            LIMIT 1";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return [
        'session_name' => date('Y') . '/' . (date('Y') + 1),
        'semester' => date('n') <= 6 ? 'First' : 'Second',
        'is_active' => true
      ];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();

    if ($session) {
      return [
        'session_name' => $session['session_name'],
        'semester' => date('n') <= 6 ? 'First' : 'Second',
        'is_active' => (bool)$session['is_active']
      ];
    }

    return [
      'session_name' => date('Y') . '/' . (date('Y') + 1),
      'semester' => date('n') <= 6 ? 'First' : 'Second',
      'is_active' => true
    ];
  } catch (Exception $e) {
    error_log("Error in get_current_session: " . $e->getMessage());
    return [
      'session_name' => date('Y') . '/' . (date('Y') + 1),
      'semester' => date('n') <= 6 ? 'First' : 'Second',
      'is_active' => true
    ];
  }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_dashboard_data') {
      $dashboard_data = get_student_dashboard_data($mysqli, $student_id);
      echo json_encode($dashboard_data);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
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
  <title>Student Dashboard | Marking & Grading System</title>
  <meta name="description" content="Student portal to view results, feedback and progress" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root { 
      --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2; 
      --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1; 
      --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15); 
      --radius:14px; --transition: all .25s ease; 
    }
    body { font-family:'Inter',sans-serif; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
    .pcoded-navbar{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-right:1px solid rgba(0,0,0,0.05); box-shadow:0 0 30px rgba(0,0,0,0.12); }
    .pcoded-header{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-bottom:1px solid rgba(0,0,0,0.05); box-shadow:0 6px 20px rgba(0,0,0,0.06); }
    .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2.5rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
    .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .hero-content{ position:relative; z-index:2; }
    .hero-stats{ display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:1rem; margin-top:1.5rem; }
    .hero-stat{ text-align:center; }
    .hero-stat-value{ font-size:1.8rem; font-weight:900; margin-bottom:.25rem; }
    .hero-stat-label{ font-size:.85rem; opacity:.9; }
    .container{ padding: 0 1.25rem; }
    .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
    .grid-4{ display:grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom:1.5rem; }
    .grid-2{ display:grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom:1.5rem; }
    .grid-3{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.5rem; transition:var(--transition); }
    .card:hover{ box-shadow:var(--card-shadow-hover); transform:translateY(-2px); }
    .card-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
    .card-title{ font-size:1.1rem; font-weight:800; color:#2d3748; margin:0; }
    .stat-card{ text-align:center; }
    .stat-icon{ width:60px; height:60px; border-radius:16px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem; margin:0 auto 1rem; }
    .blue{ background:linear-gradient(135deg,#667eea,#5a67d8); }
    .green{ background:linear-gradient(135deg,#48bb78,#38a169); }
    .orange{ background:linear-gradient(135deg,#ed8936,#dd6b20); }
    .purple{ background:linear-gradient(135deg,#9f7aea,#805ad5); }
    .stat-value{ font-size:2.2rem; font-weight:900; color:#2d3748; margin-bottom:.25rem; }
    .stat-label{ color:#718096; font-size:.9rem; font-weight:600; }
    .muted{ color:#718096; font-size:.9rem; }
    .list{ display:grid; gap:.75rem; }
    .list-item{ border:1px solid #edf2f7; border-radius:12px; padding:1rem; display:flex; align-items:center; justify-content:space-between; transition:var(--transition); }
    .list-item:hover{ border-color:#cbd5e0; background:#f8f9fa; }
    .item-content{ flex:1; }
    .item-title{ font-weight:700; color:#2d3748; margin-bottom:.25rem; }
    .item-subtitle{ color:#718096; font-size:.85rem; }
    .pill{ padding:.25rem .75rem; border-radius:999px; font-weight:700; font-size:.75rem; display:inline-flex; align-items:center; gap:.25rem; }
    .pill.success{ background:rgba(72,187,120,.12); color:#2f855a; }
    .pill.warning{ background:rgba(237,137,54,.12); color:#9c4221; }
    .pill.danger{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .pill.info{ background:rgba(66,153,225,.12); color:#2b6cb0; }
    .pill.graded{ background:rgba(72,187,120,.12); color:#2f855a; }
    .pill.pending{ background:rgba(237,137,54,.12); color:#9c4221; }
    .btn{ padding:.65rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); text-decoration:none; }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .btn:hover{ transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.15); }
    .loading{ text-align:center; padding:3rem; color:#718096; }
    .no-data{ text-align:center; padding:2rem; color:#718096; font-style:italic; }
    .chart-container{ position:relative; height:200px; margin-top:1rem; }
    .trend-up{ color:#48bb78; }
    .trend-down{ color:#f56565; }
    .trend-stable{ color:#4299e1; }
    .grade-A{ background:rgba(72,187,120,.12); color:#2f855a; }
    .grade-B{ background:rgba(66,153,225,.12); color:#2c5282; }
    .grade-C{ background:rgba(237,137,54,.12); color:#9c4221; }
    .grade-D, .grade-E{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .grade-F{ background:rgba(245,101,101,.12); color:#9b2c2c; }

    @media (max-width:1100px){ 
      .grid-4{ grid-template-columns: repeat(2, 1fr); } 
      .grid-2{ grid-template-columns: 1fr; } 
      .grid-3{ grid-template-columns: 1fr; }
      .hero-stats{ grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width:600px){ 
      .grid-4{ grid-template-columns: 1fr; }
      .hero-stats{ grid-template-columns: 1fr; }
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
          <li class="nav-item"><a href="dashboard.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-results.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
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
      <a href="#" class="b-brand"><h3 class="text-primary mb-0">Student Portal</h3></a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <div class="hero-content">
            <h1 id="welcomeText">Welcome, Student</h1>
            <p>Academic Session: <strong id="sessionName">Loading...</strong> · Semester: <strong id="semesterName">Loading...</strong></p>
            <div class="hero-stats" id="heroStats">
              <div class="hero-stat">
                <div class="hero-stat-value" id="heroGPA">0.00</div>
                <div class="hero-stat-label">Current GPA</div>
              </div>
              <div class="hero-stat">
                <div class="hero-stat-value" id="heroCourses">0</div>
                <div class="hero-stat-label">Completed Courses</div>
              </div>
              <div class="hero-stat">
                <div class="hero-stat-value" id="heroCredits">0</div>
                <div class="hero-stat-label">Credit Units</div>
              </div>
              <div class="hero-stat">
                <div class="hero-stat-value" id="heroAverage">0%</div>
                <div class="hero-stat-label">Average Score</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="main container">
        <div id="mainContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading your dashboard...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let dashboardData = null;

    async function loadDashboardData() {
      try {
        const response = await fetch('?action=get_dashboard_data');
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        dashboardData = data;
        renderDashboard();
        
      } catch (error) {
        console.error('Error loading dashboard data:', error);
        document.getElementById('mainContent').innerHTML = '<div class="no-data">Error loading dashboard: ' + error.message + '</div>';
      }
    }

    function renderDashboard() {
      if (!dashboardData) return;

      const { student, stats, recent_results, trend, courses, notifications, session } = dashboardData;

      // Update hero section
      if (student) {
        document.getElementById('welcomeText').textContent = `Welcome, ${student.full_name}`;
        document.getElementById('sessionName').textContent = session.session_name;
        document.getElementById('semesterName').textContent = session.semester;
        
        // Animate hero stats
        animateValue(document.getElementById('heroGPA'), stats.gpa || 0, 2);
        animateValue(document.getElementById('heroCourses'), stats.total_courses || 0, 0);
        animateValue(document.getElementById('heroCredits'), stats.total_credits || 0, 0);
        animateValue(document.getElementById('heroAverage'), stats.average_score || 0, 1, '%');
      }

      // Render main content
      const content = `
        <!-- Academic Statistics -->
        <div class="grid-4">
          <div class="card stat-card">
            <div class="stat-icon blue"><i class="fas fa-graduation-cap"></i></div>
            <div class="stat-value" id="statGPA">${stats.gpa || 0}</div>
            <div class="stat-label">Current GPA (5.0 Scale)</div>
          </div>
          <div class="card stat-card">
            <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value" id="statAverage">${stats.average_score || 0}%</div>
            <div class="stat-label">Average Score</div>
          </div>
          <div class="card stat-card">
            <div class="stat-icon orange"><i class="fas fa-trophy"></i></div>
            <div class="stat-value" id="statHighest">${stats.highest_score || 0}%</div>
            <div class="stat-label">Highest Score</div>
          </div>
          <div class="card stat-card">
            <div class="stat-icon purple"><i class="fas fa-books"></i></div>
            <div class="stat-value" id="statCourses">${stats.total_courses || 0}</div>
            <div class="stat-label">Completed Courses</div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid-2">
          <!-- Recent Results -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-clock"></i> Recent Results</h3>
              <a href="my-results.php" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i> View All
              </a>
            </div>
            <div class="list" id="recentResultsList">
              ${recent_results.length > 0 ? recent_results.map(result => `
                <div class="list-item">
                  <div class="item-content">
                    <div class="item-title">${escapeHtml(result.course_code)} - ${escapeHtml(result.course_name)}</div>
                    <div class="item-subtitle">${formatDate(result.created_at)} • ${result.credit_unit} Credits</div>
                  </div>
                  <div>
                    <span class="pill grade-${result.grade}">${result.grade}</span>
                    <div style="text-align:right; margin-top:.25rem; font-weight:700; color:#2d3748;">${result.final_score}%</div>
                  </div>
                </div>
              `).join('') : '<div class="no-data">No results available yet</div>'}
            </div>
          </div>

          <!-- Notifications -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-bell"></i> Notifications</h3>
            </div>
            <div class="list" id="notificationsList">
              ${notifications.map(notif => `
                <div class="list-item">
                  <div style="display:flex; align-items:center; gap:.75rem;">
                    <i class="fas ${notif.icon} ${notif.type === 'success' ? 'trend-up' : notif.type === 'warning' ? 'trend-stable' : notif.type === 'danger' ? 'trend-down' : 'trend-stable'}"></i>
                    <div class="item-content">
                      <div class="item-title">${escapeHtml(notif.title)}</div>
                      <div class="item-subtitle">${escapeHtml(notif.message)}</div>
                    </div>
                  </div>
                  <span class="pill ${notif.type}">${notif.time}</span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>

        <!-- Performance Trend & Registered Courses -->
        <div class="grid-2">
          <!-- Performance Trend -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-chart-area"></i> Performance Trend</h3>
              <span class="pill ${trend.trend}">
                <i class="fas fa-arrow-${trend.trend === 'improving' ? 'up' : trend.trend === 'declining' ? 'down' : 'right'}"></i>
                ${trend.trend.charAt(0).toUpperCase() + trend.trend.slice(1)}
              </span>
            </div>
            <div style="text-align:center; padding:1rem;">
              <div style="font-size:2rem; font-weight:900; color:${trend.trend === 'improving' ? '#48bb78' : trend.trend === 'declining' ? '#f56565' : '#4299e1'};">
                ${trend.change > 0 ? '+' : ''}${trend.change}%
              </div>
              <div class="muted">Change from previous results</div>
              ${trend.recent_avg ? `
                <div style="margin-top:1rem; display:grid; grid-template-columns:1fr 1fr; gap:1rem; text-align:center;">
                  <div>
                    <div style="font-weight:700; color:#2d3748;">${trend.recent_avg}%</div>
                    <div class="muted">Recent Average</div>
                  </div>
                  <div>
                    <div style="font-weight:700; color:#2d3748;">${trend.older_avg}%</div>
                    <div class="muted">Previous Average</div>
                  </div>
                </div>
              ` : ''}
            </div>
          </div>

          <!-- Registered Courses -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-book-open"></i> Registered Courses</h3>
              <a href="my-results.php" class="btn btn-outline">
                <i class="fas fa-plus"></i> Manage
              </a>
            </div>
            <div class="list" id="coursesList">
              ${courses.length > 0 ? courses.slice(0, 4).map(course => `
                <div class="list-item">
                  <div class="item-content">
                    <div class="item-title">${escapeHtml(course.course_code)} - ${escapeHtml(course.course_name)}</div>
                    <div class="item-subtitle">${escapeHtml(course.lecturer_name)} • ${course.credit_unit} Credits • ${escapeHtml(course.semester)} Semester</div>
                  </div>
                  <span class="pill ${course.is_graded ? 'graded' : 'pending'}">
                    <i class="fas fa-${course.is_graded ? 'check' : 'clock'}"></i>
                    ${course.is_graded ? 'Graded' : 'Pending'}
                  </span>
                </div>
              `).join('') : '<div class="no-data">No registered courses yet</div>'}
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-rocket"></i> Quick Actions</h3>
          </div>
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <a href="my-results.php" class="btn btn-primary">
              <i class="fas fa-list-check"></i> View My Results
            </a>
            <a href="predicted-grade.php" class="btn btn-outline">
              <i class="fas fa-crystal-ball"></i> Grade Prediction
            </a>
            <a href="performance-history.php" class="btn btn-outline">
              <i class="fas fa-chart-line"></i> Performance History
            </a>
            <a href="profile-settings.php" class="btn btn-outline">
              <i class="fas fa-user-cog"></i> Profile Settings
            </a>
          </div>
        </div>
      `;

      document.getElementById('mainContent').innerHTML = content;

      // Animate stat cards
      setTimeout(() => {
        animateValue(document.getElementById('statGPA'), stats.gpa || 0, 2);
        animateValue(document.getElementById('statAverage'), stats.average_score || 0, 1, '%');
        animateValue(document.getElementById('statHighest'), stats.highest_score || 0, 1, '%');
        animateValue(document.getElementById('statCourses'), stats.total_courses || 0, 0);
      }, 100);
    }

    function animateValue(element, targetValue, decimals = 0, suffix = '') {
      if (!element) return;
      
      const startValue = 0;
      const duration = 1000;
      const startTime = performance.now();
      
      function updateValue(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function for smooth animation
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const currentValue = startValue + (targetValue - startValue) * easeOutQuart;
        
        element.textContent = currentValue.toFixed(decimals) + suffix;
        
        if (progress < 1) {
          requestAnimationFrame(updateValue);
        }
      }
      
      requestAnimationFrame(updateValue);
    }

    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
      });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Initialize dashboard
    window.addEventListener('load', () => {
      loadDashboardData();
    });
  </script>
</body>
</html>