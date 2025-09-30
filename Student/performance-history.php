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
 * Get student's performance history grouped by academic sessions
 */
function get_student_performance_history(mysqli $db, int $student_id): array
{
  $sql = "SELECT 
                r.final_score,
                r.grade_letter,
                r.assignment_score,
                r.test_score,
                r.project_score,
                r.exam_score,
                r.discipline_score,
                r.punctuality_score,
                r.teamwork_score,
                r.remarks,
                r.created_at,
                c.course_code,
                c.course_name,
                c.credit_unit,
                COALESCE(acs.session_name, 'Current Session') as session_name,
                COALESCE(acs.start_date, r.created_at) as session_start
            FROM results r
            INNER JOIN courses c ON r.course_id = c.course_id
            LEFT JOIN academic_sessions acs ON acs.is_active = 1
            WHERE r.student_id = ?
            ORDER BY session_start DESC, r.created_at DESC";

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
    $final_score = (float)($row['final_score'] ?? 0);
    $calculated_grade = calculate_grade_letter($final_score);
    
    $history[] = [
      'session_name' => $row['session_name'],
      'session_start' => $row['session_start'],
      'course_code' => $row['course_code'],
      'course_name' => $row['course_name'],
      'credit_unit' => (int)($row['credit_unit'] ?? 3),
      'final_score' => $final_score,
      'grade_letter' => $calculated_grade, // Use calculated grade based on score
      'assignment_score' => (float)($row['assignment_score'] ?? 0),
      'test_score' => (float)($row['test_score'] ?? 0),
      'project_score' => (float)($row['project_score'] ?? 0),
      'exam_score' => (float)($row['exam_score'] ?? 0),
      'discipline_score' => (float)($row['discipline_score'] ?? 0),
      'punctuality_score' => (float)($row['punctuality_score'] ?? 0),
      'teamwork_score' => (float)($row['teamwork_score'] ?? 0),
      'remarks' => $row['remarks'],
      'created_at' => $row['created_at']
    ];
  }
  $stmt->close();
  return $history;
}

/**
 * Calculate GPA for a set of courses (5-point system)
 */
function calculate_gpa(array $courses): float
{
  if (empty($courses)) return 0.0;

  $total_points = 0;
  $total_units = 0;

  foreach ($courses as $course) {
    $grade_points = get_grade_points($course['grade_letter']);
    $credit_unit = $course['credit_unit'];
    
    $total_points += $grade_points * $credit_unit;
    $total_units += $credit_unit;
  }

  return $total_units > 0 ? round($total_points / $total_units, 2) : 0.0;
}

/**
 * Calculate CGPA (Cumulative GPA) across all sessions
 */
function calculate_cgpa(array $all_courses): float
{
  return calculate_gpa($all_courses);
}

/**
 * Group performance history by sessions and calculate statistics
 */
