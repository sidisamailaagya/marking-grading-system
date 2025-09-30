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
 * Get lecturer profile information
 */
function get_lecturer_profile(mysqli $db, int $lecturer_id): ?array
{
  $sql = "SELECT 
                l.lecturer_name,
                l.email,
                l.created_at,
                d.dept_name,
                f.faculty_name
            FROM lecturers l
            LEFT JOIN departments d ON l.dept_id = d.dept_id
            LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
            WHERE l.lecturer_id = ?";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log("Failed to prepare lecturer profile query: " . $db->error);
    return null;
  }

  $stmt->bind_param('i', $lecturer_id);
  if (!$stmt->execute()) {
    error_log("Failed to execute lecturer profile query: " . $stmt->error);
    $stmt->close();
    return null;
  }

  $result = $stmt->get_result();
  $profile = $result->fetch_assoc();
  $stmt->close();

  return $profile;
}

/**
 * Update lecturer profile information
 */
function update_lecturer_profile(mysqli $db, int $lecturer_id, array $data): bool
{
  $sql = "UPDATE lecturers SET 
                lecturer_name = ?,
                email = ?
            WHERE lecturer_id = ?";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log("Failed to prepare profile update query: " . $db->error);
    return false;
  }

  $stmt->bind_param('ssi', 
    $data['lecturer_name'],
    $data['email'],
    $lecturer_id
  );

  $success = $stmt->execute();
  if (!$success) {
    error_log("Failed to execute profile update query: " . $stmt->error);
  }

  $stmt->close();
  return $success;
}

/**
 * Change lecturer password (Simplified)
 */
function change_lecturer_password(mysqli $db, int $lecturer_id, string $current_password, string $new_password): array
{
  // First, verify current password
  $sql = "SELECT password FROM lecturers WHERE lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return ['success' => false, 'message' => 'Database error occurred'];
  }

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $lecturer = $result->fetch_assoc();
  $stmt->close();

  if (!$lecturer) {
    return ['success' => false, 'message' => 'Lecturer not found'];
  }

  // Verify current password
  if (!password_verify($current_password, $lecturer['password'])) {
    return ['success' => false, 'message' => 'Current password is incorrect'];
  }

  // Simple validation - just check minimum length
  if (strlen($new_password) < 6) {
    return ['success' => false, 'message' => 'New password must be at least 6 characters long'];
  }

  // Hash new password
  $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

  // Update password
  $sql = "UPDATE lecturers SET password = ? WHERE lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return ['success' => false, 'message' => 'Database error occurred'];
  }

  $stmt->bind_param('si', $hashed_password, $lecturer_id);
  $success = $stmt->execute();
  $stmt->close();

  if ($success) {
    return ['success' => true, 'message' => 'Password updated successfully'];
  } else {
    return ['success' => false, 'message' => 'Failed to update password'];
  }
}

/**
 * Get lecturer statistics for profile dashboard
 */
