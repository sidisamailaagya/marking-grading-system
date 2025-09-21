<?php
// check-user.php - Check if user exists in database
require_once __DIR__ . '/includes/connect.php';

$username = 'abdul';
$password = '123456';

echo "<h2>Database User Check</h2>";

// Check admin table
echo "<h3>Checking Admin Table:</h3>";
$sql = "SELECT admin_id, full_name, email, username, password, status FROM admins WHERE username = ? OR email = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "✅ Admin found!<br>";
        echo "ID: " . $row['admin_id'] . "<br>";
        echo "Name: " . $row['full_name'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "Username: " . $row['username'] . "<br>";
        echo "Status: " . $row['status'] . "<br>";
        echo "Password hash: " . substr($row['password'], 0, 30) . "...<br>";
        
        // Test password verification
        echo "<br><strong>Password Test:</strong><br>";
        if (password_verify($password, $row['password'])) {
            echo "✅ Hashed password verification: SUCCESS<br>";
        } else {
            echo "❌ Hashed password verification: FAILED<br>";
        }
        
        if ($password === $row['password']) {
            echo "✅ Plaintext password match: SUCCESS<br>";
        } else {
            echo "❌ Plaintext password match: FAILED<br>";
        }
        
    } else {
        echo "❌ No admin found with username/email: $username<br>";
    }
    $stmt->close();
} else {
    echo "❌ Query failed: " . $conn->error;
}

// Check lecturer table
echo "<h3>Checking Lecturer Table:</h3>";
$sql = "SELECT lecturer_id, lecturer_name, email, password FROM lecturers WHERE email = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "✅ Lecturer found!<br>";
        echo "ID: " . $row['lecturer_id'] . "<br>";
        echo "Name: " . $row['lecturer_name'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "Password hash: " . substr($row['password'], 0, 30) . "...<br>";
        
        // Test password verification
        echo "<br><strong>Password Test:</strong><br>";
        if (password_verify($password, $row['password'])) {
            echo "✅ Hashed password verification: SUCCESS<br>";
        } else {
            echo "❌ Hashed password verification: FAILED<br>";
        }
        
        if ($password === $row['password']) {
            echo "✅ Plaintext password match: SUCCESS<br>";
        } else {
            echo "❌ Plaintext password match: FAILED<br>";
        }
        
    } else {
        echo "❌ No lecturer found with email: $username<br>";
    }
    $stmt->close();
} else {
    echo "❌ Query failed: " . $conn->error;
}

// Check student table
echo "<h3>Checking Student Table:</h3>";
$sql = "SELECT student_id, full_name, email, password FROM students WHERE email = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "✅ Student found!<br>";
        echo "ID: " . $row['student_id'] . "<br>";
        echo "Name: " . $row['full_name'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "Password hash: " . substr($row['password'], 0, 30) . "...<br>";
        
        // Test password verification
        echo "<br><strong>Password Test:</strong><br>";
        if (password_verify($password, $row['password'])) {
            echo "✅ Hashed password verification: SUCCESS<br>";
        } else {
            echo "❌ Hashed password verification: FAILED<br>";
        }
        
        if ($password === $row['password']) {
            echo "✅ Plaintext password match: SUCCESS<br>";
        } else {
            echo "❌ Plaintext password match: FAILED<br>";
        }
        
    } else {
        echo "❌ No student found with email: $username<br>";
    }
    $stmt->close();
} else {
    echo "❌ Query failed: " . $conn->error;
}

echo "<hr>";
echo "<h3>All Users in Admin Table:</h3>";
$result = $conn->query("SELECT admin_id, username, email, status FROM admins");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['admin_id']}, Username: {$row['username']}, Email: {$row['email']}, Status: {$row['status']}<br>";
    }
} else {
    echo "Query failed: " . $conn->error;
}
?>