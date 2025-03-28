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
$firstName = $_POST['first-name'];
$lastName = $_POST['last-name'];
$fullName = $firstName . ' ' . $lastName;
$email = $_POST['email'];
$phone = $_POST['phone']; // Add this line to get the phone number from the form
$department = $_POST['department'];
$manager = $_POST['manager'];
$address = $_POST['address'];
$emergencyContact = $_POST['emergency-contact'];
$joinDate = $_POST['join-date'];
$userId = $_SESSION['user_id'];

// Update user data
$sql = "UPDATE users SET 
        name = ?, 
        email = ?, 
        phone = ?,  -- Add phone to the query
        department = ?, 
        manager = ?, 
        address = ?, 
        emergency_contact = ?, 
        join_date = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssssi", $fullName, $email, $phone, $department, $manager, $address, $emergencyContact, $joinDate, $userId); // Add $phone to bind_param

if ($stmt->execute()) {
    // Update session data
    $_SESSION['name'] = $fullName;
    
    // Redirect back to profile page with success message
    header("Location: EmployeeProfilePage.php?success=1");
} else {
    // Redirect back to profile page with error message
    header("Location: EmployeeProfilePage.php?error=1");
}

$stmt->close();
$conn->close();
?>