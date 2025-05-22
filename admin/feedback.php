<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Handle deletion of feedback
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $feedbackId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM feedback WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $feedbackId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: feedback.php');
    exit;
}

// Fetch all feedback entries
$sql = "
SELECT f.id,
       c.name AS client_name,
       d.name AS dentist_name,
       f.rating,
       f.comments,
       f.created_at
FROM feedback f
JOIN users c ON f.client_id = c.id
JOIN users d ON f.dentist_id = d.id
ORDER BY f.created_at DESC
";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Feedback</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1>User Feedback</h1>
        <nav>
            <a href="admin_dashboard.php">← Back to Dashboard</a>
        </nav>
    </header>

    <section>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Dentist</th>
                    <th>Rating</th>
                    <th>Comments</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['dentist_name']) ?></td>
                    <td><?= htmlspecialchars($row['rating']) ?>/5</td>
                    <td><?= nl2br(htmlspecialchars($row['comments'])) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <a href="feedback.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this feedback entry?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>
</body>
</html>
