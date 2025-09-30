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
 * Get student's historical performance data for prediction
 */
function get_student_performance_data(mysqli $db, int $student_id): array
{
  try {
    $sql = "SELECT 
                  r.final_score,
                  r.assignment_score,
                  r.test_score,
                  r.project_score,
                  r.exam_score,
                  r.discipline_score,
                  r.punctuality_score,
                  r.teamwork_score,
                  r.created_at,
                  c.course_code,
                  c.course_name,
                  c.credit_unit,
                  c.semester,
                  COALESCE(acs.session_name, 'Current Session') as session_name
              FROM results r
              INNER JOIN courses c ON r.course_id = c.course_id
              LEFT JOIN academic_sessions acs ON acs.is_active = 1
              WHERE r.student_id = ?
              ORDER BY r.created_at DESC
              LIMIT 10";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $performance_data = [];
    while ($row = $result->fetch_assoc()) {
      $final_score = (float)($row['final_score'] ?? 0);
      $calculated_grade = calculate_grade_letter($final_score);

      $performance_data[] = [
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'session_name' => $row['session_name'],
        'semester' => $row['semester'],
        'credit_unit' => (int)$row['credit_unit'],
        'final_score' => $final_score,
        'grade_letter' => $calculated_grade,
        'assignment_score' => (float)($row['assignment_score'] ?? 0),
        'test_score' => (float)($row['test_score'] ?? 0),
        'project_score' => (float)($row['project_score'] ?? 0),
        'exam_score' => (float)($row['exam_score'] ?? 0),
        'discipline_score' => (float)($row['discipline_score'] ?? 0),
        'punctuality_score' => (float)($row['punctuality_score'] ?? 0),
        'teamwork_score' => (float)($row['teamwork_score'] ?? 0),
        'created_at' => $row['created_at']
      ];
    }
    $stmt->close();
    return $performance_data;
  } catch (Exception $e) {
    error_log("Error in get_student_performance_data: " . $e->getMessage());
    return [];
  }
}

/**
 * Advanced prediction algorithm using multiple factors
 */
function predict_future_performance(array $performance_data): array
{
  if (empty($performance_data)) {
    return [
      'predicted_score' => 0,
      'predicted_grade' => 'F',
      'confidence' => 'Low',
      'trend' => 'stable',
      'recommendations' => ['No historical data available for prediction']
    ];
  }

  // Calculate trend analysis
  $scores = array_column($performance_data, 'final_score');
  $recent_scores = array_slice($scores, 0, 5); // Last 5 results
  $older_scores = array_slice($scores, 5, 5); // Previous 5 results

  $recent_avg = count($recent_scores) > 0 ? array_sum($recent_scores) / count($recent_scores) : 0;
  $older_avg = count($older_scores) > 0 ? array_sum($older_scores) / count($older_scores) : $recent_avg;

  // Determine trend
  $trend_diff = $recent_avg - $older_avg;
  $trend = 'stable';
  if ($trend_diff > 5) $trend = 'improving';
  elseif ($trend_diff < -5) $trend = 'declining';

  // Weighted prediction based on recency and trend
  $weights = [];
  $weighted_sum = 0;
  $total_weight = 0;

  foreach ($performance_data as $index => $record) {
    // More recent results have higher weight
    $recency_weight = 1 / (1 + $index * 0.2);
    
    // Credit unit weight (more important courses have higher impact)
    $credit_weight = $record['credit_unit'] / 3.0;
    
    // Combined weight
    $weight = $recency_weight * $credit_weight;
    $weights[] = $weight;
    
    $weighted_sum += $record['final_score'] * $weight;
    $total_weight += $weight;
  }

  $base_prediction = $total_weight > 0 ? $weighted_sum / $total_weight : 0;

  // Apply trend adjustment
  $trend_adjustment = 0;
  if ($trend === 'improving') {
    $trend_adjustment = min(10, abs($trend_diff) * 0.5);
  } elseif ($trend === 'declining') {
    $trend_adjustment = -min(10, abs($trend_diff) * 0.5);
  }

  $predicted_score = max(0, min(100, $base_prediction + $trend_adjustment));
  $predicted_grade = calculate_grade_letter($predicted_score);

  // Calculate confidence based on consistency and data points
  $score_variance = 0;
  if (count($scores) > 1) {
    $mean = array_sum($scores) / count($scores);
    $score_variance = array_sum(array_map(function($x) use ($mean) { 
      return pow($x - $mean, 2); 
    }, $scores)) / count($scores);
  }

  $consistency_factor = max(0, 1 - ($score_variance / 400)); // Normalize variance
  $data_factor = min(1, count($performance_data) / 8); // More data = higher confidence

  $confidence_score = ($consistency_factor + $data_factor) / 2;
  $confidence = $confidence_score > 0.7 ? 'High' : ($confidence_score > 0.4 ? 'Medium' : 'Low');

  // Generate intelligent recommendations
  $recommendations = generate_recommendations($performance_data, $trend, $predicted_score);

  return [
    'predicted_score' => round($predicted_score, 1),
    'predicted_grade' => $predicted_grade,
    'confidence' => $confidence,
    'confidence_score' => round($confidence_score * 100, 1),
    'trend' => $trend,
    'trend_diff' => round($trend_diff, 1),
    'recommendations' => $recommendations,
    'base_prediction' => round($base_prediction, 1),
    'trend_adjustment' => round($trend_adjustment, 1)
  ];
}

