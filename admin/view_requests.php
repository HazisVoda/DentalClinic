<?php
session_start();
require_once __DIR__ . '/../db.php';

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Fetch all client requests
$query = "SELECT id, name, email FROM client_requests ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Account Requests</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Client Account Requests</h1>
    <nav>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </nav>
</header>

<section>
    <?php if (mysqli_num_rows($result) === 0): ?>
        <p>No pending requests.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <a href="user_form.php?request_id=<?= $row['id'] ?>">Activate</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
</body>
</html>
