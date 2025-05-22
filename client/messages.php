<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

// 2) Handle “view message” (mark as read & display body)
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

// 3) Handle sending a new message
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
        } else {
            $errors[] = "DB error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}

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

// 5) Fetch recipients (dentist + admins)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Messages</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <h1>Your Messages</h1>
  <nav>
    <a href="client_dashboard.php">Dashboard</a> |
    <a href="appointments.php">My Appointments</a> |
    <a href="feedback.php">Give Feedback</a> |
    <a href="../logout.php">Logout</a>
  </nav>

  <!-- Inbox list -->
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
              <td>
                <a href="?view=<?= $msg['id'] ?>">
                  <?= htmlspecialchars($msg['subject']) ?>
                </a>
              </td>
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
        <p><em>From <?= htmlspecialchars($viewMessage['sender_name']) ?>
           on <?= date('Y-m-d H:i', strtotime($viewMessage['sent_at'])) ?></em></p>
        <div><?= nl2br(htmlspecialchars($viewMessage['body'])) ?></div>
      </article>
    <?php endif; ?>
  </section>

  <!-- Send new message -->
  <section>
    <h2>Send a New Message</h2>
    <?php if ($success): ?>
      <p class="success">Message sent!</p>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="errors">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <div>
          <label for="receiver_id">To:</label>
          <select name="receiver_id" id="receiver_id" required>
            <option value="">-- Select Recipient --</option>
            <?php foreach ($recipients as $rid => $rname): ?>
              <option value="<?= $rid ?>"
                <?= (isset($receiver_id) && $receiver_id == $rid) ? 'selected' : '' ?>>
                <?= htmlspecialchars($rname) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="subject">Subject:</label><br>
          <input
            type="text"
            name="subject"
            id="subject"
            value="<?= isset($subject) ? htmlspecialchars($subject) : '' ?>"
            required>
        </div>
        <div>
          <label for="body">Message:</label><br>
          <textarea
            name="body"
            id="body"
            rows="6"
            cols="50"
            required><?= isset($body) ? htmlspecialchars($body) : '' ?></textarea>
        </div>
        <button type="submit">Send Message</button>
      </form>
    <?php endif; ?>
  </section>
</body>
</html>
