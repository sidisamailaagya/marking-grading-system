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
 * Get comprehensive student profile data
 */
function get_student_profile(mysqli $db, int $student_id): ?array
{
  try {
    // First, let's check if the student exists
    $check_sql = "SELECT student_id FROM students WHERE student_id = ?";
    $check_stmt = $db->prepare($check_sql);
    if (!$check_stmt) {
      error_log("Failed to prepare check query: " . $db->error);
      return null;
    }
    
    $check_stmt->bind_param('i', $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
      error_log("Student with ID $student_id not found in database");
      $check_stmt->close();
      return null;
    }
    $check_stmt->close();

    // Check if departments and faculties tables exist
    $dept_exists = $db->query("SHOW TABLES LIKE 'departments'")->num_rows > 0;
    $faculty_exists = $db->query("SHOW TABLES LIKE 'faculties'")->num_rows > 0;
    
    if ($dept_exists && $faculty_exists) {
      $sql = "SELECT 
                    s.student_id,
                    s.full_name,
                    s.matric_no,
                    s.email,
                    s.level,
                    s.dept_id,
                    s.created_at,
                    COALESCE(d.dept_name, 'Unknown Department') as dept_name,
                    COALESCE(d.faculty_id, 0) as faculty_id,
                    COALESCE(f.faculty_name, 'Unknown Faculty') as faculty_name
                FROM students s
                LEFT JOIN departments d ON s.dept_id = d.dept_id
                LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
                WHERE s.student_id = ?";
    } else {
      // Fallback query without joins
      $sql = "SELECT 
                    s.student_id,
                    s.full_name,
                    s.matric_no,
                    s.email,
                    s.level,
                    s.dept_id,
                    s.created_at,
                    'Unknown Department' as dept_name,
                    0 as faculty_id,
                    'Unknown Faculty' as faculty_name
                FROM students s
                WHERE s.student_id = ?";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare profile query: " . $db->error);
      return null;
    }

    $stmt->bind_param('i', $student_id);
    if (!$stmt->execute()) {
      error_log("Failed to execute profile query: " . $stmt->error);
      $stmt->close();
      return null;
    }
    
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
      error_log("No student data returned for ID: $student_id");
      return null;
    }

    // Add phone field (not in current table structure)
    $student['phone'] = '';

    return $student;
  } catch (Exception $e) {
    error_log("Error in get_student_profile: " . $e->getMessage());
    return null;
  }
}

/**
 * Get student's academic statistics for profile overview
 */
function get_profile_statistics(mysqli $db, int $student_id): array
{
  try {
    // Check if results table exists
    $results_exists = $db->query("SHOW TABLES LIKE 'results'")->num_rows > 0;
    
    if (!$results_exists) {
      return [
        'total_courses' => 0,
        'average_score' => 0,
        'total_credits' => 0,
        'gpa' => 0.0,
        'last_result_date' => null
      ];
    }

    $sql = "SELECT 
                  COUNT(*) as total_courses,
                  AVG(r.final_score) as average_score,
                  SUM(COALESCE(c.credit_unit, 3)) as total_credits,
                  MAX(r.created_at) as last_result_date
              FROM results r
              LEFT JOIN courses c ON r.course_id = c.course_id
              WHERE r.student_id = ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare stats query: " . $db->error);
      return [];
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    // Calculate GPA
    $gpa_sql = "SELECT 
                      r.final_score,
                      COALESCE(c.credit_unit, 3) as credit_unit
                  FROM results r
                  LEFT JOIN courses c ON r.course_id = c.course_id
                  WHERE r.student_id = ?";

    $stmt = $db->prepare($gpa_sql);
    if (!$stmt) {
      error_log("Failed to prepare GPA query: " . $db->error);
      return [];
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_points = 0;
    $total_units = 0;

    while ($row = $result->fetch_assoc()) {
      $final_score = (float)($row['final_score'] ?? 0);
      $grade_points = 0;
      
      if ($final_score >= 70) $grade_points = 5.0;
      elseif ($final_score >= 60) $grade_points = 4.0;
      elseif ($final_score >= 50) $grade_points = 3.0;
      elseif ($final_score >= 45) $grade_points = 2.0;
      elseif ($final_score >= 40) $grade_points = 1.0;
      
      $units = (int)($row['credit_unit'] ?? 3);
      $total_points += $grade_points * $units;
      $total_units += $units;
    }
    $stmt->close();

    $gpa = $total_units > 0 ? $total_points / $total_units : 0.0;

    return [
      'total_courses' => (int)($stats['total_courses'] ?? 0),
      'average_score' => round((float)($stats['average_score'] ?? 0), 1),
      'total_credits' => (int)($stats['total_credits'] ?? 0),
      'gpa' => round($gpa, 2),
      'last_result_date' => $stats['last_result_date']
    ];
  } catch (Exception $e) {
    error_log("Error in get_profile_statistics: " . $e->getMessage());
    return [
      'total_courses' => 0,
      'average_score' => 0,
      'total_credits' => 0,
      'gpa' => 0.0,
      'last_result_date' => null
    ];
  }
}

/**
 * Update student email
 */
function update_student_email(mysqli $db, int $student_id, string $new_email): array
{
  try {
    // Validate email format
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
      return ['success' => false, 'message' => 'Invalid email format'];
    }

    // Check if email already exists for another student
    $sql = "SELECT student_id FROM students WHERE email = ? AND student_id != ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return ['success' => false, 'message' => 'Database error occurred'];
    }

    $stmt->bind_param('si', $new_email, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      $stmt->close();
      return ['success' => false, 'message' => 'Email already exists for another student'];
    }
    $stmt->close();

    // Update email
    $sql = "UPDATE students SET email = ? WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return ['success' => false, 'message' => 'Database error occurred'];
    }

    $stmt->bind_param('si', $new_email, $student_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
      return ['success' => true, 'message' => 'Email updated successfully'];
    } else {
      return ['success' => false, 'message' => 'Failed to update email'];
    }
  } catch (Exception $e) {
    error_log("Error in update_student_email: " . $e->getMessage());
    return ['success' => false, 'message' => 'Server error occurred'];
  }
}

