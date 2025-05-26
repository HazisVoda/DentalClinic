<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

// Get client name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $client_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

/**
 * Fetch appointments for this client.
 * @param string $operator  Comparison operator for start_time (>= for upcoming, < for past)
 * @param string $order     ASC or DESC
 * @return array
 */
function fetch_client_appts($conn, $client_id, $operator, $order = 'ASC') {
    $sql = "
      SELECT
        a.id,
        a.start_time,
        a.end_time,
        a.status,
        a.notes,
        u.name AS dentist_name
      FROM appointments a
      JOIN users u ON a.dentist_id = u.id
      WHERE a.client_id = ?
        AND a.start_time {$operator} NOW()
      ORDER BY a.start_time {$order}
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $client_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// 2) Load upcoming and past
$upcoming = fetch_client_appts($conn, $client_id, '>=', 'ASC');
$past     = fetch_client_appts($conn, $client_id, '<',  'DESC');

// Get unread message count for badge
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
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
    <title>My Appointments - Dental Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="clientDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic</span>
            </div>
            <div class="nav-user">
                <span>Welcome, <?= htmlspecialchars($client_name) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li>
                        <a href="client_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="appointments.php">
                            <i class="fas fa-calendar"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li>
                        <a href="feedback.php">
                            <i class="fas fa-star"></i>
                            <span>Feedback</span>
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
                    <h2>My Appointments</h2>
                    
                    <div class="appointments-tabs">
                        <button class="tab-btn active" onclick="showAppointmentTab('upcoming')">
                            Upcoming (<?= count($upcoming) ?>)
                        </button>
                        <button class="tab-btn" onclick="showAppointmentTab('past')">
                            Past (<?= count($past) ?>)
                        </button>
                    </div>

                    <div id="upcomingTab" class="tab-content active">
                        <?php if (empty($upcoming)): ?>
                            <div class="no-appointments">
                                <div class="no-appointments-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <h3>No upcoming appointments</h3>
                                <p>You don't have any scheduled appointments. Contact your dentist to book one.</p>
                                <button class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Book Appointment
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="appointment-list">
                                <?php foreach ($upcoming as $a): ?>
                                <div class="appointment-card">
                                    <div class="appointment-header">
                                        <h4><?= htmlspecialchars($a['notes'] ?: 'Dental Appointment') ?></h4>
                                        <span class="status <?= strtolower($a['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="appointment-body">
                                        <p><i class="fas fa-user-md"></i> <?= htmlspecialchars($a['dentist_name']) ?></p>
                                        <p><i class="fas fa-calendar"></i> <?= date('l, F j, Y', strtotime($a['start_time'])) ?></p>
                                        <p><i class="fas fa-clock"></i> 
                                            <?= date('g:i A', strtotime($a['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($a['end_time'])) ?>
                                        </p>
                                    </div>
                                    <div class="appointment-actions">
                                        <button class="btn btn-secondary" onclick="rescheduleAppointment(<?= $a['id'] ?>)">
                                            <i class="fas fa-calendar-alt"></i> Reschedule
                                        </button>
                                        <button class="btn btn-danger" onclick="cancelAppointment(<?= $a['id'] ?>)">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="pastTab" class="tab-content">
                        <?php if (empty($past)): ?>
                            <div class="no-appointments">
                                <div class="no-appointments-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h3>No past appointments</h3>
                                <p>You haven't had any appointments yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="appointment-list">
                                <?php foreach ($past as $a): ?>
                                <div class="appointment-card">
                                    <div class="appointment-header">
                                        <h4><?= htmlspecialchars($a['notes'] ?: 'Dental Appointment') ?></h4>
                                        <span class="status completed">
                                            <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="appointment-body">
                                        <p><i class="fas fa-user-md"></i> <?= htmlspecialchars($a['dentist_name']) ?></p>
                                        <p><i class="fas fa-calendar"></i> <?= date('l, F j, Y', strtotime($a['start_time'])) ?></p>
                                        <p><i class="fas fa-clock"></i> 
                                            <?= date('g:i A', strtotime($a['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($a['end_time'])) ?>
                                        </p>
                                    </div>
                                    <div class="appointment-actions">
                                        <a href="feedback.php" class="btn btn-primary">
                                            <i class="fas fa-star"></i> Give Feedback
                                        </a>
                                        <button class="btn btn-secondary" onclick="viewAppointmentDetails(<?= $a['id'] ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        function rescheduleAppointment(appointmentId) {
            if (confirm('Would you like to reschedule this appointment? Please contact your dentist to arrange a new time.')) {
                // In a real application, this would open a reschedule form or redirect to a booking system
                alert('Please contact your dentist to reschedule this appointment.');
            }
        }

        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                // In a real application, this would make an AJAX call to cancel the appointment
                alert('Appointment cancellation request sent. Your dentist will be notified.');
                // You could add AJAX here to actually cancel the appointment
            }
        }

        function viewAppointmentDetails(appointmentId) {
            alert('Appointment details would be displayed here.');
            // In a real application, this would show detailed appointment information
        }
    </script>
</body>
</html>
