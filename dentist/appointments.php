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

// Get dentist name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

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

// Get filter
$filter = $_GET['filter'] ?? 'all';
$whereClause = '';
$params = [$dentistId];
$paramTypes = 'i';

switch ($filter) {
    case 'today':
        $whereClause = ' AND DATE(a.start_time) = CURDATE()';
        break;
    case 'week':
        $whereClause = ' AND a.start_time BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        break;
    case 'pending':
        $whereClause = " AND a.status = 'booked'";
        break;
    case 'completed':
        $whereClause = " AND a.status = 'finished'";
        break;
}

// Fetch appointments for this dentist
$stmt = mysqli_prepare(
    $conn,
    "SELECT a.id, u.name AS client_name, a.start_time, a.end_time, a.status, a.notes
     FROM appointments a
     JOIN users u ON a.client_id = u.id
     WHERE a.dentist_id = ? $whereClause
     ORDER BY a.start_time DESC"
);
mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $clientName, $start, $end, $status, $notes);
$appointments = [];
while (mysqli_stmt_fetch($stmt)) {
    $appointments[] = [
        'id'          => $id,
        'client_name' => $clientName,
        'start'       => $start,
        'end'         => $end,
        'status'      => $status,
        'notes'       => $notes,
    ];
}
mysqli_stmt_close($stmt);

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="dentistDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic</span>
            </div>
            <div class="nav-user">
                <span>Welcome, Dr. <?= htmlspecialchars($dentistName) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li>
                        <a href="dentist_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="timetable.php">
                            <i class="fas fa-calendar-week"></i>
                            <span>Timetable</span>
                        </a>
                    </li>
                    <li>
                        <a href="clients.php">
                            <i class="fas fa-users"></i>
                            <span>Patients</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="appointments.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <div class="content-section active">
                    <h2>Manage Appointments</h2>
                    
                    <div class="appointments-header">
                        <a href="appointment_form.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Appointment
                        </a>
                        <div class="filter-options">
                            <select onchange="filterAppointments(this.value)">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Appointments</option>
                                <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>This Week</option>
                                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($appointments)): ?>
                        <div class="no-appointments">
                            <div class="no-appointments-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h3>No appointments found</h3>
                            <p>You don't have any appointments matching the current filter.</p>
                            <a href="appointment_form.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Schedule New Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="appointments-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $a): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <i class="fas fa-user"></i>
                                                <?= htmlspecialchars($a['client_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($a['start'])) ?></td>
                                        <td>
                                            <?= date('g:i A', strtotime($a['start'])) ?> - 
                                            <?= date('g:i A', strtotime($a['end'])) ?>
                                        </td>
                                        <td>
                                            <span class="status <?= strtolower($a['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="notes-cell">
                                                <?= htmlspecialchars($a['notes'] ?: 'No notes') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="appointment_form.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($a['status'] !== 'finished'): ?>
                                                    <a href="appointments.php?status=finished&id=<?= $a['id'] ?>" 
                                                       class="btn btn-sm btn-success" title="Mark Finished"
                                                       onclick="return confirm('Mark this appointment as finished?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($a['status'] !== 'cancelled'): ?>
                                                    <a href="appointments.php?status=cancelled&id=<?= $a['id'] ?>" 
                                                       class="btn btn-sm btn-warning" title="Cancel"
                                                       onclick="return confirm('Cancel this appointment?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="appointments.php?delete=<?= $a['id'] ?>" 
                                                   class="btn btn-sm btn-danger" title="Delete"
                                                   onclick="return confirm('Delete this appointment? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        function filterAppointments(filter) {
            window.location.href = 'appointments.php?filter=' + filter;
        }
    </script>
</body>
</html>
