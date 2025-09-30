<?php

declare(strict_types=1);
session_start();

/**
 * Auth and DB
 */
require_once __DIR__ . '/../includes/auth.php';
if (function_exists('require_admin')) {
    require_admin();
}
require_once __DIR__ . '/../includes/connect.php';

/**
 * Find a mysqli connection exposed by includes/connect.php
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

/**
 * CSRF Token
 */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/**
 * Flash messages
 */
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/**
 * DB connect
 */
$mysqli = db_connect_auto();
if (!$mysqli) {
    $flashError = 'Database connection not available. Please configure includes/connect.php.';
}

/**
 * Create required tables if they don't exist
 */
if ($mysqli) {
    // Grading scales table
    $mysqli->query("CREATE TABLE IF NOT EXISTS grading_scales (
        scale_id INT PRIMARY KEY AUTO_INCREMENT,
        scale_name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    )");

    // Grade boundaries table
    $mysqli->query("CREATE TABLE IF NOT EXISTS grade_boundaries (
        boundary_id INT PRIMARY KEY AUTO_INCREMENT,
        scale_id INT NOT NULL,
        grade_letter VARCHAR(5) NOT NULL,
        min_percentage DECIMAL(5,2) NOT NULL,
        max_percentage DECIMAL(5,2) NOT NULL,
        grade_point DECIMAL(3,2) NOT NULL,
        remark VARCHAR(100),
        color_code VARCHAR(7) DEFAULT '#64748b',
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_grade_per_scale (scale_id, grade_letter),
        INDEX idx_scale_grade (scale_id, sort_order)
    )");

    // Behavioral factors table
    $mysqli->query("CREATE TABLE IF NOT EXISTS behavioral_factors (
        factor_id INT PRIMARY KEY AUTO_INCREMENT,
        factor_name VARCHAR(100) NOT NULL,
        weight_percentage DECIMAL(5,2) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_factor_name (factor_name),
        INDEX idx_active (is_active)
    )");

    // System settings table for behavioral configuration
    $mysqli->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default settings if they don't exist
    $mysqli->query("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
        ('behavioral_assessment_enabled', '1', 'Enable/disable behavioral assessment in grading'),
        ('behavioral_weight_academic', '70', 'Weight percentage for academic scores'),
        ('behavioral_weight_behavior', '30', 'Weight percentage for behavioral scores')");

    // Insert default grading scale if none exists
    $result = $mysqli->query("SELECT COUNT(*) as count FROM grading_scales");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            // Insert default 5-point scale
            $mysqli->query("INSERT INTO grading_scales (scale_name, description, is_active) VALUES 
                ('5-Point Scale', 'Standard 5-point grading scale (A=70-100)', TRUE)");

            $scale_id = $mysqli->insert_id;

            // Insert default grade boundaries
            $boundaries = [
                ['A', 70, 100, 5.0, 'Excellent', '#4c51bf', 1],
                ['B', 60, 69, 4.0, 'Very Good', '#2f855a', 2],
                ['C', 50, 59, 3.0, 'Good', '#b7791f', 3],
                ['D', 45, 49, 2.0, 'Pass', '#c05621', 4],
                ['F', 0, 44, 0.0, 'Fail', '#9b2c2c', 5]
            ];

            foreach ($boundaries as $boundary) {
                $stmt = $mysqli->prepare("INSERT INTO grade_boundaries (scale_id, grade_letter, min_percentage, max_percentage, grade_point, remark, color_code, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    // Extract array values into variables
                    $grade_letter = $boundary[0];
                    $min_percentage = $boundary[1];
                    $max_percentage = $boundary[2];
                    $grade_point = $boundary[3];
                    $remark = $boundary[4];
                    $color_code = $boundary[5];
                    $sort_order = $boundary[6];

                    $stmt->bind_param('isdddssi', $scale_id, $grade_letter, $min_percentage, $max_percentage, $grade_point, $remark, $color_code, $sort_order);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Insert default behavioral factors
            $factors = [
                ['Discipline', 40.0, 'Adherence to rules and conduct', 1],
                ['Teamwork', 30.0, 'Collaboration and cooperation', 2],
                ['Punctuality', 30.0, 'Attendance and timeliness', 3]
            ];

            foreach ($factors as $factor) {
                $stmt = $mysqli->prepare("INSERT INTO behavioral_factors (factor_name, weight_percentage, description, sort_order) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    // Extract array values into variables
                    $factor_name = $factor[0];
                    $weight_percentage = $factor[1];
                    $description = $factor[2];
                    $sort_order = $factor[3];

                    $stmt->bind_param('sdsi', $factor_name, $weight_percentage, $description, $sort_order);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } // Close if ($row['count'] == 0)
    } // Close if ($result)
} // Close if ($mysqli)

/**
 * Helper Functions
 */
function get_active_grading_scale(mysqli $db): ?array
{
    $sql = "SELECT gs.*, 
                   COUNT(gb.boundary_id) as boundary_count
            FROM grading_scales gs
            LEFT JOIN grade_boundaries gb ON gs.scale_id = gb.scale_id
            WHERE gs.is_active = TRUE
            GROUP BY gs.scale_id
            LIMIT 1";

    $result = $db->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

function get_grade_boundaries(mysqli $db, int $scale_id): array
{
    $boundaries = [];
    $scale_id = (int)$scale_id;
    $stmt = $db->prepare("SELECT * FROM grade_boundaries WHERE scale_id = ? ORDER BY sort_order ASC, min_percentage DESC");

    if (!$stmt) {
        return $boundaries;
    }

    $stmt->bind_param('i', $scale_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $boundaries[] = $row;
    }

    $stmt->close();
    return $boundaries;
}

function get_behavioral_factors(mysqli $db): array
{
    $factors = [];
    $result = $db->query("SELECT * FROM behavioral_factors WHERE is_active = TRUE ORDER BY sort_order ASC");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $factors[] = $row;
        }
    }

    return $factors;
}

function get_system_setting(mysqli $db, string $key, string $default = ''): string
{
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? $row['setting_value'] : $default;
}

function set_system_setting(mysqli $db, string $key, string $value): bool
{
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $key, $value);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function validate_grade_boundaries(array $boundaries): array
{
    $errors = [];

    if (empty($boundaries)) {
        $errors[] = "At least one grade boundary is required";
        return $errors;
    }

    // Validate each boundary
    foreach ($boundaries as $i => $boundary) {
        $grade = trim($boundary['grade'] ?? '');
        $min = $boundary['min'] ?? null;
        $max = $boundary['max'] ?? null;
        $point = $boundary['point'] ?? null;

        if (empty($grade)) {
            $errors[] = "Grade letter is required for boundary " . ($i + 1);
        }

        if (!is_numeric($min) || $min < 0 || $min > 100) {
            $errors[] = "Invalid minimum percentage for grade $grade";
        }

        if (!is_numeric($max) || $max < 0 || $max > 100) {
            $errors[] = "Invalid maximum percentage for grade $grade";
        }

        if (is_numeric($min) && is_numeric($max) && $min > $max) {
            $errors[] = "Minimum percentage cannot be greater than maximum for grade $grade";
        }

        if (!is_numeric($point) || $point < 0) {
            $errors[] = "Invalid grade point for grade $grade";
        }
    }

    // Check for overlaps
    if (empty($errors)) {
        $sorted = $boundaries;
        usort($sorted, function ($a, $b) {
            return $a['min'] <=> $b['min'];
        });

        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i]['min'] <= $sorted[$i - 1]['max']) {
                $errors[] = "Overlapping ranges detected between grades {$sorted[$i - 1]['grade']} and {$sorted[$i]['grade']}";
            }
        }
    }

    return $errors;
}