/**
 * Generate intelligent recommendations based on performance analysis
 */
function generate_recommendations(array $performance_data, string $trend, float $predicted_score): array
{
  $recommendations = [];
  
  if (empty($performance_data)) {
    return ['Focus on building a strong academic foundation'];
  }

  // Analyze component weaknesses
  $avg_assignment = array_sum(array_column($performance_data, 'assignment_score')) / count($performance_data);
  $avg_test = array_sum(array_column($performance_data, 'test_score')) / count($performance_data);
  $avg_project = array_sum(array_column($performance_data, 'project_score')) / count($performance_data);
  $avg_exam = array_sum(array_column($performance_data, 'exam_score')) / count($performance_data);
  $avg_discipline = array_sum(array_column($performance_data, 'discipline_score')) / count($performance_data);
  $avg_punctuality = array_sum(array_column($performance_data, 'punctuality_score')) / count($performance_data);
  $avg_teamwork = array_sum(array_column($performance_data, 'teamwork_score')) / count($performance_data);

  // Academic component recommendations
  if ($avg_assignment < 15) {
    $recommendations[] = "Improve assignment performance - currently averaging " . round($avg_assignment, 1) . "/20";
  }
  if ($avg_test < 15) {
    $recommendations[] = "Focus on test preparation - currently averaging " . round($avg_test, 1) . "/20";
  }
  if ($avg_project < 15) {
    $recommendations[] = "Enhance project quality and submission - currently averaging " . round($avg_project, 1) . "/20";
  }
  if ($avg_exam < 30) {
    $recommendations[] = "Strengthen exam performance through better preparation - currently averaging " . round($avg_exam, 1) . "/40";
  }

  // Behavioral component recommendations
  if ($avg_discipline < 80) {
    $recommendations[] = "Improve classroom discipline and behavior - currently at " . round($avg_discipline, 1) . "%";
  }
  if ($avg_punctuality < 80) {
    $recommendations[] = "Work on punctuality and attendance - currently at " . round($avg_punctuality, 1) . "%";
  }
  if ($avg_teamwork < 80) {
    $recommendations[] = "Enhance teamwork and collaboration skills - currently at " . round($avg_teamwork, 1) . "%";
  }

  // Trend-based recommendations
  if ($trend === 'declining') {
    $recommendations[] = "Your performance is declining - consider seeking academic support or tutoring";
    $recommendations[] = "Review your study methods and time management strategies";
  } elseif ($trend === 'improving') {
    $recommendations[] = "Great improvement trend! Maintain your current study approach";
    $recommendations[] = "Consider challenging yourself with additional academic activities";
  }

  // Score-based recommendations
  if ($predicted_score < 50) {
    $recommendations[] = "Focus on fundamental concepts and seek immediate academic intervention";
    $recommendations[] = "Consider forming study groups with high-performing classmates";
  } elseif ($predicted_score < 70) {
    $recommendations[] = "You're on track for average performance - push for excellence";
    $recommendations[] = "Identify and strengthen your weakest subject areas";
  } else {
    $recommendations[] = "Excellent trajectory! Maintain consistency and aim for academic leadership";
  }

  // Ensure we have at least some recommendations
  if (empty($recommendations)) {
    $recommendations[] = "Continue your current academic approach";
    $recommendations[] = "Maintain consistent study habits and attendance";
  }

  return array_slice($recommendations, 0, 5); // Limit to 5 recommendations
}

