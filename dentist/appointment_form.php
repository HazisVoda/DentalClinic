<?php
session_start();
// 1) Access control: only dentists (role_id = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

// Get dentist name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// 2) Fetch this dentist's clients for the dropdown
$clients = [];
$stmt = mysqli_prepare($conn,
    "SELECT id, name 
       FROM users 
      WHERE dentist_id = ? 
        AND role_id = 3
      ORDER BY name"
);
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $c_id, $c_name);
while (mysqli_stmt_fetch($stmt)) {
    $clients[] = ['id' => $c_id, 'name' => $c_name];
}
mysqli_stmt_close($stmt);

// 3) Initialize variables
$errors     = [];
$appointment_id = $_REQUEST['id'] ?? null;
$client_id  = $_REQUEST['client_id'] ?? '';
$start_time = '';
$end_time   = '';
$status     = 'booked'; // default for new
$notes      = '';

// 4) If editing (GET with ?id=…), load existing record
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $appointment_id) {
    $stmt = mysqli_prepare($conn,
        "SELECT client_id, start_time, end_time, status, notes
           FROM appointments
          WHERE id = ? 
            AND dentist_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $appointment_id, $dentist_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $client_id, $start_time, $end_time, $status, $notes);
    if (!mysqli_stmt_fetch($stmt)) {
        // no such appointment or not yours
        header('Location: appointments.php');
        exit();
    }
    mysqli_stmt_close($stmt);
}

// 5) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect & sanitize
    $appointment_id = $_POST['id'] ?? null;
    $client_id      = intval($_POST['client_id'] ?? 0);
    $start_time     = trim($_POST['start_time'] ?? '');
    $end_time       = trim($_POST['end_time'] ?? '');
    $status         = $_POST['status'] ?? 'booked';
    $notes          = trim($_POST['notes'] ?? '');

    // validate
    if (!$client_id) {
        $errors[] = 'Please select a patient.';
    }
    if (!$start_time) {
        $errors[] = 'Start time is required.';
    }
    if (!$end_time) {
        $errors[] = 'End time is required.';
    }
    if ($start_time && $end_time && $end_time <= $start_time) {
        $errors[] = 'End time must be after start time.';
    }
    if (!in_array($status, ['booked','finished','cancelled'], true)) {
        $errors[] = 'Invalid status.';
    }

    // insert or update
    if (empty($errors)) {
        if ($appointment_id) {
            $stmt = mysqli_prepare($conn,
                "UPDATE appointments
                    SET client_id = ?, start_time = ?, end_time = ?, status = ?, notes = ?
                  WHERE id = ?
                    AND dentist_id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'issssii',
                $client_id,
                $start_time,
                $end_time,
                $status,
                $notes,
                $appointment_id,
                $dentist_id
            );
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO appointments
                   (client_id, dentist_id, start_time, end_time, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iissss',
                $client_id,
                $dentist_id,
                $start_time,
                $end_time,
                $status,
                $notes
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: appointments.php');
            exit();
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
    }
}

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
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
    <title><?= $appointment_id ? 'Edit' : 'New' ?> Appointment - Epoka Clinic</title>
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
                    <div class="form-header">
                        <h2>
                            <?= $appointment_id ? 'Edit' : 'New' ?> Appointment
                        </h2>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Appointments
                        </a>
                    </div>
                    <br>

                    <?php if ($errors): ?>
                        <div class="error-message">
                            <div class="error-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <ul>
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="appointment-form">
                        <form method="post" action="">
                            <?php if ($appointment_id): ?>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($appointment_id) ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="client_id">
                                        <i class="fas fa-user"></i> Patient
                                    </label>
                                    <select name="client_id" id="client_id" required>
                                        <option value="">-- Select Patient --</option>
                                        <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>"
                                            <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($clients)): ?>
                                        <small class="form-help">
                                            <a href="client_form.php">Add a patient first</a>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="status">
                                        <i class="fas fa-info-circle"></i> Status
                                    </label>
                                    <select name="status" id="status" required>
                                        <option value="booked" <?= $status==='booked' ? 'selected' : '' ?>>Booked</option>
                                        <option value="finished" <?= $status==='finished' ? 'selected' : '' ?>>Finished</option>
                                        <option value="cancelled" <?= $status==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="start_time">
                                        <i class="fas fa-clock"></i> Start Time
                                    </label>
                                    <input
                                        type="datetime-local"
                                        name="start_time"
                                        id="start_time"
                                        value="<?= $start_time ? date('Y-m-d\TH:i', strtotime($start_time)) : '' ?>"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="end_time">
                                        <i class="fas fa-clock"></i> End Time
                                    </label>
                                    <input
                                        type="datetime-local"
                                        name="end_time"
                                        id="end_time"
                                        value="<?= $end_time ? date('Y-m-d\TH:i', strtotime($end_time)) : '' ?>"
                                        required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">
                                    <i class="fas fa-sticky-note"></i> Notes
                                </label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    rows="4"
                                    placeholder="Add any notes about this appointment..."><?= htmlspecialchars($notes) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $appointment_id ? 'Update' : 'Create' ?> Appointment
                                </button>
                                <a href="appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <?php if (!$appointment_id): ?>
                    <div class="quick-times">
                        <h3>Quick Time Slots</h3>
                        <p>Click a time slot to quickly set appointment times:</p>
                        <div class="time-slots">
                            <button type="button" onclick="setQuickTime('09:00', '09:59')" class="time-slot-btn">
                                9:00 AM - 09:59 AM
                            </button>
                            <button type="button" onclick="setQuickTime('10:00', '10:59')" class="time-slot-btn">
                                10:00 AM - 10:59 AM
                            </button>
                            <button type="button" onclick="setQuickTime('11:00', '11:59')" class="time-slot-btn">
                                11:00 AM - 11:59 AM
                            </button>
                            <button type="button" onclick="setQuickTime('14:00', '14:59')" class="time-slot-btn">
                                2:00 PM - 2:59 PM
                            </button>
                            <button type="button" onclick="setQuickTime('15:00', '15:59')" class="time-slot-btn">
                                3:00 PM - 3:59 PM
                            </button>
                            <button type="button" onclick="setQuickTime('16:00', '16:59')" class="time-slot-btn">
                                4:00 PM - 4:59 PM
                            </button>
                            <button type="button" onclick="setQuickTime('17:00', '17:59')" class="time-slot-btn">
                                5:00 PM - 5:59 PM
                            </button>
                            <button type="button" onclick="setQuickTime('18:00', '18:59')" class="time-slot-btn">
                                6:00 PM - 6:59 PM
                            </button>
                            <button type="button" onclick="setQuickTime('19:00', '19:59')" class="time-slot-btn">
                                7:00 PM - 7:59 PM
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // Auto-set end time when start time changes
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = new Date(this.value);
            if (startTime) {
                const endTime = new Date(startTime.getTime() + 60 * 60 * 1000); // Add 1 hour
                document.getElementById('end_time').value = endTime.toISOString().slice(0, 16);
            }
        });

        function setQuickTime(startTime, endTime) {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const dateStr = tomorrow.toISOString().slice(0, 10);
            
            document.getElementById('start_time').value = dateStr + 'T' + startTime;
            document.getElementById('end_time').value = dateStr + 'T' + endTime;
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            document.getElementById('start_time').min = minDateTime;
            document.getElementById('end_time').min = minDateTime;
        });
    </script>
</body>
</html>