function validate_behavioral_factors(array $factors): array
{
    $errors = [];
    $total_weight = 0;

    foreach ($factors as $i => $factor) {
        $name = trim($factor['name'] ?? '');
        $weight = $factor['weight'] ?? null;

        if (empty($name)) {
            $errors[] = "Factor name is required for factor " . ($i + 1);
        }

        if (!is_numeric($weight) || $weight < 0 || $weight > 100) {
            $errors[] = "Invalid weight for factor $name (must be 0-100)";
        } else {
            $total_weight += (float)$weight;
        }
    }

    if (abs($total_weight - 100) > 0.01) {
        $errors[] = "Total weight must equal exactly 100% (currently {$total_weight}%)";
    }

    return $errors;
}

/**
 * Handle POST requests
 */
if ($mysqli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $flashError = 'Invalid CSRF token.';
    } else {
        switch ($action) {
            case 'save_grading_scale':
                $scale_name = trim($_POST['scale_name'] ?? 'Custom Scale');
                $boundaries_json = $_POST['boundaries'] ?? '[]';

                try {
                    $boundaries = json_decode($boundaries_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid boundaries data');
                    }

                    $validation_errors = validate_grade_boundaries($boundaries);
                    if (!empty($validation_errors)) {
                        $flashError = implode('; ', $validation_errors);
                        break;
                    }

                    $mysqli->begin_transaction();

                    // Deactivate current active scale
                    $mysqli->query("UPDATE grading_scales SET is_active = FALSE");

                    // Create new scale
                    $stmt = $mysqli->prepare("INSERT INTO grading_scales (scale_name, description, is_active, created_by) VALUES (?, ?, TRUE, ?)");
                    if ($stmt) {
                        $description = "Custom grading scale with " . count($boundaries) . " grade boundaries";
                        $user_id = $_SESSION['user_id'] ?? 1;
                        $stmt->bind_param('ssi', $scale_name, $description, $user_id);
                        $stmt->execute();
                        $scale_id = $stmt->insert_id;
                        $stmt->close();

                        // Insert boundaries
                        $stmt = $mysqli->prepare("INSERT INTO grade_boundaries (scale_id, grade_letter, min_percentage, max_percentage, grade_point, remark, color_code, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            foreach ($boundaries as $i => $boundary) {
                                // Extract array values into variables for bind_param
                                $grade_letter = $boundary['grade'];
                                $min_percentage = $boundary['min'];
                                $max_percentage = $boundary['max'];
                                $grade_point = $boundary['point'];
                                $remark = $boundary['remark'] ?? '';
                                $color_code = $boundary['color'] ?? '#64748b';
                                $sort_order = $i + 1;

                                $stmt->bind_param(
                                    'isdddssi',
                                    $scale_id,
                                    $grade_letter,
                                    $min_percentage,
                                    $max_percentage,
                                    $grade_point,
                                    $remark,
                                    $color_code,
                                    $sort_order
                                );
                                $stmt->execute();
                            }
                            $stmt->close();
                        }

                        $mysqli->commit();
                        $_SESSION['flash_success'] = 'Grading scale saved successfully!';
                    } else {
                        throw new Exception('Failed to prepare statement');
                    }
                } catch (Exception $e) {
                    if ($mysqli->connect_errno === 0) {
                        $mysqli->rollback();
                    }
                    $flashError = 'Error saving grading scale: ' . $e->getMessage();
                }
                break;

            case 'save_behavioral_factors':
                $factors_json = $_POST['factors'] ?? '[]';
                $behavioral_enabled = isset($_POST['behavioral_enabled']) ? '1' : '0';

                try {
                    $factors = json_decode($factors_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid factors data');
                    }

                    if ($behavioral_enabled === '1') {
                        $validation_errors = validate_behavioral_factors($factors);
                        if (!empty($validation_errors)) {
                            $flashError = implode('; ', $validation_errors);
                            break;
                        }
                    }

                    $mysqli->begin_transaction();

                    // Update system setting
                    set_system_setting($mysqli, 'behavioral_assessment_enabled', $behavioral_enabled);

                    if ($behavioral_enabled === '1' && !empty($factors)) {
                        // Clear existing factors
                        $mysqli->query("DELETE FROM behavioral_factors");

                        // Insert new factors
                        $stmt = $mysqli->prepare("INSERT INTO behavioral_factors (factor_name, weight_percentage, description, sort_order) VALUES (?, ?, ?, ?)");
                        if ($stmt) {
                            foreach ($factors as $i => $factor) {
                                // Extract array values into variables for bind_param
                                $factor_name = $factor['name'];
                                $weight_percentage = $factor['weight'];
                                $description = $factor['description'] ?? '';
                                $sort_order = $i + 1;

                                $stmt->bind_param(
                                    'sdsi',
                                    $factor_name,
                                    $weight_percentage,
                                    $description,
                                    $sort_order
                                );
                                $stmt->execute();
                            }
                            $stmt->close();
                        }
                    }

                    $mysqli->commit();
                    $_SESSION['flash_success'] = 'Behavioral factors saved successfully!';
                } catch (Exception $e) {
                    if ($mysqli->connect_errno === 0) {
                        $mysqli->rollback();
                    }
                    $flashError = 'Error saving behavioral factors: ' . $e->getMessage();
                }
                break;

            case 'load_preset':
                $preset_type = $_POST['preset_type'] ?? '';

                if ($preset_type === '7point') {
                    $boundaries = [
                        ['A', 75, 100, 4.0, 'Excellent', '#4c51bf'],
                        ['B+', 70, 74, 3.5, 'Very Good', '#2b6cb0'],
                        ['B', 65, 69, 3.0, 'Good', '#2f855a'],
                        ['C+', 60, 64, 2.5, 'Fair', '#b7791f'],
                        ['C', 55, 59, 2.0, 'Pass', '#c05621'],
                        ['D', 50, 54, 1.0, 'Poor', '#c53030'],
                        ['F', 0, 49, 0.0, 'Fail', '#742a2a']
                    ];

                    echo json_encode(['success' => true, 'boundaries' => $boundaries]);
                    exit;
                }
                break;
        }

        // Redirect to prevent resubmission
        header('Location: grading-scale.php');
        exit;
    }
}