/**
 * Calculate what-if scenario predictions
 */
function calculate_what_if_scenario(array $performance_data, float $behavior_improvement, float $academic_improvement): array
{
  if (empty($performance_data)) {
    return [
      'projected_score' => 0,
      'projected_grade' => 'F',
      'improvement' => 0
    ];
  }

  // Get current averages
  $current_avg = array_sum(array_column($performance_data, 'final_score')) / count($performance_data);
  $current_behavior_avg = (
    array_sum(array_column($performance_data, 'discipline_score')) +
    array_sum(array_column($performance_data, 'punctuality_score')) +
    array_sum(array_column($performance_data, 'teamwork_score'))
  ) / (count($performance_data) * 3);

  $current_academic_avg = (
    array_sum(array_column($performance_data, 'assignment_score')) +
    array_sum(array_column($performance_data, 'test_score')) +
    array_sum(array_column($performance_data, 'project_score')) +
    array_sum(array_column($performance_data, 'exam_score'))
  ) / count($performance_data);

  // Calculate improvements
  $behavior_boost = ($current_behavior_avg * $behavior_improvement / 100) * 0.3; // 30% weight for behavior
  $academic_boost = ($current_academic_avg * $academic_improvement / 100) * 0.7; // 70% weight for academics

  $projected_score = min(100, $current_avg + $behavior_boost + $academic_boost);
  $projected_grade = calculate_grade_letter($projected_score);
  $improvement = $projected_score - $current_avg;

  return [
    'projected_score' => round($projected_score, 1),
    'projected_grade' => $projected_grade,
    'improvement' => round($improvement, 1),
    'current_avg' => round($current_avg, 1),
    'behavior_boost' => round($behavior_boost, 1),
    'academic_boost' => round($academic_boost, 1)
  ];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_prediction_data') {
      $performance_data = get_student_performance_data($mysqli, $student_id);
      $prediction = predict_future_performance($performance_data);
      
      echo json_encode([
        'performance_data' => $performance_data,
        'prediction' => $prediction
      ]);
      exit;
    }

    if ($_GET['action'] === 'calculate_what_if' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      
      $behavior_improvement = (float)($input['behavior_improvement'] ?? 0);
      $academic_improvement = (float)($input['academic_improvement'] ?? 0);
      
      $performance_data = get_student_performance_data($mysqli, $student_id);
      $scenario = calculate_what_if_scenario($performance_data, $behavior_improvement, $academic_improvement);
      
      echo json_encode($scenario);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("Predicted Grade Error: " . $e->getMessage());
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
  <title>Predicted Future Grade | Student Portal</title>
  <meta name="description" content="AI-powered grade prediction based on your performance history" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
    .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .container{ padding: 0 1.25rem; }
    .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
    .grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap: 1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; transition:var(--transition); }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:.75rem; }
    .select, .input{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .select:focus, .input:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.65rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn:hover{ transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.15); }
    .muted{ color:#718096; font-size:.9rem; }
    .pill{ padding:.18rem .55rem; border-radius:999px; font-weight:800; font-size:.75rem; display:inline-block; }
    .pill.high{ background:rgba(72,187,120,.12); color:#2f855a; }
    .pill.medium{ background:rgba(237,137,54,.12); color:#9c4221; }
    .pill.low{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .pill.improving{ background:rgba(72,187,120,.12); color:#2f855a; }
    .pill.declining{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .pill.stable{ background:rgba(66,153,225,.12); color:#2c5282; }
    .prediction-score{ font-size:3rem; font-weight:900; color:#2d3748; margin:.25rem 0; }
    .projected-score{ font-size:2.2rem; font-weight:900; color:#2d3748; }
    .loading{ text-align:center; padding:2rem; color:#718096; }
    .no-data{ text-align:center; padding:3rem; color:#718096; font-style:italic; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
    .grade-badge{ padding:.18rem .5rem; border-radius:999px; font-size:.75rem; font-weight:800; }
    .grade-A{ background:rgba(72,187,120,.12); color:#2f855a; }
    .grade-B{ background:rgba(66,153,225,.12); color:#2c5282; }
    .grade-C{ background:rgba(237,137,54,.12); color:#9c4221; }
    .grade-D, .grade-E{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .grade-F{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .stats-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:.75rem; margin-bottom:1rem; }
    .stat-item{ background:#f8f9fa; padding:.75rem; border-radius:8px; text-align:center; }
    .stat-value{ font-size:1.2rem; font-weight:800; color:#2d3748; }
    .stat-label{ font-size:.8rem; color:#718096; margin-top:.25rem; }

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
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
    
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
          <h1>Predicted Future Grade</h1>
          <p>AI-powered prediction based on your performance history and trends</p>
        </div>
      </div>

      <div class="main container">
        <div id="mainContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Analyzing your performance data...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let predictionData = null;

    async function loadPredictionData() {
      try {
        const response = await fetch('?action=get_prediction_data');
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        predictionData = data;
        renderPredictionInterface();
        
      } catch (error) {
        console.error('Error loading prediction data:', error);
        document.getElementById('mainContent').innerHTML = '<div class="no-data">Error loading prediction data: ' + error.message + '</div>';
      }
    }

    function renderPredictionInterface() {
      if (!predictionData) return;

      const { performance_data, prediction } = predictionData;

      if (performance_data.length === 0) {
        document.getElementById('mainContent').innerHTML = `
          <div class="no-data">
            <i class="fas fa-crystal-ball" style="font-size: 3rem; color: #cbd5e0; margin-bottom: 1rem;"></i>
            <h3>No Performance History Available</h3>
            <p>Grade predictions require historical performance data. Complete some courses to see predictions.</p>
          </div>
        `;
        return;
      }

      const content = `
        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">
              <i class="fas fa-brain"></i> AI Prediction Analysis
            </div>
            <div class="muted">Based on ${performance_data.length} recent results, your predicted next performance:</div>
            <div class="prediction-score" id="predicted">${prediction.predicted_grade}</div>
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-value">${prediction.predicted_score}%</div>
                <div class="stat-label">Predicted Score</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">
                  <span class="pill ${prediction.confidence.toLowerCase()}">${prediction.confidence}</span>
                </div>
                <div class="stat-label">Confidence (${prediction.confidence_score}%)</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">
                  <span class="pill ${prediction.trend}">${prediction.trend}</span>
                </div>
                <div class="stat-label">Performance Trend</div>
              </div>
            </div>
            <div class="muted" style="margin-top:.6rem;">
              <strong>Intelligent Recommendations:</strong>
            </div>
            <ul id="suggestList" style="margin:.35rem 0 0 1rem; color:#4a5568;">
              ${prediction.recommendations.map(rec => `<li>${rec}</li>`).join('')}
            </ul>
          </div>
          
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">
              <i class="fas fa-calculator"></i> What-if Scenario
            </div>
            <div class="toolbar">
              <div class="muted">If my behavior improves by</div>
              <select id="deltaBehavior" class="select">
                <option value="0">0%</option>
                <option value="5">+5%</option>
                <option value="10" selected>+10%</option>
                <option value="15">+15%</option>
                <option value="20">+20%</option>
                <option value="25">+25%</option>
              </select>
            </div>
            <div class="toolbar">
              <div class="muted">and my academics improve by</div>
              <select id="deltaAcademic" class="select">
                <option value="0">0%</option>
                <option value="5">+5%</option>
                <option value="10">+10%</option>
                <option value="15" selected>+15%</option>
                <option value="20">+20%</option>
                <option value="25">+25%</option>
              </select>
            </div>
            <button class="btn btn-primary" onclick="recomputeScenario()">
              <i class="fas fa-magic"></i> Recompute Prediction
            </button>
            <div class="muted" style="margin-top:1rem;">Projected final percentage:</div>
            <div class="projected-score" id="projected">${prediction.predicted_score}%</div>
            <div class="muted" id="improvementText">Improvement: +0%</div>
          </div>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">
            <i class="fas fa-history"></i> Recent Performance History (Prediction Basis)
          </div>
          <div style="overflow:auto;">
            <table>
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Session</th>
                  <th>Semester</th>
                  <th>Final Score</th>
                  <th>Grade</th>
                  <th>Grade Points</th>
                </tr>
              </thead>
              <tbody id="recentBody">
                ${performance_data.map(record => `
                  <tr>
                    <td>
                      <strong>${escapeHtml(record.course_code)}</strong><br>
                      <small class="muted">${escapeHtml(record.course_name)}</small>
                    </td>
                    <td>${escapeHtml(record.session_name)}</td>
                    <td>${escapeHtml(record.semester)}</td>
                    <td><strong>${record.final_score}%</strong></td>
                    <td><span class="grade-badge grade-${record.grade_letter}">${record.grade_letter}</span></td>
                    <td><strong>${getGradePoints(record.grade_letter)}</strong></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">
            <i class="fas fa-chart-line"></i> Prediction Algorithm Details
          </div>
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-value">${prediction.base_prediction}%</div>
              <div class="stat-label">Base Prediction</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${prediction.trend_adjustment > 0 ? '+' : ''}${prediction.trend_adjustment}%</div>
              <div class="stat-label">Trend Adjustment</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${prediction.trend_diff > 0 ? '+' : ''}${prediction.trend_diff}%</div>
              <div class="stat-label">Recent vs Previous</div>
            </div>
          </div>
          <div class="muted">
            <strong>Algorithm:</strong> Uses weighted average of recent performance with recency bias, 
            credit unit weighting, trend analysis, and confidence scoring based on consistency and data volume.
          </div>
        </div>
      `;

      document.getElementById('mainContent').innerHTML = content;
    }

    async function recomputeScenario() {
      const behaviorImprovement = Number(document.getElementById('deltaBehavior').value) || 0;
      const academicImprovement = Number(document.getElementById('deltaAcademic').value) || 0;

      try {
        const response = await fetch('?action=calculate_what_if', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            behavior_improvement: behaviorImprovement,
            academic_improvement: academicImprovement
          })
        });

        const scenario = await response.json();

        if (scenario.error) {
          throw new Error(scenario.error);
        }

        document.getElementById('projected').textContent = scenario.projected_score + '%';
        document.getElementById('predicted').textContent = scenario.projected_grade;
        
        const improvementText = scenario.improvement > 0 
          ? `Improvement: +${scenario.improvement}%` 
          : scenario.improvement < 0 
            ? `Change: ${scenario.improvement}%` 
            : 'No change';
        
        document.getElementById('improvementText').textContent = improvementText;
        document.getElementById('improvementText').style.color = scenario.improvement > 0 ? '#48bb78' : scenario.improvement < 0 ? '#f56565' : '#718096';

      } catch (error) {
        console.error('Error calculating scenario:', error);
        alert('Error calculating scenario: ' + error.message);
      }
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

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Initialize on page load
    window.addEventListener('load', () => {
      loadPredictionData();
    });
  </script>
</body>
</html>