function analyze_performance_by_sessions(array $history): array
{
  if (empty($history)) {
    return [
      'sessions' => [],
      'overall_stats' => [
        'best_session' => null,
        'worst_session' => null,
        'overall_gpa' => 0.0,
        'cgpa' => 0.0,
        'overall_average' => 0.0,
        'total_courses' => 0,
        'total_credit_units' => 0,
        'trend' => 'stable'
      ]
    ];
  }

  // Group by sessions
  $sessions = [];
  foreach ($history as $record) {
    $session_name = $record['session_name'];
    if (!isset($sessions[$session_name])) {
      $sessions[$session_name] = [
        'name' => $session_name,
        'courses' => [],
        'start_date' => $record['session_start']
      ];
    }
    $sessions[$session_name]['courses'][] = $record;
  }

  // Calculate statistics for each session
  $session_stats = [];
  foreach ($sessions as $session_name => $session_data) {
    $courses = $session_data['courses'];
    $final_scores = array_column($courses, 'final_score');
    $credit_units = array_sum(array_column($courses, 'credit_unit'));
    $average_score = count($final_scores) > 0 ? round(array_sum($final_scores) / count($final_scores), 2) : 0;
    $gpa = calculate_gpa($courses);

    $session_stats[] = [
      'name' => $session_name,
      'courses' => $courses,
      'course_count' => count($courses),
      'credit_units' => $credit_units,
      'average_score' => $average_score,
      'gpa' => $gpa,
      'start_date' => $session_data['start_date']
    ];
  }

  // Sort sessions by start date (most recent first)
  usort($session_stats, function($a, $b) {
    return strtotime($b['start_date']) - strtotime($a['start_date']);
  });

  // Calculate CGPA for each session (cumulative up to that point)
  // Reverse the array to calculate CGPA chronologically
  $chronological_sessions = array_reverse($session_stats);
  $cumulative_courses = [];
  
  foreach ($chronological_sessions as $index => $session) {
    // Add current session courses to cumulative
    $cumulative_courses = array_merge($cumulative_courses, $session['courses']);
    
    // Calculate CGPA up to this session
    $cgpa = calculate_cgpa($cumulative_courses);
    
    // Update the session with CGPA
    $chronological_sessions[$index]['cgpa'] = $cgpa;
  }
  
  // Reverse back to most recent first
  $session_stats = array_reverse($chronological_sessions);

  // Calculate overall statistics
  $all_scores = array_column($history, 'final_score');
  $total_credit_units = array_sum(array_column($history, 'credit_unit'));
  $overall_average = count($all_scores) > 0 ? round(array_sum($all_scores) / count($all_scores), 2) : 0;
  $overall_gpa = calculate_gpa($history); // This is actually CGPA since it's across all courses
  $cgpa = calculate_cgpa($history);

  // Find best and worst sessions
  $best_session = null;
  $worst_session = null;
  if (!empty($session_stats)) {
    $best_session = array_reduce($session_stats, function($best, $current) {
      return ($best === null || $current['gpa'] > $best['gpa']) ? $current : $best;
    });
    
    $worst_session = array_reduce($session_stats, function($worst, $current) {
      return ($worst === null || $current['gpa'] < $worst['gpa']) ? $current : $worst;
    });
  }

  // Determine trend based on GPA
  $trend = 'stable';
  if (count($session_stats) >= 2) {
    $recent_gpa = $session_stats[0]['gpa'];
    $previous_gpa = $session_stats[1]['gpa'];
    $difference = $recent_gpa - $previous_gpa;
    
    if ($difference > 0.3) {
      $trend = 'improving';
    } elseif ($difference < -0.3) {
      $trend = 'declining';
    }
  }

  return [
    'sessions' => $session_stats,
    'overall_stats' => [
      'best_session' => $best_session,
      'worst_session' => $worst_session,
      'overall_gpa' => $overall_gpa,
      'cgpa' => $cgpa,
      'overall_average' => $overall_average,
      'total_courses' => count($history),
      'total_credit_units' => $total_credit_units,
      'trend' => $trend
    ]
  ];
}

/**
 * Get student basic information
 */
