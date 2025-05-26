<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../send_mail.php';

function random_str() {
    $length = 12;
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;

    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
}

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Get admin name
$admin_id = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $admin_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Initialize variables
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$name = '';
$email = '';
$role_id = 0;
$dentist_id = null;
$password = '';
$errors = [];
$success = false;
$generated_password = '';

// Fetch all roles
$roles = [];
$roleRes = mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');

if ($request_id) {
    $stmt = mysqli_prepare($conn, 'SELECT name, email FROM client_requests WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $request_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE name = 'dentist'");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $role_id);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    while ($r = mysqli_fetch_assoc($roleRes)) {
        if ($r['id'] == 3)
            $roles[] = $r;
    }
}
else {
    while ($r = mysqli_fetch_assoc($roleRes)) {
        $roles[] = $r;
    }
}

// Get role IDs
$clientRoleId = null;
$dentistRoleId = null;
foreach ($roles as $r) {
    if ($r['name'] === 'client') $clientRoleId = $r['id'];
    if ($r['name'] === 'dentist') $dentistRoleId = $r['id'];
}

$dstmt = mysqli_prepare($conn,
    "SELECT id 
       FROM roles 
      WHERE name = 'dentist'"
);
mysqli_stmt_execute($dstmt);
mysqli_stmt_bind_result($dstmt, $dentistRoleId);
mysqli_stmt_fetch($dstmt);
mysqli_stmt_close($dstmt);

// Fetch dentists list
$dentists = [];
if ($dentistRoleId) {
    $stmt = mysqli_prepare($conn, 'SELECT id, name FROM users WHERE role_id = ? ORDER BY name');
    mysqli_stmt_bind_param($stmt, 'i', $dentistRoleId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $dId, $dName);
    while (mysqli_stmt_fetch($stmt)) {
        $dentists[] = ['id' => $dId, 'name' => $dName];
    }
    mysqli_stmt_close($stmt);
}

