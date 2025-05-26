<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn
require_once __DIR__ . '/../send_mail.php';

// Ensure user is logged in and is a dentist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
// Verify dentist role
$roleStmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
$roleName = 'dentist';
mysqli_stmt_bind_param($roleStmt, 's', $roleName);
mysqli_stmt_execute($roleStmt);
mysqli_stmt_bind_result($roleStmt, $dentistRoleId);
mysqli_stmt_fetch($roleStmt);
mysqli_stmt_close($roleStmt);

if ($_SESSION['role_id'] != $dentistRoleId) {
    header('Location: ../login.php');
    exit;
}

$dentistId = $_SESSION['user_id'];

// Get dentist name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Prepare client role ID
$roleStmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
$clientRoleName = 'client';
mysqli_stmt_bind_param($roleStmt, 's', $clientRoleName);
mysqli_stmt_execute($roleStmt);
mysqli_stmt_bind_result($roleStmt, $clientRoleId);
mysqli_stmt_fetch($roleStmt);
mysqli_stmt_close($roleStmt);

// Initialize variables
$id       = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$name     = '';
$email    = '';
$password = '';
$errors   = [];  

// If editing, load existing client data
if ($id) {
    $stmt = mysqli_prepare($conn,
        'SELECT name, email FROM users WHERE id = ? AND role_id = ? AND dentist_id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'iii', $id, $clientRoleId, $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email);
    if (!mysqli_stmt_fetch($stmt)) {
        // Client not found or not belonging to this dentist
        mysqli_stmt_close($stmt);
        header('Location: clients.php');
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Name and valid email are required.';
    }
    // Unique email check (exclude current client if editing)
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? AND id != ?');
    mysqli_stmt_bind_param($stmt, 'si', $email, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = 'Email is already registered.';
    }
    mysqli_stmt_close($stmt);

    // Require password for new client
    if (!$id && empty($password)) {
        $errors[] = 'Password is required for new clients.';
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
        }
        if ($id) {
            // Update client
            if (!empty($password)) {
                $stmt = mysqli_prepare($conn,
                    'UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?'
                );
                mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $hash, $id);
            } else {
                $stmt = mysqli_prepare($conn,
                    'UPDATE users SET name = ?, email = ? WHERE id = ?'
                );
                mysqli_stmt_bind_param($stmt, 'ssi', $name, $email, $id);
            }
            editAccount($email, $password);
        } else {
            // Insert new client under this dentist
            $stmt = mysqli_prepare($conn,
                'INSERT INTO users (role_id, dentist_id, name, email, password_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            mysqli_stmt_bind_param($stmt, 'iisss', $clientRoleId, $dentistId, $name, $email, $hash);
            send_mail($email, $password);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: clients.php');
        exit;
    }
}

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Edit' : 'Add New' ?> Patient - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="dentistDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic</span>
            </div>
            <div class="nav-user">
                <span>Welcome, Dr. <?= htmlspecialchars($dentistName) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li>
                        <a href="dentist_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="timetable.php">
                            <i class="fas fa-calendar-week"></i>
                            <span>Timetable</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="clients.php">
                            <i class="fas fa-users"></i>
                            <span>Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="appointments.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <div class="content-section active">
                    <div class="form-header">
                        <h2>
                            <i class="fas fa-<?= $id ? 'user-edit' : 'user-plus' ?>"></i>
                            <?= $id ? 'Edit Patient' : 'Add New Patient' ?>
                        </h2>
                        <a href="clients.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Patients
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <div class="error-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="appointment-form">
                        <form method="post">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">
                                        <i class="fas fa-user"></i> Full Name
                                    </label>
                                    <input type="text" 
                                           id="name"
                                           name="name" 
                                           value="<?= htmlspecialchars($name) ?>" 
                                           required
                                           placeholder="Enter patient's full name">
                                </div>

                                <div class="form-group">
                                    <label for="email">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" 
                                           id="email"
                                           name="email" 
                                           value="<?= htmlspecialchars($email) ?>" 
                                           required
                                           placeholder="Enter email address">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" 
                                       id="password"
                                       name="password" 
                                       <?= $id ? '' : 'required' ?>
                                       placeholder="<?= $id ? 'Leave blank to keep current password' : 'Enter a secure password' ?>">
                                <?php if ($id): ?>
                                    <small class="form-help">Leave blank to keep current password</small>
                                <?php else: ?>
                                    <small class="form-help">Patient will use this password to log in to their account</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $id ? 'Update Patient' : 'Add Patient' ?>
                                </button>
                                <a href="clients.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <?php if (!$id): ?>
                    <div class="quick-actions">
                        <h3>After Adding Patient</h3>
                        <div class="action-buttons">
                            <div class="action-btn" onclick="showScheduleInfo()">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Schedule Appointment</span>
                            </div>
                            <div class="action-btn" onclick="showMessageInfo()">
                                <i class="fas fa-envelope"></i>
                                <span>Send Welcome Message</span>
                            </div>
                            <div class="action-btn" onclick="showHistoryInfo()">
                                <i class="fas fa-file-medical"></i>
                                <span>Add Medical History</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // Auto-generate email from name
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value.toLowerCase().replace(/\s+/g, '.');
            const emailField = document.getElementById('email');
            
            if (name && !emailField.value) {
                emailField.placeholder = name + '@example.com';
            }
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strength = getPasswordStrength(password);
            
            // You could add a visual strength indicator here
        });

        function getPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }

        function showScheduleInfo() {
            alert('After adding the patient, you can schedule their first appointment from the patient list.');
        }

        function showMessageInfo() {
            alert('You can send a welcome message to the patient from the Messages section.');
        }

        function showHistoryInfo() {
            alert('Medical history functionality would be implemented here.');
        }
    </script>
</body>
</html>