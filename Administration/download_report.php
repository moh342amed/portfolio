<?php
session_start();

// Logging function
function logError($message) {
    $logFile = __DIR__ . '/logs/download_report_errors.log';
    $logMessage = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    
    // Ensure logs directory exists
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Enhanced error handling
function sendErrorResponse($message, $statusCode = 404) {
    http_response_code($statusCode);
    
    // Log the error
    logError($message);
    
    // Set content type to JSON for structured error response
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => $message
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized access', 403);
}

// Database connection with improved error handling
try {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "attendance_management";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Validate and sanitize report ID
    $report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$report_id) {
        sendErrorResponse("Invalid report ID", 400);
    }

    // Fetch report details with enhanced security
    $stmt = $conn->prepare("SELECT attachment, title, generated_by FROM reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendErrorResponse("Report not found", 404);
    }

    $report = $result->fetch_assoc();
    $filename = $report['attachment'];
    $title = $report['title'];

    // Additional authorization check
    // Ensure user can only download their own reports or if they have admin role
    if ($_SESSION['role'] !== 'administration' && $report['generated_by'] != $_SESSION['user_id']) {
        sendErrorResponse("You are not authorized to download this report", 403);
    }

    // Construct secure file path
    $upload_base_dir = __DIR__ . '/uploads/reports/';
    $file_path = $upload_base_dir . $filename;

    // Additional file validation
    if (!file_exists($file_path)) {
        sendErrorResponse("File not found", 404);
    }

    if (!is_readable($file_path)) {
        sendErrorResponse("File is not readable", 500);
    }

    // Determine MIME type based on file extension
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel'
    ];

    $mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

    // Safe filename for download
    $safe_filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title);
    $download_filename = $safe_filename . '.' . $file_extension;

    // Force download with security headers
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file contents
    readfile($file_path);

    // Log successful download
    logError("Report $report_id downloaded by user {$_SESSION['user_id']}");

    $stmt->close();
    $conn->close();
    exit;

} catch (Exception $e) {
    sendErrorResponse("An unexpected error occurred: " . $e->getMessage(), 500);
}
?>