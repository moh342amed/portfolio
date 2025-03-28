<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$currentPassword = $_POST['current-password'];
$newPassword = $_POST['new-password'];
$confirmPassword = $_POST['confirm-password'];
$userId = $_SESSION['user_id'];

// Check if new password and confirm password match
if ($newPassword !== $confirmPassword) {
    header("Location: EmployeeProfilePage.php?error=passwords_dont_match");
    exit;
}

// Fetch current user data to verify the current password
$sql = "SELECT password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: EmployeeProfilePage.php?error=user_not_found");
    exit;
}

$user = $result->fetch_assoc();

// Verify current password
if (!password_verify($currentPassword, $user['password'])) {
    header("Location: EmployeeProfilePage.php?error=incorrect_password");
    exit;
}

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password in the database
$updateSql = "UPDATE users SET password = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("si", $hashedPassword, $userId);

if ($updateStmt->execute()) {
    header("Location: EmployeeProfilePage.php?success=password_updated");
} else {
    header("Location: EmployeeProfilePage.php?error=update_failed");
}

$stmt->close();
$updateStmt->close();
$conn->close();
?>