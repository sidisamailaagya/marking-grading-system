<?php
// register_action.php - Student registration handler (FIXED)
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: register.php');
    exit;
}

// Get form data
$email = trim($_POST['email'] ?? '');
$matric = trim($_POST['matric'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm'] ?? '';
$terms = isset($_POST['terms']) && $_POST['terms'] === '1';

// Validation
$errors = [];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($matric)) {
    $errors[] = 'Please enter your matric number.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
}

if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number.';
}

if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
    $errors[] = 'Password must contain at least one symbol.';
}

if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if (!$terms) {
    $errors[] = 'You must agree to the Terms and Privacy Policy.';
}

// If validation errors, redirect back
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    $_SESSION['form_data'] = [
        'email' => $email,
        'matric' => $matric
    ];
    header('Location: register.php');
    exit;
}

// Step 1: Check if matric number exists in database (student record must exist)
$sql = "SELECT student_id, full_name, matric_no, email, password FROM students WHERE matric_no = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = 'Database error. Please try again later.';
    header('Location: register.php');
    exit;
}

$stmt->bind_param('s', $matric);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['error'] = 'Matric number not found. Please contact the administrator to add your record first.';
    $_SESSION['form_data'] = [
        'email' => $email,
        'matric' => $matric
    ];
    header('Location: register.php');
    exit;
}

// Step 2: Check if this student record already has an email (account already activated)
if (!empty($student['email'])) {
    $_SESSION['error'] = 'This matric number has already been registered. Please use the login page instead.';
    header('Location: login.php');
    exit;
}

// Step 3: Check if the email is already used by another student
$sql = "SELECT student_id FROM students WHERE email = ? AND student_id != ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = 'Database error. Please try again later.';
    header('Location: register.php');
    exit;
}

$stmt->bind_param('si', $email, $student['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$emailExists = $result->fetch_assoc();
$stmt->close();

if ($emailExists) {
    $_SESSION['error'] = 'This email address is already registered. Please use a different email or contact the administrator.';
    $_SESSION['form_data'] = [
        'email' => '',
        'matric' => $matric
    ];
    header('Location: register.php');
    exit;
}

// Step 4: Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Step 5: Update the student record with email and password
$sql = "UPDATE students SET email = ?, password = ? WHERE student_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = 'Database error. Please try again later.';
    header('Location: register.php');
    exit;
}

$stmt->bind_param('ssi', $email, $hashedPassword, $student['student_id']);

if ($stmt->execute()) {
    $stmt->close();
    
    // Success - account activated
    $_SESSION['success'] = 'Account activated successfully! Welcome, ' . htmlspecialchars($student['full_name']) . '. You can now login with your credentials.';
    $_SESSION['registered_email'] = $email;
    
    // Optional: Auto-login the user
    /*
    $_SESSION['uid'] = $student['student_id'];
    $_SESSION['role'] = 'student';
    $_SESSION['name'] = $student['full_name'];
    $_SESSION['username'] = $email;
    $_SESSION['email'] = $email;
    
    header('Location: /Grading system/Student/dashboard.php');
    */
    
    // Redirect to login page with success message
    header('Location: login.php');
    exit;
    
} else {
    $stmt->close();
    $_SESSION['error'] = 'Failed to activate account. Please try again later.';
    $_SESSION['form_data'] = [
        'email' => $email,
        'matric' => $matric
    ];
    header('Location: register.php');
    exit;
}
?>