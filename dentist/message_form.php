<?php
session_start();
// Only dentists (role_id = 2) can access
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

// Handle reply functionality
$reply_subject = '';
$reply_recipient = '';
if (isset($_GET['reply_to']) && isset($_GET['subject'])) {
    $reply_recipient = $_GET['reply_to'];
    $reply_subject = $_GET['subject'];
}

// Handle sending a new message
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $subject     = trim($_POST['subject'] ?? '');
    $body        = trim($_POST['body']    ?? '');

    // validation
    if (!$receiver_id) {
        $errors[] = "Please select a recipient.";
    }
    if ($subject === '') {
        $errors[] = "Subject cannot be empty.";
    }
    if ($body === '') {
        $errors[] = "Message body cannot be empty.";
    }

    if (empty($errors)) {
        $ins = mysqli_prepare($conn,
            "INSERT INTO messages (sender_id, receiver_id, subject, body)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, 'iiss',
            $dentist_id,
            $receiver_id,
            $subject,
            $body
        );
        if (mysqli_stmt_execute($ins)) {
            $success = true;
            // Redirect back to messages with success message
            header('Location: messages.php?sent=1');
            exit();
        } else {
            $errors[] = "DB error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}

// Build recipients list: your clients + all admins
$recipients = [];

// your clients
$cstmt = mysqli_prepare($conn,
    "SELECT id, name 
       FROM users 
      WHERE dentist_id = ? 
        AND role_id = 3"
);
mysqli_stmt_bind_param($cstmt, 'i', $dentist_id);
mysqli_stmt_execute($cstmt);
mysqli_stmt_bind_result($cstmt, $rid, $rname);
while (mysqli_stmt_fetch($cstmt)) {
    $recipients[$rid] = $rname . " (Patient)";
}
mysqli_stmt_close($cstmt);

// all admins
$astmt = mysqli_prepare($conn,
    "SELECT id, name 
       FROM users 
      WHERE role_id = (SELECT id FROM roles WHERE name='admin')"
);
mysqli_stmt_execute($astmt);
mysqli_stmt_bind_result($astmt, $rid, $rname);
while (mysqli_stmt_fetch($astmt)) {
    $recipients[$rid] = $rname . " (Admin)";
}
mysqli_stmt_close($astmt);

// Get unread message count for navigation
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Handle template selection
$template_subject = '';
$template_body = '';
if (isset($_GET['template'])) {
    $template = $_GET['template'];
    switch($template) {
        case 'appointment':
            $template_subject = 'Appointment Reminder';
            $template_body = "Dear Patient,\n\nThis is a friendly reminder about your upcoming appointment.\n\nPlease let us know if you need to reschedule.\n\nBest regards,\nDr. " . $dentistName;
            break;
        case 'followup':
            $template_subject = 'Follow-up Care Instructions';
            $template_body = "Dear Patient,\n\nI hope you are feeling well after your recent visit.\n\nPlease follow the care instructions we discussed and don't hesitate to contact us if you have any questions.\n\nBest regards,\nDr. " . $dentistName;
            break;
        case 'welcome':
            $template_subject = 'Welcome to Our Practice';
            $template_body = "Dear Patient,\n\nWelcome to our dental practice! We are excited to have you as a patient.\n\nIf you have any questions or need to schedule an appointment, please don't hesitate to contact us.\n\nBest regards,\nDr. " . $dentistName;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Message - Epoka Clinic</title>
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
                    <li>
                        <a href="appointments.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li class="active">
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
                                <?= $reply_subject ? 'Reply to Message' : 'Compose Message' ?>
                            </h2>
                        </div>
                    </div>
                    
                    <div class="compose-form-container">
                        <?php if ($errors): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="alert-content">
                                    <h4>Please fix the following errors:</h4>
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-card">
                            <form method="post" action="">
                                <div class="form-group">
                                    <label for="receiver_id">
                                        <i class="fas fa-user"></i> To
                                    </label>
                                    <select name="receiver_id" id="receiver_id" required>
                                        <option value="">Select recipient</option>
                                        <?php foreach ($recipients as $rid => $rname): ?>
                                            <option value="<?= $rid ?>"
                                                <?php 
                                                if (isset($_POST['receiver_id']) && $_POST['receiver_id'] == $rid) {
                                                    echo 'selected';
                                                } elseif ($reply_recipient && strpos($rname, $reply_recipient) !== false) {
                                                    echo 'selected';
                                                }
                                                ?>>
                                                <?= htmlspecialchars($rname) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="subject">
                                        <i class="fas fa-tag"></i> Subject
                                    </label>
                                    <input type="text" name="subject" id="subject" 
                                           value="<?= htmlspecialchars($reply_subject ?: ($_POST['subject'] ?? '')) ?>"
                                           placeholder="Enter subject" required>
                                </div>

                                <div class="form-group">
                                    <label for="body">
                                        <i class="fas fa-edit"></i> Message
                                    </label>
                                    <textarea name="body" id="body" rows="8" 
                                              placeholder="Type your message..." required><?= isset($_POST['body']) ? htmlspecialchars($_POST['body']) : '' ?></textarea>
                                </div>

                                <div class="form-actions">
                                    <a href="messages.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