/**
 * Change student password
 */
function change_student_password(mysqli $db, int $student_id, string $current_password, string $new_password): array
{
  try {
    // Get current password hash
    $sql = "SELECT password FROM students WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return ['success' => false, 'message' => 'Database error occurred'];
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
      return ['success' => false, 'message' => 'Student not found'];
    }

    // Verify current password
    if (!password_verify($current_password, $student['password'])) {
      return ['success' => false, 'message' => 'Current password is incorrect'];
    }

    // Validate new password (simplified validation)
    if (strlen($new_password) < 6) {
      return ['success' => false, 'message' => 'New password must be at least 6 characters long'];
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $sql = "UPDATE students SET password = ? WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return ['success' => false, 'message' => 'Database error occurred'];
    }

    $stmt->bind_param('si', $hashed_password, $student_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
      return ['success' => true, 'message' => 'Password updated successfully'];
    } else {
      return ['success' => false, 'message' => 'Failed to update password'];
    }
  } catch (Exception $e) {
    error_log("Error in change_student_password: " . $e->getMessage());
    return ['success' => false, 'message' => 'Server error occurred'];
  }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_profile_data') {
      // Debug information
      error_log("Getting profile for student ID: $student_id");
      error_log("Session data: " . print_r($_SESSION, true));
      
      $profile = get_student_profile($mysqli, $student_id);
      $stats = get_profile_statistics($mysqli, $student_id);
      
      if (!$profile) {
        error_log("Profile not found for student ID: $student_id");
        echo json_encode(['error' => 'Student profile not found. Please check your login status.']);
        exit;
      }

      echo json_encode([
        'profile' => $profile,
        'stats' => $stats
      ]);
      exit;
    }

    if ($_GET['action'] === 'update_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      
      if (!$input || !isset($input['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
      }

      $result = update_student_email($mysqli, $student_id, $input['email']);
      echo json_encode($result);
      exit;
    }

    if ($_GET['action'] === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      
      if (!$input || !isset($input['current_password']) || !isset($input['new_password'])) {
        echo json_encode(['success' => false, 'message' => 'Current and new passwords are required']);
        exit;
      }

      $result = change_student_password($mysqli, $student_id, $input['current_password'], $input['new_password']);
      echo json_encode($result);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("Profile Settings Error: " . $e->getMessage());
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
  <title>Profile Settings | Student Portal</title>
  <meta name="description" content="View details and update email & password" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #667eea; --primary-dark: #5a67d8; --secondary-color: #764ba2;
      --success-color: #48bb78; --warning-color: #ed8936; --danger-color: #f56565; --info-color: #4299e1;
      --light-bg: #f8fafc; --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
      --radius: 14px; --transition: all .25s ease;
    }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    .pcoded-navbar { background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(10px); border-right: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 0 30px rgba(0, 0, 0, 0.12); }
    .pcoded-header { background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06); }
    .page-hero { background: linear-gradient(135deg, rgba(102, 126, 234, .92), rgba(118, 75, 162, .92)); color: #fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
    .page-hero::before { content: ''; position: absolute; inset: 0; opacity: .15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .container { padding: 0 1.25rem; }
    .main { max-width: 1400px; margin: 0 auto; padding: 1.25rem; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .card { background: #fff; border-radius: var(--radius); box-shadow: var(--card-shadow); padding: 1.5rem; transition: var(--transition); }
    .card:hover { box-shadow: var(--card-shadow-hover); }
    .section-title { font-weight: 800; color: #2d3748; margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
    .profile-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding: 1rem; background: linear-gradient(135deg, #f8fafc, #edf2f7); border-radius: 12px; }
    .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2rem; font-weight: 800; }
    .profile-info h3 { margin: 0 0 .25rem 0; color: #2d3748; font-weight: 800; }
    .profile-info p { margin: 0; color: #718096; font-size: .9rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin-bottom: 1.5rem; }
    .stat-item { background: #f8f9fa; padding: .75rem; border-radius: 8px; text-align: center; }
    .stat-value { font-size: 1.2rem; font-weight: 800; color: #2d3748; }
    .stat-label { font-size: .8rem; color: #718096; margin-top: .25rem; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; margin-bottom: .5rem; font-weight: 600; color: #374151; }
    .input { padding: .75rem; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; width: 100%; font-size: .9rem; }
    .input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(102, 126, 234, .12); }
    .input[readonly] { background: #f8fafc; color: #475569; cursor: not-allowed; }
    .btn { padding: .75rem 1rem; border: none; border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; transition: var(--transition); text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #667eea, #5a67d8); color: #fff; }
    .btn-outline { background: #fff; color: #4a5568; border: 1px solid #e2e8f0; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); }
    .btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .muted { color: #718096; font-size: .85rem; }
    .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; animation: slideIn 0.3s ease-out; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .loading { text-align: center; padding: 2rem; color: #718096; }
    .debug { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; margin-bottom: 1rem; padding: 1rem; border-radius: 10px; }
    .password-requirements { background: #f8f9fa; padding: .75rem; border-radius: 8px; margin-top: .5rem; }
    .password-requirements ul { margin: .5rem 0 0 1rem; }
    .password-requirements li { font-size: .85rem; color: #6b7280; margin-bottom: .25rem; }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1100px) {
      .grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .stats-grid { grid-template-columns: 1fr; }
      .profile-header { flex-direction: column; text-align: center; }
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
          <li class="nav-item"><a href="performance-history.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
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
          <h1>Profile Settings</h1>
          <p>Manage your personal information and account settings</p>
        </div>
      </div>

      <div class="main container">
        <div id="alertContainer"></div>
        
        <div id="mainContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading your profile...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let profileData = null;

    async function loadProfileData() {
      try {
        console.log('Loading profile data...');
        const response = await fetch('?action=get_profile_data');
        
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
        
        profileData = data;
        renderProfile();
        
      } catch (error) {
        console.error('Error loading profile data:', error);
        document.getElementById('mainContent').innerHTML = `
          <div class="debug">
            <h4>Debug Information</h4>
            <p><strong>Error:</strong> ${error.message}</p>
            <p><strong>Check browser console for more details</strong></p>
            <p><strong>Possible causes:</strong></p>
            <ul>
              <li>Student ID not found in database</li>
              <li>Session expired or invalid</li>
              <li>Database connection issues</li>
              <li>Missing database tables</li>
            </ul>
            <p><strong>Try:</strong> <a href="../logout.php">Logout and login again</a></p>
          </div>
          <div class="loading">Error loading profile: ${error.message}</div>
        `;
      }
    }

    function renderProfile() {
      if (!profileData) return;

      const { profile, stats } = profileData;

      const content = `
        <!-- Profile Header -->
        <div class="card">
          <div class="profile-header">
            <div class="profile-avatar">
              ${profile.full_name.charAt(0).toUpperCase()}
            </div>
            <div class="profile-info">
              <h3>${escapeHtml(profile.full_name)}</h3>
              <p><strong>Matric No:</strong> ${escapeHtml(profile.matric_no)}</p>
              <p><strong>Department:</strong> ${escapeHtml(profile.dept_name)} • <strong>Faculty:</strong> ${escapeHtml(profile.faculty_name)}</p>
              <p><strong>Level:</strong> ${profile.level} • <strong>Member since:</strong> ${formatDate(profile.created_at)}</p>
            </div>
          </div>
          
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-value">${stats.gpa || '0.00'}</div>
              <div class="stat-label">Current GPA</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.total_courses || 0}</div>
              <div class="stat-label">Completed Courses</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.total_credits || 0}</div>
              <div class="stat-label">Credit Units</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.average_score || 0}%</div>
              <div class="stat-label">Average Score</div>
            </div>
          </div>
        </div>

        <div class="grid">
          <!-- Personal Information -->
          <div class="card">
            <div class="section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input class="input" value="${escapeHtml(profile.full_name)}" readonly />
              <div class="muted">Managed by the school administration</div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Matric Number</label>
              <input class="input" value="${escapeHtml(profile.matric_no)}" readonly />
              <div class="muted">Unique student identifier</div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Department</label>
              <input class="input" value="${escapeHtml(profile.dept_name)}" readonly />
            </div>
            
            <div class="form-group">
              <label class="form-label">Faculty</label>
              <input class="input" value="${escapeHtml(profile.faculty_name)}" readonly />
            </div>
            
            <div class="form-group">
              <label class="form-label">Current Level</label>
              <input class="input" value="Level ${profile.level}" readonly />
            </div>
          </div>

          <!-- Contact Information -->
          <div class="card">
            <div class="section-title"><i class="fas fa-envelope"></i> Contact Information</div>
            
            <form id="contactForm">
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input id="email" class="input" type="email" value="${escapeHtml(profile.email || '')}" placeholder="Enter your email address" />
                <div class="muted">Used for important notifications and communications</div>
              </div>
              
              <div style="display: flex; gap: .5rem;">
                <button type="button" class="btn btn-primary" onclick="updateEmail()">
                  <i class="fas fa-save"></i> Update Email
                </button>
                <button type="button" class="btn btn-outline" onclick="resetEmail()">
                  <i class="fas fa-undo"></i> Reset
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Password Change -->
        <div class="card">
          <div class="section-title"><i class="fas fa-key"></i> Change Password</div>
          
          <form id="passwordForm">
            <div class="grid">
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <input id="currentPassword" class="input" type="password" placeholder="Enter current password" autocomplete="current-password" />
              </div>
              <div></div>
              
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input id="newPassword" class="input" type="password" placeholder="Enter new password" autocomplete="new-password" />
              </div>
              
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input id="confirmPassword" class="input" type="password" placeholder="Confirm new password" autocomplete="new-password" />
              </div>
            </div>
            
            <div class="password-requirements">
              <strong>Password Requirements:</strong>
              <ul>
                <li>At least 6 characters long</li>
                <li>Must match the confirmation password</li>
              </ul>
            </div>
            
            <div style="margin-top: 1rem; display: flex; gap: .5rem;">
              <button type="button" class="btn btn-primary" onclick="changePassword()">
                <i class="fas fa-key"></i> Change Password
              </button>
              <button type="button" class="btn btn-outline" onclick="clearPasswordForm()">
                <i class="fas fa-times"></i> Clear Form
              </button>
            </div>
          </form>
        </div>
      `;

      document.getElementById('mainContent').innerHTML = content;
    }

    async function updateEmail() {
      const email = document.getElementById('email').value.trim();

      if (!email) {
        showAlert('Email address is required', 'error');
        return;
      }

      if (!isValidEmail(email)) {
        showAlert('Please enter a valid email address', 'error');
        return;
      }

      try {
        const response = await fetch('?action=update_email', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ email: email })
        });

        const result = await response.json();

        if (result.success) {
          showAlert(result.message, 'success');
          profileData.profile.email = email;
        } else {
          showAlert(result.message, 'error');
        }

      } catch (error) {
        console.error('Update email error:', error);
        showAlert('Failed to update email. Please try again.', 'error');
      }
    }

    async function changePassword() {
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if (!currentPassword) {
        showAlert('Please enter your current password', 'error');
        return;
      }

      if (!newPassword) {
        showAlert('Please enter a new password', 'error');
        return;
      }

      if (newPassword.length < 6) {
        showAlert('New password must be at least 6 characters long', 'error');
        return;
      }

      if (newPassword !== confirmPassword) {
        showAlert('New passwords do not match', 'error');
        return;
      }

      try {
        const response = await fetch('?action=change_password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword
          })
        });

        const result = await response.json();

        if (result.success) {
          showAlert(result.message, 'success');
          clearPasswordForm();
        } else {
          showAlert(result.message, 'error');
        }

      } catch (error) {
        console.error('Password change error:', error);
        showAlert('Failed to change password. Please try again.', 'error');
      }
    }

    function resetEmail() {
      if (profileData && profileData.profile) {
        document.getElementById('email').value = profileData.profile.email || '';
      }
    }

    function clearPasswordForm() {
      document.getElementById('currentPassword').value = '';
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
    }

    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
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

    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Initialize on page load
    window.addEventListener('load', () => {
      loadProfileData();
    });
  </script>
</body>
</html>