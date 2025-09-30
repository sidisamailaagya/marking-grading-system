<?php

declare(strict_types=1);
session_start();

// Authentication and database connection
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/connect.php';

// Clear any login errors since we're successfully logged in
unset($_SESSION['error']);

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
 * Get lecturer information
 */
function get_lecturer_info(mysqli $db, int $lecturer_id): ?array
{
  $stmt = $db->prepare("SELECT lecturer_id, lecturer_name, email, dept_id FROM lecturers WHERE lecturer_id = ?");
  if (!$stmt) return null;

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $lecturer = $result->fetch_assoc();
  $stmt->close();

  return $lecturer;
}

/**
 * Get lecturer's assigned courses with student counts
 */
function get_lecturer_courses(mysqli $db, int $lecturer_id): array
{
  $sql = "SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                ca.session_id,
                ca.level_id,
                ca.dept_id,
                ca.session,
                COUNT(DISTINCT s.student_id) as enrolled_count
            FROM course_assignments ca
            INNER JOIN courses c ON ca.course_id = c.course_id
            LEFT JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
            WHERE ca.lecturer_id = ?
            GROUP BY c.course_id, ca.session_id, ca.level_id, ca.dept_id
            ORDER BY c.course_code";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $courses = [];
  while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
  }

  $stmt->close();
  return $courses;
}

/**
 * Get lecturer statistics
 */
function get_lecturer_stats(mysqli $db, int $lecturer_id): array
{
  // Total students across all lecturer's courses (based on dept_id and level matching)
  $stmt = $db->prepare("
        SELECT COUNT(DISTINCT s.student_id) as total_students
        FROM course_assignments ca
        LEFT JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
        WHERE ca.lecturer_id = ?
    ");

  if (!$stmt) return ['students' => 0, 'pending' => 0, 'average' => 0];

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $total_students = $result->fetch_assoc()['total_students'] ?? 0;
  $stmt->close();

  // For now, set pending grades and average to 0 since we need to clarify results table structure
  $pending_grades = 0;
  $avg_performance = 0;

  return [
    'students' => (int)$total_students,
    'pending' => (int)$pending_grades,
    'average' => round((float)$avg_performance, 1)
  ];
}

/**
 * Get lecturer notifications
 */
function get_lecturer_notifications(mysqli $db, int $lecturer_id): array
{
  $notifications = [];

  // Info notifications - course student summary
  $stmt = $db->prepare("
        SELECT 
            c.course_code,
            COUNT(DISTINCT s.student_id) as total_students
        FROM course_assignments ca
        INNER JOIN courses c ON ca.course_id = c.course_id
        LEFT JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
        WHERE ca.lecturer_id = ?
        GROUP BY c.course_id, c.course_code
        HAVING total_students > 0
        ORDER BY total_students DESC
        LIMIT 2
    ");

  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $notifications[] = [
        'type' => 'info',
        'text' => $row['course_code'] . ' has ' . $row['total_students'] . ' eligible student(s)',
        'time' => 'Current registration'
      ];
    }
    $stmt->close();
  }

  // Course assignment notifications
  $stmt = $db->prepare("
        SELECT 
            c.course_code,
            c.course_name,
            ca.session
        FROM course_assignments ca
        INNER JOIN courses c ON ca.course_id = c.course_id
        WHERE ca.lecturer_id = ?
        ORDER BY ca.created_at DESC
        LIMIT 3
    ");

  if ($stmt) {
    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $notifications[] = [
        'type' => 'info',
        'text' => 'You are assigned to teach ' . $row['course_code'] . ' - ' . $row['course_name'],
        'time' => 'Session: ' . ($row['session'] ?? 'Current')
      ];
    }
    $stmt->close();
  }

  // If no notifications, add a default one
  if (empty($notifications)) {
    $notifications[] = [
      'type' => 'info',
      'text' => 'All caught up! No urgent notifications.',
      'time' => 'Today'
    ];
  }

  return $notifications;
}

// Fetch all data
$lecturer = get_lecturer_info($mysqli, $lecturer_id);
$courses = get_lecturer_courses($mysqli, $lecturer_id);
$stats = get_lecturer_stats($mysqli, $lecturer_id);
$notifications = get_lecturer_notifications($mysqli, $lecturer_id);

