<?php
session_start();
// 1) Only admins (role_id = 1) can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$admin_id = $_SESSION['user_id'];

// Get admin name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $admin_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Handle reply functionality
$reply_subject = '';
$reply_recipient = '';
if (isset($_GET['reply_to']) && isset($_GET['subject'])) {
    $reply_recipient = $_GET['reply_to'];
    $reply_subject = $_GET['subject'];
}

// 3) Send new message
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $subject     = trim($_POST['subject'] ?? '');
    $body        = trim($_POST['body']    ?? '');

    if (!$receiver_id)   $errors[] = "Select a recipient.";
    if ($subject === '') $errors[] = "Subject cannot be empty.";
    if ($body === '')    $errors[] = "Message cannot be empty.";

    if (empty($errors)) {
        $ins = mysqli_prepare($conn,
            "INSERT INTO messages (sender_id, receiver_id, subject, body)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, 'iiss',
            $admin_id, $receiver_id, $subject, $body
        );
        if (mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            header('Location: messages.php?sent=1');
            exit;
        } else {
            $errors[] = "DB error: ".mysqli_error($conn);
            mysqli_stmt_close($ins);
        }
    }
}

// 5) Build recipients: all dentists and clients
$recipients = [];
$rstmt = mysqli_prepare($conn,
    "SELECT id, name, role_id 
       FROM users 
      WHERE id <> ? 
        AND role_id IN (
           SELECT id FROM roles WHERE name IN ('dentist','client')
        )
      ORDER BY role_id, name"
);
mysqli_stmt_bind_param($rstmt, 'i', $admin_id);
mysqli_stmt_execute($rstmt);
mysqli_stmt_bind_result($rstmt, $rid, $rname, $rrole);
while (mysqli_stmt_fetch($rstmt)) {
    $label = $rname . ($rrole==2 ? ' (Dentist)' : ' (Client)');
    $recipients[$rid] = $label;
}
mysqli_stmt_close($rstmt);

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Handle template selection
$template_subject = '';
$template_body = '';
if (isset($_GET['template'])) {
    switch ($_GET['template']) {
        case 'system_update':
            $template_subject = 'System Update Notification';
            $template_body = "Dear User,\n\nWe wanted to inform you about an upcoming system update that will improve your experience with our dental clinic management system.\n\nThe update is scheduled for [DATE] and may cause brief service interruptions.\n\nThank you for your understanding.\n\nBest regards,\n" . $admin_name . "\nSystem Administrator";
            break;
        case 'policy_update':
            $template_subject = 'Policy Update Notice';
            $template_body = "Dear User,\n\nWe have updated our clinic policies to better serve you. Please review the changes at your earliest convenience.\n\nKey updates include:\n- [Policy change 1]\n- [Policy change 2]\n- [Policy change 3]\n\nIf you have any questions, please don't hesitate to contact us.\n\nBest regards,\n" . $admin_name . "\nAdministration";
            break;
        case 'welcome':
            $template_subject = 'Welcome to Our Dental Clinic';
            $template_body = "Dear New User,\n\nWelcome to our dental clinic management system! We're excited to have you as part of our community.\n\nYour account has been successfully created and you can now:\n- Schedule appointments\n- View your treatment history\n- Communicate with your dental team\n- Provide feedback\n\nIf you need any assistance getting started, please don't hesitate to reach out.\n\nBest regards,\n" . $admin_name . "\nAdministration";
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Message - Dental Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="adminDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic - Admin</span>
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
                        <a href="view_requests.php">
                            <i class="fas fa-user-clock"></i>
                            <span>Account Requests</span>
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
