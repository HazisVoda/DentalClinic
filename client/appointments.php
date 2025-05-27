<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

// Handle appointment actions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $action = $_POST['action'];
        
        // Verify appointment belongs to this client
        $verify_stmt = mysqli_prepare($conn, "SELECT id FROM appointments WHERE id = ? AND client_id = ?");
        mysqli_stmt_bind_param($verify_stmt, 'ii', $appointment_id, $client_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        
        if (mysqli_num_rows($verify_result) > 0) {
            if ($action === 'cancel') {
                $update_stmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, 'i', $appointment_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_message = "Appointment has been cancelled successfully.";
                } else {
                    $error_message = "Failed to cancel appointment. Please try again.";
                }
                mysqli_stmt_close($update_stmt);
            } elseif ($action === 'reschedule_request') {
                // Create a message to dentist requesting reschedule
                $dentist_stmt = mysqli_prepare($conn, "SELECT dentist_id FROM appointments WHERE id = ?");
                mysqli_stmt_bind_param($dentist_stmt, 'i', $appointment_id);
                mysqli_stmt_execute($dentist_stmt);
                mysqli_stmt_bind_result($dentist_stmt, $dentist_id);
                mysqli_stmt_fetch($dentist_stmt);
                mysqli_stmt_close($dentist_stmt);
                
                $subject = "Reschedule Request for Appointment #" . $appointment_id;
                $body = "Hello, I would like to reschedule my appointment #" . $appointment_id . ". Please contact me to arrange a new time. Thank you.";
                
                $message_stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, subject, body, sent_at) VALUES (?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($message_stmt, 'iiss', $client_id, $dentist_id, $subject, $body);
                if (mysqli_stmt_execute($message_stmt)) {
                    $success_message = "Reschedule request has been sent to your dentist.";
                } else {
                    $error_message = "Failed to send reschedule request. Please try again.";
                }
                mysqli_stmt_close($message_stmt);
            }
        } else {
            $error_message = "Invalid appointment.";
        }
        mysqli_stmt_close($verify_stmt);
    }
}

// Get current tab from URL parameter
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'upcoming';
if (!in_array($current_tab, ['upcoming', 'past'])) {
    $current_tab = 'upcoming';
}

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
    <title>My Appointments - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="clientDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic</span>
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
                    
                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- PHP-based Tab Navigation -->
                    <div class="appointments-tabs">
                        <a href="?tab=upcoming" style="text-decoration: none;" class="tab-btn <?= $current_tab === 'upcoming' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-plus"></i>
                            Upcoming (<?= count($upcoming) ?>)
                        </a>
                        <a href="?tab=past" style="text-decoration: none;" class="tab-btn <?= $current_tab === 'past' ? 'active' : '' ?>">
                            <i class="fas fa-history"></i>
                            Past (<?= count($past) ?>)
                        </a>
                    </div>

                    <!-- Upcoming Appointments Tab -->
                    <?php if ($current_tab === 'upcoming'): ?>
                        <div class="tab-content active">
                            <?php if (empty($upcoming)): ?>
                                <div class="no-appointments">
                                    <h3>No upcoming appointments</h3>
                                    <p>You don't have any scheduled appointments. Contact your dentist to book one.</p>
                                    <br>
                                    <a href="messages.php" class="btn btn-primary">
                                        <i class="fas fa-envelope"></i> Contact Dentist
                                    </a>
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
                                            <p><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($a['dentist_name']) ?></p>
                                            <p><i class="fas fa-calendar"></i> <?= date('l, F j, Y', strtotime($a['start_time'])) ?></p>
                                            <p><i class="fas fa-clock"></i> 
                                                <?= date('g:i A', strtotime($a['start_time'])) ?> - 
                                                <?= date('g:i A', strtotime($a['end_time'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Past Appointments Tab -->
                    <?php if ($current_tab === 'past'): ?>
                        <div class="tab-content active">
                            <?php if (empty($past)): ?>
                                <div class="no-appointments">
                                    <div class="no-appointments-icon">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <h3>No past appointments</h3>
                                    <p>You haven't had any appointments yet.</p>
                                </div>
                                <br>
                            <?php else: ?>
                                <div class="appointment-list">
                                    <?php foreach ($past as $a): ?>
                                    <div class="appointment-card">
                                        <div class="appointment-header">
                                            <h4><?= htmlspecialchars($a['notes'] ?: 'Dental Appointment') ?></h4>
                                            <span class="status <?= strtolower($a['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                            </span>
                                        </div>
                                        <div class="appointment-body">
                                            <p><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($a['dentist_name']) ?></p>
                                            <p><i class="fas fa-calendar"></i> <?= date('l, F j, Y', strtotime($a['start_time'])) ?></p>
                                            <p><i class="fas fa-clock"></i> 
                                                <?= date('g:i A', strtotime($a['start_time'])) ?> - 
                                                <?= date('g:i A', strtotime($a['end_time'])) ?>
                                            </p>
                                        </div>
                                        <div class="appointment-actions">
                                            <?php if ($a['status'] === 'finished'): ?>
                                                <a href="feedback.php?appointment_id=<?= $a['id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-star"></i> Give Feedback
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