// Handle case where lecturer is not found
if (!$lecturer) {
  session_destroy();
  header('Location: ../login.php?error=invalid_lecturer');
  exit;
}

// Group courses by course_code for display
$grouped_courses = [];
foreach ($courses as $course) {
  $key = $course['course_code'];
  if (!isset($grouped_courses[$key])) {
    $grouped_courses[$key] = [
      'course_id' => $course['course_id'],
      'course_code' => $course['course_code'],
      'course_name' => $course['course_name'],
      'sessions' => [],
      'levels' => [],
      'total_enrolled' => 0,
      'total_pending' => 0
    ];
  }

  $grouped_courses[$key]['sessions'][] = $course['session'] ?? $course['session_id'];
  $grouped_courses[$key]['levels'][] = $course['level_id'];
  $grouped_courses[$key]['total_enrolled'] += (int)$course['enrolled_count'];
  // Removed pending grades calculation for now
  $grouped_courses[$key]['total_pending'] = 0;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Lecturer Dashboard - Marking & Grading System</title>
  <meta name="description" content="Lecturer portal for managing courses, grades, and analytics" />
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

    * {
      box-sizing: border-box;
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

    .page-hero h1 {
      font-size: 2rem;
      font-weight: 800;
      margin: 0;
    }

    .page-hero p {
      opacity: .95;
      margin: .3rem 0 0;
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
      grid-template-columns: 2fr 1fr;
      gap: 1.25rem;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
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

    .stat {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .stat .icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.25rem;
    }

    .icon.blue {
      background: linear-gradient(135deg, #667eea, #5a67d8);
    }

    .icon.green {
      background: linear-gradient(135deg, #48bb78, #38a169);
    }

    .icon.orange {
      background: linear-gradient(135deg, #ed8936, #dd6b20);
    }

    .stat .value {
      font-size: 1.8rem;
      font-weight: 800;
      color: #2d3748;
    }

    .muted {
      color: #718096;
      font-size: .9rem;
    }

    .list {
      display: grid;
      gap: .75rem;
    }

    .notif {
      display: flex;
      gap: .75rem;
      padding: .75rem;
      border-radius: 10px;
      border: 1px solid #edf2f7;
      align-items: flex-start;
      transition: var(--transition);
    }

    .notif:hover {
      background: #f7fafc;
    }

    .notif .badge {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: .95rem;
    }

    .badge.warn {
      background: #ed8936;
    }

    .badge.info {
      background: #4299e1;
    }

    .badge.deadline {
      background: #f56565;
    }

    .btn {
      padding: .7rem 1rem;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      font-size: 0.9rem;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea, #5a67d8);
      color: white;
    }

    .btn-outline {
      background: #fff;
      color: #4a5568;
      border: 1px solid #e2e8f0;
    }

    .actions {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
    }

    .courses {
      display: grid;
      gap: .6rem;
    }

    .course-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .75rem;
      border: 1px solid #edf2f7;
      border-radius: 10px;
      transition: var(--transition);
    }

    .course-row:hover {
      background: #f7fafc;
      border-color: #cbd5e0;
    }

    .course-meta {
      display: flex;
      gap: .6rem;
      align-items: center;
      color: #718096;
      font-size: .88rem;
    }

    .no-data {
      text-align: center;
      color: #718096;
      padding: 2rem;
      font-style: italic;
    }

    .alert {
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      border: 1px solid;
    }

    .alert-info {
      background: #ebf8ff;
      color: #2b6cb0;
      border-color: #bee3f8;
    }

    @media (max-width: 1100px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .grid-3 {
        grid-template-columns: 1fr;
      }

      .course-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
      }

      .actions {
        width: 100%;
        justify-content: flex-end;
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
          <li class="nav-item"><a href="dashboard.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
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
        <h3 class="text-primary mb-0">MGS</h3>
      </a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>Welcome back, <?= h($lecturer['lecturer_name']) ?></h1>
          <p>Manage your courses, grading tasks, and track student performance</p>
        </div>
      </div>

      <div class="main container">
        <!-- Stats -->
        <div class="grid-3">
          <div class="card stat">
            <div class="icon blue"><i class="fas fa-users"></i></div>
            <div>
              <div class="value" id="statStudents"><?= $stats['students'] ?></div>
              <div class="muted">Eligible Students</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon orange"><i class="fas fa-tasks"></i></div>
            <div>
              <div class="value" id="statPending"><?= $stats['pending'] ?></div>
              <div class="muted">Pending Grading Tasks</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon green"><i class="fas fa-gauge-high"></i></div>
            <div>
              <div class="value" id="statAverage"><?= $stats['average'] ?>%</div>
              <div class="muted">Average Performance</div>
            </div>
          </div>
        </div>

        <div class="grid" style="margin-top:1.25rem;">
          <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
              <h3 style="margin:0;color:#2d3748;">My Courses</h3>
              <div class="actions">
                <a class="btn btn-outline" href="my-courses.php"><i class="fas fa-arrow-right"></i> View All</a>
              </div>
            </div>

            <?php if (empty($grouped_courses)): ?>
              <div class="no-data">
                <i class="fas fa-book" style="font-size: 2rem; color: #cbd5e0; margin-bottom: 1rem;"></i>
                <p>No courses assigned yet. Contact your administrator to get course assignments.</p>
              </div>
            <?php else: ?>
              <div class="courses">
                <?php foreach (array_slice($grouped_courses, 0, 5) as $course): ?>
                  <div class="course-row">
                    <div>
                      <div style="font-weight:800;color:#2d3748;"><?= h($course['course_code']) ?> - <?= h($course['course_name']) ?></div>
                      <div class="course-meta">
                        <span><i class="fas fa-calendar"></i> Sessions: <?= implode(', ', array_unique($course['sessions'])) ?></span>
                        <span><i class="fas fa-layer-group"></i> Levels: <?= implode(', ', array_unique($course['levels'])) ?></span>
                        <span><i class="fas fa-user"></i> <?= $course['total_enrolled'] ?> students</span>
                        <?php if ($course['total_pending'] > 0): ?>
                          <span style="color: #ed8936;"><i class="fas fa-clock"></i> <?= $course['total_pending'] ?> pending</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="actions">
                      <a class="btn btn-outline" href="enter-grades.php?course=<?= urlencode($course['course_code']) ?>"><i class="fas fa-pen-to-square"></i> Enter Grades</a>
                      <a class="btn btn-primary" href="student-performance.php?course=<?= urlencode($course['course_code']) ?>"><i class="fas fa-chart-line"></i> Performance</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3 style="margin:0 0 .75rem;color:#2d3748;">Notifications</h3>
            <div class="list">
              <?php foreach ($notifications as $notification): ?>
                <div class="notif">
                  <?php
                  $badge_class = $notification['type'];
                  $icon = match ($notification['type']) {
                    'deadline' => 'fa-clock',
                    'warn' => 'fa-triangle-exclamation',
                    'info' => 'fa-bell',
                    default => 'fa-bell'
                  };
                  ?>
                  <div class="badge <?= $badge_class ?>"><i class="fas <?= $icon ?>"></i></div>
                  <div>
                    <div style="font-weight:600;color:#2d3748;margin-bottom:.2rem"><?= h($notification['text']) ?></div>
                    <div class="muted"><?= h($notification['time']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    // Animate statistics on page load
    function animateValue(el, to, suffix = '') {
      const start = 0;
      const dur = 800;
      const t0 = performance.now();
      const step = (t) => {
        const p = Math.min((t - t0) / dur, 1);
        const value = (start + (to - start) * p);
        el.textContent = (suffix === '%' ? value.toFixed(1) : Math.floor(value)) + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }

    // Initialize animations when page loads
    window.addEventListener('load', () => {
      const studentsEl = document.getElementById('statStudents');
      const pendingEl = document.getElementById('statPending');
      const averageEl = document.getElementById('statAverage');

      if (studentsEl) animateValue(studentsEl, <?= $stats['students'] ?>);
      if (pendingEl) animateValue(pendingEl, <?= $stats['pending'] ?>);
      if (averageEl) animateValue(averageEl, <?= $stats['average'] ?>, '%');
    });

    // Add loading states for buttons
    document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        if (this.href && !this.href.includes('#') && !this.href.includes('javascript:')) {
          const originalText = this.innerHTML;
          this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
          this.style.pointerEvents = 'none';

          // Restore button if navigation fails
          setTimeout(() => {
            this.innerHTML = originalText;
            this.style.pointerEvents = 'auto';
          }, 5000);
        }
      });
    });
  </script>
</body>

</html>
</qodoArtifact>