<?php
session_start();
require_once __DIR__ . '/db.php';

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
            } else if ($roleId == 2) {
                header('Location: dentist/dentist_dashboard.php');
            } else if ($roleId == 3) {
                header('Location: client/client_dashboard.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="loginPage" class="page active">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <i class="fas fa-tooth"></i>
                    <h1>Dental Clinic</h1>
                    <p>Management System</p>
                </div>

                <?php if ($errors): ?>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <form id="loginForm" class="login-form" method="post" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

