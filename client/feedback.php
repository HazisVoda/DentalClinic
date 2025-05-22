<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

// 2) Figure out this client’s dentist
$stmt = mysqli_prepare($conn, "SELECT dentist_id FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentist_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$dentist_id) {
    die("You don’t have an assigned dentist yet.");
}

$errors = [];
$success = false;

// 3) Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating   = intval($_POST['rating'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    // validation
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5.";
    }

    if (empty($errors)) {
        $ins = mysqli_prepare($conn,
            "INSERT INTO feedback (client_id, dentist_id, rating, comments)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins,
            'iiis',
            $client_id,
            $dentist_id,
            $rating,
            $comments
        );
        if (mysqli_stmt_execute($ins)) {
            $success = true;
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Give Feedback</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <h1>Give Feedback</h1>
  <nav>
    <a href="client_dashboard.php">Dashboard</a> |
    <a href="appointments.php">My Appointments</a> |
    <a href="messages.php">Messages</a> |
    <a href="../logout.php">Logout</a>
  </nav>

  <?php if ($success): ?>
    <p class="success">Thank you! Your feedback has been submitted.</p>
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
        <label for="rating">Rating:</label>
        <select name="rating" id="rating" required>
          <option value="">-- Select --</option>
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <option value="<?= $i ?>"
              <?= (isset($rating) && $rating == $i) ? 'selected' : '' ?>>
              <?= $i ?> <?= $i === 1 ? 'star' : 'stars' ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label for="comments">Comments (optional):</label><br>
        <textarea
          name="comments"
          id="comments"
          rows="5"
          cols="50"
        ><?= isset($comments) ? htmlspecialchars($comments) : '' ?></textarea>
      </div>
      <button type="submit">Submit Feedback</button>
    </form>
  <?php endif; ?>
</body>
</html>
