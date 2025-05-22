<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is a dentist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
// Verify dentist role
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

// Handle deletion of appointment
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM appointments WHERE id = ? AND dentist_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $deleteId, $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: appointments.php');
    exit;
}

// Handle status update (finish or cancel)
if (isset($_GET['status'], $_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = ($_GET['status'] === 'finished' || $_GET['status'] === 'cancelled') ? $_GET['status'] : 'booked';
    $stmt = mysqli_prepare($conn, 'UPDATE appointments SET status = ? WHERE id = ? AND dentist_id = ?');
    mysqli_stmt_bind_param($stmt, 'sii', $status, $id, $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: appointments.php');
    exit;
}

// Fetch appointments for this dentist
$stmt = mysqli_prepare(
    $conn,
    "SELECT a.id, u.name AS client_name, a.start_time, a.end_time, a.status
     FROM appointments a
     JOIN users u ON a.client_id = u.id
     WHERE a.dentist_id = ?
     ORDER BY a.start_time DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $clientName, $start, $end, $status);
$appointments = [];
while (mysqli_stmt_fetch($stmt)) {
    $appointments[] = [
        'id'          => $id,
        'client_name' => $clientName,
        'start'       => $start,
        'end'         => $end,
        'status'      => $status,
    ];
}
mysqli_stmt_close($stmt);
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
        <h1>My Appointments</h1>
        <nav>
            <a href="dentist_dashboard.php">← Back to Dashboard</a>
        </nav>
    </header>

    <section>
        <a href="appointment_form.php" class="button">Add New Appointment</a>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="6">No appointments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['id']) ?></td>
                        <td><?= htmlspecialchars($a['client_name']) ?></td>
                        <td><?= htmlspecialchars($a['start']) ?></td>
                        <td><?= htmlspecialchars($a['end']) ?></td>
                        <td><?= htmlspecialchars($a['status']) ?></td>
                        <td>
                            <a href="appointment_form.php?id=<?= $a['id'] ?>">Edit</a> |
                            <?php if ($a['status'] !== 'finished'): ?>
                                <a href="appointments.php?status=finished&id=<?= $a['id'] ?>">Mark Finished</a> |
                            <?php endif; ?>
                            <?php if ($a['status'] !== 'cancelled'): ?>
                                <a href="appointments.php?status=cancelled&id=<?= $a['id'] ?>">Cancel</a> |
                            <?php endif; ?>
                            <a href="appointments.php?delete=<?= $a['id'] ?>" onclick="return confirm('Delete this appointment?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</body>
</html>
