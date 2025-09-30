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
 * Get lecturer's courses for feedback
 */
function get_lecturer_courses_for_feedback(mysqli $db, int $lecturer_id): array
{
  $sql = "SELECT DISTINCT
                c.course_id,
                c.course_code,
                c.course_name
            FROM course_assignments ca
            INNER JOIN courses c ON ca.course_id = c.course_id
            WHERE ca.lecturer_id = ?
            ORDER BY c.course_code";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $courses = [];
  while ($row = $result->fetch_assoc()) {
    $courses[] = [
      'course_id' => $row['course_id'],
      'course_code' => $row['course_code'],
      'course_name' => $row['course_name']
    ];
  }
  $stmt->close();
  return $courses;
}

/**
 * Get students for a specific course
 */
function get_course_students(mysqli $db, int $course_id, int $lecturer_id): array
{
  $sql = "SELECT DISTINCT
                s.student_id,
                s.matric_no,
                s.full_name,
                s.level
            FROM course_assignments ca
            INNER JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
            WHERE ca.course_id = ? AND ca.lecturer_id = ?
            ORDER BY s.full_name";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param('ii', $course_id, $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $students = [];
  while ($row = $result->fetch_assoc()) {
    $students[] = [
      'student_id' => $row['student_id'],
      'matric_no' => $row['matric_no'],
      'full_name' => $row['full_name'],
      'level' => $row['level']
    ];
  }
  $stmt->close();
  return $students;
}

/**
 * Save feedback for a student
 */