function get_student_info(mysqli $db, int $student_id): ?array
{
  $sql = "SELECT 
                s.full_name,
                s.matric_no,
                s.level,
                d.dept_name
            FROM students s
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE s.student_id = ?";

  $stmt = $db->prepare($sql);
  if (!$stmt) return null;

  $stmt->bind_param('i', $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $student = $result->fetch_assoc();
  $stmt->close();

  return $student;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_performance_history') {
      $history = get_student_performance_history($mysqli, $student_id);
      $analysis = analyze_performance_by_sessions($history);
      $student_info = get_student_info($mysqli, $student_id);
      
      echo json_encode([
        'student' => $student_info,
        'history' => $history,
        'analysis' => $analysis
      ]);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("Performance History Error: " . $e->getMessage());
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
  <title>Performance History | Student Portal</title>
  <meta name="description" content="Compare semester results and visualize trends" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root { --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2; --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1; --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15); --radius:14px; --transition: all .25s ease; }
    body { font-family:'Inter',sans-serif; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
    .pcoded-navbar{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-right:1px solid rgba(0,0,0,0.05); box-shadow:0 0 30px rgba(0,0,0,0.12); }
    .pcoded-header{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-bottom:1px solid rgba(0,0,0,0.05); box-shadow:0 6px 20px rgba(0,0,0,0.06); }
    .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
    .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .container{ padding: 0 1.25rem; }
    .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
    .grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap: 1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; transition:var(--transition); }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .muted{ color:#718096; font-size:.9rem; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
    .chip{ padding:.18rem .55rem; border-radius:999px; background:#edf2f7; color:#4a5568; font-weight:800; font-size:.75rem; display:inline-block; }
    .chip.excellent{ background:rgba(72,187,120,.12); color:#2f855a; }
    .chip.good{ background:rgba(66,153,225,.12); color:#2c5282; }
    .chip.average{ background:rgba(237,137,54,.12); color:#9c4221; }
    .chip.poor{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .chip.improving{ background:rgba(72,187,120,.12); color:#2f855a; }
    .chip.declining{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .chip.stable{ background:rgba(66,153,225,.12); color:#2c5282; }
    .loading{ text-align:center; padding:2rem; color:#718096; }
    .chart-container{ position:relative; height:320px; }
    .stats-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:.75rem; margin-bottom:1rem; }
    .stat-item{ background:#f8f9fa; padding:.75rem; border-radius:8px; text-align:center; }
    .stat-value{ font-size:1.5rem; font-weight:800; color:#2d3748; }
    .stat-label{ font-size:.8rem; color:#718096; margin-top:.25rem; }
    .trend-indicator{ display:inline-flex; align-items:center; gap:.25rem; }
    .no-data{ text-align:center; padding:3rem; color:#718096; font-style:italic; }
    .gpa-highlight{ background: linear-gradient(135deg, #667eea, #764ba2); color: white; }

    @media (max-width: 768px) {
      .grid{ grid-template-columns: 1fr; }
      .stats-grid{ grid-template-columns: 1fr; }
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
          <li class="nav-item"><a href="my-results.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
          <li class="nav-item"><a href="performance-history.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
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
          <h1>Performance History</h1>
          <p>Compare academic sessions and view improvement trends (5-Point Grading System)</p>
        </div>
      </div>

      <div class="main container">
        <div id="performanceContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading performance history...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let performanceData = null;
    let trendChart = null;

    async function loadPerformanceHistory() {
      try {
        const response = await fetch('?action=get_performance_history');
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        performanceData = data;
        renderPerformanceHistory();
        
      } catch (error) {
        console.error('Error loading performance history:', error);
        document.getElementById('performanceContent').innerHTML = '<div class="no-data">Error loading performance history: ' + error.message + '</div>';
      }
    }

    function renderPerformanceHistory() {
      if (!performanceData) return;

      const { student, analysis } = performanceData;
      const { sessions, overall_stats } = analysis;

      if (sessions.length === 0) {
        document.getElementById('performanceContent').innerHTML = `
          <div class="no-data">
            <i class="fas fa-chart-line" style="font-size: 3rem; color: #cbd5e0; margin-bottom: 1rem;"></i>
            <h3>No Performance History Available</h3>
            <p>Your academic results will appear here once grades are entered by your lecturers.</p>
          </div>
        `;
        return;
      }

      const content = `
        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Performance Trend (5-Point Scale)</div>
            <div class="chart-container">
              <canvas id="trendChart"></canvas>
            </div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:1rem;">Academic Summary</div>
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-value">${overall_stats.overall_average}%</div>
                <div class="stat-label">Overall Average</div>
              </div>
              <div class="stat-item gpa-highlight">
                <div class="stat-value">${overall_stats.cgpa}</div>
                <div class="stat-label">CGPA</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">${overall_stats.total_credit_units}</div>
                <div class="stat-label">Total Units</div>
              </div>
            </div>
            <div class="muted">Best Session: <span class="chip ${getGPAClass(overall_stats.best_session?.gpa)}">${overall_stats.best_session?.name || 'N/A'} ${overall_stats.best_session ? `(${overall_stats.best_session.gpa})` : ''}</span></div>
            <div class="muted" style="margin-top:.5rem;">Worst Session: <span class="chip ${getGPAClass(overall_stats.worst_session?.gpa)}">${overall_stats.worst_session?.name || 'N/A'} ${overall_stats.worst_session ? `(${overall_stats.worst_session.gpa})` : ''}</span></div>
            <div class="muted" style="margin-top:.5rem;">Trend: <span class="chip ${overall_stats.trend}">
              <span class="trend-indicator">
                ${getTrendIcon(overall_stats.trend)}
                ${overall_stats.trend.charAt(0).toUpperCase() + overall_stats.trend.slice(1)}
              </span>
            </span></div>
            <div class="muted" style="margin-top:.5rem;">Total Courses: <span class="chip">${overall_stats.total_courses}</span></div>
          </div>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Academic Session Comparison</div>
          <table>
            <thead>
              <tr>
                <th>Academic Session</th>
                <th>Courses</th>
                <th>Credit Units</th>
                <th>Average Score</th>
                <th>GPA</th>
                <th>CGPA</th>
                <th>Performance</th>
              </tr>
            </thead>
            <tbody>
              ${sessions.map(session => `
                <tr>
                  <td>${escapeHtml(session.name)}</td>
                  <td>${session.course_count}</td>
                  <td>${session.credit_units}</td>
                  <td>${session.average_score}%</td>
                  <td><strong>${session.gpa}</strong></td>
                  <td><strong>${session.cgpa}</strong></td>
                  <td><span class="chip ${getGPAClass(session.gpa)}">${getGPALabel(session.gpa)}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Detailed Course History</div>
          <table>
            <thead>
              <tr>
                <th>Session</th>
                <th>Course</th>
                <th>Credit Units</th>
                <th>Assignment</th>
                <th>Test</th>
                <th>Project</th>
                <th>Exam</th>
                <th>Final Score</th>
                <th>Grade</th>
                <th>Grade Points</th>
              </tr>
            </thead>
            <tbody>
              ${performanceData.history.map(record => `
                <tr>
                  <td>${escapeHtml(record.session_name)}</td>
                  <td>
                    <div style="font-weight:600;">${escapeHtml(record.course_code)}</div>
                    <div class="muted" style="font-size:.8rem;">${escapeHtml(record.course_name)}</div>
                  </td>
                  <td>${record.credit_unit}</td>
                  <td>${record.assignment_score}/20</td>
                  <td>${record.test_score}/20</td>
                  <td>${record.project_score}/20</td>
                  <td>${record.exam_score}/40</td>
                  <td><strong>${record.final_score}%</strong></td>
                  <td><span class="chip ${getGradeClass(record.grade_letter)}">${record.grade_letter}</span></td>
                  <td><strong>${getGradePoints(record.grade_letter)}</strong></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;

      document.getElementById('performanceContent').innerHTML = content;
      
      // Render the trend chart
      setTimeout(() => renderTrendChart(sessions), 100);
    }

    function renderTrendChart(sessions) {
      const ctx = document.getElementById('trendChart');
      if (!ctx) return;

      // Destroy existing chart if it exists
      if (trendChart) {
        trendChart.destroy();
      }

      // Prepare data for chart (reverse order for chronological display)
      const reversedSessions = [...sessions].reverse();
      const labels = reversedSessions.map(s => s.name);
      const averageScores = reversedSessions.map(s => s.average_score);
      const gpas = reversedSessions.map(s => s.gpa);
      const cgpas = reversedSessions.map(s => s.cgpa);

      trendChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Average Score (%)',
              data: averageScores,
              borderColor: '#667eea',
              backgroundColor: 'rgba(102, 126, 234, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: '#667eea',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 6,
              yAxisID: 'y'
            },
            {
              label: 'Session GPA',
              data: gpas,
              borderColor: '#48bb78',
              backgroundColor: 'rgba(72, 187, 120, 0.1)',
              borderWidth: 2,
              fill: false,
              tension: 0.4,
              pointBackgroundColor: '#48bb78',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 4,
              yAxisID: 'y1'
            },
            {
              label: 'CGPA',
              data: cgpas,
              borderColor: '#ed8936',
              backgroundColor: 'rgba(237, 137, 54, 0.1)',
              borderWidth: 2,
              fill: false,
              tension: 0.4,
              pointBackgroundColor: '#ed8936',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 4,
              yAxisID: 'y1'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'top',
              labels: {
                usePointStyle: true,
                padding: 20
              }
            }
          },
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              beginAtZero: true,
              max: 100,
              grid: {
                color: 'rgba(0, 0, 0, 0.1)'
              },
              ticks: {
                callback: function(value) {
                  return value + '%';
                }
              },
              title: {
                display: true,
                text: 'Average Score (%)'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              beginAtZero: true,
              max: 5,
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                callback: function(value) {
                  return value.toFixed(1);
                }
              },
              title: {
                display: true,
                text: 'GPA / CGPA (5.0 Scale)'
              }
            },
            x: {
              grid: {
                color: 'rgba(0, 0, 0, 0.1)'
              }
            }
          },
          elements: {
            point: {
              hoverRadius: 8
            }
          }
        }
      });
    }

    function getGPAClass(gpa) {
      if (!gpa) return '';
      if (gpa >= 4.5) return 'excellent';
      if (gpa >= 3.5) return 'good';
      if (gpa >= 2.5) return 'average';
      return 'poor';
    }

    function getGPALabel(gpa) {
      if (!gpa) return 'N/A';
      if (gpa >= 4.5) return 'Excellent';
      if (gpa >= 3.5) return 'Good';
      if (gpa >= 2.5) return 'Average';
      return 'Needs Improvement';
    }

    function getGradeClass(grade) {
      switch (grade.toUpperCase()) {
        case 'A': return 'excellent';
        case 'B': return 'good';
        case 'C': return 'average';
        case 'D': case 'E': return 'poor';
        case 'F': return 'poor';
        default: return '';
      }
    }

    function getGradePoints(grade) {
      switch (grade.toUpperCase()) {
        case 'A': return 5.0;
        case 'B': return 4.0;
        case 'C': return 3.0;
        case 'D': return 2.0;
        case 'E': return 1.0;
        case 'F': return 0.0;
        default: return 0.0;
      }
    }

    function getTrendIcon(trend) {
      switch (trend) {
        case 'improving': return '<i class="fas fa-arrow-up"></i>';
        case 'declining': return '<i class="fas fa-arrow-down"></i>';
        case 'stable': return '<i class="fas fa-minus"></i>';
        default: return '<i class="fas fa-minus"></i>';
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Initialize on page load
    window.addEventListener('load', () => {
      loadPerformanceHistory();
    });
  </script>
</body>
</html>
</qodoArtifact>