// If editing, load existing user data
if ($id) {
    $stmt = mysqli_prepare($conn, 'SELECT name, email, role_id, dentist_id FROM users WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email, $role_id, $dentist_id);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $dentist_id = isset($_POST['dentist_id']) && $_POST['dentist_id'] !== '' ? intval($_POST['dentist_id']) : null;
    $password = $_POST['password'] ?? '';
    $generate_password = isset($_POST['generate_password']);

    if ($generate_password) {
        $password = random_str();
        $generated_password = $password;
    }

    // Basic validation
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$role_id) $errors[] = 'Role is required.';

    // Check for unique email
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? AND id != ?');
    mysqli_stmt_bind_param($stmt, 'si', $email, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = 'Email is already in use by another user.';
    }
    mysqli_stmt_close($stmt);

    // Require password for new users
    if (!$id && empty($password)) {
        $errors[] = 'Password is required for new users.';
    }

    if (empty($errors)) {
        // Hash password if provided
        $hash = null;
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($id) {
            // Update existing user
            if (!empty($password)) {
                $stmt = mysqli_prepare($conn, 'UPDATE users SET name = ?, email = ?, role_id = ?, dentist_id = ?, password_hash = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssissi', $name, $email, $role_id, $dentist_id, $hash, $id);
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE users SET name = ?, email = ?, role_id = ?, dentist_id = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssiii', $name, $email, $role_id, $dentist_id, $id);
            }
        } else {
            // Insert new user
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (name, email, role_id, dentist_id, password_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            mysqli_stmt_bind_param($stmt, 'ssiss', $name, $email, $role_id, $dentist_id, $hash);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success = true;

            // Send email with credentials if new user
            if (!$id && !empty($password)) {
                send_mail($email, $password);
            }

            // Delete request if this was created from a request
            if ($request_id) {
                $del_stmt = mysqli_prepare($conn, 'DELETE FROM client_requests WHERE id = ?');
                mysqli_stmt_bind_param($del_stmt, 'i', $request_id);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get unread message count for badge
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
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
    <title><?= $id ? 'Edit User' : ($request_id ? 'Activate Account Request' : 'Create New User') ?> - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div id="adminDashboard" class="page active">
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-tooth"></i>
            <span>Epoka Clinic - Admin</span>
        </div>
        <div class="nav-user">
            <span>Welcome, <?= htmlspecialchars($admin_name) ?>!</span>
            <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li>
                    <a href="admin_dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="active">
                    <a href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="view_requests.php">
                        <i class="fas fa-user-clock"></i>
                        <span>Account Requests</span>
                    </a>
                </li>
                <li>
                    <a href="appointments.php">
                        <i class="fas fa-calendar"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="feedback.php">
                        <i class="fas fa-star"></i>
                        <span>Feedback</span>
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
                <div class="page-header">
                    <div class="page-title">
                        <h2>
                            <i class="fas fa-<?= $id ? 'edit' : 'user-plus' ?>"></i>
                            <?php if ($request_id): ?>
                                Activate Account Request
                            <?php elseif ($id): ?>
                                Edit User Account
                            <?php else: ?>
                                Create New User
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="page-actions">
                        <a href="<?= $request_id ? 'view_requests.php' : 'users.php' ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?= $request_id ? 'Back to Requests' : 'Back to Users' ?>
                        </a>
                    </div>
                </div>
                <br>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Success!</strong> User account <?= $id ? 'updated' : 'created' ?> successfully.
                        <?php if ($generated_password): ?>
                            <div class="generated-password">
                                <strong>Generated Password:</strong>
                                <small>(A password has been generated and emailed to the user.)</small>
                            </div>
                        <?php endif; ?>
                        <div class="alert-actions">
                            <a href="users.php" class="btn btn-primary btn-sm">View All Users</a>
                            <?php if (!$id): ?>
                                <a href="user_form.php" class="btn btn-secondary btn-sm">Create Another User</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($request_id): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Account Request:</strong> You are creating an account from a client request.
                        The name and email have been pre-filled from the request.
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="post" class="user-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-user"></i> Full Name *
                                </label>
                                <input type="text" name="name" id="name"
                                       value="<?= htmlspecialchars($name) ?>"
                                       placeholder="Enter full name" required>
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address *
                                </label>
                                <input type="email" name="email" id="email"
                                       value="<?= htmlspecialchars($email) ?>"
                                       placeholder="Enter email address" required>
                            </div>

                            <div class="form-group">
                                <label for="role_id">
                                    <i class="fas fa-user-tag"></i> User Role *
                                </label>
                                <select name="role_id" id="role_id" required>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $r['id'] == $role_id ? 'selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($r['name'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="dentist-assignment"
                                 style="display: <?= $role_id === $clientRoleId ? 'block' : 'none' ?>;">
                                <label for="dentist_id">
                                    <i class="fas fa-user-md"></i> Assign to Dentist
                                </label>
                                <select name="dentist_id" id="dentist_id">
                                    <option value="">-- No specific dentist --</option>
                                    <?php foreach ($dentists as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $dentist_id ? 'selected' : '' ?>>
                                            Dr. <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="password-section">
                            <h3><i class="fas fa-key"></i> Password Settings</h3>

                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i> Password
                                    <?php if (!$id): ?> *<?php endif; ?>
                                </label>
                                <div class="password-input-group">
                                    <input type="password" name="password" id="password"
                                           placeholder="<?= $id ? 'Leave blank to keep current password' : 'Enter password' ?>"
                                           <?php if (!$id): ?>required<?php endif; ?>>
                                    <button type="button" class="password-toggle" id="toggle-password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if ($id): ?>
                                    <small class="form-help">Leave blank to keep the current password</small>
                                <?php endif; ?>
                            </div>

                            <div class="password-options">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="generate_password" id="generate_password">
                                    <span class="checkmark"></span>
                                    Generate secure random password
                                </label>
                                <small class="form-help">
                                    If checked, a secure password will be generated and emailed to the user
                                </small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?= $id ? 'save' : 'user-plus' ?>"></i>
                                <?= $id ? 'Update User' : 'Create User' ?>
                            </button>
                            <a href="<?= $request_id ? 'view_requests.php' : 'users.php' ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="../script.js"></script>
<script>
    // Role-based field visibility
    document.getElementById('role_id').addEventListener('change', function() {
        const dentistAssignment = document.getElementById('dentist-assignment');
        const clientRoleId = <?= $clientRoleId ?>;

        if (parseInt(this.value) === clientRoleId) {
            dentistAssignment.style.display = 'block';
        } else {
            dentistAssignment.style.display = 'none';
            document.getElementById('dentist_id').value = '';
        }
    });

    document.getElementById('role_id').dispatchEvent(new Event('change'));

    // Password visibility toggle
    document.getElementById('toggle-password').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });

    // Password generation toggle
    document.getElementById('generate_password').addEventListener('change', function() {
        const passwordInput = document.getElementById('password');

        if (this.checked) {
            passwordInput.value = '';
            passwordInput.placeholder = 'Will be auto-generated and emailed to user';
            passwordInput.readOnly = true;
            passwordInput.required = false;
        } else {
            passwordInput.placeholder = '<?= $id ? "Leave blank to keep current password" : "Enter password" ?>';
            passwordInput.readOnly = false;
            <?php if (!$id): ?>
            passwordInput.required = true;
            <?php endif; ?>
        }
    });
</script>
</body>
</html>
