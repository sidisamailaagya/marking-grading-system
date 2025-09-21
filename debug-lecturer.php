<?php
// debug-lecturer.php - Check lecturer login issues
require_once __DIR__ . '/includes/connect.php';

echo "<h2>Lecturer Login Debug</h2>";

// Check what's in the lecturers table
echo "<h3>Current Lecturers in Database:</h3>";
$result = $conn->query("SELECT lecturer_id, lecturer_name, email, password, status FROM lecturers");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Password</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['lecturer_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['lecturer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . (strlen($row['password']) > 20 ? 'HASHED (' . strlen($row['password']) . ' chars)' : 'PLAIN: ' . $row['password']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Query failed: " . $conn->error;
}

// Test specific lecturer
$email = 'sarah@edu.ng';
$password = '123456';

echo "<h3>Testing Login for: $email</h3>";

$sql = "SELECT lecturer_id, lecturer_name, email, password FROM lecturers WHERE email = ? LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "✅ Lecturer found!<br>";
        echo "ID: " . $row['lecturer_id'] . "<br>";
        echo "Name: " . htmlspecialchars($row['lecturer_name']) . "<br>";
        echo "Email: " . htmlspecialchars($row['email']) . "<br>";
        echo "Password in DB: " . substr($row['password'], 0, 30) . "...<br>";
        
        // Test password verification
        echo "<br><strong>Password Tests:</strong><br>";
        if (password_verify($password, $row['password'])) {
            echo "✅ password_verify('$password', stored_hash): SUCCESS<br>";
        } else {
            echo "❌ password_verify('$password', stored_hash): FAILED<br>";
        }
        
        if ($password === $row['password']) {
            echo "✅ Plain text match: SUCCESS<br>";
        } else {
            echo "❌ Plain text match: FAILED<br>";
        }
        
        // Test with different passwords
        $testPasswords = ['123456', '12345678', 'password', 'sarah123'];
        foreach ($testPasswords as $testPwd) {
            if (password_verify($testPwd, $row['password'])) {
                echo "✅ Found working password: '$testPwd'<br>";
                break;
            }
        }
        
    } else {
        echo "❌ No lecturer found with email: $email<br>";
    }
    $stmt->close();
} else {
    echo "❌ Query preparation failed: " . $conn->error;
}

// Show table structure
echo "<h3>Lecturers Table Structure:</h3>";
$result = $conn->query("DESCRIBE lecturers");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>