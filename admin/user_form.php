<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Initialize variables
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$name = '';
$email = '';
$role_id = 0;
$dentist_id = null;
$password = '';
$errors = [];

// Fetch all roles
$roles = [];
$roleRes = mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');
while ($r = mysqli_fetch_assoc($roleRes)) {
    $roles[] = $r;
}

// Identify client role ID for toggling dentist field
$clientRoleId = null;
foreach ($roles as $r) {
    if ($r['name'] === 'client') {
        $clientRoleId = $r['id'];
        break;
    }
}

// Get dentist role ID
$dentistRoleName = 'dentist';
$stmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
mysqli_stmt_bind_param($stmt, 's', $dentistRoleName);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistRoleId);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Fetch dentists list
$dentists = [];
$stmt = mysqli_prepare($conn, 'SELECT id, name FROM users WHERE role_id = ? ORDER BY name');
mysqli_stmt_bind_param($stmt, 'i', $dentistRoleId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dId, $dName);
while (mysqli_stmt_fetch($stmt)) {
    $dentists[] = ['id' => $dId, 'name' => $dName];
}
mysqli_stmt_close($stmt);

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

    // Basic validation
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role_id) {
        $errors[] = 'Name, valid email, and role are required.';
    }

    // Check for unique email
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? AND id != ?');
    mysqli_stmt_bind_param($stmt, 'si', $email, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = 'Email is already in use.';
    }
    mysqli_stmt_close($stmt);

    // Require password for new users
    if (!$id && empty($password)) {
        $errors[] = 'Password is required for new users.';
    }

    if (empty($errors)) {
        // Hash new password if provided
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
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: users.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Edit User' : 'Add New User' ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1><?= $id ? 'Edit User' : 'Add New User' ?></h1>
        <nav>
            <a href="users.php">← Back to Manage Users</a>
        </nav>
    </header>

    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">
        <label>Name:<br>
            <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
        </label><br><br>

        <label>Email:<br>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </label><br><br>

        <label>Role:<br>
            <select name="role_id" id="role-select" required>
                <option value="">-- Select Role --</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $r['id'] == $role_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <div id="dentist-row" style="display: <?= $role_id === $clientRoleId ? 'block' : 'none' ?>;">
            <label>Assign to Dentist:<br>
                <select name="dentist_id">
                    <option value="">-- None --</option>
                    <?php foreach ($dentists as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $dentist_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label><br><br>
        </div>

        <label>Password:<br>
            <input type="password" name="password" <?php if (!$id) echo 'required'; ?>>
            <?php if ($id): ?><small>Leave blank to keep current password</small><?php endif; ?>
        </label><br><br>

        <button type="submit"><?= $id ? 'Update User' : 'Create User' ?></button>
    </form>

    <script>
    document.getElementById('role-select').addEventListener('change', function() {
        var dentistRow = document.getElementById('dentist-row');
        if (parseInt(this.value) === <?= $clientRoleId ?>) {
            dentistRow.style.display = 'block';
        } else {
            dentistRow.style.display = 'none';
        }
    });
    </script>
</body>
</html>
