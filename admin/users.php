<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Handle deletion with cascading removal of related data
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);

    if ($deleteId === $_SESSION['user_id']) {
        header('Location: users.php?error=self-delete');
        exit;
    }

    // Start transaction to ensure data integrity
    mysqli_begin_transaction($conn);
    try {
        // Delete appointments where this user is client or dentist
        $stmt = mysqli_prepare($conn, 'DELETE FROM appointments WHERE client_id = ? OR dentist_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $deleteId, $deleteId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Delete feedback entries tied to this user
        $stmt = mysqli_prepare($conn, 'DELETE FROM feedback WHERE client_id = ? OR dentist_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $deleteId, $deleteId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Delete messages sent or received by this user
        $stmt = mysqli_prepare($conn, 'DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $deleteId, $deleteId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Finally delete the user record
        $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $deleteId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Commit all deletions
        mysqli_commit($conn);
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        die('Error deleting user and related data: ' . $e->getMessage());
    }

    header('Location: users.php');
    exit;
}

// Fetch users with roles and, if client, their dentist's name
$sql = "
SELECT u.id, u.name, u.email, r.name AS role,
       CASE WHEN u.dentist_id IS NOT NULL THEN d.name ELSE '—' END AS dentist_name
FROM users u
JOIN roles r ON u.role_id = r.id
LEFT JOIN users d ON u.dentist_id = d.id
ORDER BY r.name ASC, u.name ASC
";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Manage Users</h1>
    <nav>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </nav>
</header>

<?php if (isset($_GET['error']) && $_GET['error'] === 'self-delete'): ?>
    <p class="error" style="color: red;">You cannot delete your own account.</p>
<?php endif; ?>

<section>
    <a href="user_form.php" class="button">Add New User</a>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Dentist</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td><?= htmlspecialchars($row['dentist_name']) ?></td>
                <td>
                    <a href="user_form.php?id=<?= $row['id'] ?>">Edit</a> |
                    <a href="users.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this user and all related data?');">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</section>
</body>
</html>