<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Fetch some counts for dashboard overview
// Total users, dentists, clients, appointments, feedback items
$stats = [];
$queries = [
    'total_users'       => 'SELECT COUNT(*) FROM users',
    'total_dentists'    => "SELECT COUNT(*) FROM users WHERE role_id = 'dentist'",
    'total_clients'     => "SELECT COUNT(*) FROM users WHERE role_id = 'client'",
    'total_appointments'=> 'SELECT COUNT(*) FROM appointments',
    'pending_appointments'=> "SELECT COUNT(*) FROM appointments WHERE status = 'booked'",
    'total_feedback'    => 'SELECT COUNT(*) FROM feedback'
];

foreach ($queries as $key => $sql) {
    $result = mysqli_query($conn, $sql);
    $stats[$key] = mysqli_fetch_row($result)[0] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../home.css">
</head>
<body>
    <header>
        <h1>Admin Dashboard</h1>

        <nav>
            <a href="users.php">Manage Users</a> |
            <a href="appointments.php">Manage Appointments</a> |
            <a href="feedback.php">View Feedback</a> |
            <a href="messages.php">View Messages</a> |
            <a href="view_requests.php">View Requests</a> |
            <a href = "../logout.php?token=<?php echo $_SESSION['token']; ?>">Logout</a>
        </nav>
    </header>

    <section class="stats">
        <h2>Overview</h2>
        <ul>
            <li>Total Users: <?php echo $stats['total_users']; ?></li>
            <li>Total Dentists: <?php echo $stats['total_dentists']; ?></li>
            <li>Total Clients: <?php echo $stats['total_clients']; ?></li>
            <li>Total Appointments: <?php echo $stats['total_appointments']; ?></li>
            <li>Pending Appointments: <?php echo $stats['pending_appointments']; ?></li>
            <li>Total Feedback Entries: <?php echo $stats['total_feedback']; ?></li>
        </ul>
    </section>

    <section class="quick-links">
        <h2>Quick Links</h2>
        <ul>
            <li><a href="users.php">Create/Edit/Delete Users</a></li>
            <li><a href="appointments.php">Create/Edit/Cancel Appointments</a></li>
            <li><a href="feedback.php">Browse Feedback</a></li>
        </ul>
    </section>

</body>
</html>
