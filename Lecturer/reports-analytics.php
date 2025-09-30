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
 * Get lecturer's courses for analytics
 */
function get_lecturer_courses_for_analytics(mysqli $db, int $lecturer_id): array
{
  $sql = "SELECT DISTINCT
                c.course_id,
                c.course_code,
                c.course_name,
                ca.level_id
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
    $course_key = $row['course_code'];
    if (!isset($courses[$course_key])) {
      $courses[$course_key] = [
        'course_id' => $row['course_id'],
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'levels' => []
      ];
    }
    if (!in_array($row['level_id'], $courses[$course_key]['levels'])) {
      $courses[$course_key]['levels'][] = (int)$row['level_id'];
    }
  }
  $stmt->close();
  return array_values($courses);
}

/**
 * Get course analytics data
 */
function get_course_analytics(mysqli $db, string $course_code, ?int $level_filter, int $lecturer_id): array
{
  // Base query to get results for the course
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
                s.full_name,
                s.matric_no,
                s.level
            FROM results r
            INNER JOIN courses c ON r.course_id = c.course_id
            INNER JOIN course_assignments ca ON c.course_id = ca.course_id
            INNER JOIN students s ON ca.dept_id = s.dept_id AND ca.level_id = s.level
            WHERE c.course_code = ? AND ca.lecturer_id = ?";

  $params = [$course_code, $lecturer_id];
  $types = 'si';

  if ($level_filter) {
    $sql .= " AND s.level = ?";
    $params[] = $level_filter;
    $types .= 'i';
  }

  $sql .= " ORDER BY r.final_score DESC";

  $stmt = $db->prepare($sql);
  if (!$stmt) return [];

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'student_name' => $row['full_name'],
      'matric_no' => $row['matric_no'],
      'level' => $row['level'],
      'final_score' => (float)($row['final_score'] ?? 0),
      'grade_letter' => $row['grade_letter'] ?? 'F',
      'assignment_score' => (float)($row['assignment_score'] ?? 0),
      'test_score' => (float)($row['test_score'] ?? 0),
      'project_score' => (float)($row['project_score'] ?? 0),
      'exam_score' => (float)($row['exam_score'] ?? 0),
      'discipline_score' => (float)($row['discipline_score'] ?? 0),
      'punctuality_score' => (float)($row['punctuality_score'] ?? 0),
      'teamwork_score' => (float)($row['teamwork_score'] ?? 0)
    ];
  }
  $stmt->close();
  return $data;
}

/**
 * Calculate analytics statistics
 */
function calculate_analytics_stats(array $data): array
{
  if (empty($data)) {
    return [
      'total_students' => 0,
      'average_score' => 0,
      'pass_rate' => 0,
      'grade_distribution' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0],
      'component_averages' => [],
      'top_performers' => [],
      'bottom_performers' => []
    ];
  }

  $total_students = count($data);
  $total_score = array_sum(array_column($data, 'final_score'));
  $average_score = round($total_score / $total_students, 2);

  // Calculate pass rate (assuming 40% is passing)
  $passed = count(array_filter($data, fn($d) => $d['final_score'] >= 40));
  $pass_rate = round(($passed / $total_students) * 100, 2);

  // Grade distribution
  $grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
  foreach ($data as $record) {
    $grade = $record['grade_letter'];
    if (isset($grade_distribution[$grade])) {
      $grade_distribution[$grade]++;
    }
  }

  // Component averages
  $component_averages = [
    'assignment' => round(array_sum(array_column($data, 'assignment_score')) / $total_students, 2),
    'test' => round(array_sum(array_column($data, 'test_score')) / $total_students, 2),
    'project' => round(array_sum(array_column($data, 'project_score')) / $total_students, 2),
    'exam' => round(array_sum(array_column($data, 'exam_score')) / $total_students, 2),
    'discipline' => round(array_sum(array_column($data, 'discipline_score')) / $total_students, 2),
    'punctuality' => round(array_sum(array_column($data, 'punctuality_score')) / $total_students, 2),
    'teamwork' => round(array_sum(array_column($data, 'teamwork_score')) / $total_students, 2)
  ];

  // Top and bottom performers
  $sorted_data = $data;
  usort($sorted_data, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

  $top_performers = array_slice($sorted_data, 0, 5);
  $bottom_performers = array_slice($sorted_data, -3);

  return [
    'total_students' => $total_students,
    'average_score' => $average_score,
    'pass_rate' => $pass_rate,
    'grade_distribution' => $grade_distribution,
    'component_averages' => $component_averages,
    'top_performers' => $top_performers,
    'bottom_performers' => $bottom_performers
  ];
}

