<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Handle deletion of appointment
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM appointments WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $deleteId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: appointments.php');
    exit;
}

// Handle status update (cancel or finish)
if (isset($_GET['status'], $_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = in_array($_GET['status'], ['booked','finished','cancelled']) ? $_GET['status'] : 'booked';
    $stmt = mysqli_prepare($conn, 'UPDATE appointments SET status = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: appointments.php');
    exit;
}

// Fetch all appointments
$sql = "
SELECT a.id, c.name AS client_name, d.name AS dentist_name,
       a.start_time, a.end_time, a.status
FROM appointments a
JOIN users c ON a.client_id = c.id
JOIN users d ON a.dentist_id = d.id
ORDER BY a.start_time DESC
";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1>Manage Appointments</h1>
        <nav>
            <a href="admin_dashboard.php">← Back to Dashboard</a>
        </nav>
    </header>

    <section>
        <a href="appointment_form.php" class="button">Add New Appointment</a>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Dentist</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['dentist_name']) ?></td>
                    <td><?= htmlspecialchars($row['start_time']) ?></td>
                    <td><?= htmlspecialchars($row['end_time']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td>
                        <a href="appointment_form.php?id=<?= $row['id'] ?>">Edit</a> |
                        <?php if ($row['status'] !== 'finished'): ?>
                            <a href="appointments.php?status=finished&id=<?= $row['id'] ?>">Mark Finished</a> |
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'cancelled'): ?>
                            <a href="appointments.php?status=cancelled&id=<?= $row['id'] ?>">Cancel</a> |
                        <?php endif; ?>
                        <a href="appointments.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this appointment?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>
</body>
</html>
