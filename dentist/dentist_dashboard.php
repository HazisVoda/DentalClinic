<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is a dentist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
// Fetch dentist role ID
$stmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
$roleName = 'dentist';
mysqli_stmt_bind_param($stmt, 's', $roleName);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistRoleId);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($_SESSION['role_id'] != $dentistRoleId) {
    header('Location: ../login.php');
    exit;
}

$dentistId = $_SESSION['user_id'];

// Gather dashboard stats
$stats = [];
$queries = [
    'total_clients'        => 'SELECT COUNT(*) FROM users WHERE dentist_id = ?',
    'todays_appointments'  => "SELECT COUNT(*) FROM appointments WHERE dentist_id = ? AND DATE(start_time) = CURDATE()",
    'week_appointments'    => "SELECT COUNT(*) FROM appointments WHERE dentist_id = ? AND start_time BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
    'pending_appointments' => "SELECT COUNT(*) FROM appointments WHERE dentist_id = ? AND status = 'booked'",
    'total_feedback'       => 'SELECT COUNT(*) FROM feedback WHERE dentist_id = ?'
];
foreach ($queries as $key => $sql) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    $stats[$key] = $count;
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dentist Dashboard</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1>Welcome, Dentist</h1>
        <nav>
            <a href="clients.php">Manage Clients</a> |
            <a href="appointments.php">Manage Appointments</a> |
            <a href="timetable.php">Weekly Timetable</a>  |
            <a href="messages.php">View Messages</a> |
            <a href="../logout.php">Logout</a>
        </nav>
    </header>

    <section class="stats">
        <h2>Overview</h2>
        <ul>
            <li>Total Clients: <?= htmlspecialchars($stats['total_clients']) ?></li>
            <li>Today's Appointments: <?= htmlspecialchars($stats['todays_appointments']) ?></li>
            <li>This Week's Appointments: <?= htmlspecialchars($stats['week_appointments']) ?></li>
            <li>Pending Appointments: <?= htmlspecialchars($stats['pending_appointments']) ?></li>
            <li>Total Feedback Received: <?= htmlspecialchars($stats['total_feedback']) ?></li>
        </ul>
    </section>

</body>
</html>
