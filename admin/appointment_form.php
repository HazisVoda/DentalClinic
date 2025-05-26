<?php
session_start();
require_once __DIR__ . '/../db.php';

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Get admin name
$admin_id = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $admin_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = false;

// Fetch lists of clients and dentists
$clients = [];
$res = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role_id = (SELECT id FROM roles WHERE name='client') ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $clients[] = $row;
}

$dentists = [];
$res = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role_id = (SELECT id FROM roles WHERE name='dentist') ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $dentists[] = $row;
}

// Initialize form values
$client_id = '';
$dentist_id = '';
$start_time = '';
$end_time = '';
$status = 'booked';
$notes = '';

// If editing, load existing data
if ($id) {
    $stmt = mysqli_prepare($conn, 'SELECT client_id, dentist_id, start_time, end_time, status, notes FROM appointments WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $client_id, $dentist_id, $db_start, $db_end, $status, $notes);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    // Format for HTML5 datetime-local
    $start_time = date('Y-m-d\TH:i', strtotime($db_start));
    $end_time = date('Y-m-d\TH:i', strtotime($db_end));
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $dentist_id = intval($_POST['dentist_id'] ?? 0);
    $start_raw = $_POST['start_time'] ?? '';
    $end_raw = $_POST['end_time'] ?? '';
    $status = in_array($_POST['status'] ?? 'booked', ['booked','finished','cancelled']) ? $_POST['status'] : 'booked';
    $notes = trim($_POST['notes'] ?? '');

    // Validate
    if (!$client_id || !$dentist_id || !$start_raw || !$end_raw) {
        $errors[] = 'Client, dentist, start time, and end time are required.';
    } else {
        $start_time_db = str_replace('T', ' ', $start_raw) . ':00';
        $end_time_db = str_replace('T', ' ', $end_raw) . ':00';
        
        if (strtotime($start_time_db) >= strtotime($end_time_db)) {
            $errors[] = 'End time must be after start time.';
        }
        
        // Check for conflicts (same dentist, overlapping times)
        $conflict_query = "SELECT id FROM appointments 
                          WHERE dentist_id = ? 
                          AND id != ? 
                          AND status != 'cancelled'
                          AND (
                              (start_time <= ? AND end_time > ?) OR
                              (start_time < ? AND end_time >= ?) OR
                              (start_time >= ? AND end_time <= ?)
                          )";
        $stmt = mysqli_prepare($conn, $conflict_query);
        mysqli_stmt_bind_param($stmt, 'iissssss', 
            $dentist_id, $id, 
            $start_time_db, $start_time_db,
            $end_time_db, $end_time_db,
            $start_time_db, $end_time_db
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'This dentist already has an appointment during the selected time period.';
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        if ($id) {
            // Update
            $stmt = mysqli_prepare($conn, 
                'UPDATE appointments SET client_id=?, dentist_id=?, start_time=?, end_time=?, status=?, notes=? WHERE id=?'
            );
            mysqli_stmt_bind_param($stmt, 'iissssi', $client_id, $dentist_id, $start_time_db, $end_time_db, $status, $notes, $id);
        } else {
            // Insert
            $stmt = mysqli_prepare($conn, 
                'INSERT INTO appointments (client_id, dentist_id, start_time, end_time, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            mysqli_stmt_bind_param($stmt, 'iissss', $client_id, $dentist_id, $start_time_db, $end_time_db, $status, $notes);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get unread message count for badge
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
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
    <title><?= $id ? 'Edit Appointment' : 'Create Appointment' ?> - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="adminDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic - Admin</span>
            </div>
            <div class="nav-user">
                <span>Welcome, <?= htmlspecialchars($admin_name) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li>
                        <a href="admin_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
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
                    <div class="page-header">
                        <div class="page-title">
                            <h2>
                                <i class="fas fa-<?= $id ? 'edit' : 'plus' ?>"></i>
                                <?= $id ? 'Edit Appointment' : 'Create New Appointment' ?>
                            </h2>
                            <p><?= $id ? 'Update appointment details' : 'Schedule a new appointment for a client' ?></p>
                        </div>
                        <div class="page-actions">
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Appointments
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Appointment <?= $id ? 'updated' : 'created' ?> successfully!
                            <div class="alert-actions">
                                <a href="appointments.php" class="btn btn-primary btn-sm">View All Appointments</a>
                                <?php if (!$id): ?>
                                    <a href="appointment_form.php" class="btn btn-secondary btn-sm">Create Another</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Please fix the following errors:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="form-container">
                        <form method="post" class="appointment-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="client_id">
                                        <i class="fas fa-user"></i> Select Client *
                                    </label>
                                    <select name="client_id" id="client_id" required>
                                        <option value="">-- Choose a client --</option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="dentist_id">
                                        <i class="fas fa-user-md"></i> Select Dentist *
                                    </label>
                                    <select name="dentist_id" id="dentist_id" required>
                                        <option value="">-- Choose a dentist --</option>
                                        <?php foreach ($dentists as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= $d['id'] == $dentist_id ? 'selected' : '' ?>>
                                                Dr. <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="start_time">
                                        <i class="fas fa-clock"></i> Start Time *
                                    </label>
                                    <input type="datetime-local" name="start_time" id="start_time" 
                                           value="<?= htmlspecialchars($start_time) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="end_time">
                                        <i class="fas fa-clock"></i> End Time *
                                    </label>
                                    <input type="datetime-local" name="end_time" id="end_time" 
                                           value="<?= htmlspecialchars($end_time) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="status">
                                        <i class="fas fa-info-circle"></i> Status
                                    </label>
                                    <select name="status" id="status" required>
                                        <?php 
                                        $statuses = [
                                            'booked' => 'Booked',
                                            'finished' => 'Finished',
                                            'cancelled' => 'Cancelled'
                                        ];
                                        foreach ($statuses as $value => $label): 
                                        ?>
                                            <option value="<?= $value ?>" <?= $value === $status ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label for="notes">
                                        <i class="fas fa-sticky-note"></i> Notes (Optional)
                                    </label>
                                    <textarea name="notes" id="notes" rows="4" 
                                              placeholder="Add any additional notes about this appointment..."><?= htmlspecialchars($notes) ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-<?= $id ? 'save' : 'plus' ?>"></i>
                                    <?= $id ? 'Update Appointment' : 'Create Appointment' ?>
                                </button>
                                <a href="appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Tips -->
                    <div class="tips-section">
                        <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
                        <div class="tips-grid">
                            <div class="tip-item">
                                <i class="fas fa-clock"></i>
                                <p>Appointments should be at least 30 minutes long for proper treatment time.</p>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-calendar-check"></i>
                                <p>The system will check for scheduling conflicts with the selected dentist.</p>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-sticky-note"></i>
                                <p>Use the notes field to add special instructions or treatment details.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
