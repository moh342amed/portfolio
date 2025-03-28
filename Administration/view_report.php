<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
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

// Validate report ID
$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$report_id) {
    die("Invalid report ID");
}

// Fetch report details
$stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Report not found");
}

$report = $result->fetch_assoc();

// Authorization check
if ($_SESSION['role'] !== 'administration' && $report['generated_by'] != $_SESSION['user_id']) {
    die("You are not authorized to view this report");
}

// Get file path
$upload_base_dir = __DIR__ . '/uploads/reports/';
$file_path = $upload_base_dir . $report['attachment'];

// Read file contents
$file_contents = file_exists($file_path) ? file_get_contents($file_path) : "No content available";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .report-container {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .report-header {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .report-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <h1><?php echo htmlspecialchars($report['title']); ?></h1>
            <p>
                Generated on: <?php echo date('M d, Y H:i', strtotime($report['generated_date'])); ?> 
                | Format: <?php echo strtoupper(pathinfo($report['attachment'], PATHINFO_EXTENSION)); ?>
            </p>
        </div>
        <div class="report-content">
            <?php echo htmlspecialchars($file_contents); ?>
        </div>
    </div>
</body>
</html>