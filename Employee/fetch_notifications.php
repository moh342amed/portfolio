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
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM notifications WHERE user_id = ?";

if ($filter === 'unread') {
    $sql .= " AND `read` = 0";
} elseif ($filter === 'leave') {
    $sql .= " AND (title LIKE '%Leave%' OR message LIKE '%Leave%')";
} elseif ($filter === 'attendance') {
    $sql .= " AND (title LIKE '%Attendance%' OR message LIKE '%Attendance%')";
} elseif ($filter === 'system') {
    $sql .= " AND (title LIKE '%System%' OR message LIKE '%System%')";
} elseif ($filter === 'penalty') {
    $sql .= " AND (title LIKE '%Penalty%' OR message LIKE '%Warning%')";
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
if ($filter === 'unread') {
    $count_sql .= " AND `read` = 0";
} elseif ($filter === 'leave') {
    $count_sql .= " AND (title LIKE '%Leave%' OR message LIKE '%Leave%')";
} elseif ($filter === 'attendance') {
    $count_sql .= " AND (title LIKE '%Attendance%' OR message LIKE '%Attendance%')";
} elseif ($filter === 'system') {
    $count_sql .= " AND (title LIKE '%System%' OR message LIKE '%System%')";
} elseif ($filter === 'penalty') {
    $count_sql .= " AND (title LIKE '%Penalty%' OR message LIKE '%Warning%')";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_notifications = $count_row['total'];

$unread_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND `read` = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_row = $unread_result->fetch_assoc();
$unread_count = $unread_row['unread_count'];

$notifications = [];
$has_more = ($total_notifications > ($page * $limit));

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $timestamp;

    if ($time_difference < 60) {
        return "Just now";
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($time_difference < 172800) {
        return "Yesterday";
    } elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

function getIconClass($title) {
    if (strpos($title, 'Approved') !== false) {
        return "success";
    } elseif (strpos($title, 'Rejected') !== false) {
        return "danger";
    } elseif (strpos($title, 'Warning') !== false || strpos($title, 'Penalty') !== false) {
        return "warning";
    } else {
        return "";
    }
}

function getIconName($title) {
    if (strpos($title, 'Leave') !== false) {
        return "calendar-check";
    } elseif (strpos($title, 'Attendance') !== false) {
        return "calendar-alt";
    } elseif (strpos($title, 'System') !== false) {
        return "cog";
    } elseif (strpos($title, 'Warning') !== false) {
        return "exclamation-triangle";
    } elseif (strpos($title, 'Penalty') !== false) {
        return "exclamation-circle";
    } else {
        return "bell";
    }
}

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'created_at' => $row['created_at'],
        'time_ago' => timeAgo($row['created_at']),
        'read' => (bool)$row['read'],
        'icon_class' => getIconClass($row['title']),
        'icon_name' => getIconName($row['title'])
    ];
}

$category_counts = [
    'all' => $total_notifications,
    'unread' => $unread_count
];

$leave_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (title LIKE '%Leave%' OR message LIKE '%Leave%')";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $user_id);
$leave_stmt->execute();
$leave_result = $leave_stmt->get_result();
$leave_row = $leave_result->fetch_assoc();
$category_counts['leave'] = $leave_row['count'];

$attendance_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (title LIKE '%Attendance%' OR message LIKE '%Attendance%')";
$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("i", $user_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
$attendance_row = $attendance_result->fetch_assoc();
$category_counts['attendance'] = $attendance_row['count'];

$system_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (title LIKE '%System%' OR message LIKE '%System%')";
$system_stmt = $conn->prepare($system_sql);
$system_stmt->bind_param("i", $user_id);
$system_stmt->execute();
$system_result = $system_stmt->get_result();
$system_row = $system_result->fetch_assoc();
$category_counts['system'] = $system_row['count'];

$penalty_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (title LIKE '%Penalty%' OR message LIKE '%Warning%')";
$penalty_stmt = $conn->prepare($penalty_sql);
$penalty_stmt->bind_param("i", $user_id);
$penalty_stmt->execute();
$penalty_result = $penalty_stmt->get_result();
$penalty_row = $penalty_result->fetch_assoc();
$category_counts['penalty'] = $penalty_row['count'];

echo json_encode([
    'notifications' => $notifications,
    'has_more' => $has_more,
    'category_counts' => $category_counts
]);

$conn->close();
?>