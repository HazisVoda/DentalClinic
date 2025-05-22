<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

/**
 * Fetch appointments for this client.
 * @param string $operator  Comparison operator for start_time (>= for upcoming, < for past)
 * @param string $order     ASC or DESC
 * @return array
 */
function fetch_client_appts($conn, $client_id, $operator, $order = 'ASC') {
    $sql = "
      SELECT
        a.id,
        a.start_time,
        a.end_time,
        a.status,
        a.notes,
        u.name AS dentist_name
      FROM appointments a
      JOIN users u ON a.dentist_id = u.id
      WHERE a.client_id = ?
        AND a.start_time {$operator} NOW()
      ORDER BY a.start_time {$order}
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $client_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// 2) Load upcoming and past
$upcoming = fetch_client_appts($conn, $client_id, '>=', 'ASC');
$past     = fetch_client_appts($conn, $client_id, '<',  'DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Appointments</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <h1>My Appointments</h1>
  <nav>
    <a href="client_dashboard.php">Dashboard</a> |
    <a href="feedback.php">Give Feedback</a> |
    <a href="messages.php">Messages</a> |
    <a href="../logout.php">Logout</a>
  </nav>

  <section>
    <h2>Upcoming Appointments</h2>
    <?php if (empty($upcoming)): ?>
      <p>No upcoming appointments.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Dentist</th>
            <th>Status</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $a): ?>
            <tr>
              <td>
                <?= date('Y-m-d H:i', strtotime($a['start_time'])) ?>
                &ndash;
                <?= date('H:i',     strtotime($a['end_time'])) ?>
              </td>
              <td><?= htmlspecialchars($a['dentist_name']) ?></td>
              <td><?= htmlspecialchars(ucfirst($a['status'])) ?></td>
              <td><?= nl2br(htmlspecialchars($a['notes'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section>
    <h2>Past Appointments</h2>
    <?php if (empty($past)): ?>
      <p>No past appointments.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Dentist</th>
            <th>Status</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($past as $a): ?>
            <tr>
              <td>
                <?= date('Y-m-d H:i', strtotime($a['start_time'])) ?>
                &ndash;
                <?= date('H:i',     strtotime($a['end_time'])) ?>
              </td>
              <td><?= htmlspecialchars($a['dentist_name']) ?></td>
              <td><?= htmlspecialchars(ucfirst($a['status'])) ?></td>
              <td><?= nl2br(htmlspecialchars($a['notes'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</body>
</html>
