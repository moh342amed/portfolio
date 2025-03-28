<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Logging function for debugging
function detailedErrorLog($message, $data = null) {
    $logMessage = '[Attendance Modification Debug] ' . $message;
    if ($data !== null) {
        $logMessage .= ' | Details: ' . json_encode($data);
    }
    error_log($logMessage);
}

// Start session and enable strict session handling
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Enhanced authorization check
function isAuthorized() {
    $allowedRoles = ['administration', 'admin', 'administrator'];
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    return in_array(strtolower($_SESSION['role']), $allowedRoles);
}

// Comprehensive error handling
function sendErrorResponse($message, $details = null) {
    $response = ['error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

// Validate and sanitize input
function validateInput($input) {
    return htmlspecialchars(stripslashes(trim($input)));
}

// Check if user is authorized
if (!isAuthorized()) {
    sendErrorResponse('Unauthorized access', [
        'user_id_set' => isset($_SESSION['user_id']),
        'role_set' => isset($_SESSION['role']),
        'current_role' => $_SESSION['role'] ?? 'Not set'
    ]);
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method');
}

// Validate required parameters
$requiredParams = ['request_id', 'status'];
foreach ($requiredParams as $param) {
    if (!isset($_POST[$param])) {
        sendErrorResponse("Missing required parameter: $param");
    }
}

$request_id = intval($_POST['request_id']);
$status = validateInput($_POST['status']);
$admin_comment = isset($_POST['admin_comment']) ? validateInput($_POST['admin_comment']) : '';
$admin_id = $_SESSION['user_id'];

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    sendErrorResponse('Invalid status');
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    sendErrorResponse('Database connection failed', $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->begin_transaction();

try {
    // Fetch the modification request details
    $sql = "SELECT * FROM attendance_modifications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Request not found');
    }

    $request = $result->fetch_assoc();
    
    // Fix modification type inconsistencies
    $mod_type = str_replace('-', '_', $request['modification_type']);

    // Update modification request status
    $update_sql = "
        UPDATE attendance_modifications 
        SET status = ?, admin_comment = ?, updated_at = NOW() 
        WHERE id = ?
    ";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $status, $admin_comment, $request_id);
    $update_stmt->execute();

    // Process only if approved
    if ($status === 'approved') {
        $user_id = $request['user_id'];
        $mod_date = $request['modification_date'];
        $mod_time = $request['modification_time'] ?? date('H:i:s');
        $datetime = $mod_date . ' ' . $mod_time;

        // Detailed logging
        detailedErrorLog('Processing Approval', [
            'user_id' => $user_id,
            'mod_date' => $mod_date,
            'mod_type' => $mod_type,
            'mod_time' => $mod_time
        ]);

        // Check existing attendance record
        $check_sql = "SELECT id, clock_in, clock_out FROM users_attendance WHERE user_id = ? AND DATE(clock_in) = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $mod_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $existing_record = $check_result->fetch_assoc();
            $attendance_id = $existing_record['id'];

            switch ($mod_type) {
                case 'clock_in':
                    $update_attendance_sql = "UPDATE users_attendance SET clock_in = ? WHERE id = ?";
                    $update_attendance_stmt = $conn->prepare($update_attendance_sql);
                    $update_attendance_stmt->bind_param("si", $datetime, $attendance_id);
                    $update_attendance_stmt->execute();
                    break;
                
                case 'clock_out':
                    $update_attendance_sql = "UPDATE users_attendance SET clock_out = ? WHERE id = ?";
                    $update_attendance_stmt = $conn->prepare($update_attendance_sql);
                    $update_attendance_stmt->bind_param("si", $datetime, $attendance_id);
                    $update_attendance_stmt->execute();
                    break;
                
                case 'delete_record':
                    $delete_attendance_sql = "DELETE FROM users_attendance WHERE id = ?";
                    $delete_attendance_stmt = $conn->prepare($delete_attendance_sql);
                    $delete_attendance_stmt->bind_param("i", $attendance_id);
                    $delete_attendance_stmt->execute();
                    break;
                
                case 'add_record':
                    $update_both_sql = "UPDATE users_attendance SET clock_in = ?, clock_out = ? WHERE id = ?";
                    $update_both_stmt = $conn->prepare($update_both_sql);
                    $update_both_stmt->bind_param("ssi", $datetime, $datetime, $attendance_id);
                    $update_both_stmt->execute();
                    break;
                
                default:
                    throw new Exception('Invalid modification type: ' . $mod_type);
            }
        } else {
            if (in_array($mod_type, ['add_record', 'clock_in'])) {
                $insert_sql = "INSERT INTO users_attendance (user_id, clock_in, clock_out) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $clock_out = $mod_type === 'add_record' ? $datetime : null;
                $insert_stmt->bind_param("iss", $user_id, $datetime, $clock_out);
                $insert_stmt->execute();
            } else {
                throw new Exception('Cannot process modification request without existing record');
            }
        }
    }

    // Prepare notification
    $title = $status === 'approved' ? "Attendance Modification Approved" : "Attendance Modification Rejected";
    $message = "Your attendance modification request for " . date('F j, Y', strtotime($request['modification_date'])) . " has been " . $status . ".";

    // Insert notification
    $notification_sql = "INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())";
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->bind_param("iss", $request['user_id'], $title, $message);
    $notification_stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Modification request processed successfully']);

} catch (Exception $e) {
    $conn->rollback();
    detailedErrorLog('Processing error: ' . $e->getMessage());
    sendErrorResponse('Request processing failed', ['message' => $e->getMessage()]);
}

$conn->close();
?>