function get_lecturer_stats(mysqli $db, int $lecturer_id): array
{
  $stats = [
    'total_courses' => 0,
    'total_students' => 0,
    'grades_entered' => 0,
    'member_since' => ''
  ];

  // Get total courses
  $sql = "SELECT COUNT(DISTINCT course_id) as total FROM course_assignments WHERE lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_courses'] = (int)($row['total'] ?? 0);
    $stmt->close();
  }

  // Get total students (approximate based on course assignments)
  $sql = "SELECT COUNT(DISTINCT CONCAT(ca.dept_id, '-', ca.level_id)) as total 
          FROM course_assignments ca 
          WHERE ca.lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_students'] = (int)($row['total'] ?? 0) * 30; // Estimate 30 students per class
    $stmt->close();
  }

  // Get grades entered
  $sql = "SELECT COUNT(*) as total 
          FROM results r 
          INNER JOIN courses c ON r.course_id = c.course_id 
          INNER JOIN course_assignments ca ON c.course_id = ca.course_id 
          WHERE ca.lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['grades_entered'] = (int)($row['total'] ?? 0);
    $stmt->close();
  }

  // Get member since date
  $sql = "SELECT created_at FROM lecturers WHERE lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row && $row['created_at']) {
      $stats['member_since'] = date('F Y', strtotime($row['created_at']));
    } else {
      $stats['member_since'] = 'Unknown';
    }
    $stmt->close();
  }

  return $stats;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');

  try {
    if ($_GET['action'] === 'get_profile') {
      $profile = get_lecturer_profile($mysqli, $lecturer_id);
      if (!$profile) {
        echo json_encode(['error' => 'Failed to load profile information']);
        exit;
      }

      $stats = get_lecturer_stats($mysqli, $lecturer_id);

      echo json_encode([
        'profile' => $profile,
        'stats' => $stats
      ]);
      exit;
    }

    if ($_GET['action'] === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);

      if (!$input || !isset($input['lecturer_name']) || !isset($input['email'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
      }

      // Validate email
      if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
      }

      // Check if email is already taken by another lecturer
      $sql = "SELECT lecturer_id FROM lecturers WHERE email = ? AND lecturer_id != ?";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('si', $input['email'], $lecturer_id);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address is already in use']);
        exit;
      }
      $stmt->close();

      $success = update_lecturer_profile($mysqli, $lecturer_id, [
        'lecturer_name' => trim($input['lecturer_name']),
        'email' => trim($input['email'])
      ]);

      if ($success) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
      }
      exit;
    }

    if ($_GET['action'] === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);

      if (!$input || !isset($input['current_password']) || !isset($input['new_password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
      }

      $result = change_lecturer_password(
        $mysqli,
        $lecturer_id,
        $input['current_password'],
        $input['new_password']
      );

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
  <title>Profile Settings - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Update profile information and change password" />
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

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      padding: 1.25rem;
      transition: var(--transition);
    }

    .card:hover {
      box-shadow: var(--card-shadow-hover);
    }

    .stat-card {
      text-align: center;
    }

    .stat-value {
      font-size: 1.8rem;
      font-weight: 900;
      color: #2d3748;
      margin: .3rem 0;
    }

    .stat-label {
      color: #718096;
      font-size: .85rem;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      margin-bottom: .5rem;
      font-weight: 600;
      color: #374151;
    }

    .input,
    .select {
      padding: .68rem .8rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      width: 100%;
      box-sizing: border-box;
    }

    .input:focus,
    .select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .input:disabled {
      background: #f9fafb;
      color: #6b7280;
      cursor: not-allowed;
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
      text-decoration: none;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea, #5a67d8);
      color: #fff;
    }

    .btn-success {
      background: linear-gradient(135deg, #48bb78, #38a169);
      color: #fff;
    }

    .btn-outline {
      background: #fff;
      color: #4a5568;
      border: 1px solid #e2e8f0;
    }

    .muted {
      color: #718096;
      font-size: .9rem;
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

    .alert-info {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
    }

    .loading {
      text-align: center;
      padding: 2rem;
      color: #718096;
    }

    .profile-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 2rem;
    }

    .profile-info h2 {
      margin: 0;
      color: #2d3748;
    }

    .profile-info .muted {
      margin: 0;
    }

    .password-requirements {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 8px;
      margin-top: .5rem;
    }

    .password-requirements ul {
      margin: .5rem 0;
      padding-left: 1.5rem;
    }

    .password-requirements li {
      margin-bottom: .25rem;
      color: #6b7280;
      font-size: .85rem;
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

    @media (max-width:1100px) {
      .grid {
        grid-template-columns: 1fr
      }

      .form-grid {
        grid-template-columns: 1fr
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
          <li class="nav-item pcoded-menu-caption"><label>Lecturer Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user-cog"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
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
          <h1>Profile Settings</h1>
          <p>Manage your profile information and account settings</p>
        </div>
      </div>

      <div class="main container">
        <div id="alertContainer"></div>

        <div id="profileContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading profile information...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    let currentProfile = null;

    async function loadProfile() {
      try {
        const response = await fetch('?action=get_profile');
        const text = await response.text();
        
        console.log('Profile response:', text);
        
        let data;
        try {
          data = JSON.parse(text);
        } catch (jsonError) {
          console.error('JSON parse error:', jsonError);
          console.log('Raw response:', text);
          throw new Error('Invalid JSON response from server');
        }

        if (data.error) {
          throw new Error(data.error);
        }

        currentProfile = data;
        renderProfile();

      } catch (error) {
        console.error('Error loading profile:', error);
        document.getElementById('profileContent').innerHTML = '<div class="loading">Error loading profile: ' + error.message + '</div>';
      }
    }

    function renderProfile() {
      if (!currentProfile) return;

      const { profile, stats } = currentProfile;

      // Generate initials for avatar
      const initials = profile.lecturer_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      const content = `
        <div class="profile-header">
          <div class="avatar">${initials}</div>
          <div class="profile-info">
            <h2>${escapeHtml(profile.lecturer_name)}</h2>
            <div class="muted">${escapeHtml(profile.email)} • Member since ${stats.member_since}</div>
          </div>
        </div>

        <div class="stats-grid">
          <div class="card stat-card">
            <div class="stat-value">${stats.total_courses}</div>
            <div class="stat-label">Courses Assigned</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value">${stats.total_students}</div>
            <div class="stat-label">Students (Est.)</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value">${stats.grades_entered}</div>
            <div class="stat-label">Grades Entered</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value"><i class="fas fa-user-check" style="color: var(--success-color);"></i></div>
            <div class="stat-label">Account Status</div>
          </div>
        </div>

        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:1rem;">Profile Information</div>
            <form id="profileForm">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input id="lecturer_name" name="lecturer_name" class="input" placeholder="Full Name" value="${escapeHtml(profile.lecturer_name)}" required />
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input id="email" name="email" class="input" type="email" placeholder="Email" value="${escapeHtml(profile.email)}" required />
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Department</label>
                  <input class="input" value="${escapeHtml(profile.dept_name || 'Not Assigned')}" disabled />
                </div>
                <div class="form-group">
                  <label class="form-label">Faculty</label>
                  <input class="input" value="${escapeHtml(profile.faculty_name || 'Not Assigned')}" disabled />
                </div>
              </div>
              <div style="display:flex;gap:.5rem;">
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Profile</button>
                <button type="button" class="btn btn-outline" onclick="resetProfile()"><i class="fas fa-undo"></i> Reset</button>
              </div>
            </form>
          </div>
          
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:1rem;">Change Password</div>
            <form id="passwordForm">
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <input id="currentPassword" name="current_password" class="input" type="password" placeholder="Enter current password" required />
              </div>
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input id="newPassword" name="new_password" class="input" type="password" placeholder="Enter new password" required />
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input id="confirmPassword" name="confirm_password" class="input" type="password" placeholder="Confirm new password" required />
              </div>
              <div style="display:flex;gap:.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                <button type="button" class="btn btn-outline" onclick="clearPasswordForm()"><i class="fas fa-times"></i> Clear</button>
              </div>
            </form>
            
            <div class="password-requirements">
              <strong>Password Requirements:</strong>
              <ul>
                <li>At least 6 characters long</li>
                <li>Must match the confirmation password</li>
              </ul>
            </div>
          </div>
        </div>
      `;

      document.getElementById('profileContent').innerHTML = content;

      // Attach event listeners
      document.getElementById('profileForm').addEventListener('submit', handleProfileUpdate);
      document.getElementById('passwordForm').addEventListener('submit', handlePasswordChange);
    }

    async function handleProfileUpdate(e) {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = Object.fromEntries(formData.entries());

      // Validate required fields
      if (!data.lecturer_name.trim() || !data.email.trim()) {
        showAlert('Please fill in all required fields', 'error');
        return;
      }

      // Validate email format
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(data.email)) {
        showAlert('Please enter a valid email address', 'error');
        return;
      }

      try {
        const response = await fetch('?action=update_profile', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
          showAlert(result.message, 'success');
          // Reload profile to show updated data
          setTimeout(() => loadProfile(), 1000);
        } else {
          showAlert(result.message, 'error');
        }

      } catch (error) {
        console.error('Profile update error:', error);
        showAlert('Failed to update profile. Please try again.', 'error');
      }
    }

    async function handlePasswordChange(e) {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = Object.fromEntries(formData.entries());

      // Validate passwords match
      if (data.new_password !== data.confirm_password) {
        showAlert('New passwords do not match', 'error');
        return;
      }

      // Simple validation - just check minimum length
      if (data.new_password.length < 6) {
        showAlert('New password must be at least 6 characters long', 'error');
        return;
      }

      try {
        const response = await fetch('?action=change_password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            current_password: data.current_password,
            new_password: data.new_password
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

    function resetProfile() {
      if (currentProfile && currentProfile.profile) {
        const profile = currentProfile.profile;
        document.getElementById('lecturer_name').value = profile.lecturer_name;
        document.getElementById('email').value = profile.email;
      }
    }

    function clearPasswordForm() {
      document.getElementById('currentPassword').value = '';
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
    }

    function showAlert(message, type) {
      const container = document.getElementById('alertContainer');
      const alertClass = type === 'success' ? 'alert-success' : type === 'info' ? 'alert-info' : 'alert-error';
      const icon = type === 'success' ? 'fa-check-circle' : type === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle';

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

    // Initialize on page load
    window.addEventListener('load', () => {
      loadProfile();
    });
  </script>
</body>
</html>