function save_student_feedback(mysqli $db, int $student_id, int $course_id, int $lecturer_id, string $feedback): bool
{
  // Check if feedback already exists for this student-course combination
  $sql = "SELECT result_id FROM results WHERE student_id = ? AND course_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) return false;

  $stmt->bind_param('ii', $student_id, $course_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $existing = $result->fetch_assoc();
  $stmt->close();

  if ($existing) {
    // Update existing record
    $sql = "UPDATE results SET remarks = ?, updated_at = NOW() WHERE result_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param('si', $feedback, $existing['result_id']);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
  } else {
    // Create new record with just feedback (other scores can be added later)
    $sql = "INSERT INTO results (student_id, course_id, remarks, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param('iis', $student_id, $course_id, $feedback);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
  }
}

/**
 * Get existing feedback for a student
 */
function get_student_feedback(mysqli $db, int $student_id, int $course_id): ?string
{
  $sql = "SELECT remarks FROM results WHERE student_id = ? AND course_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) return null;

  $stmt->bind_param('ii', $student_id, $course_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  return $row ? $row['remarks'] : null;
}

/**
 * Get feedback history for a student across all courses
 */
function get_student_feedback_history(mysqli $db, int $student_id, int $lecturer_id): array
{
  $sql = "SELECT 
                c.course_code,
                c.course_name,
                r.remarks,
                r.final_score,
                r.grade_letter,
                r.updated_at
            FROM results r
            INNER JOIN courses c ON r.course_id = c.course_id
            INNER JOIN course_assignments ca ON c.course_id = ca.course_id
            WHERE r.student_id = ? AND ca.lecturer_id = ? AND r.remarks IS NOT NULL
            ORDER BY r.updated_at DESC";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param('ii', $student_id, $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $history = [];
  while ($row = $result->fetch_assoc()) {
    $history[] = [
      'course_code' => $row['course_code'],
      'course_name' => $row['course_name'],
      'remarks' => $row['remarks'],
      'final_score' => $row['final_score'],
      'grade_letter' => $row['grade_letter'],
      'updated_at' => $row['updated_at']
    ];
  }
  $stmt->close();
  return $history;
}

/**
 * Generate intelligent feedback suggestions based on student performance
 */
function generate_feedback_suggestions(mysqli $db, int $student_id, int $course_id): array
{
  // Get student's performance data
  $sql = "SELECT 
                assignment_score,
                test_score,
                project_score,
                exam_score,
                discipline_score,
                punctuality_score,
                teamwork_score,
                final_score,
                grade_letter
            FROM results 
            WHERE student_id = ? AND course_id = ?";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param('ii', $student_id, $course_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $performance = $result->fetch_assoc();
  $stmt->close();

  $suggestions = [];

  if ($performance) {
    $assignment_pct = ($performance['assignment_score'] / 20) * 100;
    $test_pct = ($performance['test_score'] / 20) * 100;
    $project_pct = ($performance['project_score'] / 20) * 100;
    $exam_pct = ($performance['exam_score'] / 40) * 100;
    $final_score = $performance['final_score'];

    // Performance-based suggestions
    if ($final_score >= 70) {
      $suggestions[] = "Excellent overall performance! Continue maintaining this high standard.";
      $suggestions[] = "Consider taking on leadership roles in group projects to help peers.";
    } elseif ($final_score >= 60) {
      $suggestions[] = "Good performance overall. Focus on consistency across all assessments.";
      $suggestions[] = "With some improvement, you can achieve excellent grades.";
    } elseif ($final_score >= 50) {
      $suggestions[] = "Satisfactory performance. Identify weak areas and work on improvement.";
      $suggestions[] = "Consider attending extra tutorial sessions for better understanding.";
    } elseif ($final_score >= 40) {
      $suggestions[] = "Performance needs improvement. Please seek additional help and support.";
      $suggestions[] = "Focus on fundamental concepts and practice regularly.";
    } else {
      $suggestions[] = "Immediate intervention required. Please schedule a meeting to discuss improvement strategies.";
      $suggestions[] = "Consider retaking assessments where possible and seek intensive tutoring.";
    }

    // Component-specific suggestions
    if ($assignment_pct < 60) {
      $suggestions[] = "Assignment performance needs attention. Ensure timely submission and quality work.";
    }
    if ($test_pct < 60) {
      $suggestions[] = "Test performance could be improved with better preparation and study techniques.";
    }
    if ($project_pct < 60) {
      $suggestions[] = "Project work needs enhancement. Focus on planning, research, and presentation skills.";
    }
    if ($exam_pct < 60) {
      $suggestions[] = "Exam performance requires improvement. Practice past questions and time management.";
    }

    // Behavioral suggestions
    if ($performance['discipline_score'] < 70) {
      $suggestions[] = "Maintain better classroom discipline and professional behavior.";
    }
    if ($performance['punctuality_score'] < 70) {
      $suggestions[] = "Improve punctuality for classes and assignment submissions.";
    }
    if ($performance['teamwork_score'] < 70) {
      $suggestions[] = "Enhance collaboration skills and contribute more effectively in group work.";
    }
  } else {
    // Default suggestions when no performance data exists
    $suggestions = [
      "Welcome to the course! Focus on understanding fundamental concepts.",
      "Participate actively in class discussions and ask questions when needed.",
      "Maintain consistent study habits and complete assignments on time.",
      "Seek help early if you encounter difficulties with course material.",
      "Build good relationships with classmates for collaborative learning."
    ];
  }

  return array_slice($suggestions, 0, 5); // Return top 5 suggestions
}

// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  
  try {
    if ($_GET['action'] === 'get_courses') {
      $courses = get_lecturer_courses_for_feedback($mysqli, $lecturer_id);
      echo json_encode($courses);
      exit;
    }

    if ($_GET['action'] === 'get_students' && isset($_GET['course_id'])) {
      $course_id = (int)$_GET['course_id'];
      $students = get_course_students($mysqli, $course_id, $lecturer_id);
      echo json_encode($students);
      exit;
    }

    if ($_GET['action'] === 'get_feedback' && isset($_GET['student_id']) && isset($_GET['course_id'])) {
      $student_id = (int)$_GET['student_id'];
      $course_id = (int)$_GET['course_id'];
      
      $feedback = get_student_feedback($mysqli, $student_id, $course_id);
      $suggestions = generate_feedback_suggestions($mysqli, $student_id, $course_id);
      
      echo json_encode([
        'feedback' => $feedback,
        'suggestions' => $suggestions
      ]);
      exit;
    }

    if ($_GET['action'] === 'save_feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      
      if (!$input || !isset($input['student_id']) || !isset($input['course_id']) || !isset($input['feedback'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
      }

      $student_id = (int)$input['student_id'];
      $course_id = (int)$input['course_id'];
      $feedback = trim($input['feedback']);

      if (empty($feedback)) {
        echo json_encode(['success' => false, 'message' => 'Feedback cannot be empty']);
        exit;
      }

      $success = save_student_feedback($mysqli, $student_id, $course_id, $lecturer_id, $feedback);

      if ($success) {
        echo json_encode(['success' => true, 'message' => 'Feedback saved successfully']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save feedback']);
      }
      exit;
    }

    if ($_GET['action'] === 'get_history' && isset($_GET['student_id'])) {
      $student_id = (int)$_GET['student_id'];
      $history = get_student_feedback_history($mysqli, $student_id, $lecturer_id);
      echo json_encode($history);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
    
  } catch (Exception $e) {
    error_log("Feedback Error: " . $e->getMessage());
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
  <title>Feedback & Remarks - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Add personalized feedback and view intelligent suggestions" />
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
    .grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap:1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; transition:var(--transition); }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:.75rem; }
    .input, .select, .textarea{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .textarea{ min-height:120px; width:100%; resize:vertical; font-family:inherit; }
    .input:focus, .select:focus, .textarea:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); text-decoration:none; }
    .btn:hover{ transform:translateY(-1px); }
    .btn:disabled{ opacity:0.6; cursor:not-allowed; transform:none; }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-success{ background:linear-gradient(135deg,#48bb78,#38a169); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .btn-small{ padding:.4rem .6rem; font-size:.8rem; }
    .muted{ color:#718096; font-size:.9rem; }
    .list{ display:grid; gap:.6rem; }
    .suggest{ border:1px solid #edf2f7; border-radius:12px; padding:.75rem; cursor:pointer; transition:var(--transition); }
    .suggest:hover{ border-color:#cbd5e0; background:#f8f9fa; }
    .suggest.performance{ border-left:4px solid var(--info-color); }
    .suggest.improvement{ border-left:4px solid var(--warning-color); }
    .suggest.excellent{ border-left:4px solid var(--success-color); }
    .suggest.concern{ border-left:4px solid var(--danger-color); }
    .loading{ text-align:center; padding:2rem; color:#718096; }
    .alert{ padding:1rem; border-radius:10px; margin-bottom:1rem; animation:slideIn 0.3s ease-out; }
    .alert-success{ background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .alert-error{ background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .history-item{ border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; margin-bottom:.5rem; }
    .history-date{ font-size:.8rem; color:#718096; }
    .history-course{ font-weight:600; color:#2d3748; margin:.25rem 0; }
    .history-feedback{ color:#4a5568; font-size:.9rem; }
    .template-buttons{ display:flex; gap:.25rem; flex-wrap:wrap; margin:.5rem 0; }

    @keyframes slideIn {
      from { opacity:0; transform:translateY(-10px); }
      to { opacity:1; transform:translateY(0); }
    }

    @media (max-width: 768px) {
      .grid{ grid-template-columns: 1fr; }
      .toolbar{ flex-direction:column; align-items:stretch; }
      .template-buttons{ flex-direction:column; }
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
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
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
          <h1>Feedback & Remarks</h1>
          <p>Add personalized feedback and review intelligent suggestions</p>
        </div>
      </div>

      <div class="main container">
        <div id="alertContainer"></div>

        <div class="grid">
          <div class="card">
            <div class="toolbar">
              <div class="muted">Course:</div>
              <select id="courseSelect" class="select" style="min-width:200px;">
                <option value="">Loading courses...</option>
              </select>
              <div class="muted">Student:</div>
              <select id="studentSelect" class="select" style="min-width:200px;">
                <option value="">Select course first</option>
              </select>
              <button class="btn btn-outline btn-small" onclick="viewHistory()"><i class="fas fa-history"></i> History</button>
            </div>
            
            <div>
              <div class="muted" style="margin-bottom:.4rem;">Feedback & Remarks</div>
              <textarea id="feedback" class="textarea" placeholder="Write your personalized feedback and remarks for the student..."></textarea>
              
              <div class="muted" style="margin:.5rem 0 .25rem;">Quick Templates:</div>
              <div class="template-buttons">
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Excellent work! Your dedication and effort are clearly reflected in your performance.')"><i class="fas fa-star"></i> Excellent</button>
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Good progress shown. Continue working hard to achieve even better results.')"><i class="fas fa-thumbs-up"></i> Good Progress</button>
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Please improve attendance and participation in class activities.')"><i class="fas fa-calendar-check"></i> Attendance</button>
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Focus more on assignment quality and timely submission.')"><i class="fas fa-tasks"></i> Assignments</button>
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Enhance your teamwork and collaboration skills in group projects.')"><i class="fas fa-users"></i> Teamwork</button>
                <button class="btn btn-outline btn-small" onclick="insertTemplate('Seek additional help during office hours for challenging topics.')"><i class="fas fa-question-circle"></i> Need Help</button>
              </div>
              
              <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                <button class="btn btn-success" onclick="saveFeedback()"><i class="fas fa-save"></i> Save Feedback</button>
                <button class="btn btn-outline" onclick="clearFeedback()"><i class="fas fa-eraser"></i> Clear</button>
                <button class="btn btn-outline" onclick="loadExistingFeedback()"><i class="fas fa-sync"></i> Reload</button>
              </div>
            </div>
          </div>
          
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Intelligent Suggestions</div>
            <div class="muted" style="margin-bottom:1rem;">AI-powered feedback suggestions based on student performance</div>
            <div class="list" id="suggestions">
              <div class="loading">Select a student to view suggestions</div>
            </div>
          </div>
        </div>

        <!-- History Modal (Simple version) -->
        <div id="historyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
          <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:var(--radius); padding:2rem; max-width:600px; width:90%; max-height:80%; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
              <h3 style="margin:0;">Feedback History</h3>
              <button onclick="closeHistory()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="historyContent">
              <div class="loading">Loading history...</div>
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
    let courses = [];
    let currentStudents = [];
    let currentSuggestions = [];

    async function loadCourses() {
      try {
        const response = await fetch('?action=get_courses');
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        courses = data;
        
        const select = document.getElementById('courseSelect');
        select.innerHTML = '<option value="">Select a course...</option>';
        
        courses.forEach(course => {
          const option = document.createElement('option');
          option.value = course.course_id;
          option.textContent = `${course.course_code} — ${course.course_name}`;
          select.appendChild(option);
        });
        
      } catch (error) {
        console.error('Error loading courses:', error);
        document.getElementById('courseSelect').innerHTML = '<option value="">Error loading courses</option>';
      }
    }

    async function loadStudents(courseId) {
      if (!courseId) {
        document.getElementById('studentSelect').innerHTML = '<option value="">Select course first</option>';
        return;
      }

      try {
        const response = await fetch(`?action=get_students&course_id=${courseId}`);
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        currentStudents = data;
        
        const select = document.getElementById('studentSelect');
        select.innerHTML = '<option value="">Select a student...</option>';
        
        currentStudents.forEach(student => {
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

    async function loadFeedbackAndSuggestions() {
      const courseId = document.getElementById('courseSelect').value;
      const studentId = document.getElementById('studentSelect').value;
      
      if (!courseId || !studentId) {
        document.getElementById('suggestions').innerHTML = '<div class="loading">Select a student to view suggestions</div>';
        return;
      }

      try {
        const response = await fetch(`?action=get_feedback&student_id=${studentId}&course_id=${courseId}`);
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        // Load existing feedback
        document.getElementById('feedback').value = data.feedback || '';
        
        // Load suggestions
        currentSuggestions = data.suggestions || [];
        renderSuggestions();
        
      } catch (error) {
        console.error('Error loading feedback and suggestions:', error);
        document.getElementById('suggestions').innerHTML = '<div class="loading">Error loading suggestions</div>';
      }
    }

    function renderSuggestions() {
      const container = document.getElementById('suggestions');
      
      if (currentSuggestions.length === 0) {
        container.innerHTML = '<div class="muted">No suggestions available</div>';
        return;
      }

      container.innerHTML = '';
      
      currentSuggestions.forEach((suggestion, index) => {
        const div = document.createElement('div');
        div.className = 'suggest';
        
        // Categorize suggestions for styling
        if (suggestion.includes('Excellent') || suggestion.includes('outstanding')) {
          div.classList.add('excellent');
        } else if (suggestion.includes('improvement') || suggestion.includes('needs')) {
          div.classList.add('improvement');
        } else if (suggestion.includes('intervention') || suggestion.includes('concern')) {
          div.classList.add('concern');
        } else {
          div.classList.add('performance');
        }
        
        div.innerHTML = `
          <div style="display:flex; justify-content:space-between; align-items:start;">
            <div>
              <div style="font-weight:600; color:#2d3748; margin-bottom:.25rem;">
                <i class="fas fa-lightbulb" style="color:var(--warning-color); margin-right:.5rem;"></i>
                Suggestion ${index + 1}
              </div>
              <div class="muted">${escapeHtml(suggestion)}</div>
            </div>
            <button onclick="insertTemplate('${escapeHtml(suggestion)}')" class="btn btn-outline btn-small" style="margin-left:.5rem;">
              <i class="fas fa-plus"></i>
            </button>
          </div>
        `;
        
        container.appendChild(div);
      });
    }

    async function saveFeedback() {
      const courseId = document.getElementById('courseSelect').value;
      const studentId = document.getElementById('studentSelect').value;
      const feedback = document.getElementById('feedback').value.trim();
      
      if (!courseId || !studentId) {
        showAlert('Please select both course and student', 'error');
        return;
      }
      
      if (!feedback) {
        showAlert('Please enter feedback before saving', 'error');
        return;
      }

      try {
        const response = await fetch('?action=save_feedback', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            student_id: parseInt(studentId),
            course_id: parseInt(courseId),
            feedback: feedback
          })
        });
        
        const result = await response.json();
        
        if (result.success) {
          showAlert(result.message, 'success');
        } else {
          showAlert(result.message, 'error');
        }
        
      } catch (error) {
        console.error('Error saving feedback:', error);
        showAlert('Failed to save feedback. Please try again.', 'error');
      }
    }

    async function viewHistory() {
      const studentId = document.getElementById('studentSelect').value;
      
      if (!studentId) {
        showAlert('Please select a student first', 'error');
        return;
      }

      document.getElementById('historyModal').style.display = 'block';
      document.getElementById('historyContent').innerHTML = '<div class="loading">Loading history...</div>';

      try {
        const response = await fetch(`?action=get_history&student_id=${studentId}`);
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        renderHistory(data);
        
      } catch (error) {
        console.error('Error loading history:', error);
        document.getElementById('historyContent').innerHTML = '<div class="muted">Error loading history</div>';
      }
    }

    function renderHistory(history) {
      const container = document.getElementById('historyContent');
      
      if (history.length === 0) {
        container.innerHTML = '<div class="muted">No feedback history found for this student</div>';
        return;
      }

      let html = '';
      history.forEach(item => {
        const date = new Date(item.updated_at).toLocaleDateString();
        const grade = item.grade_letter ? `Grade: ${item.grade_letter}` : 'No grade';
        
        html += `
          <div class="history-item">
            <div class="history-date">${date}</div>
            <div class="history-course">${escapeHtml(item.course_code)} - ${escapeHtml(item.course_name)}</div>
            <div class="muted" style="font-size:.8rem; margin:.25rem 0;">${grade} ${item.final_score ? `(${item.final_score}%)` : ''}</div>
            <div class="history-feedback">${escapeHtml(item.remarks)}</div>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    function insertTemplate(text) {
      const textarea = document.getElementById('feedback');
      const currentValue = textarea.value.trim();
      textarea.value = currentValue ? currentValue + '\n\n' + text : text;
      textarea.focus();
    }

    function clearFeedback() {
      document.getElementById('feedback').value = '';
    }

    function loadExistingFeedback() {
      loadFeedbackAndSuggestions();
    }

    function closeHistory() {
      document.getElementById('historyModal').style.display = 'none';
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
    document.getElementById('courseSelect').addEventListener('change', function() {
      loadStudents(this.value);
      clearFeedback();
      document.getElementById('suggestions').innerHTML = '<div class="loading">Select a student to view suggestions</div>';
    });

    document.getElementById('studentSelect').addEventListener('change', function() {
      if (this.value) {
        loadFeedbackAndSuggestions();
      } else {
        clearFeedback();
        document.getElementById('suggestions').innerHTML = '<div class="loading">Select a student to view suggestions</div>';
      }
    });

    // Close modal when clicking outside
    document.getElementById('historyModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeHistory();
      }
    });

    // Initialize on page load
    window.addEventListener('load', () => {
      loadCourses();
    });
  </script>
</body>
</html>