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

// Handle appointment status updates
if (isset($_GET['action'], $_GET['appointment_id']) && is_numeric($_GET['appointment_id'])) {
    $appointmentId = intval($_GET['appointment_id']);
    $action = $_GET['action'];
    
    // Verify the appointment belongs to this dentist
    $verifyStmt = mysqli_prepare($conn, "SELECT id FROM appointments WHERE id = ? AND dentist_id = ?");
    mysqli_stmt_bind_param($verifyStmt, 'ii', $appointmentId, $dentistId);
    mysqli_stmt_execute($verifyStmt);
    mysqli_stmt_bind_result($verifyStmt, $verifyId);
    
    if (mysqli_stmt_fetch($verifyStmt)) {
        mysqli_stmt_close($verifyStmt);
        
        // Update appointment status based on action
        if ($action === 'finish') {
            $updateStmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'finished' WHERE id = ? AND dentist_id = ?");
            mysqli_stmt_bind_param($updateStmt, 'ii', $appointmentId, $dentistId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            $statusMessage = "Appointment marked as finished successfully!";
        } elseif ($action === 'cancel') {
            $updateStmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND dentist_id = ?");
            mysqli_stmt_bind_param($updateStmt, 'ii', $appointmentId, $dentistId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            $statusMessage = "Appointment cancelled successfully!";
        }
        
        // Redirect to prevent resubmission
        header('Location: dentist_dashboard.php?updated=1');
        exit;
    } else {
        mysqli_stmt_close($verifyStmt);
        $errorMessage = "Appointment not found or access denied.";
    }
}

// Check for update confirmation
$showUpdateMessage = isset($_GET['updated']) && $_GET['updated'] == '1';

// Get dentist name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

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

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Get today's schedule
$todaySchedule = [];
$stmt = mysqli_prepare($conn, "
    SELECT a.id, a.start_time, a.end_time, a.status, u.name as client_name, u.email as client_email
    FROM appointments a
    JOIN users u ON a.client_id = u.id
    WHERE a.dentist_id = ? AND DATE(a.start_time) = CURDATE() AND a.status = 'booked'
    ORDER BY a.start_time ASC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $todaySchedule[] = $row;
}
mysqli_stmt_close($stmt);

// Get recent feedback
$recentFeedback = [];
$stmt = mysqli_prepare($conn, "
    SELECT f.rating, f.comments, f.created_at, u.name as client_name
    FROM feedback f
    JOIN users u ON f.client_id = u.id
    WHERE f.dentist_id = ?
    ORDER BY f.created_at DESC
    LIMIT 3
");
mysqli_stmt_bind_param($stmt, 'i', $dentistId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $recentFeedback[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Dashboard - Dental Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="dentistDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic</span>
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
                    <li class="active">
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
                    <li>
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
                    <h2>Dashboard Overview</h2>
                    
                    <?php if (isset($statusMessage)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($statusMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($showUpdateMessage): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Appointment status updated successfully!
                        </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['total_clients'] ?></h3>
                                <p>Total Patients</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['todays_appointments'] ?></h3>
                                <p>Appointments Today</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['week_appointments'] ?></h3>
                                <p>This Week</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['pending_appointments'] ?></h3>
                                <p>Pending</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['total_feedback'] ?></h3>
                                <p>Total Feedback</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $unread_count ?></h3>
                                <p>Unread Messages</p>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
    <div class="today-schedule">
        <div class="section-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
            <span class="schedule-date"><?= date('l, F j, Y') ?></span>
        </div>
        <br>
        <div class="schedule-container">
            <?php if (empty($todaySchedule)): ?>
                <div class="empty-state-card">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h4>No appointments scheduled for today</h4>
                    <p>Your schedule is clear. Perfect time to catch up on other tasks!</p>
                    <div class="empty-actions">
                        <a href="appointment_form.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Schedule Appointment
                        </a>
                        <a href="timetable.php" class="btn btn-secondary">
                            <i class="fas fa-calendar-week"></i> View Weekly Schedule
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($todaySchedule as $appt): ?>
                <div class="appointment-card">
                    <div class="appointment-time">
                        <div class="time-badge">
                            <?= date('g:i A', strtotime($appt['start_time'])) ?>
                        </div>
                        <div class="duration-info">
                            <?php 
                            $duration = (strtotime($appt['end_time']) - strtotime($appt['start_time'])) / 60;
                            echo $duration . ' min';
                            ?>
                        </div>
                    </div>
                    <div class="appointment-details">
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="patient-details">
                                <h4><?= htmlspecialchars($appt['client_name']) ?></h4>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($appt['client_email']) ?></p>
                            </div>
                        </div>
                        <div class="appointment-actions">
                            <a href="?action=finish&appointment_id=<?= $appt['id'] ?>" 
                               class="action-btn success" 
                               title="Mark as Finished"
                               onclick="return confirm('Mark this appointment as finished?')">
                                <i class="fas fa-check"></i>
                            </a>
                            <a href="appointment_form.php?id=<?= $appt['id'] ?>" 
                               class="action-btn edit" 
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?action=cancel&appointment_id=<?= $appt['id'] ?>" 
                               class="action-btn cancel" 
                               title="Cancel"
                               onclick="return confirm('Cancel this appointment?')">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <br>
                <?php endforeach; ?>
                
                <div class="schedule-summary-card">
                    <div class="summary-content">
                        <div class="summary-stat">
                            <i class="fas fa-users"></i>
                            <span><?= count($todaySchedule) ?> patients scheduled</span>
                        </div>
                        <div class="summary-stat">
                            <i class="fas fa-clock"></i>
                            <span>
                                <?php
                                $totalMinutes = 0;
                                foreach ($todaySchedule as $appt) {
                                    $totalMinutes += (strtotime($appt['end_time']) - strtotime($appt['start_time'])) / 60;
                                }
                                $hours = floor($totalMinutes / 60);
                                $minutes = $totalMinutes % 60;
                                echo $hours . 'h ' . $minutes . 'm total';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="summary-actions">
                        <a href="appointments.php" class="btn btn-outline">View All</a>
                        <a href="timetable.php" class="btn btn-primary">Weekly View</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <br>

    <div class="recent-feedback">
        <div class="section-header">
            <h3><i class="fas fa-star"></i> Recent Feedback</h3>
            <br>
        </div>
        <div class="feedback-container">
            <?php if (empty($recentFeedback)): ?>
                <div class="empty-state-card">
                    <div class="empty-icon">
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <h4>No feedback received yet</h4>
                    <p>Patient feedback will appear here once you start receiving reviews.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentFeedback as $feedback): ?>
                <div class="feedback-card">
                    <div class="feedback-header">
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="patient-name">
                                <h4><?= htmlspecialchars($feedback['client_name']) ?></h4>
                                <span class="feedback-date"><?= date('M j, Y', strtotime($feedback['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="rating-display">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= $feedback['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-number"><?= $feedback['rating'] ?>/5</span>
                        </div>
                    </div>
                    <?php if ($feedback['comments']): ?>
                        <div class="feedback-content">
                            <p>"<?= htmlspecialchars($feedback['comments']) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
                <br>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
<script>
// Remove the markAsFinished and cancelAppointment functions

// Keep only the animation code
document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on load
    const cards = document.querySelectorAll('.appointment-card, .feedback-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
});
</script>
</body>
</html>
