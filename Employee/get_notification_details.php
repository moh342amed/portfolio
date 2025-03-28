<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed!']));
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in!']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['notification_id'])) {
    echo json_encode(['error' => 'Notification ID is required!']);
    exit;
}

$notification_id = $_GET['notification_id'];

$sql = "SELECT * FROM notifications WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Notification not found!']);
    exit;
}

$notification = $result->fetch_assoc();

$update_sql = "UPDATE notifications SET `read` = 1 WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $notification_id);
$update_stmt->execute();

$details = [];

if (strpos($notification['title'], 'Leave Request') !== false) {
    preg_match('/leave request #(\d+)/', strtolower($notification['message']), $matches);
    
    if (isset($matches[1])) {
        $leave_id = $matches[1];
        $leave_sql = "SELECT * FROM leave_requests WHERE id = ? AND user_id = ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("ii", $leave_id, $user_id);
        $leave_stmt->execute();
        $leave_result = $leave_stmt->get_result();
        
        if ($leave_result->num_rows > 0) {
            $leave_details = $leave_result->fetch_assoc();
            $details = [
                'leave_id' => $leave_details['id'],
                'leave_type' => $leave_details['leave_type'],
                'start_date' => $leave_details['start_date'],
                'end_date' => $leave_details['end_date'],
                'days' => $leave_details['days'],
                'reason' => $leave_details['reason'],
                'status' => $leave_details['status'],
                'submitted_at' => $leave_details['submitted_at']
            ];
        }
    }
} 
elseif (strpos($notification['title'], 'Attendance') !== false) {
    preg_match('/attendance request #(\d+)/', strtolower($notification['message']), $matches);
    
    if (isset($matches[1])) {
        $attendance_id = $matches[1];
        $attendance_sql = "SELECT * FROM attendance_modifications WHERE id = ? AND user_id = ?";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("ii", $attendance_id, $user_id);
        $attendance_stmt->execute();
        $attendance_result = $attendance_stmt->get_result();
        
        if ($attendance_result->num_rows > 0) {
            $attendance_details = $attendance_result->fetch_assoc();
            $details = [
                'attendance_id' => $attendance_details['id'],
                'modification_date' => $attendance_details['modification_date'],
                'modification_type' => $attendance_details['modification_type'],
                'reason' => $attendance_details['reason'],
                'status' => $attendance_details['status'],
                'admin_comment' => $attendance_details['admin_comment'],
                'created_at' => $attendance_details['created_at']
            ];
        }
    }
}

echo json_encode([
    'notification' => $notification,
    'details' => $details
]);

$conn->close();
?>
