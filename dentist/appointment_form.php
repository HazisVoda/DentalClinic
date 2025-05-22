<?php
session_start();
// 1) Access control: only dentists (role_id = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

// 2) Fetch this dentist’s clients for the dropdown
$clients = [];
$stmt = mysqli_prepare($conn,
    "SELECT id, name 
       FROM users 
      WHERE dentist_id = ? 
        AND role_id = 3
      ORDER BY name"
);
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $c_id, $c_name);
while (mysqli_stmt_fetch($stmt)) {
    $clients[] = ['id' => $c_id, 'name' => $c_name];
}
mysqli_stmt_close($stmt);

// 3) Initialize variables
$errors     = [];
$appointment_id = $_REQUEST['id'] ?? null;
$client_id  = '';
$start_time = '';
$end_time   = '';
$status     = 'booked'; // default for new

// 4) If editing (GET with ?id=…), load existing record
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $appointment_id) {
    $stmt = mysqli_prepare($conn,
        "SELECT client_id, start_time, end_time, status
           FROM appointments
          WHERE id = ? 
            AND dentist_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $appointment_id, $dentist_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $client_id, $start_time, $end_time, $status);
    if (!mysqli_stmt_fetch($stmt)) {
        // no such appointment or not yours
        header('Location: appointments.php');
        exit();
    }
    mysqli_stmt_close($stmt);
}

// 5) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect & sanitize
    $appointment_id = $_POST['id'] ?? null;
    $client_id      = intval($_POST['client_id'] ?? 0);
    $start_time     = trim($_POST['start_time'] ?? '');
    $end_time       = trim($_POST['end_time'] ?? '');
    $status         = $_POST['status'] ?? 'booked';

    // validate
    if (!$client_id) {
        $errors[] = 'Please select a client.';
    }
    if (!$start_time) {
        $errors[] = 'Start time is required.';
    }
    if (!$end_time) {
        $errors[] = 'End time is required.';
    }
    if ($start_time && $end_time && $end_time <= $start_time) {
        $errors[] = 'End time must be after start time.';
    }
    if (!in_array($status, ['booked','finished','cancelled'], true)) {
        $errors[] = 'Invalid status.';
    }

    // insert or update
    if (empty($errors)) {
        if ($appointment_id) {
            $stmt = mysqli_prepare($conn,
                "UPDATE appointments
                    SET client_id = ?, start_time = ?, end_time = ?, status = ?
                  WHERE id = ?
                    AND dentist_id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'isssii',
                $client_id,
                $start_time,
                $end_time,
                $status,
                $appointment_id,
                $dentist_id
            );
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO appointments
                   (client_id, dentist_id, start_time, end_time, status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iisss',
                $client_id,
                $dentist_id,
                $start_time,
                $end_time,
                $status
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: appointments.php');
            exit();
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $appointment_id ? 'Edit' : 'New' ?> Appointment</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1><?= $appointment_id ? 'Edit' : 'New' ?> Appointment</h1>

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
        <?php if ($appointment_id): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($appointment_id) ?>">
        <?php endif; ?>

        <div>
            <label for="client_id">Client</label>
            <select name="client_id" id="client_id" required>
                <option value="">-- Select Client --</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>"
                    <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="start_time">Start Time</label>
            <input
                type="datetime-local"
                name="start_time"
                id="start_time"
                value="<?= $start_time ? date('Y-m-d\TH:i', strtotime($start_time)) : '' ?>"
                required>
        </div>

        <div>
            <label for="end_time">End Time</label>
            <input
                type="datetime-local"
                name="end_time"
                id="end_time"
                value="<?= $end_time ? date('Y-m-d\TH:i', strtotime($end_time)) : '' ?>"
                required>
        </div>

        <div>
            <label for="status">Status</label>
            <select name="status" id="status" required>
                <option value="booked"    <?= $status==='booked'    ? 'selected' : '' ?>>Booked</option>
                <option value="finished"  <?= $status==='finished'  ? 'selected' : '' ?>>Finished</option>
                <option value="cancelled" <?= $status==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <button type="submit">
            <?= $appointment_id ? 'Update' : 'Create' ?> Appointment
        </button>
        <a href="appointments.php">Cancel</a>
    </form>
</body>
</html>
