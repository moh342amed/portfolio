<?php
// Start session to access user info
session_start();

// Check if user is logged in and has administration role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administration') {
    header("Location: ../login.html");
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ReportsGenerationPage.php");
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
$reportName = $_POST['reportName'] ?? '';
$reportType = $_POST['reportType'] ?? '';
$recipients = $_POST['recipients'] ?? '';
$schedule = $_POST['schedule'] ?? 'weekly';
$format = $_POST['format'] ?? 'pdf';

// Validate form data
if (empty($reportName) || empty($reportType) || empty($recipients) || empty($schedule)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: ReportsGenerationPage.php");
    exit;
}

// This is where you would insert the scheduled report into a new database table
// Since we need to add this table, here's the SQL you would use:

/*
CREATE TABLE scheduled_reports (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    recipients TEXT NOT NULL,
    schedule VARCHAR(20) NOT NULL,
    last_run DATETIME NULL,
    next_run DATETIME NULL,
    format VARCHAR(10) NOT NULL,
    created_by INT(11) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

// For now, just simulate success
$_SESSION['success'] = "Report scheduled successfully.";
header("Location: ReportsGenerationPage.php");

// Close connection
$conn->close();
?>