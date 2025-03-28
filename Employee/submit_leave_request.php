<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get form data
$userId = $_SESSION['user_id'];
$leaveType = $_POST['leave_type'];
$startDate = $_POST['start_date'];
$endDate = $_POST['end_date'];
$reason = $_POST['reason'];

// Validate input
if (empty($leaveType) || empty($startDate) || empty($endDate)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'All required fields must be filled']);
    exit;
}

// Calculate number of days
$start = new DateTime($startDate);
$end = new DateTime($endDate);
$end->modify('+1 day'); // Include end date in the count
$interval = $start->diff($end);
$days = $interval->days;

// Validate dates
if ($days <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'End date must be after start date']);
    exit;
}

// Check leave balance
$balanceColumn = $leaveType . '_leave_balance';
$stmt = $conn->prepare("SELECT $balanceColumn FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verify sufficient leave balance
if ($leaveType != 'unpaid' && $user[$balanceColumn] < $days) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Insufficient leave balance']);
    exit;
}

// Handle file upload if present
$attachmentPath = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
    $uploadDir = __DIR__ . '/uploads/leave_attachments/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . $_FILES['attachment']['name'];
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
        $attachmentPath = $uploadPath;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert leave request
    $insertLeave = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, days, reason, attachment) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertLeave->bind_param("isssiss", $userId, $leaveType, $startDate, $endDate, $days, $reason, $attachmentPath);
    $insertLeave->execute();
    
    // Update leave balance (except for unpaid leave)
    if ($leaveType != 'unpaid') {
        $updateBalance = $conn->prepare("UPDATE users SET $balanceColumn = $balanceColumn - ? WHERE id = ?");
        $updateBalance->bind_param("ii", $days, $userId);
        $updateBalance->execute();
    }
    
    // Create notification for managers
    $notifTitle = "New Leave Request";
    $notifMessage = "Employee " . $_SESSION['name'] . " requested " . $days . " day(s) of " . $leaveType . " leave.";
    
    // Find managers
    $getManagers = $conn->prepare("SELECT id FROM users WHERE role = 'manager'");
    $getManagers->execute();
    $managersResult = $getManagers->get_result();
    
    while ($manager = $managersResult->fetch_assoc()) {
        $insertNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        $insertNotif->bind_param("iss", $manager['id'], $notifTitle, $notifMessage);
        $insertNotif->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to submit leave request: ' . $e->getMessage()]);
}

$conn->close();
?>