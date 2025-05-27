<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dentistName);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

//Get current week (Monday → Sunday)
$today     = new DateTime('today');
$dayOfWeek = (int)$today->format('N');
$monday    = (clone $today)->modify('-'.($dayOfWeek-1).' days');
$sunday    = (clone $monday)->modify('+6 days');
$weekNum   = $monday->format('W');

//Load appointments for that range
$sql = "
  SELECT id, client_id, start_time, end_time, status
    FROM appointments
   WHERE dentist_id = ?
     AND start_time BETWEEN ? AND ?
   ORDER BY start_time
";
$stmt = mysqli_prepare($conn, $sql);
$startDate = $monday->format('Y-m-d').' 00:00:00';
$endDate   = $sunday->format('Y-m-d').' 23:59:59';
mysqli_stmt_bind_param($stmt, 'iss', $dentist_id, $startDate, $endDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

//Bucket appointments by day name
$appointments = [];
while ($row = mysqli_fetch_assoc($res)) {
    $dayName = date('l', strtotime($row['start_time']));
    $appointments[$dayName][] = $row;
}
mysqli_stmt_close($stmt);

//Define your grid "slots" and weekdays
$days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
//Time slots
$slots = [
  '08:00–08:59','09:00–09:59','10:00–10:59','11:00–11:59',
  '12:00–12:59','13:00–13:59','14:00–14:59','15:00–15:59',
  '16:00–16:59','17:00–17:59','18:00–18:59','19:00–19:59'
];

function slotIndex($datetime) {
    global $slots;
    // normalize the appointment time to "HH:MM"
    $time = date('H:i', strtotime($datetime));

    foreach ($slots as $i => $label) {
        // split on en-dash or hyphen
        $parts     = preg_split('/–|-/', $label);
        $slotStart = trim($parts[0]);  // e.g. "08:40"

        // if our appointment is before this slot starts, we belong in the previous
        if ($time < $slotStart) {
            return max(0, $i - 1);
        }
    }
    // if it's after all slots, put it in the last one
    return count($slots) - 1;
}

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $dentist_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Timetable - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .timetable-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .timetable-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .timetable-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .week-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .week-nav-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .week-nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .timetable {
            display: grid;
            grid-template-columns: 100px repeat(<?= count($slots) ?>, 1fr);
            grid-template-rows: 50px repeat(<?= count($days) ?>, 80px);
            gap: 1px;
            background: #e9ecef;
            padding: 1px;
        }
        
        .timetable > div { 
            background: #fff; 
            padding: 8px; 
            font-size: 0.85em;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .header-slot {
            background: #f8f9fa;
            text-align: center;
            font-size: 0.75em;
            line-height: 1.2;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #667eea;
        }
        
        .day-label {
            background: #f8f9fa;
            font-weight: bold;
            color: #495057;
            border-right: 2px solid #667eea;
            writing-mode: vertical-rl;
            text-orientation: mixed;
        }
        
        .cell { 
            background: #fff;
            border: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.3s ease;
        } 
        
        .cell:hover {
            background: #f0f4ff;
        }
        
        .event {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.75em;
            line-height: 1.2;
            cursor: pointer;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .event.status-booked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .event.status-finished {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .event.status-cancelled {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .event-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .event-time {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .event-status {
            font-size: 0.8em;
            opacity: 0.8;
            font-style: italic;
        }
        
        .timetable-actions {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .legend-booked { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .legend-finished { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .legend-cancelled { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
    </style>
</head>
<body>
    <div id="dentistDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic</span>
            </div>
            <div class="nav-user">
                <span>Welcome, Dr. <?= htmlspecialchars($dentistName) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li>
                        <a href="dentist_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="timetable.php">
                            <i class="fas fa-calendar-week"></i>
                            <span>Timetable</span>
                        </a>
                    </li>
                    <li>
                        <a href="clients.php">
                            <i class="fas fa-users"></i>
                            <span>Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="appointments.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <div class="content-section active">
                    <h2>Weekly Timetable</h2>
                    
                    <div class="timetable-container">
                        <div class="timetable-header">
                            <h3>Week <?= $weekNum ?></h3>
                        </div>
                        
                        <div class="timetable">
                            <!-- Time-slot headers -->
                            <?php foreach ($slots as $si => $sl): ?>
                                <div class="header-slot" style="grid-column: <?= 2 + $si ?>; grid-row: 1;">
                                    <?= htmlspecialchars($sl) ?>
                                </div>
                            <?php endforeach; ?>

                            <!-- Each day row -->
                            <?php foreach($days as $di => $day):
                                $row = 2 + $di;
                                $dayDate = (clone $monday)->modify('+' . $di . ' days');
                                $isToday = $dayDate->format('Y-m-d') === $today->format('Y-m-d');
                            ?>
                                <!-- Day name in fixed column 1 -->
                                <div class="day-label" style="grid-column: 1; grid-row: <?= $row ?>">
                                    <div>
                                        <?= $day ?>
                                        <br>
                                        <small><?= $dayDate->format('M j') ?></small>
                                        <?php if ($isToday): ?>
                                            <br><span style="color: #667eea;">Today</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Empty cells for each time-slot -->
                                <?php foreach(array_keys($slots) as $si):
                                    $col = 2 + $si;
                                ?>
                                    <div class="cell" style="grid-column: <?= $col ?>; grid-row: <?= $row ?>"></div>
                                <?php endforeach; ?>

                                <!-- Place each event block -->
                                <?php if (!empty($appointments[$day])): ?>
                                    <?php foreach($appointments[$day] as $appt):
                                        $startIdx = slotIndex($appt['start_time']);
                                        $endIdx   = slotIndex($appt['end_time']) + 1;
                                        $colStart = 2 + $startIdx;
                                        $colEnd   = 2 + $endIdx;
                                        
                                        $q = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
                                        mysqli_stmt_bind_param($q, 'i', $appt['client_id']);
                                        mysqli_stmt_execute($q);
                                        mysqli_stmt_bind_result($q, $cname);
                                        mysqli_stmt_fetch($q);
                                        mysqli_stmt_close($q);
                                    ?>
                                        <div class="event status-<?= $appt['status'] ?>"
                                             style="grid-column: <?= $colStart ?> / <?= $colEnd ?>; grid-row: <?= $row ?>;"
                                             onclick="viewAppointment(<?= $appt['id'] ?>)"
                                             title="Click to view details">
                                            <div class="event-name"><?= htmlspecialchars($cname) ?></div>
                                            <div class="event-time">
                                                <?= date('H:i', strtotime($appt['start_time'])) ?> – 
                                                <?= date('H:i', strtotime($appt['end_time'])) ?>
                                            </div>
                                            <div class="event-status"><?= htmlspecialchars(ucfirst($appt['status'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="timetable-actions">
                            <div class="legend">
                                <div class="legend-item">
                                    <div class="legend-color legend-booked"></div>
                                    <span>Booked</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color legend-finished"></div>
                                    <span>Finished</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color legend-cancelled"></div>
                                    <span>Cancelled</span>
                                </div>
                            </div>
                            <div class="timetable-buttons">
                                <a href="appointment_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> New Appointment
                                </a>
                                <a href="appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> View All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        function viewAppointment(appointmentId) {
            window.location.href = 'appointment_form.php?id=' + appointmentId;
        }
    </script>
</body>
</html>