<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $client_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// helper to fetch appointment lists
function fetch_appts($conn, $client_id, $operator, $limit = null) {
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
      ORDER BY a.start_time " . ($operator === '>=' ? 'ASC' : 'DESC') . "
      " . ($limit ? "LIMIT $limit" : "");
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

// 2) Load appointments
$upcoming = fetch_appts($conn, $client_id, '>=', 5);
$past     = fetch_appts($conn, $client_id, '<', 5);

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Get feedback count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM feedback WHERE client_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $feedback_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Epoka Clinic</title>
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
                    <li class="active">
                        <a href="client_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li>
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
                    <h2>Dashboard Overview</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= count($upcoming) ?></h3>
                                <p>Upcoming Appointments</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= count($past) ?></h3>
                                <p>Past Appointments</p>
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

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $feedback_count ?></h3>
                                <p>Feedback Given</p>
                            </div>
                        </div>
                    </div>

                    <div class="recent-appointments">
                        <h3>Upcoming Appointments</h3>
                        <br>
                        <div class="appointment-list">
                            <?php if (count($upcoming) === 0): ?>
                                <div class="no-appointments">
                                    <p>No upcoming appointments.</p>
                                </div>
                                <br>
                            <?php else: ?>
                                <?php foreach ($upcoming as $a): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <span class="date"><?= date('M d', strtotime($a['start_time'])) ?></span>
                                        <span class="time"><?= date('H:i', strtotime($a['start_time'])) ?></span>
                                    </div>
                                    <div class="appointment-details">
                                        <h4><?= htmlspecialchars($a['notes'] ?: 'Appointment') ?></h4>
                                        <p><?= htmlspecialchars($a['dentist_name']) ?></p>
                                    </div>
                                    <div class="appointment-status">
                                        <span class="status <?= strtolower($a['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <br>
                                <?php endforeach; ?>
                                <div class="appointment-actions">
                                    <a href="appointments.php" class="btn btn-secondary">View All Appointments</a>
                                </div>
                                <br>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($past) > 0): ?>
                    <div class="recent-appointments">
                        <h3>Recent Past Appointments</h3>
                        <br>
                        <div class="appointment-list">
                            <?php foreach (array_slice($past, 0, 3) as $a): ?>
                            <div class="appointment-item">
                                <div class="appointment-date">
                                    <span class="date"><?= date('M d', strtotime($a['start_time'])) ?></span>
                                    <span class="time"><?= date('H:i', strtotime($a['start_time'])) ?></span>
                                </div>
                                <div class="appointment-details">
                                    <h4><?= htmlspecialchars($a['notes'] ?: 'Appointment') ?></h4>
                                    <p><?= htmlspecialchars($a['dentist_name']) ?></p>
                                </div>
                                <div class="appointment-status">
                                    <span class="status completed">
                                        <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            <br>
                            <?php endforeach; ?>
                            <div class="appointment-actions">
                                <a href="feedback.php" class="btn btn-primary">Give Feedback</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
