<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

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
        } else {
            // Insert new client under this dentist
            $stmt = mysqli_prepare($conn,
                'INSERT INTO users (role_id, dentist_id, name, email, password_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            mysqli_stmt_bind_param($stmt, 'iisss', $clientRoleId, $dentistId, $name, $email, $hash);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: clients.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Edit Client' : 'Add New Client' ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1><?= $id ? 'Edit Client' : 'Add New Client' ?></h1>
        <nav>
            <a href="clients.php">← Back to My Clients</a>
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

        <label>Password:<br>
            <input type="password" name="password" <?= $id ? '' : 'required' ?>>
            <?php if ($id): ?><small>Leave blank to keep current password</small><?php endif; ?>
        </label><br><br>

        <button type="submit"><?= $id ? 'Update Client' : 'Create Client' ?></button>
    </form>
</body>
</html>
