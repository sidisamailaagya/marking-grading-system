<?php


declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);



// Auth guard: only logged-in admins can access
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// DB connection
require_once __DIR__ . '/../includes/connect.php';

// Normalize DB connection to $conn if connect.php uses a different variable/function
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($con) && $con instanceof mysqli) {
        $conn = $con;
    } elseif (isset($mysqli) && $mysqli instanceof mysqli) {
        $conn = $mysqli;
    } elseif (function_exists('db') && db() instanceof mysqli) {
        $conn = db();
    } elseif (function_exists('db_connect') && db_connect() instanceof mysqli) {
        $conn = db_connect();
    } else {
        http_response_code(500);
        exit('Database connection not initialized.');
    }
}

// Helpers
function scalar_count(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_row())) {
        $res->free();
        return (int)$row[0];
    }
    return 0;
}

function latest_session_id(mysqli $conn): ?int
{
    // Using max session_id present in enrollments as the latest active session
    $res = $conn->query("SELECT MAX(session_id) FROM enrollments");
    if ($res) {
        $row = $res->fetch_row();
        $res->free();
        if ($row && $row[0] !== null) {
            return (int)$row[0];
        }
    }
    return null;
}

$adminName = htmlspecialchars($_SESSION['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

// Metrics
$totalStudents    = scalar_count($conn, "SELECT COUNT(*) FROM students");
$totalLecturers   = scalar_count($conn, "SELECT COUNT(*) FROM lecturers");
$totalCourses     = scalar_count($conn, "SELECT COUNT(*) FROM courses");
$submissionsToday = scalar_count($conn, "SELECT COUNT(*) FROM results WHERE DATE(created_at) = CURDATE()");

// Grade Distribution (A+, A, B+, B, C+, C, F) for latest session if available, else overall
$gradeBuckets = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'F'];
$gradeCounts  = array_fill_keys($gradeBuckets, 0);
$sessionId    = latest_session_id($conn);

$gradeSql =
    "SELECT r.grade_letter, COUNT(*) AS cnt
     FROM results r
     JOIN enrollments e ON e.enrollment_id = r.enrollment_id " .
    ($sessionId ? "WHERE e.session_id = ?" : "") .
    " GROUP BY r.grade_letter";

if ($stmt = $conn->prepare($gradeSql)) {
    if ($sessionId) {
        $stmt->bind_param('i', $sessionId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $gl = (string)$row['grade_letter'];
        if (isset($gradeCounts[$gl])) {
            $gradeCounts[$gl] = (int)$row['cnt'];
        }
    }
    $stmt->close();
}
$gradeSeries     = array_values($gradeCounts);
$gradeCategories = array_keys($gradeCounts);

// Course Performance (donut): top 5 courses by average final_score (latest session if present)
$courseLabels = [];
$courseSeries = [];
$courseSql =
    "SELECT c.course_name, AVG(r.final_score) AS avg_score
     FROM results r
     JOIN enrollments e ON e.enrollment_id = r.enrollment_id
     JOIN courses c ON c.course_id = e.course_id " .
    ($sessionId ? "WHERE e.session_id = ?" : "") . "
     GROUP BY c.course_id, c.course_name
     ORDER BY avg_score DESC
     LIMIT 5";

if ($stmt = $conn->prepare($courseSql)) {
    if ($sessionId) {
        $stmt->bind_param('i', $sessionId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $courseLabels[] = (string)$row['course_name'];
        $courseSeries[] = round((float)$row['avg_score'], 2);
    }
    $stmt->close();
}
if (empty($courseLabels)) {
    $courseLabels = ['No data'];
    $courseSeries = [0];
}

// Top Performing Students (top 5 by average final_score)
$topStudents = [];
$topSql =
    "SELECT s.full_name, d.dept_name, AVG(r.final_score) AS avg_score
     FROM results r
     JOIN enrollments e ON e.enrollment_id = r.enrollment_id
     JOIN students s ON s.student_id = e.student_id
     LEFT JOIN departments d ON d.dept_id = s.dept_id " .
    ($sessionId ? "WHERE e.session_id = ?" : "") . "
     GROUP BY s.student_id, s.full_name, d.dept_name
     ORDER BY avg_score DESC
     LIMIT 5";

if ($stmt = $conn->prepare($topSql)) {
    if ($sessionId) {
        $stmt->bind_param('i', $sessionId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rank = 1;
    while ($row = $res->fetch_assoc()) {
        $topStudents[] = [
            'rank' => $rank++,
            'name' => (string)$row['full_name'],
            'dept' => $row['dept_name'] !== null ? (string)$row['dept_name'] : '—',
            'avg'  => round((float)$row['avg_score'], 1),
        ];
    }
    $stmt->close();
}

// Recent Activity: latest 5 results recorded
$activities = [];
$actSql =
    "SELECT r.created_at,
            s.full_name   AS student_name,
            c.course_code AS course_code,
            c.course_name AS course_name,
            r.final_score,
            r.grade_letter
     FROM results r
     JOIN enrollments e ON e.enrollment_id = r.enrollment_id
     JOIN students s    ON s.student_id    = e.student_id
     JOIN courses c     ON c.course_id     = e.course_id
     ORDER BY r.created_at DESC
     LIMIT 5";
if ($res = $conn->query($actSql)) {
    while ($row = $res->fetch_assoc()) {
        $activities[] = [
            'when'   => (string)$row['created_at'],
            'title'  => sprintf(
                'Grade %s (%s) for %s',
                $row['grade_letter'] ?? '-',
                $row['final_score'] !== null ? (string)$row['final_score'] : '-',
                $row['course_code'] ?? ''
            ),
            'detail' => (string)$row['course_name'],
            'type'   => 'grade',
        ];
    }
    $res->free();
}

// Compact stats
$stats = [
    'students'         => (int)$totalStudents,
    'lecturers'        => (int)$totalLecturers,
    'courses'          => (int)$totalCourses,
    'submissionsToday' => (int)$submissionsToday,
];

// Data for JS
$jsGradeSeries     = json_encode($gradeSeries, JSON_NUMERIC_CHECK);
$jsGradeCategories = json_encode($gradeCategories);
$jsCourseSeries    = json_encode($courseSeries, JSON_NUMERIC_CHECK);
$jsCourseLabels    = json_encode($courseLabels);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Marking & Grading System - Admin Dashboard</title>
    <!-- HTML5 Shim and Respond.js IE11 support of HTML5 elements and media queries -->
    <!--[if lt IE 11]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Professional Marking & Grading System Dashboard" />
    <meta name="keywords" content="grading, marking, education, dashboard">
    <meta name="author" content="Marking & Grading System" />
    <!-- Favicon icon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
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
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation Styles */
        .pcoded-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            transition: var(--transition);
            border-radius: 8px;
            margin: 2px 8px;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white !important;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white !important;
        }

        /* Header Styles */
        .pcoded-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .welcome-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            animation: slideInDown 1s ease-out;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            animation: slideInUp 1s ease-out 0.2s both;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Main Content */
        .pcoded-main-container {
            background: transparent;
        }

        .pcoded-content {
            background: var(--light-bg);
            min-height: calc(100vh - 70px);
            border-radius: 30px 0 0 0;
            padding: 0;
            margin-top: 0;
        }

        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.students {
            background: linear-gradient(135deg, var(--info-color), #3182ce);
        }

        .stat-icon.lecturers {
            background: linear-gradient(135deg, var(--success-color), #38a169);
        }

        .stat-icon.courses {
            background: linear-gradient(135deg, var(--warning-color), #dd6b20);
        }

        .stat-icon.submissions {
            background: linear-gradient(135deg, var(--danger-color), #e53e3e);
        }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.5rem;
    /* Remove the counter and ::after - let JavaScript handle the animation */
}
        

        .stat-label {
            color: #718096;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            animation: fadeInLeft 0.8s ease-out;
        }

        .chart-card.recent-activity {
            animation: fadeInRight 0.8s ease-out;
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
        }

        .chart-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #4a5568;
            font-size: 0.875rem;
        }

        /* Recent Activity */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            transition: var(--transition);
        }

        .activity-item:hover {
            background: #f8fafc;
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 8px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.875rem;
        }

        .activity-icon.grade {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }

        .activity-icon.submission {
            background: rgba(66, 153, 225, 0.1);
            color: var(--info-color);
        }

        .activity-icon.course {
            background: rgba(237, 137, 54, 0.1);
            color: var(--warning-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #718096;
        }

        /* Top Performers */
        .performers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .performer-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            animation: fadeInUp 1s ease-out;
        }

        .performer-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .performer-item:last-child {
            border-bottom: none;
        }

        .performer-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            font-size: 0.875rem;
        }

        .performer-rank.first {
            background: #ffd700;
            color: #744210;
        }

        .performer-rank.second {
            background: #c0c0c0;
            color: #2d3748;
        }

        .performer-rank.third {
            background: #cd7f32;
            color: white;
        }

        .performer-rank.other {
            background: #e2e8f0;
            color: #4a5568;
        }

        .performer-info {
            flex: 1;
        }

        .performer-name {
            font-weight: 500;
            color: #2d3748;
        }

        .performer-course {
            font-size: 0.8rem;
            color: #718096;
        }

        .performer-grade {
            font-weight: 600;
            color: var(--success-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .dashboard-container {
                padding: 1rem;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loader-bg {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .loader-track {
            background: rgba(255, 255, 255, 0.2);
        }

        .loader-fill {
            background: white;
        }
    </style>
</head>

<body class="">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- [ navigation menu ] start -->
    <nav class="pcoded-navbar menu-light">
        <div class="navbar-wrapper">
            <div class="navbar-content scroll-div">
                <ul class="nav pcoded-inner-navbar">
                    <li class="nav-item pcoded-menu-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <span class="pcoded-micon"><i class="fas fa-home"></i></span>
                            <span class="pcoded-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-students.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span>
                            <span class="pcoded-mtext">Manage Students</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-lecturers.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span>
                            <span class="pcoded-mtext">Manage Lecturers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-courses.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-book"></i></span>
                            <span class="pcoded-mtext">Manage Courses</span>
                        </a>
                    </li>
                    <li class="nav-item"><a href="assign-courses.php" class=""><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Assign Courses</span></a></li>
                    <li class="nav-item">
                        <a href="grading-scale.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                            <span class="pcoded-mtext">Grading Scale</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span>
                            <span class="pcoded-mtext">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- [ navigation menu ] end -->

    <!-- [ Header ] start -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light">
        <div class="m-header">
            <a class="mobile-menu" id="mobile-collapse" href="#!"><span></span></a>
            <a href="#!" class="b-brand">
                <h3 class="text-primary mb-0">MGS Admin</h3>
            </a>
            <a href="#!" class="mob-toggler">
                <i class="feather icon-more-vertical"></i>
            </a>
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="container-fluid">
                    <div class="welcome-content text-center">
                        <h1 class="welcome-title">Welcome Back, <?php echo $adminName; ?>!</h1>
                        <p class="welcome-subtitle">Manage your marking and grading system with ease</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-container">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-number" data-target="<?php echo (int)$stats['students']; ?>"><?php echo number_format($stats['students']); ?></div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12% from last month</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon lecturers">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-number" data-target="<?php echo (int)$stats['lecturers']; ?>"><?php echo number_format($stats['lecturers']); ?></div>
                        <div class="stat-label">Active Lecturers</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+3 new this month</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-number" data-target="<?php echo (int)$stats['courses']; ?>"><?php echo number_format($stats['courses']); ?></div>
                        <div class="stat-label">Active Courses</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8 new courses</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon submissions">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-number" data-target="<?php echo (int)$stats['submissionsToday']; ?>"><?php echo number_format($stats['submissionsToday']); ?></div>
                        <div class="stat-label">Submissions Today</div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>-5% from yesterday</span>
                        </div>
                    </div>
                </div>

                <!-- Charts and Recent Activity -->
                <div class="charts-section">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Grade Distribution</h3>
                            <select class="chart-filter">
                                <option>This Semester</option>
                                <option>Last Semester</option>
                                <option>This Year</option>
                            </select>
                        </div>
                        <div id="gradeChart" style="height: 300px;"></div>
                    </div>

                    <div class="chart-card recent-activity">
                        <div class="chart-header">
                            <h3 class="chart-title">Recent Activity</h3>
                        </div>
                        <div class="activity-list">
                            <?php if (!empty($activities)): ?>
                                <?php foreach ($activities as $a): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon grade">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="activity-time"><?php echo htmlspecialchars($a['when'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No recent activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="performers-grid">
                    <div class="performer-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Top Performing Students</h3>
                        </div>
                        <?php if (!empty($topStudents)): ?>
                            <?php foreach ($topStudents as $stu): ?>
                                <?php
                                $rankClass = 'other';
                                if ($stu['rank'] === 1) $rankClass = 'first';
                                elseif ($stu['rank'] === 2) $rankClass = 'second';
                                elseif ($stu['rank'] === 3) $rankClass = 'third';
                                ?>
                                <div class="performer-item">
                                    <div class="performer-rank <?php echo $rankClass; ?>"><?php echo (int)$stu['rank']; ?></div>
                                    <div class="performer-info">
                                        <div class="performer-name"><?php echo htmlspecialchars($stu['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="performer-course"><?php echo htmlspecialchars($stu['dept'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="performer-grade"><?php echo number_format((float)$stu['avg'], 1); ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No performance data yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="performer-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Course Performance</h3>
                        </div>
                        <div id="courseChart" style="height: 250px;"></div>
                    </div>
                </div> <!-- /.performers-grid -->
            </div> <!-- /.dashboard-container -->
        </div> <!-- /.pcoded-content -->
    </div> <!-- /.pcoded-main-container -->

    <!-- Required Js -->
    <script src="assets/js/vendor-all.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/ripple.js"></script>
    <script src="assets/js/pcoded.min.js"></script>

    <!-- Apex Chart -->
    <script src="assets/js/plugins/apexcharts.min.js"></script>

    <script>
    // Grade Distribution Chart (dynamic)
    var gradeOptions = {
        series: [{
            name: 'Students',
            data: <?php echo $jsGradeSeries; ?>
        }],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: {
                show: false
            }
        },
        colors: ['#667eea'],
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '60%'
            }
        },
        dataLabels: {
            enabled: false
        },
        xaxis: {
            categories: <?php echo $jsGradeCategories; ?>,
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            show: false
        },
        grid: {
            show: false
        }
    };
    new ApexCharts(document.querySelector("#gradeChart"), gradeOptions).render();

    // Course Performance Chart (dynamic)
    var courseOptions = {
        series: <?php echo $jsCourseSeries; ?>,
        chart: {
            type: 'donut',
            height: 250
        },
        labels: <?php echo $jsCourseLabels; ?>,
        colors: ['#667eea', '#764ba2', '#48bb78', '#ed8936', '#f56565'],
        legend: {
            position: 'bottom'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%'
                }
            }
        }
    };
    new ApexCharts(document.querySelector("#courseChart"), courseOptions).render();

    // Animate numbers to show real database values (FIXED)
    function animateNumbers() {
        const numbers = document.querySelectorAll('.stat-number');
        numbers.forEach(number => {
            const target = parseInt(number.getAttribute('data-target')) || parseInt(number.textContent.replace(/,/g, '')) || 0;
            let current = 0;
            const increment = Math.max(1, Math.ceil(target / 50)); // Smoother animation
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                number.textContent = current.toLocaleString();
            }, 30);
        });
    }
    window.addEventListener('load', () => {
        setTimeout(animateNumbers, 300);
    });
</script>
</body>

</html>