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
$reportType = $_POST['reportType'] ?? '';
$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';
$department = $_POST['department'] ?? '';
$format = $_POST['format'] ?? 'pdf';
$includeCharts = isset($_POST['includeCharts']) ? 1 : 0;

// Validate form data
if (empty($reportType) || empty($startDate) || empty($endDate)) {
    $_SESSION['error'] = "Required fields are missing.";
    header("Location: ReportsGenerationPage.php");
    exit;
}

// Define upload directory with a relative path
$uploadDir = __DIR__ . "/uploads/reports/";

// Ensure the upload directory exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate a unique filename
$filename = $reportType . '_' . date('Ymd_His') . '.' . $format;
$filepath = $uploadDir . $filename;

// Create title based on report type
$titlePrefix = "";
switch ($reportType) {
    case 'attendance':
        $titlePrefix = "Attendance Summary";
        break;
    case 'leave':
        $titlePrefix = "Leave Management";
        break;
    case 'performance':
        $titlePrefix = "Performance Analysis";
        break;
    case 'penalty':
        $titlePrefix = "Penalty Report";
        break;
    case 'department':
        $titlePrefix = "Department Summary";
        break;
    default:
        $titlePrefix = "Custom Report";
}

$dateRange = date('M d, Y', strtotime($startDate)) . " to " . date('M d, Y', strtotime($endDate));
$title = $titlePrefix . ": " . $dateRange;
if (!empty($department)) {
    $title .= " - " . $department . " Department";
}

// Prepare department filter
$departmentFilter = !empty($department) ? " AND u.department = '" . $conn->real_escape_string($department) . "'" : "";

// Fetch report data based on report type
$reportData = [];
switch ($reportType) {
    case 'attendance':
        $sql = "SELECT 
                    u.id, 
                    u.name, 
                    u.department, 
                    COUNT(ua.id) as total_days_present,
                    (SELECT COUNT(*) FROM users_attendance ua2 
                     WHERE ua2.user_id = u.id 
                     AND ua2.clock_in BETWEEN ? AND ?) as date_range_present,
                    u.present_days,
                    u.absent_days
                FROM users u
                LEFT JOIN users_attendance ua ON u.id = ua.user_id
                WHERE ua.clock_in BETWEEN ? AND ?
                $departmentFilter
                GROUP BY u.id
                ORDER BY date_range_present DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;

    case 'leave':
        $sql = "SELECT 
                    u.id, 
                    u.name, 
                    u.department, 
                    lr.leave_type,
                    lr.start_date,
                    lr.end_date,
                    lr.status,
                    lr.days
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.id
                WHERE lr.start_date BETWEEN ? AND ?
                $departmentFilter
                ORDER BY lr.start_date";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;

    case 'performance':
        $sql = "SELECT 
                    u.id, 
                    u.name, 
                    u.department, 
                    u.present_days,
                    u.absent_days,
                    u.late_arrivals,
                    (SELECT COUNT(*) FROM penalties p WHERE p.user_id = u.id) as total_penalties
                FROM users u
                WHERE 1=1
                $departmentFilter
                ORDER BY u.present_days DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;

    case 'penalty':
        $sql = "SELECT 
                    u.id, 
                    u.name, 
                    u.department, 
                    p.penalty_type,
                    p.incident_date,
                    p.severity,
                    p.description,
                    p.status
                FROM penalties p
                JOIN users u ON p.user_id = u.id
                WHERE p.incident_date BETWEEN ? AND ?
                $departmentFilter
                ORDER BY p.incident_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;

    case 'department':
        $sql = "SELECT 
                    department, 
                    COUNT(DISTINCT id) as total_employees,
                    SUM(present_days) as total_present_days,
                    SUM(absent_days) as total_absent_days,
                    AVG(leave_balance) as avg_leave_balance
                FROM users
                WHERE department IS NOT NULL AND department != ''
                $departmentFilter
                GROUP BY department";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;
}

// Insert report record into database
$sql = "INSERT INTO reports (title, department, generated_by, generated_date, status, attachment, created_at) 
        VALUES (?, ?, ?, NOW(), 'pending', ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $title, $department, $_SESSION['user_id'], $filename);

if ($stmt->execute()) {
    $report_id = $conn->insert_id;
    
    // Prepare report content
    $reportContent = "Report Details:\n\n";
    $reportContent .= "Title: " . $title . "\n";
    $reportContent .= "Report Type: " . $reportType . "\n";
    $reportContent .= "Date Range: " . $dateRange . "\n";
    if (!empty($department)) {
        $reportContent .= "Department: " . $department . "\n";
    }
    $reportContent .= "Generated On: " . date('Y-m-d H:i:s') . "\n";
    $reportContent .= "Generated By: " . $_SESSION['name'] . "\n\n";
    
    // Add report data to content
    $reportContent .= "Report Data:\n";
    switch ($reportType) {
        case 'attendance':
            $reportContent .= "Name\tDepartment\tDays Present\n";
            foreach ($reportData as $row) {
                $reportContent .= "{$row['name']}\t{$row['department']}\t{$row['date_range_present']}\n";
            }
            break;
        case 'leave':
            $reportContent .= "Name\tDepartment\tLeave Type\tStart Date\tEnd Date\tStatus\n";
            foreach ($reportData as $row) {
                $reportContent .= "{$row['name']}\t{$row['department']}\t{$row['leave_type']}\t{$row['start_date']}\t{$row['end_date']}\t{$row['status']}\n";
            }
            break;
        // Add similar formatting for other report types
    }
    
    // Write the content to the file based on format
    try {
        switch ($format) {
            case 'pdf':
                // In a real-world scenario, you'd use a PDF library like TCPDF or FPDF
                file_put_contents($filepath, $reportContent);
                break;
            case 'excel':
                // In a real-world scenario, you'd use PHPExcel or PhpSpreadsheet
                file_put_contents($filepath, $reportContent);
                break;
            case 'csv':
                file_put_contents($filepath, $reportContent);
                break;
            default:
                file_put_contents($filepath, $reportContent);
        }
        
        // Verify file was created
        if (file_exists($filepath)) {
            // Redirect to download page
            $_SESSION['success'] = "Report generated successfully.";
            header("Location: download_report.php?id=" . $report_id);
        } else {
            $_SESSION['error'] = "Failed to create report file.";
            header("Location: ReportsGenerationPage.php");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error generating report: " . $e->getMessage();
        header("Location: ReportsGenerationPage.php");
    }
} else {
    $_SESSION['error'] = "Failed to generate report: " . $conn->error;
    header("Location: ReportsGenerationPage.php");
}

// Close connection
$stmt->close();
$conn->close();
?>