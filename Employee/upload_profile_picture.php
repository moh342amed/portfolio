<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    // Define upload directory with absolute path for reliability
    $uploadDir = __DIR__ . '/uploads/profile_pictures/';
    
    // Debug information
    error_log("Upload directory: " . $uploadDir);
    
    // Ensure the upload directory exists with proper permissions
    if (!is_dir($uploadDir)) {
        error_log("Creating directory: " . $uploadDir);
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create directory: " . $uploadDir);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory. Check server permissions.']);
            exit;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        error_log("Directory not writable: " . $uploadDir);
        echo json_encode(['success' => false, 'error' => 'Upload directory is not writable. Check server permissions.']);
        exit;
    }

    // Validate file type and size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    
    // Debug file information
    error_log("File type: " . $_FILES['profile_picture']['type']);
    error_log("File size: " . $_FILES['profile_picture']['size']);

    if (!in_array($_FILES['profile_picture']['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
        exit;
    }

    if ($_FILES['profile_picture']['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds the maximum limit of 2MB.']);
        exit;
    }

    // Generate a unique file name
    $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $uploadFile = $uploadDir . $fileName;
    
    error_log("Attempting to move uploaded file to: " . $uploadFile);

    // Move the uploaded file to the target directory
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
        error_log("File successfully uploaded to: " . $uploadFile);
        
        // Update the database with the new profile picture path
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "attendance_management";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
            exit;
        }

        $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fileName, $userId);

        if ($stmt->execute()) {
            error_log("Database updated successfully for user ID: " . $userId);
            echo json_encode(['success' => true, 'fileName' => $fileName]);
        } else {
            error_log("Database update failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Error updating profile picture in the database.']);
        }

        $stmt->close();
        $conn->close();
    } else {
        // Debugging: Check for file upload errors
        $error = $_FILES['profile_picture']['error'];
        $errorMessages = [
            0 => 'No error',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload',
        ];
        $errorMessage = isset($errorMessages[$error]) ? $errorMessages[$error] : 'Unknown error';
        error_log("File upload failed with error code $error: $errorMessage");
        echo json_encode(['success' => false, 'error' => 'Error uploading file: ' . $errorMessage]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
}
?>