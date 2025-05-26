<?php
session_start();
// 1) Only dentists (role_id = 2) can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

// Check for success message from compose form
$show_success = isset($_GET['sent']) && $_GET['sent'] == '1';

// Check for success message from compose form
$showSentMessage = isset($_GET['sent']) && $_GET['sent'] == '1';

// Get dentist name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// 2) View a message (mark as read + fetch details)
$viewMessage = null;
if (!empty($_GET['view'])) {
    $msg_id = intval($_GET['view']);
    // mark as read
    $upd = mysqli_prepare($conn,
        "UPDATE messages 
           SET is_read = 1 
         WHERE id = ? 
           AND receiver_id = ?"
    );
    mysqli_stmt_bind_param($upd, 'ii', $msg_id, $dentist_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    // fetch its content
    $q = mysqli_prepare($conn,
        "SELECT m.subject, m.body, m.sent_at, u.name AS sender_name
           FROM messages m
           JOIN users u ON m.sender_id = u.id
          WHERE m.id = ? 
            AND m.receiver_id = ?"
    );
    mysqli_stmt_bind_param($q, 'ii', $msg_id, $dentist_id);
    mysqli_stmt_execute($q);
    mysqli_stmt_bind_result($q, $subject, $body, $sent_at, $sender_name);
    if (mysqli_stmt_fetch($q)) {
        $viewMessage = compact('subject','body','sent_at','sender_name');
    }
    mysqli_stmt_close($q);
}

// 3) Send new message
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
            // Clear form data
            $receiver_id = '';
            $subject = '';
            $body = '';
        } else {
            $errors[] = "DB error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}

// 4) Fetch inbox (latest 20 messages to this dentist)
$inbox = [];
$stmt = mysqli_prepare($conn,
    "SELECT m.id, m.subject, m.sent_at, m.is_read, u.name AS sender_name
       FROM messages m
       JOIN users u ON m.sender_id = u.id
      WHERE m.receiver_id = ?
      ORDER BY m.sent_at DESC
      LIMIT 20"
);
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $inbox[] = $row;
}
mysqli_stmt_close($stmt);

// 5) Build recipients list: your clients + all admins
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
    <title>Messages - Epoka Clinic</title>
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
                    <h2>Messages</h2>
                    
                    <?php if ($show_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>Message Sent Successfully!</h4>
                                <p>Your message has been delivered.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="messages-container">
                        <div class="message-compose">
                            <a href="message_form.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Message
                            </a>
                        </div>
                        <br>
                        
                        <?php if ($viewMessage): ?>
                            <div class="message-view">
                                <div class="message-view-content">
                                    <h3><?= htmlspecialchars($viewMessage['subject']) ?></h3>
                                    <div class="message-meta">
                                        <span><i class="fas fa-user"></i> From: <?= htmlspecialchars($viewMessage['sender_name']) ?></span>
                                        <span><i class="fas fa-clock"></i> <?= date('F j, Y \a\t g:i A', strtotime($viewMessage['sent_at'])) ?></span>
                                    </div>
                                    <div class="message-body">
                                        <?= nl2br(htmlspecialchars($viewMessage['body'])) ?>
                                    </div>
                                    <div class="message-actions">
                                        <a href="message_form.php?reply_to=<?= urlencode($viewMessage['sender_name']) ?>&subject=<?= urlencode('Re: ' . $viewMessage['subject']) ?>" class="btn btn-primary">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                        <a href="messages.php" class="btn btn-secondary">
                                            <i class="fas fa-list"></i> Back to Inbox
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="message-list">
                                <?php if (empty($inbox)): ?>
                                    <div class="no-messages" style="margin-left: 10px">
                                        <div class="no-messages-icon">
                                            <i class="fas fa-envelope-open"></i>
                                        </div>
                                        <h3>No messages yet</h3>
                                        <p>You don't have any messages. Start a conversation with your dentist or admin.</p>
                                        <a href="message_form.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Send First Message
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($inbox as $msg): ?>
                                        <a href="?view=<?= $msg['id'] ?>" class="message-item-link" style="text-decoration: none;">
                                            <div class="message-item <?= !$msg['is_read'] ? 'unread' : '' ?>">
                                                <div class="message-header">
                                                    <h4><?= htmlspecialchars($msg['subject']) ?></h4> &nbsp; &nbsp;
                                                    <span class="message-date"><?= date('M j, Y', strtotime($msg['sent_at'])) ?></span>
                                                </div>
                                                <p class="message-preview">
                                                    Click to read this message...
                                                </p>
                                                <span class="message-from">From: <?= htmlspecialchars($msg['sender_name']) ?></span>
                                                <?php if (!$msg['is_read']): ?>
                                                    <div class="unread-indicator">
                                                        <i class="fas fa-circle"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
