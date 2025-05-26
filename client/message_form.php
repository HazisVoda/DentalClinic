<?php
session_start();
// Only clients can access
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

    // insert
    if (empty($errors)) {
        $ins = mysqli_prepare($conn, "
            INSERT INTO messages (sender_id, receiver_id, subject, body)
            VALUES (?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($ins, 'iiss',
            $client_id,
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

// Fetch recipients (dentist + admins)
$recipients = [];
// dentist
$stmt = mysqli_prepare($conn, "SELECT dentist_id FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentist_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
if ($dentist_id) {
    $q = mysqli_prepare($conn, "SELECT id, name FROM users WHERE id = ?");
    mysqli_stmt_bind_param($q, 'i', $dentist_id);
    mysqli_stmt_execute($q);
    mysqli_stmt_bind_result($q, $rid, $rname);
    if (mysqli_stmt_fetch($q)) {
        $recipients[$rid] = $rname . " (Your Dentist)";
    }
    mysqli_stmt_close($q);
}

// all admins
$stmt = mysqli_prepare($conn, "
    SELECT id, name
    FROM users
    WHERE role_id = (SELECT id FROM roles WHERE name = 'admin')
");
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $rid, $rname);
while (mysqli_stmt_fetch($stmt)) {
    $recipients[$rid] = $rname . " (Admin)";
}
mysqli_stmt_close($stmt);

// Get unread count for navigation
$unread_stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
mysqli_stmt_bind_param($unread_stmt, 'i', $client_id);
mysqli_stmt_execute($unread_stmt);
mysqli_stmt_bind_result($unread_stmt, $unread_count);
mysqli_stmt_fetch($unread_stmt);
mysqli_stmt_close($unread_stmt);
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
    <div id="clientDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic</span>
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
                                <a href="messages.php" class="back-link">
                                    <i class="fas fa-arrow-left"></i>
                                </a>
                                Compose Message
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
                                                <?= (isset($_POST['receiver_id']) && $_POST['receiver_id'] == $rid) ? 'selected' : '' ?>>
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
                                           value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>"
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
