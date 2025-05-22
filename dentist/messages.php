<?php
session_start();
// 1) Only dentists (role_id = 2) can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

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
    $recipients[$rid] = $rname . " (Client)";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dentist Messages</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <h1>Your Messages</h1>
  <nav>
    <a href="dentist_dashboard.php">Dashboard</a> |
    <a href="clients.php">My Clients</a> |
    <a href="appointments.php">My Appointments</a> |
    <a href="timetable.php">Timetable</a> |
    <a href="../logout.php">Logout</a>
  </nav>

  <!-- Inbox -->
  <section>
    <h2>Inbox</h2>
    <?php if (empty($inbox)): ?>
      <p>No messages.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th>From</th>
            <th>Subject</th>
            <th>Received</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inbox as $msg): ?>
            <tr>
              <td><?= $msg['is_read'] ? 'Read' : '<strong>New</strong>' ?></td>
              <td><?= htmlspecialchars($msg['sender_name']) ?></td>
              <td><a href="?view=<?= $msg['id'] ?>"><?= htmlspecialchars($msg['subject']) ?></a></td>
              <td><?= date('Y-m-d H:i', strtotime($msg['sent_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- View a single message -->
    <?php if ($viewMessage): ?>
      <article class="message-view">
        <h3><?= htmlspecialchars($viewMessage['subject']) ?></h3>
        <p><em>From <?= htmlspecialchars($viewMessage['sender_name']) ?> on 
           <?= date('Y-m-d H:i', strtotime($viewMessage['sent_at'])) ?></em></p>
        <div><?= nl2br(htmlspecialchars($viewMessage['body'])) ?></div>
      </article>
    <?php endif; ?>
  </section>

  <!-- Send New Message -->
  <section>
    <h2>Send a New Message</h2>
    <?php if ($success): ?>
      <p class="success">Message sent!</p>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="errors">
          <ul><?php foreach ($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <form method="post">
        <div>
          <label for="receiver_id">To:</label>
          <select name="receiver_id" id="receiver_id" required>
            <option value="">-- Select Recipient --</option>
            <?php foreach ($recipients as $rid => $label): ?>
              <option value="<?=$rid?>" <?= (isset($receiver_id)&&$receiver_id==$rid)?'selected':''?>>
                <?=htmlspecialchars($label)?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="subject">Subject:</label><br>
          <input type="text" name="subject" id="subject" value="<?=htmlspecialchars($subject ?? '')?>" required>
        </div>
        <div>
          <label for="body">Message:</label><br>
          <textarea name="body" id="body" rows="6" required><?=htmlspecialchars($body ?? '')?></textarea>
        </div>
        <button type="submit">Send</button>
      </form>
    <?php endif; ?>
  </section>
</body>
</html>
