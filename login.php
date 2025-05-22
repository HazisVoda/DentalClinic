<?php
session_start();
// include your existing MySQLi connection
require_once __DIR__ . '/db.php'; // defines $conn

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, role_id, password_hash FROM users WHERE email = ?'
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId, $roleId, $hash);
        if (mysqli_stmt_fetch($stmt) && password_verify($password, $hash)) {
            // Successful login
            $_SESSION['user_id'] = $userId;
            $_SESSION['role_id'] = $roleId;
            
            if ($roleId == 1) {
                header('Location: admin/admin_dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;

        } else {
            $errors[] = 'Invalid email or password.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>
    <h2>Login to Your Account</h2>
    <?php if ($errors): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="">
        <label>Email:<br>
            <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </label><br><br>
        <label>Password:<br>
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a>.</p>
</body>
</html>
