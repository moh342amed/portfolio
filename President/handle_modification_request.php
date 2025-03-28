<?php
session_start();

// Increase error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has president role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'president') {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Validate and sanitize input
$modification_id = isset($_POST['modification_id']) ? intval($_POST['modification_id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Validate input
if ($modification_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid modification ID'
    ]);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid action'
    ]);
    exit;
}

$admin_id = $_SESSION['user_id'];

// Begin transaction for data integrity
$conn->begin_transaction();

try {
    // First, verify the modification request exists
    $sql_check_modification = "
        SELECT * 
        FROM attendance_modifications 
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql_check_modification);
    $stmt->bind_param("i", $modification_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If no modification found, return error
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'No modification request found'
        ]);
        exit;
    }

    // Fetch the modification details
    $modification = $result->fetch_assoc();
    error_log('Modification Details: ' . print_r($modification, true));

    // Check if request is already processed
    if ($modification['status'] !== 'pending') {
        echo json_encode([
            'success' => false, 
            'message' => 'This modification request has already been ' . $modification['status']
        ]);
        exit;
    }

    // Update modification request status
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $sql_update_modification = "
        UPDATE attendance_modifications 
        SET status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql_update_modification);
    $stmt->bind_param("si", $status, $modification_id);
    $stmt->execute();

    // If approved, update the users_attendance table
    if ($action === 'approve') {
        // Prepare the full datetime for modification
        $full_datetime = $modification['modification_date'] . ' ' . 
                         ($modification['modification_time'] ?: '09:00:00');

        // Check if an attendance record exists for this user and date
        $sql_check_attendance = "
            SELECT id 
            FROM users_attendance 
            WHERE user_id = ? AND DATE(clock_in) = ?
        ";
        $stmt = $conn->prepare($sql_check_attendance);
        $stmt->bind_param("is", 
            $modification['user_id'], 
            $modification['modification_date']
        );
        $stmt->execute();
        $attendance_result = $stmt->get_result();

        // More generic approach to handle different modification types
        switch (strtolower($modification['modification_type'])) {
            case 'missing clock-in':
            case 'clockin':
            case 'clock-in':
                // If no existing record, insert a new one
                if ($attendance_result->num_rows === 0) {
                    $sql_update_attendance = "
                        INSERT INTO users_attendance 
                        (user_id, clock_in) 
                        VALUES (?, ?)
                    ";
                    $stmt = $conn->prepare($sql_update_attendance);
                    $stmt->bind_param("is", 
                        $modification['user_id'], 
                        $full_datetime
                    );
                } else {
                    // If record exists, update clock_in
                    $sql_update_attendance = "
                        UPDATE users_attendance 
                        SET clock_in = ? 
                        WHERE user_id = ? AND DATE(clock_in) = ?
                    ";
                    $stmt = $conn->prepare($sql_update_attendance);
                    $stmt->bind_param("sis", 
                        $full_datetime,
                        $modification['user_id'], 
                        $modification['modification_date']
                    );
                }
                break;

            case 'missing clock-out':
            case 'clockout':
            case 'clock-out':
                // Update or set clock_out
                $sql_update_attendance = "
                    UPDATE users_attendance 
                    SET clock_out = ? 
                    WHERE user_id = ? AND DATE(clock_in) = ?
                ";
                $stmt = $conn->prepare($sql_update_attendance);
                $stmt->bind_param("sis", 
                    $full_datetime,
                    $modification['user_id'], 
                    $modification['modification_date']
                );
                break;

            default:
                // Log the unexpected modification type
                error_log('Unexpected modification type: ' . $modification['modification_type']);
                throw new Exception('Unsupported modification type: ' . $modification['modification_type']);
        }

        // Execute the attendance update
        $stmt->execute();
    }

    // Create a notification for the user
    $notification_message = $action === 'approve' 
        ? 'Your attendance modification request has been approved.' 
        : 'Your attendance modification request has been rejected.';
    
    $sql_notification = "
        INSERT INTO notifications (user_id, title, message, created_at) 
        VALUES (?, 'Attendance Modification', ?, NOW())
    ";
    $stmt = $conn->prepare($sql_notification);
    $stmt->bind_param("is", $modification['user_id'], $notification_message);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => ucfirst($action) . ' successful',
        'status' => $status
    ]);

} catch (Exception $e) {
    // Rollback transaction in case of error
    $conn->rollback();

    // Return error response with more detailed error
    echo json_encode([
        'success' => false, 
        'message' => 'Error processing request: ' . $e->getMessage(),
        'debug' => [
            'modification_type' => $modification['modification_type'] ?? 'Not available'
        ]
    ]);
}

$conn->close();
?>