/**
 * Load current data
 */
$current_scale = $mysqli ? get_active_grading_scale($mysqli) : null;
$grade_boundaries = $current_scale ? get_grade_boundaries($mysqli, (int)$current_scale['scale_id']) : [];
$behavioral_factors = $mysqli ? get_behavioral_factors($mysqli) : [];
$behavioral_enabled = $mysqli ? get_system_setting($mysqli, 'behavioral_assessment_enabled', '1') === '1' : true;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Grading Scale Settings - Marking & Grading System</title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Configure grade boundaries and behavioral factors" />
    <meta name="keywords" content="grading, scale, boundaries, behavior, education">
    <meta name="author" content="Marking & Grading System" />
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
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

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

        .pcoded-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .page-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"50\" cy=\"10\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .page-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            animation: slideInDown 1s ease-out;
        }

        .page-subtitle {
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

        .settings-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            gap: 2rem;
            grid-template-columns: 2fr 1fr;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }

        .card:hover {
            box-shadow: var(--card-shadow-hover);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .divider {
            height: 1px;
            background: #edf2f7;
            margin: 1rem 0;
        }

        .toolbar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #334155;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #38a169);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .select,
        .input {
            padding: 0.6rem 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            font-size: 0.9rem;
        }

        .input:focus,
        .select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            transition: .2s;
            border-radius: 999px;
        }

        .slider:before {
            content: "";
            position: absolute;
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 3px;
            background: white;
            transition: .2s;
            border-radius: 999px;
        }

        input:checked+.slider {
            background: var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(22px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 0.6rem;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .badge-A {
            background: rgba(102, 126, 234, 0.12);
            color: #4c51bf;
        }

        .badge-B {
            background: rgba(72, 187, 120, 0.12);
            color: #2f855a;
        }

        .badge-C {
            background: rgba(237, 137, 54, 0.12);
            color: #9c4221;
        }

        .badge-D {
            background: rgba(245, 101, 101, 0.12);
            color: #9b2c2c;
        }

        .badge-F {
            background: rgba(229, 62, 62, 0.12);
            color: #822727;
        }

        .scale-preview {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 2px;
            margin-top: 1rem;
        }

        .scale-segment {
            height: 16px;
            border-radius: 4px;
            background: #e2e8f0;
        }

        .factor-row {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 1fr 40px;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .remove-icon {
            background: rgba(245, 101, 101, 0.1);
            border: 1px solid rgba(245, 101, 101, 0.25);
            color: #e53e3e;
            border-radius: 6px;
            height: 36px;
            cursor: pointer;
        }

        .remove-icon:hover {
            background: #e53e3e;
            color: white;
        }

        .help-text {
            color: #64748b;
            font-size: 0.85rem;
        }

        .error-text {
            color: var(--danger-color);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .success-text {
            color: var(--success-color);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: rgba(72, 187, 120, .12);
            color: #2f855a;
            border: 1px solid rgba(72, 187, 120, .25);
        }

        .alert-error {
            background: rgba(245, 101, 101, .12);
            color: #c53030;
            border: 1px solid rgba(245, 101, 101, .25);
        }

        @media (max-width: 1100px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
            }

            .factor-row {
                grid-template-columns: 1fr 1fr 1fr 40px;
            }
        }
    </style>
</head>

<body class="">
    <!-- [ navigation menu ] start -->
    <nav class="pcoded-navbar menu-light">
        <div class="navbar-wrapper">
            <div class="navbar-content scroll-div">
                <ul class="nav pcoded-inner-navbar">
                    <li class="nav-item pcoded-menu-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
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
                        <a href="grading-scale.php" class="nav-link active">
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
            <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
            <a href="#" class="b-brand">
                <h3 class="text-primary mb-0">MGS Admin</h3>
            </a>
            <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <div class="page-header">
                <div class="container-fluid">
                    <div class="page-content">
                        <h1 class="page-title">Grading Scale Settings</h1>
                        <p class="page-subtitle">Configure grade boundaries and behavioral factors used in overall evaluation</p>
                    </div>
                </div>
            </div>

            <div class="settings-container">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" style="grid-column: 1 / -1;"><?= h($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-error" style="grid-column: 1 / -1;"><?= h($flashError) ?></div>
                <?php endif; ?>

                <!-- Left column: Grading Scale -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-sliders-h"></i> Grade Boundaries</div>
                    <?php if ($current_scale): ?>
                        <div class="help-text" style="margin-bottom: 1rem;">
                            Current Scale: <strong><?= h($current_scale['scale_name']) ?></strong>
                            (<?= $current_scale['boundary_count'] ?> boundaries)
                        </div>
                    <?php endif; ?>

                    <form id="gradingScaleForm" method="post" action="">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                        <input type="hidden" name="action" value="save_grading_scale">
                        <input type="hidden" name="boundaries" id="boundariesData">

                        <div class="toolbar" style="margin-bottom: .75rem;">
                            <input type="text" name="scale_name" class="input" placeholder="Scale name" value="Custom Scale" style="min-width: 150px;">
                            <select id="presetScale" class="select">
                                <option value="">Load preset...</option>
                                <option value="5point">5-Point (70-100=A)</option>
                                <option value="7point">7-Point (75-100=A)</option>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="addGradeRow()"><i class="fas fa-plus"></i> Add Grade</button>
                            <button type="button" class="btn btn-danger" onclick="clearGrades()"><i class="fas fa-trash"></i> Clear</button>
                            <button type="button" class="btn btn-success" onclick="validateScale()"><i class="fas fa-check-circle"></i> Validate</button>
                        </div>

                        <div style="overflow-x:auto; border: 1px solid #edf2f7; border-radius: 10px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="min-width:80px;">Grade</th>
                                        <th style="min-width:120px;">Min %</th>
                                        <th style="min-width:120px;">Max %</th>
                                        <th style="min-width:120px;">Point</th>
                                        <th style="min-width:120px;">Color</th>
                                        <th style="min-width:200px;">Remark</th>
                                        <th style="min-width:120px;">Preview</th>
                                        <th style="min-width:80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="gradesBody">
                                    <!-- Rows via JS -->
                                </tbody>
                            </table>
                        </div>
                        <div id="scaleError" class="error-text" style="display:none; margin-top:.75rem;"></div>
                        <div class="divider"></div>
                        <div>
                            <div class="help-text">Scale preview</div>
                            <div id="scalePreview" class="scale-preview" aria-label="Grading scale preview"></div>
                        </div>

                        <div class="sticky-actions">
                            <button type="button" class="btn btn-secondary" onclick="loadDefaultScale()"><i class="fas fa-rotate"></i> Reset to Default</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Grading Scale</button>
                        </div>
                    </form>
                </div>

                <!-- Right column: Behavioral factors -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-user-check"></i> Behavioral Factors</div>

                    <form id="behavioralForm" method="post" action="">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
                        <input type="hidden" name="action" value="save_behavioral_factors">
                        <input type="hidden" name="factors" id="factorsData">

                        <div style="display:flex; align-items:center; justify-content: space-between; gap: 1rem;">
                            <div class="help-text">Include behavior scores (e.g., discipline, teamwork, punctuality) in overall grade computation.</div>
                            <label class="switch">
                                <input id="behaviorToggle" name="behavioral_enabled" type="checkbox" <?= $behavioral_enabled ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="divider"></div>

                        <div id="behaviorContent" style="<?= $behavioral_enabled ? '' : 'display:none;' ?>">
                            <div class="help-text" style="margin-bottom:.5rem;">Distribute weights (must total 100%).</div>
                            <div id="factors"></div>
                            <div style="display:flex; align-items:center; justify-content: space-between; margin-top:.5rem;">
                                <div class="help-text">Total weight: <strong id="factorsTotal">0%</strong></div>
                                <div class="toolbar">
                                    <button type="button" class="btn btn-secondary" onclick="addFactorRow()"><i class="fas fa-plus"></i> Add Factor</button>
                                    <button type="button" class="btn btn-success" onclick="validateFactors()"><i class="fas fa-check-circle"></i> Validate</button>
                                </div>
                            </div>
                            <div id="factorsError" class="error-text" style="display:none; margin-top:.5rem;"></div>
                            <div class="divider"></div>
                            <div>
                                <div class="help-text" style="margin-bottom:.5rem;">Rating rubric</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Rating</th>
                                            <th>Description</th>
                                            <th>Score Mapping</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge badge-A">Excellent</span></td>
                                            <td>Consistently exemplary behavior</td>
                                            <td>100%</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-B">Good</span></td>
                                            <td>Usually positive, minor issues</td>
                                            <td>80%</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-C">Fair</span></td>
                                            <td>Mixed behavior, needs improvement</td>
                                            <td>60%</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-D">Poor</span></td>
                                            <td>Frequent issues observed</td>
                                            <td>40%</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-F">Very Poor</span></td>
                                            <td>Unacceptable, persistent issues</td>
                                            <td>0-20%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="sticky-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Behavioral Rules</button>
                            </div>
                        </div>
                        <div id="behaviorDisabledMsg" class="help-text" style="<?= $behavioral_enabled ? 'display:none;' : '' ?>">Behavioral factors are disabled.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Required Js -->
    <script src="assets/js/vendor-all.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/ripple.js"></script>
    <script src="assets/js/pcoded.min.js"></script>

    <script>
        // ---------- Grading scale state ----------
        let grades = <?= json_encode(array_map(function ($b) {
                            return [
                                'grade' => $b['grade_letter'],
                                'min' => (float)$b['min_percentage'],
                                'max' => (float)$b['max_percentage'],
                                'point' => (float)$b['grade_point'],
                                'remark' => $b['remark'],
                                'color' => $b['color_code']
                            ];
                        }, $grade_boundaries)) ?>;

        const gradesBody = document.getElementById('gradesBody');
        const presetScale = document.getElementById('presetScale');

        function loadDefaultScale() {
            grades = [{
                    grade: 'A',
                    min: 70,
                    max: 100,
                    point: 5,
                    remark: 'Excellent',
                    color: '#4c51bf'
                },
                {
                    grade: 'B',
                    min: 60,
                    max: 69,
                    point: 4,
                    remark: 'Very Good',
                    color: '#2f855a'
                },
                {
                    grade: 'C',
                    min: 50,
                    max: 59,
                    point: 3,
                    remark: 'Good',
                    color: '#b7791f'
                },
                {
                    grade: 'D',
                    min: 45,
                    max: 49,
                    point: 2,
                    remark: 'Pass',
                    color: '#c05621'
                },
                {
                    grade: 'F',
                    min: 0,
                    max: 44,
                    point: 0,
                    remark: 'Fail',
                    color: '#9b2c2c'
                },
            ];
            renderGrades();
        }

        function addGradeRow(g = {
            grade: '',
            min: '',
            max: '',
            point: '',
            remark: '',
            color: '#64748b'
        }) {
            grades.push(g);
            renderGrades();
        }

        function removeGradeRow(idx) {
            grades.splice(idx, 1);
            renderGrades();
        }

        function clearGrades() {
            if (!confirm('Clear all grade rows?')) return;
            grades = [];
            renderGrades();
        }

        function renderGrades() {
            gradesBody.innerHTML = '';
            grades.forEach((g, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input class="input" value="${esc(g.grade)}" oninput="grades[${idx}].grade=this.value; updatePreview()" placeholder="A, B+" style="width:80px"></td>
                    <td><input class="input" type="number" value="${g.min}" min="0" max="100" oninput="grades[${idx}].min=Number(this.value); updatePreview()" placeholder="min"></td>
                    <td><input class="input" type="number" value="${g.max}" min="0" max="100" oninput="grades[${idx}].max=Number(this.value); updatePreview()" placeholder="max"></td>
                    <td><input class="input" type="number" step="0.1" value="${g.point}" oninput="grades[${idx}].point=Number(this.value)" placeholder="GPA"></td>
                    <td><input class="input" type="color" value="${g.color || '#64748b'}" oninput="grades[${idx}].color=this.value; updatePreview()" style="padding:0.2rem"></td>
                    <td><input class="input" value="${esc(g.remark)}" oninput="grades[${idx}].remark=this.value" placeholder="Remark"></td>
                    <td><span class="badge" style="color:${g.color}; border:1px solid ${g.color}">${esc(g.grade || '?')}</span></td>
                    <td><button type="button" class="remove-icon" onclick="removeGradeRow(${idx})" title="Remove"><i class="fas fa-trash"></i></button></td>
                `;
                gradesBody.appendChild(tr);
            });
            updatePreview();
        }

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                "\"": "&quot;",
                "'": "&#39;"
            } [c]));
        }

        function updatePreview() {
            const preview = document.getElementById('scalePreview');
            preview.innerHTML = '';
            // Build 10 segments 0-100 in steps of 10 for coarse display
            for (let i = 0; i < 10; i++) {
                const start = i * 10;
                const end = start + 10 - 1;
                const box = document.createElement('div');
                box.className = 'scale-segment';
                const g = grades.find(x => Number.isFinite(x.min) && Number.isFinite(x.max) && start >= x.min && end <= x.max);
                if (g) box.style.background = g.color || '#94a3b8';
                preview.appendChild(box);
            }
        }

        function validateScale() {
            const error = document.getElementById('scaleError');
            error.style.display = 'none';
            error.textContent = '';
            // Checks: each row valid, no overlaps, coverage within 0..100 ascending allowed
            for (const g of grades) {
                if (g.grade === '' || !Number.isFinite(g.min) || !Number.isFinite(g.max) || g.min < 0 || g.max > 100 || g.min > g.max) {
                    error.textContent = 'Each grade must have a letter and valid min/max between 0 and 100 (min <= max).';
                    error.style.display = 'block';
                    return false;
                }
            }
            // Sort copy and check overlaps
            const sorted = [...grades].sort((a, b) => a.min - b.min);
            for (let i = 1; i < sorted.length; i++) {
                if (sorted[i].min <= sorted[i - 1].max) {
                    error.textContent = `Overlapping ranges detected between ${sorted[i-1].grade} and ${sorted[i].grade}.`;
                    error.style.display = 'block';
                    return false;
                }
            }
            // Coverage optional; warn if gaps exist
            let msg = 'Grading scale looks valid.';
            if (sorted.length) {
                if (sorted[0].min > 0 || sorted[sorted.length - 1].max < 100) {
                    msg += ' Note: The scale does not cover the full 0-100 range.';
                }
                for (let i = 1; i < sorted.length; i++) {
                    if (sorted[i].min - sorted[i - 1].max > 1) {
                        msg += ' There are gaps between ranges.';
                        break;
                    }
                }
            }
            alert(msg);
            return true;
        }

        presetScale.addEventListener('change', function() {
            if (this.value === '5point') {
                grades = [{
                        grade: 'A',
                        min: 70,
                        max: 100,
                        point: 5,
                        remark: 'Excellent',
                        color: '#4c51bf'
                    },
                    {
                        grade: 'B',
                        min: 60,
                        max: 69,
                        point: 4,
                        remark: 'Very Good',
                        color: '#2f855a'
                    },
                    {
                        grade: 'C',
                        min: 50,
                        max: 59,
                        point: 3,
                        remark: 'Good',
                        color: '#b7791f'
                    },
                    {
                        grade: 'D',
                        min: 45,
                        max: 49,
                        point: 2,
                        remark: 'Pass',
                        color: '#c05621'
                    },
                    {
                        grade: 'F',
                        min: 0,
                        max: 44,
                        point: 0,
                        remark: 'Fail',
                        color: '#9b2c2c'
                    }
                ];
                renderGrades();
            } else if (this.value === '7point') {
                grades = [{
                        grade: 'A',
                        min: 75,
                        max: 100,
                        point: 4,
                        remark: 'Excellent',
                        color: '#4c51bf'
                    },
                    {
                        grade: 'B+',
                        min: 70,
                        max: 74,
                        point: 3.5,
                        remark: 'Very Good',
                        color: '#2b6cb0'
                    },
                    {
                        grade: 'B',
                        min: 65,
                        max: 69,
                        point: 3,
                        remark: 'Good',
                        color: '#2f855a'
                    },
                    {
                        grade: 'C+',
                        min: 60,
                        max: 64,
                        point: 2.5,
                        remark: 'Fair',
                        color: '#b7791f'
                    },
                    {
                        grade: 'C',
                        min: 55,
                        max: 59,
                        point: 2,
                        remark: 'Pass',
                        color: '#c05621'
                    },
                    {
                        grade: 'D',
                        min: 50,
                        max: 54,
                        point: 1,
                        remark: 'Poor',
                        color: '#c53030'
                    },
                    {
                        grade: 'F',
                        min: 0,
                        max: 49,
                        point: 0,
                        remark: 'Fail',
                        color: '#742a2a'
                    }
                ];
                renderGrades();
            }
            this.value = '';
        });

        // Form submission
        document.getElementById('gradingScaleForm').addEventListener('submit', function(e) {
            if (!validateScale()) {
                e.preventDefault();
                return;
            }
            document.getElementById('boundariesData').value = JSON.stringify(grades);
        });

        // ---------- Behavioral factors ----------
        let factors = <?= json_encode(array_map(function ($f) {
                            return [
                                'name' => $f['factor_name'],
                                'weight' => (float)$f['weight_percentage'],
                                'description' => $f['description']
                            ];
                        }, $behavioral_factors)) ?>;

        const factorsContainer = document.getElementById('factors');
        const factorsTotalEl = document.getElementById('factorsTotal');
        const factorsError = document.getElementById('factorsError');

        function renderFactors() {
            factorsContainer.innerHTML = '';
            factors.forEach((f, idx) => {
                const row = document.createElement('div');
                row.className = 'factor-row';
                row.innerHTML = `
                    <input class="input" value="${esc(f.name)}" oninput="factors[${idx}].name=this.value;" placeholder="Factor (e.g., Teamwork)">
                    <input class="input" type="number" min="0" max="100" value="${f.weight}" oninput="factors[${idx}].weight=Number(this.value); updateFactorsTotal()" placeholder="Weight %">
                    <input class="input" value="${esc(f.description||'')}" oninput="factors[${idx}].description=this.value" placeholder="Description (optional)">
                    <button type="button" class="remove-icon" onclick="removeFactor(${idx})" title="Remove"><i class="fas fa-trash"></i></button>
                `;
                factorsContainer.appendChild(row);
            });
            updateFactorsTotal();
        }

        function addFactorRow() {
            factors.push({
                name: '',
                weight: 0,
                description: ''
            });
            renderFactors();
        }

        function removeFactor(idx) {
            factors.splice(idx, 1);
            renderFactors();
        }

        function updateFactorsTotal() {
            const total = factors.reduce((a, b) => a + (Number(b.weight) || 0), 0);
            factorsTotalEl.textContent = total + '%';
            if (total !== 100) {
                factorsError.style.display = 'block';
                factorsError.textContent = 'Weights must sum to exactly 100%.';
            } else {
                factorsError.style.display = 'none';
                factorsError.textContent = '';
            }
        }

        function validateFactors() {
            updateFactorsTotal();
            if (factors.some(f => !f.name) || factors.some(f => !Number.isFinite(f.weight))) {
                factorsError.style.display = 'block';
                factorsError.textContent = 'Each factor must have a name and numeric weight.';
                return false;
            }
            if (factors.reduce((a, b) => a + (Number(b.weight) || 0), 0) !== 100) {
                return false;
            }
            alert('Behavioral factors look valid.');
            return true;
        }

        document.getElementById('behaviorToggle').addEventListener('change', function() {
            const on = this.checked;
            document.getElementById('behaviorContent').style.display = on ? '' : 'none';
            document.getElementById('behaviorDisabledMsg').style.display = on ? 'none' : '';
        });

        // Form submission
        document.getElementById('behavioralForm').addEventListener('submit', function(e) {
            const behaviorEnabled = document.getElementById('behaviorToggle').checked;
            if (behaviorEnabled && !validateFactors()) {
                e.preventDefault();
                return;
            }
            document.getElementById('factorsData').value = JSON.stringify(factors);
        });

        // Init
        window.addEventListener('load', () => {
            renderGrades();
            renderFactors();
        });
    </script>
</body>

</html>
</qodoArtifact>