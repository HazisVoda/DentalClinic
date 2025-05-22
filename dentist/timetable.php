<?php
session_start();
// 1) Only dentists can view
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$dentist_id = $_SESSION['user_id'];

// 2) Figure out current week (Monday → Sunday)
$today     = new DateTime('today');
$dayOfWeek = (int)$today->format('N');               // 1 (Mon)–7 (Sun)
$monday    = (clone $today)->modify('-'.($dayOfWeek-1).' days');
$sunday    = (clone $monday)->modify('+6 days');
$weekNum   = $monday->format('W');

// 3) Load appointments for that date range
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

// 4) Bucket them by day name
$appointments = [];
while ($row = mysqli_fetch_assoc($res)) {
    $dayName = date('l', strtotime($row['start_time'])); // e.g. "Monday"
    $appointments[$dayName][] = $row;
}
mysqli_stmt_close($stmt);

// 5) Define your grid “slots” and weekdays
$days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
// You can adjust these labels to whatever times you need:
$slots = [
  '08:40–09:30','09:40–10:30','10:40–11:30','11:40–12:30',
  '12:40–13:30','13:40–14:30','14:40–15:30','15:40–16:30',
  '16:40–17:30','17:40–18:30','18:40–19:30','19:40–20:30'
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
    // if it’s after all slots, put it in the last one
    return count($slots) - 1;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Weekly Timetable (Week <?= $weekNum ?>)</title>
  <style>
    .timetable {
      display: grid;
      /* 2 cols for labels + one per slot */
      grid-template-columns: 80px 100px repeat(<?= count($slots) ?>, 1fr);
      grid-template-rows: 40px repeat(<?= count($days) ?>, 80px);
      gap: 2px;
      background: #ccc;
    }
    .timetable > div { 
      background: #fff; 
      padding: 4px; 
      font-size: 0.85em;
      overflow: hidden;
    }
    .header-week {
      grid-column: 1 / -1;
      background: #f0f0f0;
      font-weight: bold;
      display: flex;
      align-items: center;
      padding-left: 8px;
    }
    .header-day, .day-label {
      background: #fafafa;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .header-slot {
      background: #f7f7f7;
      text-align: center;
      font-size: 0.75em;
      line-height: 1.2;
    }
    .cell { background: #fff; } 
    .event {
      position: relative;
      background: #4a90e2;
      color: black;
      padding: 4px;
      border-radius: 4px;
      font-size: 0.75em;
      line-height: 1.1;
    }
  </style>
</head>
<body>
  <h1>Week <?= $weekNum ?> Timetable</h1>
  <div class="timetable">
      <!-- top row -->
  <div 
    class="header-week" 
    style="grid-column: 1 / -1; grid-row: 1;"
  >
    Week <?= $weekNum ?>
  </div>

  <!-- Day label at row 1, col 2 -->
  <div 
    class="header-day" 
    style="grid-column: 2; grid-row: 1;"
  >
    Day
  </div>

  <!-- Time-slot headers, each at row 1 and its correct column -->
  <?php foreach ($slots as $si => $sl): ?>
    <div 
      class="header-slot" 
      style="grid-column: <?= 3 + $si ?>; grid-row: 1;"
    >
      <?= htmlspecialchars($sl) ?>
    </div>
  <?php endforeach; ?>


    <!-- each day row -->
    <?php foreach($days as $di => $day):
        $row = 2 + $di; // grid-row number for this weekday
    ?>
      <!-- Day name in fixed column 2 -->
      <div
        class="day-label"
        style="grid-column: 2; grid-row: <?= $row ?>"
      >
        <?= $day ?>
      </div>

      <!-- Empty cells for each time-slot -->
      <?php foreach(array_keys($slots) as $si):
        $col = 3 + $si; // first slot is column 3
      ?>
        <div
          class="cell"
          style="grid-column: <?= $col ?>; grid-row: <?= $row ?>"
        ></div>
      <?php endforeach; ?>

      <!-- Place each event block -->
      <?php if (!empty($appointments[$day])): ?>
        <?php foreach($appointments[$day] as $appt):
          $startIdx = slotIndex($appt['start_time']);
          $endIdx   = slotIndex($appt['end_time']) + 1;
          $colStart = 3 + $startIdx;
          $colEnd   = 3 + $endIdx;
          // lookup client name
          $q = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
          mysqli_stmt_bind_param($q, 'i', $appt['client_id']);
          mysqli_stmt_execute($q);
          mysqli_stmt_bind_result($q, $cname);
          mysqli_stmt_fetch($q);
          mysqli_stmt_close($q);
        ?>
          <div
            class="event"
            style="
              grid-column: <?= $colStart ?> / <?= $colEnd ?>;
              grid-row: <?= $row ?>;
            "
          >
            <strong><?= htmlspecialchars($cname) ?></strong><br>
            <?= date('H:i', strtotime($appt['start_time'])) ?> –
            <?= date('H:i', strtotime($appt['end_time'])) ?><br>
            <em><?= htmlspecialchars($appt['status']) ?></em>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</body>
</html>
