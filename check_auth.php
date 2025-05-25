<?php
session_start();
require_once __DIR__ . '/db.php';

// If user is already logged in via session
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    redirectToDashboard($_SESSION['role_id']);
    exit;
}

// Check for remember_token cookie
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = mysqli_prepare($conn, '
        SELECT users.id, users.role_id 
        FROM tokens 
        JOIN users ON tokens.user_id = users.id 
        WHERE tokens.token = ?
    ');
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $userId, $roleId);

    if (mysqli_stmt_fetch($stmt)) {
        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $roleId;
        $_SESSION['token'] = $token;

        mysqli_stmt_close($stmt);
        redirectToDashboard($roleId);
        exit;
    }

    mysqli_stmt_close($stmt);
}

// Helper function to redirect based on role
function redirectToDashboard($roleId) {
    if ($roleId == 1) {
        header('Location: admin/admin_dashboard.php');
    } elseif ($roleId == 2) {
        header('Location: dentist/dentist_dashboard.php');
    } elseif ($roleId == 3) {
        header('Location: client/client_dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit;
}