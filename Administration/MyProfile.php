<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit;
}

// Centralized image upload function
function uploadProfilePicture($file, $user_id) {
    $upload_dir = __DIR__ . '/uploads/profile_pictures/';
    
    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Validate file
    $file_info = pathinfo($file['name']);
    $file_extension = strtolower($file_info['extension']);
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    // Validation checks
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception("Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.");
    }

    if ($file['size'] > $max_file_size) {
        throw new Exception("File is too large. Maximum size is 5MB.");
    }

    // Generate unique filename
    $new_filename = $user_id . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;

    // Image resizing and processing
    $source_image = match($file_extension) {
        'jpeg', 'jpg' => imagecreatefromjpeg($file['tmp_name']),
        'png' => imagecreatefrompng($file['tmp_name']),
        'gif' => imagecreatefromgif($file['tmp_name']),
        default => null
    };

    if (!$source_image) {
        throw new Exception("Failed to process image file.");
    }

    // Resize image
    $width = imagesx($source_image);
    $height = imagesy($source_image);
    $max_dimension = 800;

    if ($width > $height && $width > $max_dimension) {
        $new_width = $max_dimension;
        $new_height = floor($height * ($max_dimension / $width));
    } elseif ($height > $width && $height > $max_dimension) {
        $new_height = $max_dimension;
        $new_width = floor($width * ($max_dimension / $height));
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    $resized_image = imagecreatetruecolor($new_width, $new_height);

    // Handle transparency
    if ($file_extension == 'png' || $file_extension == 'gif') {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
        imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // Resize
    imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save resized image
    $save_functions = [
        'jpeg' => fn($img, $path) => imagejpeg($img, $path, 85),
        'jpg' => fn($img, $path) => imagejpeg($img, $path, 85),
        'png' => fn($img, $path) => imagepng($img, $path, 8),
        'gif' => fn($img, $path) => imagegif($img, $path)
    ];

    $save_functions[$file_extension]($resized_image, $target_file);

    // Free up memory
    imagedestroy($source_image);
    imagedestroy($resized_image);

    return $new_filename;
}

try {
    // Database connection with error handling
    $conn = new mysqli("localhost", "root", "", "attendance_management");
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Prepare user data
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['name'];
    $user_role = $_SESSION['role'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Fetch departments for dropdown
    $departments_query = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''");
    $departments = $departments_query ? $departments_query->fetch_all(MYSQLI_ASSOC) : [];

    // Fetch potential managers
    $managers_query = $conn->query("SELECT id, name FROM users WHERE role IN ('administration', 'president')");
    $managers = $managers_query ? $managers_query->fetch_all(MYSQLI_ASSOC) : [];

    $errors = [];
    $success_message = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Input sanitization and validation for additional fields
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $emergency_contact = filter_input(INPUT_POST, 'emergency_contact', FILTER_SANITIZE_STRING);
        
        // New fields
        $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
        $manager_id = filter_input(INPUT_POST, 'manager', FILTER_VALIDATE_INT);
        $join_date = filter_input(INPUT_POST, 'join_date', FILTER_SANITIZE_STRING);

        // Validation checks
        if (empty($name)) {
            $errors[] = "Name cannot be empty.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }

        // Profile picture upload (existing implementation)
        $profile_picture = $user['profile_picture'] ?? 'default-profile.png';
        if (!empty($_FILES['profile_picture']['name'])) {
            try {
                // Remove old profile picture if it's not default
                $old_file_path = __DIR__ . '/uploads/profile_pictures/' . $profile_picture;
                if ($profile_picture !== 'default-profile.png' && file_exists($old_file_path)) {
                    unlink($old_file_path);
                }

                // Upload and process new profile picture
                $profile_picture = uploadProfilePicture($_FILES['profile_picture'], $user_id);
            } catch (Exception $upload_error) {
                $errors[] = $upload_error->getMessage();
            }
        }

        // Update profile if no errors
        if (empty($errors)) {
            $update_stmt = $conn->prepare("UPDATE users SET 
                name = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                emergency_contact = ?, 
                profile_picture = ?,
                department = ?,
                manager = ?,
                join_date = ?
                WHERE id = ?");
            $manager_str = $manager_id ? (string)$manager_id : null;
            $update_stmt->bind_param("sssssssssi", 
                $name, $email, $phone, $address, $emergency_contact, 
                $profile_picture, $department, $manager_str, $join_date, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                $_SESSION['name'] = $name;
                
                // Refresh user data
                $user = array_merge($user, [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'emergency_contact' => $emergency_contact,
                    'profile_picture' => $profile_picture,
                    'department' => $department,
                    'manager' => $manager_str,
                    'join_date' => $join_date
                ]);
            } else {
                $errors[] = "Failed to update profile. Please try again.";
            }
        }
    }

    // Determine profile picture path
    $profile_picture_path = !empty($user['profile_picture']) 
        ? "./uploads/profile_pictures/" . $user['profile_picture'] 
        : "./uploads/profile_pictures/default-profile.png";

    // Fetch manager name if manager exists
    $manager_name = null;
    if (!empty($user['manager'])) {
        $manager_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $manager_stmt->bind_param("i", $user['manager']);
        $manager_stmt->execute();
        $manager_result = $manager_stmt->get_result();
        $manager_data = $manager_result->fetch_assoc();
        $manager_name = $manager_data ? $manager_data['name'] : null;
    }

} catch (Exception $e) {
    // Log error and redirect
    error_log($e->getMessage());
    header("Location: ../error.php");
    exit;
} finally {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS Variables */
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2ecc71;
            --secondary-dark: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f5f7fa;
            --dark-bg: #34495e;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-color: #bdc3c7;
            
            --header-height: 60px;
            --sidebar-width: 250px;
            --sidebar-collapsed: 60px;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Styles */
        header {
            height: var(--header-height);
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header .logo {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        header .app-title {
            margin-left: 20px;
            font-size: 1.1rem;
        }

        header .user-profile {
            margin-left: auto;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        header .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
        }

        /* User Dropdown */
        .user-dropdown {
            position: absolute;
            top: calc(var(--header-height) + 10px);
            right: 20px;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 200px;
            display: none;
            z-index: 101;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-dropdown ul {
            list-style-type: none;
        }

        .user-dropdown ul li a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: var(--dark-text);
            transition: background-color 0.3s;
        }

        .user-dropdown ul li a:hover {
            background-color: var(--light-bg);
        }

        /* Layout */
        .layout {
            display: flex;
            min-height: calc(100vh - var(--header-height));
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-bg);
            color: var(--light-text);
            height: calc(100vh - var(--header-height));
            position: sticky;
            top: var(--header-height);
            transition: width 0.3s;
            overflow-x: hidden;
        }

        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 15px;
            color: var(--light-text);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: var(--primary-color);
        }

        .sidebar ul li a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 20px;
        }

        /* Profile Specific Styles */
        .profile-header {
            display: flex;
            align-items: center;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 30px;
            border: 4px solid var(--primary-color);
        }

        .profile-details {
            flex-grow: 1;
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .profile-role {
            color: #777;
            margin-bottom: 10px;
        }

        /* Form Styles */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: bold;
        }

        .card-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        /* Notification Styles */
        .error-message {
            color: var(--danger-color);
            background-color: rgba(231, 76, 60, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .success-message {
            color: var(--secondary-color);
            background-color: rgba(46, 204, 113, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        /* Statistics Styles */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #777;
            margin-top: 10px;
        }

        /* File Input Styles */
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: inline-block;
            padding: 10px 15px;
            background-color: var(--secondary-color);
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 99;
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
            }
        }
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">AttendX</div>
        <div class="app-title">Employee Directory</div>
        <div class="user-profile" id="userProfileBtn">
            <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
            <span><?php echo htmlspecialchars($user_name); ?></span>
            <i class="fas fa-chevron-down" style="margin-left: 10px;"></i>
        </div>
        <div class="user-dropdown" id="userDropdown">
            <ul>
                <li><a href="./MyProfile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <ul>
                <li><a href="./EmployeeDirectoryPage.php"><i class="fas fa-users"></i> <span>Employee Directory</span></a></li>
                <li><a href="./AdminLeaveRequestManagementPage.php"><i class="fas fa-calendar-alt"></i> <span>Leave Requests</span></a></li>
                <li><a href="./AttendanceModificationManagementPage.php"><i class="fas fa-clock"></i> <span>Attendance Modification</span></a></li>
                <li><a href="./PenaltyManagementPage.php"><i class="fas fa-exclamation-triangle"></i> <span>Penalty Management</span></a></li>
                <li><a href="./ReportsGenerationPage.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="page-title">
                <h1>My Profile</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="profile-header">
                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
                             alt="Profile Picture" 
                             class="profile-avatar" 
                             id="profilePicturePreview">
                        <div class="profile-details">
                            <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="profile-role"><?php echo htmlspecialchars(ucfirst($user_role)); ?></div>
                            <div>
                                <?php echo htmlspecialchars($user['department'] ?? 'No Department'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Profile Information</div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group file-input-wrapper">
                            <label>Profile Picture</label>
                            <input type="file" 
                                   name="profile_picture" 
                                   id="profilePictureInput" 
                                   accept="image/jpeg,image/png,image/gif">
                            <label for="profilePictureInput" class="file-input-label">
                                <i class="fas fa-upload"></i> Change Profile Picture
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="name">Full Name*</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea 
                                id="address" 
                                name="address" 
                                rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact</label>
                            <textarea 
                                id="emergency_contact" 
                                name="emergency_contact" 
                                rows="3"><?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="department">Department</label>
                            <select id="department" name="department">
                                <option value="">Select Department</option>
                                <?php 
                                $unique_departments = array_unique(array_column($departments, 'department'));
                                foreach ($unique_departments as $dept): 
                                    $selected = ($user['department'] == $dept) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" 
                                id="department_other" 
                                name="department_other" 
                                placeholder="Specify Department" 
                                style="display:none; margin-top: 10px;">
                        </div>

                        <div class="form-group">
                            <label for="manager">Manager</label>
                            <select id="manager" name="manager">
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $mgr): 
                                    $selected = ($user['manager'] == $mgr['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($mgr['id']); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($mgr['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="join_date">Join Date</label>
                            <input type="date" 
                                id="join_date" 
                                name="join_date" 
                                value="<?php echo htmlspecialchars($user['join_date'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="btn">Update Profile</button>
                    </form>
                </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile picture preview
        const profilePictureInput = document.getElementById('profilePictureInput');
        const profilePicturePreview = document.getElementById('profilePicturePreview');

        profilePictureInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePicturePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // User dropdown toggle
        const userProfileBtn = document.getElementById('userProfileBtn');
        const userDropdown = document.getElementById('userDropdown');

        userProfileBtn.addEventListener('click', function() {
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!userProfileBtn.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('show');
            }
        });


        // Department other option handling
        const departmentSelect = document.getElementById('department');
        const departmentOtherInput = document.getElementById('department_other');

        departmentSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                departmentOtherInput.style.display = 'block';
                departmentOtherInput.required = true;
            } else {
                departmentOtherInput.style.display = 'none';
                departmentOtherInput.required = false;
            }
        });

        // If "other" was previously selected, show the input
        if (departmentSelect.value === 'other') {
            departmentOtherInput.style.display = 'block';
        }
    });
    </script>
</body>
</html>