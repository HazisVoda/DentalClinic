<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is a dentist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
// Verify role is dentist
$stmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE name = ?');
$roleName = 'dentist';
mysqli_stmt_bind_param($stmt, 's', $roleName);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistRoleId);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($_SESSION['role_id'] != $dentistRoleId) {
    header('Location: ../login.php');
    exit;
}

$dentistId = $_SESSION['user_id'];

// Handle removal (unassign client)
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $clientId = intval($_GET['remove']);
    // Ensure client belongs to this dentist
    $stmt = mysqli_prepare($conn, 'UPDATE users SET dentist_id = NULL WHERE id = ? AND dentist_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $clientId, $dentistId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: clients.php');
    exit;
}

// Fetch clients for this dentist
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, email FROM users WHERE role_id = (SELECT id FROM roles WHERE name = ?) AND dentist_id = ? ORDER BY name'
);
$clientRole = 'client';
mysqli_stmt_bind_param($stmt, 'si', $clientRole, $dentistId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $name, $email);
$clients = [];
while (mysqli_stmt_fetch($stmt)) {
    $clients[] = ['id' => $id, 'name' => $name, 'email' => $email];
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage My Clients</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1>My Clients</h1>
        <nav>
            <a href="dentist_dashboard.php">← Back to Dashboard</a>
        </nav>
    </header>

    <section>
        <a href="client_form.php" class="button">Add New Client</a>
        <?php if (empty($clients)): ?>
            <p>You have no clients yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['id']) ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td>
                        <a href="client_form.php?id=<?= $c['id'] ?>">Edit</a> |
                        <a href="clients.php?remove=<?= $c['id'] ?>" onclick="return confirm('Remove this client?');">Remove</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</body>
</html>
