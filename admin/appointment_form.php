<?php
session_start();
require_once __DIR__ . '/../db.php'; // defines $conn

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];

// Fetch lists of clients and dentists
$clients = [];
$res = mysqli_query($conn, "SELECT id, name FROM users WHERE role_id = (SELECT id FROM roles WHERE name='client') ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $clients[] = $row;
}
$dentists = [];
$res = mysqli_query($conn, "SELECT id, name FROM users WHERE role_id = (SELECT id FROM roles WHERE name='dentist') ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $dentists[] = $row;
}

// Initialize form values
$client_id = '';
$dentist_id = '';
$start_time = '';
$end_time = '';
$status = 'booked';

// If editing, load existing data
if ($id) {
    $stmt = mysqli_prepare($conn, 'SELECT client_id, dentist_id, start_time, end_time, status FROM appointments WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $client_id, $dentist_id, $db_start, $db_end, $status);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    // Format for HTML5 datetime-local
    $start_time = date('Y-m-d\TH:i', strtotime($db_start));
    $end_time   = date('Y-m-d\TH:i', strtotime($db_end));
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id  = intval($_POST['client_id'] ?? 0);
    $dentist_id = intval($_POST['dentist_id'] ?? 0);
    $start_raw  = $_POST['start_time'] ?? '';
    $end_raw    = $_POST['end_time'] ?? '';
    $status     = in_array($_POST['status'] ?? 'booked', ['booked','finished','cancelled']) ? $_POST['status'] : 'booked';

    // Validate
    if (!$client_id || !$dentist_id || !$start_raw || !$end_raw) {
        $errors[] = 'All fields are required.';
    } else {
        $start_time = str_replace('T',' ',$start_raw) . ':00';
        $end_time   = str_replace('T',' ',$end_raw)   . ':00';
        if (strtotime($start_time) >= strtotime($end_time)) {
            $errors[] = 'End time must be after start time.';
        }
    }

    if (empty($errors)) {
        if ($id) {
            // Update
            $stmt = mysqli_prepare($conn, 
                'UPDATE appointments SET client_id=?, dentist_id=?, start_time=?, end_time=?, status=? WHERE id=?'
            );
            mysqli_stmt_bind_param($stmt, 'iisssi', $client_id, $dentist_id, $start_time, $end_time, $status, $id);
        } else {
            // Insert
            $stmt = mysqli_prepare($conn, 
                'INSERT INTO appointments (client_id, dentist_id, start_time, end_time, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            mysqli_stmt_bind_param($stmt, 'iisss', $client_id, $dentist_id, $start_time, $end_time, $status);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: appointments.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Edit Appointment' : 'Add New Appointment' ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <h1><?= $id ? 'Edit Appointment' : 'Add New Appointment' ?></h1>
        <nav>
            <a href="appointments.php">← Back to Manage Appointments</a>
        </nav>
    </header>

    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">
        <label>Client:<br>
            <select name="client_id" required>
                <option value="">-- Select Client --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <label>Dentist:<br>
            <select name="dentist_id" required>
                <option value="">-- Select Dentist --</option>
                <?php foreach ($dentists as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $dentist_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <label>Start Time:<br>
            <input type="datetime-local" name="start_time" value="<?= htmlspecialchars($start_time) ?>" required>
        </label><br><br>

        <label>End Time:<br>
            <input type="datetime-local" name="end_time" value="<?= htmlspecialchars($end_time) ?>" required>
        </label><br><br>

        <label>Status:<br>
            <select name="status" required>
                <?php foreach (['booked','finished','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $s === $status ? 'selected' : '' ?>>
                        <?= ucfirst($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <button type="submit"><?= $id ? 'Update Appointment' : 'Create Appointment' ?></button>
    </form>
</body>
</html>