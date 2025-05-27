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

// 2) Handle "view message" (mark as read & display body)
$viewMessage = null;
if (isset($_GET['view'])) {
    $msg_id = intval($_GET['view']);
    // mark as read
    $upd = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    mysqli_stmt_bind_param($upd, 'ii', $msg_id, $client_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    // fetch the message
    $q = mysqli_prepare($conn, "
        SELECT m.subject, m.body, m.sent_at, u.name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ? AND m.receiver_id = ?
    ");
    mysqli_stmt_bind_param($q, 'ii', $msg_id, $client_id);
    mysqli_stmt_execute($q);
    mysqli_stmt_bind_result($q, $subject, $body, $sent_at, $sender_name);
    if (mysqli_stmt_fetch($q)) {
        $viewMessage = [
            'subject'     => $subject,
            'body'        => $body,
            'sent_at'     => $sent_at,
            'sender_name' => $sender_name,
        ];
    }
    mysqli_stmt_close($q);
}


// Check for success message from compose form
$show_success = isset($_GET['sent']) && $_GET['sent'] == '1';

// 4) Fetch inbox (latest 20)
$inbox = [];
$stmt = mysqli_prepare($conn, "
    SELECT m.id, m.subject, m.sent_at, m.is_read, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ?
    ORDER BY m.sent_at DESC
    LIMIT 20
");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $inbox[] = $row;
}
mysqli_stmt_close($stmt);

// Get unread count
$unread_count = 0;
foreach ($inbox as $msg) {
    if (!$msg['is_read']) $unread_count++;
}
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
</body>
</html>
