<?php
session_start();
require_once __DIR__ . '/db.php';
if(isset($_SESSION["token"]) && isset($_GET["token"])){
    $token = $_GET["token"];
    if($_SESSION['token'] == $token){
        $stmt = mysqli_prepare($conn, 'DELETE FROM tokens WHERE token = ?');
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        setcookie('remember_token', '', time() - 3600, '/', '', false, true);

        $_SESSION = [];
        session_destroy();
    }
}
header("location: index.php");

