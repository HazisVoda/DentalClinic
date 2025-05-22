<?php
session_start();
// include your existing MySQLi connection
require_once __DIR__ . '/db.php'; // defines $conn

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $errors[] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (empty($errors)) {
        // Check if email already exists
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Email is already registered.';
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Get role_id for 'client'
            $roleName = 'client';
            $stmtRole = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
            mysqli_stmt_bind_param($stmtRole, 's', $roleName);
            mysqli_stmt_execute($stmtRole);
            mysqli_stmt_bind_result($stmtRole, $roleId);
            mysqli_stmt_fetch($stmtRole);
            mysqli_stmt_close($stmtRole);

            // Insert new user
            $insert = mysqli_prepare(
                $conn,
                'INSERT INTO users (role_id, name, email, password_hash) VALUES (?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param($insert, 'isss', $roleId, $name, $email, $password_hash);
            mysqli_stmt_execute($insert);

            header('Location: login.php');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>
    <h2>Create Account</h2>
    <?php if ($errors): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="">
        <label>Name:<br>
            <input type="text" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required>
        </label><br><br>
        <label>Email:<br>
            <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </label><br><br>
        <label>Password:<br>
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</body>
</html>