/**
 * Get lecturer information for PDF header
 */
function get_lecturer_info(mysqli $db, int $lecturer_id): array
{
  $sql = "SELECT lecturer_name, email FROM lecturers WHERE lecturer_id = ?";
  $stmt = $db->prepare($sql);
  if (!$stmt) return ['lecturer_name' => 'Unknown Lecturer', 'email' => ''];

  $stmt->bind_param('i', $lecturer_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $lecturer = $result->fetch_assoc();
  $stmt->close();

  return $lecturer ?: ['lecturer_name' => 'Unknown Lecturer', 'email' => ''];
}
/**
 * Generate PDF report using HTML to PDF conversion
 */
/**
 * Generate PDF report using HTML to PDF conversion
 */
function generate_pdf_report(string $course_code, ?int $level_filter, array $data, array $stats, array $lecturer_info): void
{
  $level_text = $level_filter ? " (Level $level_filter)" : " (All Levels)";
  $date = date('F j, Y');
  $time = date('g:i A');

  // Create comprehensive HTML for PDF
  $html = "
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset='utf-8'>
    <title>Course Analytics Report - $course_code</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
      .header { text-align: center; border-bottom: 3px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
      .header h1 { color: #667eea; margin: 0; font-size: 28px; }
      .header h2 { color: #666; margin: 5px 0; font-size: 18px; font-weight: normal; }
      .meta-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; }
      .meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
      .meta-label { font-weight: bold; color: #555; }
      .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
      .stat-card { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; border-left: 4px solid #667eea; }
      .stat-value { font-size: 32px; font-weight: bold; color: #667eea; margin-bottom: 5px; }
      .stat-label { color: #666; font-size: 14px; }
      .section { margin-bottom: 40px; }
      .section-title { font-size: 20px; font-weight: bold; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
      th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
      th { background-color: #f8f9fa; font-weight: bold; color: #555; }
      tr:nth-child(even) { background-color: #f9f9f9; }
      .grade-A { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .grade-B { background: #cce7ff; color: #004085; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .grade-C { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .grade-D { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .grade-E { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .grade-F { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
      .component-analysis { margin-bottom: 20px; }
      .component-item { margin-bottom: 15px; }
      .component-name { font-weight: bold; margin-bottom: 5px; }
      .component-bar { height: 20px; background: #e9ecef; border-radius: 10px; position: relative; }
      .component-fill { height: 100%; border-radius: 10px; }
      .component-fill.assignment { background: linear-gradient(90deg, #667eea, #764ba2); }
      .component-fill.test { background: linear-gradient(90deg, #48bb78, #38a169); }
      .component-fill.project { background: linear-gradient(90deg, #ed8936, #dd6b20); }
      .component-fill.exam { background: linear-gradient(90deg, #f56565, #e53e3e); }
      .component-fill.behavior { background: linear-gradient(90deg, #4299e1, #3182ce); }
      .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px; }
      .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
      .insights { background: #e8f4fd; padding: 20px; border-radius: 8px; border-left: 4px solid #4299e1; }
      .insights h3 { color: #2c5282; margin-top: 0; }
      .insights ul { margin: 0; padding-left: 20px; }
      .insights li { margin-bottom: 8px; color: #2d3748; }
      @media print { body { margin: 0; } .header { page-break-after: avoid; } }
    </style>
  </head>
  <body>
    <div class='header'>
      <h1>Course Analytics Report</h1>
      <h2>$course_code$level_text</h2>
    </div>
    
    <div class='meta-info'>
      <div class='meta-row'>
        <span class='meta-label'>Generated By:</span>
        <span>{$lecturer_info['lecturer_name']} ({$lecturer_info['email']})</span>
      </div>
      <div class='meta-row'>
        <span class='meta-label'>Generated On:</span>
        <span>$date at $time</span>
      </div>
      <div class='meta-row'>
        <span class='meta-label'>Total Students:</span>
        <span>{$stats['total_students']} students</span>
      </div>
    </div>
    
    <div class='stats-grid'>
      <div class='stat-card'>
        <div class='stat-value'>{$stats['total_students']}</div>
        <div class='stat-label'>Total Students</div>
      </div>
      <div class='stat-card'>
        <div class='stat-value'>{$stats['average_score']}%</div>
        <div class='stat-label'>Average Score</div>
      </div>
      <div class='stat-card'>
        <div class='stat-value'>{$stats['pass_rate']}%</div>
        <div class='stat-label'>Pass Rate</div>
      </div>
      <div class='stat-card'>
        <div class='stat-value'>{$stats['grade_distribution']['A']}</div>
        <div class='stat-label'>A Grades</div>
      </div>
    </div>
    
    <div class='section'>
      <h2 class='section-title'>Grade Distribution</h2>
      <table>
        <thead>
          <tr><th>Grade</th><th>Count</th><th>Percentage</th></tr>
        </thead>
        <tbody>";

  foreach ($stats['grade_distribution'] as $grade => $count) {
    $percentage = $stats['total_students'] > 0 ? round(($count / $stats['total_students']) * 100, 1) : 0;
    $html .= "<tr><td><span class='grade-$grade'>$grade</span></td><td>$count</td><td>{$percentage}%</td></tr>";
  }

  $html .= "
        </tbody>
      </table>
    </div>
    
    <div class='two-column'>
      <div class='section'>
        <h2 class='section-title'>Top Performers</h2>
        <table>
          <thead>
            <tr><th>Student</th><th>Matric No</th><th>Final %</th><th>Grade</th></tr>
          </thead>
          <tbody>";

  foreach ($stats['top_performers'] as $student) {
    $html .= "<tr>
      <td>" . htmlspecialchars($student['student_name']) . "</td>
      <td>{$student['matric_no']}</td>
      <td>{$student['final_score']}%</td>
      <td><span class='grade-{$student['grade_letter']}'>{$student['grade_letter']}</span></td>
    </tr>";
  }

  $html .= "
          </tbody>
        </table>
      </div>
      
      <div class='section'>
        <h2 class='section-title'>Students Needing Attention</h2>
        <table>
          <thead>
            <tr><th>Student</th><th>Matric No</th><th>Final %</th><th>Grade</th></tr>
          </thead>
          <tbody>";

  foreach ($stats['bottom_performers'] as $student) {
    $html .= "<tr>
      <td>" . htmlspecialchars($student['student_name']) . "</td>
      <td>{$student['matric_no']}</td>
      <td>{$student['final_score']}%</td>
      <td><span class='grade-{$student['grade_letter']}'>{$student['grade_letter']}</span></td>
    </tr>";
  }

  $html .= "
          </tbody>
        </table>
      </div>
    </div>
    
    <div class='section'>
      <h2 class='section-title'>Component Performance Analysis</h2>
      <div class='component-analysis'>";

  $max_scores = [
    'assignment' => 20,
    'test' => 20,
    'project' => 20,
    'exam' => 40,
    'discipline' => 100,
    'punctuality' => 100,
    'teamwork' => 100
  ];

  $component_names = [
    'assignment' => 'Assignments',
    'test' => 'Tests',
    'project' => 'Projects',
    'exam' => 'Exams',
    'discipline' => 'Discipline',
    'punctuality' => 'Punctuality',
    'teamwork' => 'Teamwork'
  ];

  foreach ($stats['component_averages'] as $component => $average) {
    $max_score = $max_scores[$component];
    $percentage = ($average / $max_score) * 100;
    $class = in_array($component, ['discipline', 'punctuality', 'teamwork']) ? 'behavior' : $component;

    $html .= "
      <div class='component-item'>
        <div class='component-name'>{$component_names[$component]}: $average/$max_score (" . round($percentage, 1) . "%)</div>
        <div class='component-bar'>
          <div class='component-fill $class' style='width: {$percentage}%;'></div>
        </div>
      </div>";
  }

  $html .= "
      </div>
    </div>";

  // Add insights section
  $insights = [];
  if ($stats['pass_rate'] < 60) {
    $insights[] = "Low pass rate ({$stats['pass_rate']}%) indicates need for additional student support";
  }
  if ($stats['average_score'] < 50) {
    $insights[] = "Below-average class performance suggests curriculum review may be needed";
  }
  if ($stats['grade_distribution']['A'] > $stats['total_students'] * 0.3) {
    $insights[] = "High number of A grades indicates strong student performance";
  }
  if (count($stats['bottom_performers']) > 0) {
    $insights[] = "Consider providing additional support to underperforming students";
  }

  if (!empty($insights)) {
    $html .= "
    <div class='section'>
      <div class='insights'>
        <h3>Key Insights & Recommendations</h3>
        <ul>";
    foreach ($insights as $insight) {
      $html .= "<li>$insight</li>";
    }
    $html .= "
        </ul>
      </div>
    </div>";
  }

  $html .= "
    <div class='section'>
      <h2 class='section-title'>Complete Student List</h2>
      <table>
        <thead>
          <tr>
            <th>Student Name</th>
            <th>Matric No</th>
            <th>Level</th>
            <th>Assignment</th>
            <th>Test</th>
            <th>Project</th>
            <th>Exam</th>
            <th>Final %</th>
            <th>Grade</th>
          </tr>
        </thead>
        <tbody>";

  foreach ($data as $student) {
    $html .= "<tr>
      <td>" . htmlspecialchars($student['student_name']) . "</td>
      <td>{$student['matric_no']}</td>
      <td>{$student['level']}</td>
      <td>{$student['assignment_score']}</td>
      <td>{$student['test_score']}</td>
      <td>{$student['project_score']}</td>
      <td>{$student['exam_score']}</td>
      <td>{$student['final_score']}%</td>
      <td><span class='grade-{$student['grade_letter']}'>{$student['grade_letter']}</span></td>
    </tr>";
  }

  $html .= "
        </tbody>
      </table>
    </div>
    
    <div class='footer'>
      <p>This report was automatically generated by the Marking & Grading System</p>
      <p>Generated on $date at $time by {$lecturer_info['lecturer_name']}</p>
    </div>
  </body>
  </html>";

  // Set headers for PDF download
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $course_code . '_analytics_report.pdf"');

  // Use wkhtmltopdf if available, otherwise use browser print
  if (function_exists('shell_exec') && shell_exec('which wkhtmltopdf')) {
    // Save HTML to temporary file
    $temp_html = tempnam(sys_get_temp_dir(), 'report_') . '.html';
    file_put_contents($temp_html, $html);

    // Generate PDF using wkhtmltopdf
    $temp_pdf = tempnam(sys_get_temp_dir(), 'report_') . '.pdf';
    shell_exec("wkhtmltopdf --page-size A4 --margin-top 0.75in --margin-right 0.75in --margin-bottom 0.75in --margin-left 0.75in '$temp_html' '$temp_pdf'");

    // Output PDF
    readfile($temp_pdf);

    // Clean up
    unlink($temp_html);
    unlink($temp_pdf);
  } else {
    // Fallback: Return HTML with print styles for browser PDF generation
    header('Content-Type: text/html');
    header('Content-Disposition: inline');
    echo $html;
    echo "<script>window.onload = function() { window.print(); }</script>";
  }
}
// Handle AJAX requests
if (isset($_GET['action'])) {
  header('Content-Type: application/json');

  try {
    if ($_GET['action'] === 'get_courses') {
      $courses = get_lecturer_courses_for_analytics($mysqli, $lecturer_id);
      echo json_encode($courses);
      exit;
    }

    if ($_GET['action'] === 'get_analytics' && isset($_GET['course_code'])) {
      $course_code = $_GET['course_code'];
      $level_filter = isset($_GET['level']) && $_GET['level'] !== '' ? (int)$_GET['level'] : null;

      $data = get_course_analytics($mysqli, $course_code, $level_filter, $lecturer_id);
      $stats = calculate_analytics_stats($data);

      echo json_encode([
        'course_code' => $course_code,
        'level_filter' => $level_filter,
        'data' => $data,
        'stats' => $stats
      ]);
      exit;
    }

    if ($_GET['action'] === 'export' && isset($_GET['course_code']) && isset($_GET['format'])) {
      $course_code = $_GET['course_code'];
      $level_filter = isset($_GET['level']) && $_GET['level'] !== '' ? (int)$_GET['level'] : null;
      $format = $_GET['format'];

      $data = get_course_analytics($mysqli, $course_code, $level_filter, $lecturer_id);
      $stats = calculate_analytics_stats($data);

      if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $course_code . '_analytics.csv"');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, ['Student Name', 'Matric No', 'Level', 'Assignment', 'Test', 'Project', 'Exam', 'Final Score', 'Grade']);

        // CSV Data
        foreach ($data as $record) {
          fputcsv($output, [
            $record['student_name'],
            $record['matric_no'],
            $record['level'],
            $record['assignment_score'],
            $record['test_score'],
            $record['project_score'],
            $record['exam_score'],
            $record['final_score'],
            $record['grade_letter']
          ]);
        }

        fclose($output);
        exit;
      }

      if ($format === 'pdf') {
        $lecturer_info = get_lecturer_info($mysqli, $lecturer_id);
        generate_pdf_report($course_code, $level_filter, $data, $stats, $lecturer_info);
        exit;
      }

      echo json_encode(['message' => 'Export functionality for ' . $format . ' is not supported']);
      exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
  } catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
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
  <title>Reports & Analytics - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Course-level analytics, grade distribution, export" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      grid-template-columns: 2fr 1fr;
      gap: 1.25rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
      font-size: 2rem;
      font-weight: 900;
      color: #2d3748;
      margin: .5rem 0;
    }

    .stat-label {
      color: #718096;
      font-size: .9rem;
    }

    .toolbar {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .select {
      padding: .65rem .8rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
    }

    .select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
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

    .muted {
      color: #718096;
      font-size: .9rem;
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
    }

    .center {
      text-align: center;
    }

    .grade-badge {
      padding: .3rem .6rem;
      border-radius: 999px;
      font-weight: 800;
      font-size: .9rem;
    }

    .grade-A {
      background: rgba(72, 187, 120, .12);
      color: #2f855a;
    }

    .grade-B {
      background: rgba(66, 153, 225, .12);
      color: #2c5282;
    }

    .grade-C {
      background: rgba(237, 137, 54, .12);
      color: #9c4221;
    }

    .grade-D {
      background: rgba(237, 137, 54, .15);
      color: #9c4221;
    }

    .grade-E {
      background: rgba(245, 101, 101, .12);
      color: #9b2c2c;
    }

    .grade-F {
      background: rgba(245, 101, 101, .15);
      color: #9b2c2c;
    }

    .loading {
      text-align: center;
      padding: 2rem;
      color: #718096;
    }

    .chart-container {
      position: relative;
      height: 300px;
    }

    .component-bar {
      height: 20px;
      background: #e2e8f0;
      border-radius: 10px;
      margin: .5rem 0;
      position: relative;
      overflow: hidden;
    }

    .component-fill {
      height: 100%;
      border-radius: 10px;
      transition: width 0.5s ease;
    }

    .component-fill.assignment {
      background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .component-fill.test {
      background: linear-gradient(90deg, #48bb78, #38a169);
    }

    .component-fill.project {
      background: linear-gradient(90deg, #ed8936, #dd6b20);
    }

    .component-fill.exam {
      background: linear-gradient(90deg, #f56565, #e53e3e);
    }

    .component-fill.behavior {
      background: linear-gradient(90deg, #4299e1, #3182ce);
    }

    .component-label {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      font-size: .8rem;
      font-weight: 700;
      color: #2d3748;
    }

    .component-value {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      font-size: .8rem;
      font-weight: 700;
      color: #2d3748;
    }

    @media (max-width: 768px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .toolbar {
        flex-direction: column;
        align-items: stretch;
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
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
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
          <h1>Reports & Analytics</h1>
          <p>Comprehensive course analytics, grade distributions, and performance insights</p>
        </div>
      </div>

      <div class="main container">
        <div class="card" style="margin-bottom:1rem;">
          <div class="toolbar">
            <div class="muted">Course:</div>
            <select id="courseSelect" class="select">
              <option value="">Loading courses...</option>
            </select>
            <div class="muted">Level:</div>
            <select id="levelSelect" class="select">
              <option value="">All Levels</option>
              <option value="100">100 Level</option>
              <option value="200">200 Level</option>
              <option value="300">300 Level</option>
              <option value="400">400 Level</option>
            </select>
            <button class="btn btn-outline" onclick="generateAnalytics()"><i class="fas fa-magnifying-glass-chart"></i> Generate</button>
            <button class="btn btn-success" onclick="exportData('csv')"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button class="btn btn-primary" onclick="exportData('pdf')"><i class="fas fa-file-pdf"></i> Export PDF</button>
          </div>
        </div>

        <div id="analyticsContent">
          <div class="loading"><i class="fas fa-spinner fa-spin"></i> Select a course and click Generate to view analytics</div>
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
    let currentAnalytics = null;
    let gradeChart = null;

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
          option.value = course.course_code;
          option.textContent = `${course.course_code} — ${course.course_name}`;
          select.appendChild(option);
        });

      } catch (error) {
        console.error('Error loading courses:', error);
        document.getElementById('courseSelect').innerHTML = '<option value="">Error loading courses</option>';
      }
    }

    async function generateAnalytics() {
      const courseCode = document.getElementById('courseSelect').value;
      const level = document.getElementById('levelSelect').value;

      if (!courseCode) {
        alert('Please select a course first');
        return;
      }

      document.getElementById('analyticsContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Generating analytics...</div>';

      try {
        let url = `?action=get_analytics&course_code=${encodeURIComponent(courseCode)}`;
        if (level) {
          url += `&level=${level}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        currentAnalytics = data;
        renderAnalytics();

      } catch (error) {
        console.error('Error generating analytics:', error);
        document.getElementById('analyticsContent').innerHTML = '<div class="loading">Error generating analytics: ' + error.message + '</div>';
      }
    }

    function renderAnalytics() {
      if (!currentAnalytics) return;

      const {
        stats,
        course_code,
        level_filter
      } = currentAnalytics;

      const levelText = level_filter ? ` (Level ${level_filter})` : ' (All Levels)';

      const content = `
        <div class="stats-grid">
          <div class="card stat-card">
            <div class="stat-value">${stats.total_students}</div>
            <div class="stat-label">Total Students</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value">${stats.average_score}%</div>
            <div class="stat-label">Average Score</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value">${stats.pass_rate}%</div>
            <div class="stat-label">Pass Rate</div>
          </div>
          <div class="card stat-card">
            <div class="stat-value">${stats.grade_distribution.A}</div>
            <div class="stat-label">A Grades</div>
          </div>
        </div>

        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Grade Distribution - ${course_code}${levelText}</div>
            <div class="chart-container">
              <canvas id="gradeChart"></canvas>
            </div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Top Performers</div>
            <table>
              <thead><tr><th>Student</th><th>Final %</th><th>Grade</th></tr></thead>
              <tbody>
                ${stats.top_performers.map(student => `
                  <tr>
                    <td>${escapeHtml(student.student_name)}</td>
                    <td class="center">${student.final_score}%</td>
                    <td class="center"><span class="grade-badge grade-${student.grade_letter}">${student.grade_letter}</span></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <div class="grid" style="margin-top:1.5rem;">
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Students Needing Attention</div>
            <table>
              <thead><tr><th>Student</th><th>Final %</th><th>Grade</th></tr></thead>
              <tbody>
                ${stats.bottom_performers.map(student => `
                  <tr>
                    <td>${escapeHtml(student.student_name)}</td>
                    <td class="center">${student.final_score}%</td>
                    <td class="center"><span class="grade-badge grade-${student.grade_letter}">${student.grade_letter}</span></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      document.getElementById('analyticsContent').innerHTML = content;

      // Render the chart
      setTimeout(() => renderGradeChart(stats.grade_distribution), 100);
    }

    function renderComponentAnalysis(components) {
      const maxScores = {
        assignment: 20,
        test: 20,
        project: 20,
        exam: 40,
        discipline: 100,
        punctuality: 100,
        teamwork: 100
      };

      const componentNames = {
        assignment: 'Assignments',
        test: 'Tests',
        project: 'Projects',
        exam: 'Exams',
        discipline: 'Discipline',
        punctuality: 'Punctuality',
        teamwork: 'Teamwork'
      };

      let html = '';

      Object.keys(components).forEach(key => {
        const value = components[key];
        const maxValue = maxScores[key];
        const percentage = (value / maxValue) * 100;
        const behaviorClass = ['discipline', 'punctuality', 'teamwork'].includes(key) ? 'behavior' : key;

        html += `
          <div style="margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:.25rem;">
              <span style="font-weight:600;">${componentNames[key]}</span>
              <span style="color:#718096;">${value}/${maxValue} (${Math.round(percentage)}%)</span>
            </div>
            <div class="component-bar">
              <div class="component-fill ${behaviorClass}" style="width:${percentage}%"></div>
            </div>
          </div>
        `;
      });

      return html;
    }

    function renderGradeChart(gradeDistribution) {
      const ctx = document.getElementById('gradeChart');
      if (!ctx) return;

      // Destroy existing chart if it exists
      if (gradeChart) {
        gradeChart.destroy();
      }

      const grades = Object.keys(gradeDistribution);
      const counts = Object.values(gradeDistribution);
      const colors = {
        A: '#48bb78',
        B: '#4299e1',
        C: '#ed8936',
        D: '#ed8936',
        E: '#f56565',
        F: '#f56565'
      };

      gradeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: grades,
          datasets: [{
            data: counts,
            backgroundColor: grades.map(grade => colors[grade]),
            borderWidth: 2,
            borderColor: '#fff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true
              }
            }
          }
        }
      });
    }

    async function exportData(format) {
      const courseCode = document.getElementById('courseSelect').value;
      const level = document.getElementById('levelSelect').value;

      if (!courseCode) {
        alert('Please select a course and generate analytics first');
        return;
      }

      try {
        let url = `?action=export&course_code=${encodeURIComponent(courseCode)}&format=${format}`;
        if (level) {
          url += `&level=${level}`;
        }

        if (format === 'csv') {
          // For CSV, trigger download
          window.location.href = url;
        } else if (format === 'pdf') {
          // For PDF, trigger download
          window.location.href = url;
        } else {
          // For other formats, show message
          const response = await fetch(url);
          const data = await response.json();
          alert(data.message || 'Export completed');
        }

      } catch (error) {
        console.error('Export error:', error);
        alert('Export failed: ' + error.message);
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Event listeners
    document.getElementById('courseSelect').addEventListener('change', function() {
      // Auto-generate when course changes
      if (this.value) {
        generateAnalytics();
      }
    });

    document.getElementById('levelSelect').addEventListener('change', function() {
      // Auto-regenerate when level filter changes
      const courseCode = document.getElementById('courseSelect').value;
      if (courseCode) {
        generateAnalytics();
      }
    });

    // Initialize on page load
    window.addEventListener('load', () => {
      loadCourses();
    });
  </script>
</body>

</html>