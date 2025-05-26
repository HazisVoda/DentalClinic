<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is a dentist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
// Verify role is dentist
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

// Handle removal (unassign client)
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $clientId = intval($_GET['remove']);
    // Ensure client belongs to this dentist
    $stmt = mysqli_prepare($conn, 'UPDATE users SET dentist_id = NULL WHERE id = ? AND dentist_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $clientId, $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: clients.php');
    exit;
}

// Get search term
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get active tab for history modal
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'appointments';

// Handle patient history request
$patientHistory = null;
if (isset($_GET['history']) && is_numeric($_GET['history'])) {
    $patientId = intval($_GET['history']);
    
    // Get client role id
    $stmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
    $clientRoleName = 'client';
    mysqli_stmt_bind_param($stmt, 's', $clientRoleName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $clientRoleId);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    // Verify patient belongs to this dentist
    $verifyStmt = mysqli_prepare($conn, 'SELECT name, email, created_at FROM users WHERE id = ? AND dentist_id = ? AND role_id = ?');
    mysqli_stmt_bind_param($verifyStmt, 'iii', $patientId, $dentistId, $clientRoleId);
    mysqli_stmt_execute($verifyStmt);
    mysqli_stmt_bind_result($verifyStmt, $patientName, $patientEmail, $patientCreated);
    
    if (mysqli_stmt_fetch($verifyStmt)) {
        mysqli_stmt_close($verifyStmt);
        
        // Get patient's appointment history
        $historyStmt = mysqli_prepare($conn, "
            SELECT a.start_time, a.end_time, a.status, a.notes, a.created_at
            FROM appointments a
            WHERE a.client_id = ? AND a.dentist_id = ?
            ORDER BY a.start_time DESC
        ");
        mysqli_stmt_bind_param($historyStmt, 'ii', $patientId, $dentistId);
        mysqli_stmt_execute($historyStmt);
        $historyResult = mysqli_stmt_get_result($historyStmt);
        
        $appointments = [];
        while ($row = mysqli_fetch_assoc($historyResult)) {
            $appointments[] = $row;
        }
        mysqli_stmt_close($historyStmt);
        
        // Get patient's feedback
        $feedbackStmt = mysqli_prepare($conn, "
            SELECT rating, comments, created_at
            FROM feedback
            WHERE client_id = ? AND dentist_id = ?
            ORDER BY created_at DESC
        ");
        mysqli_stmt_bind_param($feedbackStmt, 'ii', $patientId, $dentistId);
        mysqli_stmt_execute($feedbackStmt);
        $feedbackResult = mysqli_stmt_get_result($feedbackStmt);
        
        $feedback = [];
        while ($row = mysqli_fetch_assoc($feedbackResult)) {
            $feedback[] = $row;
        }
        mysqli_stmt_close($feedbackStmt);
        
        $patientHistory = [
            'id' => $patientId,
            'name' => $patientName,
            'email' => $patientEmail,
            'created_at' => $patientCreated,
            'appointments' => $appointments,
            'feedback' => $feedback
        ];
    } else {
        mysqli_stmt_close($verifyStmt);
    }
}

// Build search query
$searchQuery = 'SELECT u.id, u.name, u.email, u.created_at,
                (SELECT COUNT(*) FROM appointments WHERE client_id = u.id) as total_appointments,
                (SELECT MAX(start_time) FROM appointments WHERE client_id = u.id) as last_visit
                FROM users u 
                WHERE u.role_id = (SELECT id FROM roles WHERE name = ?) AND u.dentist_id = ?';

$searchParams = ['client', $dentistId];
$searchTypes = 'si';

if (!empty($searchTerm)) {
    $searchQuery .= ' AND u.name LIKE ?';
    $searchParams[] = '%' . $searchTerm . '%';
    $searchTypes .= 's';
}

$searchQuery .= ' ORDER BY u.name';

// Fetch clients for this dentist with search filter
$stmt = mysqli_prepare($conn, $searchQuery);
mysqli_stmt_bind_param($stmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$clients = [];
while ($row = mysqli_fetch_assoc($res)) {
    $clients[] = $row;
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
    <title>Manage Patients - Dental Clinic</title>
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
                    <li class="active">
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
                    <h2>My Patients</h2>
                    
                    <div class="patients-header">
                        <a href="client_form.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New Patient
                        </a>
                        
                        <!-- PHP-based Search Form -->
                        <form method="GET" action="clients.php" class="search-form">
                            <div class="search-box">
                                <input type="text" name="search" placeholder="Search patients..." 
                                       value="<?= htmlspecialchars($searchTerm) ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="clients.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Clear Search
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <?php if (!empty($searchTerm)): ?>
                        <div class="search-results-info">
                            <p><i class="fas fa-search"></i> 
                               Found <?= count($clients) ?> patient(s) matching "<?= htmlspecialchars($searchTerm) ?>"
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($clients)): ?>
                        <div class="no-patients">
                            <div class="no-patients-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <?php if (!empty($searchTerm)): ?>
                                <h3>No patients found</h3>
                                <p>No patients match your search criteria "<?= htmlspecialchars($searchTerm) ?>".</p>
                                <a href="clients.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> View All Patients
                                </a>
                            <?php else: ?>
                                <h3>No patients yet</h3>
                                <p>You haven't added any patients to your practice yet.</p>
                                <a href="client_form.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Add Your First Patient
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="patients-list">
                            <?php foreach ($clients as $c): ?>
                            <div class="patient-card">
                                <div class="patient-info">
                                    <div class="patient-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="patient-details">
                                        <h4><?= htmlspecialchars($c['name']) ?></h4>
                                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($c['email']) ?></p>
                                        <p><i class="fas fa-calendar"></i> Patient since <?= date('M Y', strtotime($c['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="patient-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $c['total_appointments'] ?></span>
                                        <span class="stat-label">Total Visits</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">
                                            <?= $c['last_visit'] ? date('M j', strtotime($c['last_visit'])) : 'Never' ?>
                                        </span>
                                        <span class="stat-label">Last Visit</span>
                                    </div>
                                </div>
                                <div class="patient-actions">
                                    <a href="client_form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary" title="Edit Patient">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="appointment_form.php?client_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary" title="Schedule Appointment">
                                        <i class="fas fa-calendar-plus"></i> Schedule
                                    </a>
                                    <a href="?history=<?= $c['id'] ?>&tab=appointments<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                                       class="btn btn-sm btn-info" title="View History">
                                        <i class="fas fa-history"></i> History
                                    </a>
                                    <a href="clients.php?remove=<?= $c['id'] ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                                       class="btn btn-sm btn-danger" title="Remove Patient"
                                       onclick="return confirm('Remove <?= htmlspecialchars($c['name']) ?> from your patient list? This will not delete their account.')">
                                        <i class="fas fa-user-minus"></i> Remove
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Patient History Modal -->
    <?php if ($patientHistory): ?>
    <div id="patientHistoryModal" class="modal active">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-md"></i> Patient History</h3>
                <a href="clients.php<?= !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '' ?>" class="close-btn">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <div class="modal-body">
                <!-- Patient Info -->
                <div class="patient-info-header">
                    <div class="patient-avatar-large">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="patient-details-large">
                        <h2><?= htmlspecialchars($patientHistory['name']) ?></h2>
                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($patientHistory['email']) ?></p>
                        <p><i class="fas fa-calendar"></i> Patient since <?= date('F j, Y', strtotime($patientHistory['created_at'])) ?></p>
                    </div>
                    <div class="patient-stats-large">
                        <div class="stat-item">
                            <span class="stat-number"><?= count($patientHistory['appointments']) ?></span>
                            <span class="stat-label">Total Appointments</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= count($patientHistory['feedback']) ?></span>
                            <span class="stat-label">Feedback Given</span>
                        </div>
                    </div>
                </div>

                <!-- History Tabs - PHP Based -->
                <div class="history-tabs">
                    <a href="?history=<?= $patientHistory['id'] ?>&tab=appointments<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                       class="history-tab-btn <?= $activeTab === 'appointments' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i> Appointments (<?= count($patientHistory['appointments']) ?>)
                    </a>
                    <a href="?history=<?= $patientHistory['id'] ?>&tab=feedback<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                       class="history-tab-btn <?= $activeTab === 'feedback' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i> Feedback (<?= count($patientHistory['feedback']) ?>)
                    </a>
                </div>

                <!-- Appointments History -->
                <?php if ($activeTab === 'appointments'): ?>
                <div id="appointmentsHistory" class="history-tab-content active">
                    <?php if (empty($patientHistory['appointments'])): ?>
                        <div class="empty-history">
                            <div class="empty-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h4>No appointments yet</h4>
                            <p>This patient hasn't had any appointments.</p>
                            <a href="appointment_form.php?client_id=<?= $patientHistory['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Schedule First Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="appointments-timeline">
                            <?php foreach ($patientHistory['appointments'] as $index => $appointment): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <div class="timeline-dot status-<?= strtolower($appointment['status']) ?>">
                                            <i class="fas fa-<?= $appointment['status'] === 'finished' ? 'check' : ($appointment['status'] === 'cancelled' ? 'times' : 'clock') ?>"></i>
                                        </div>
                                        <?php if ($index < count($patientHistory['appointments']) - 1): ?>
                                            <div class="timeline-line"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="appointment-card-history">
                                            <div class="appointment-header-history">
                                                <h4><?= date('F j, Y', strtotime($appointment['start_time'])) ?></h4>
                                                <span class="status-badge status-<?= strtolower($appointment['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($appointment['status'])) ?>
                                                </span>
                                            </div>
                                            <div class="appointment-details-history">
                                                <p><i class="fas fa-clock"></i> 
                                                    <?= date('g:i A', strtotime($appointment['start_time'])) ?> - 
                                                    <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                                                </p>
                                                <?php if ($appointment['notes']): ?>
                                                    <p><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($appointment['notes']) ?></p>
                                                <?php endif; ?>
                                                <p class="appointment-created">
                                                    <i class="fas fa-calendar-plus"></i> 
                                                    Scheduled on <?= date('M j, Y', strtotime($appointment['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Feedback History -->
                <?php if ($activeTab === 'feedback'): ?>
                <div id="feedbackHistory" class="history-tab-content active">
                    <?php if (empty($patientHistory['feedback'])): ?>
                        <div class="empty-history">
                            <div class="empty-icon">
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <h4>No feedback yet</h4>
                            <p>This patient hasn't provided any feedback.</p>
                        </div>
                    <?php else: ?>
                        <div class="feedback-history-list">
                            <?php foreach ($patientHistory['feedback'] as $fb): ?>
                                <div class="feedback-history-item">
                                    <div class="feedback-history-header">
                                        <div class="feedback-rating-large">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $fb['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="rating-number"><?= $fb['rating'] ?>/5</span>
                                        </div>
                                        <span class="feedback-date-large">
                                            <?= date('F j, Y', strtotime($fb['created_at'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($fb['comments']): ?>
                                        <div class="feedback-comment-large">
                                            <p>"<?= htmlspecialchars($fb['comments']) ?>"</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="clients.php<?= !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '' ?>" class="btn btn-secondary">Close</a>
                <a href="appointment_form.php?client_id=<?= $patientHistory['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Schedule New Appointment
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../script.js"></script>
</body>
</html>
