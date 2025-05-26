<?php
require_once __DIR__ . '/db.php';
require_once __DIR__.'/check_auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = htmlspecialchars(trim($_POST['email'] ?? ''));
    $password = htmlspecialchars($_POST['password'] ?? '');

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

        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            if (password_verify($password, $hash)) {
                // Successful login
                $_SESSION['user_id'] = $userId;
                $_SESSION['role_id'] = $roleId;
                $token = bin2hex(random_bytes(32));
                $_SESSION['token'] = $token;

                if (!empty($_POST['remember_me'])) {


                    // Delete old tokens
                    $stmt_delete = mysqli_prepare($conn, 'DELETE FROM tokens WHERE user_id = ?');
                    mysqli_stmt_bind_param($stmt_delete, 'i', $userId);
                    mysqli_stmt_execute($stmt_delete);
                    mysqli_stmt_close($stmt_delete);

                    // Insert new token
                    $stmt_token = mysqli_prepare($conn, 'INSERT INTO tokens (user_id, token) VALUES (?, ?)');
                    mysqli_stmt_bind_param($stmt_token, 'is', $userId, $token);
                    mysqli_stmt_execute($stmt_token);
                    mysqli_stmt_close($stmt_token);

                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                }

                if ($roleId == 1) {
                    header('Location: admin/admin_dashboard.php');
                } elseif ($roleId == 2) {
                    header('Location: dentist/dentist_dashboard.php');
                } elseif ($roleId == 3) {
                    header('Location: client/client_dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid password.';
            }
        } else {
            $errors[] = 'No account found with that email address.';
        }
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
                    <h1>Epoka Clinic</h1>
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

                    <div class="form-group remember">
                        <input type="checkbox" name="remember_me">
                        <label for="checkbox"> Remember Me</label>
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

