<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $client_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// helper to fetch appointment lists
function fetch_appts($conn, $client_id, $operator, $limit = null) {
    $sql = "
      SELECT 
        a.id,
        a.start_time,
        a.end_time,
        a.status,
        u.name AS dentist_name
      FROM appointments a
      JOIN users u ON a.dentist_id = u.id
      WHERE a.client_id = ?
        AND a.start_time {$operator} NOW()
      ORDER BY a.start_time " . ($operator === '>=' ? 'ASC' : 'DESC') . "
      " . ($limit ? "LIMIT $limit" : "");
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

// 2) Load appointments
$upcoming = fetch_appts($conn, $client_id, '>=', 5);
$past     = fetch_appts($conn, $client_id, '<', 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
  <div id="clientDashboard" class="page">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic</span>
            </div>
            <div class="nav-user">
                <span>Welcome, <?= htmlspecialchars($client_name) ?>!</span>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li class="active">
                        <a href="client_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="appointments.php">
                            <i class="fas fa-calendar"></i>
                            <span>Appointments</span>
                        </a>
                    </li>
                    <li>
                        <a href="feedback.php">
                            <i class="fas fa-star"></i>
                            <span>Feedback</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <!-- Client Overview -->
                <div id="clientOverview" class="content-section active">
                    <h2>Dashboard Overview</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= htmlspecialchars(count($upcoming))?></h3>
                                <p>Upcoming Appointments</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= htmlspecialchars(count($past))?></h3>
                                <p>Past Appointments</p>
                            </div>
                        </div>
                    </div>

                    <div class="recent-appointments">
                        <h3>Upcoming Appointments</h3>
                        <div class="appointment-list">
                          <?php if (count($upcoming) === 0): ?>
                            <p>No upcoming appointments.</p>
                          <?php else: ?>
                            <?php foreach ($upcoming as $a): ?>
                            <div class="appointment-item">
                                <div class="appointment-date">
                                    <span class="date"><?= date('Y-m-d', strtotime($a['start_time'])) ?></span>
                                    <span class="time"><?= date('H:i', strtotime($a['start_time'])) ?></span>
                                </div>
                                <div class="appointment-details">
                                    <h4><?= htmlspecialchars($a['notes']) ?></h4>
                                    <p><?= htmlspecialchars($a['dentist_name']) ?></p>
                                </div>
                                <div class="appointment-status">
                                    <span class="status confirmed"><?= htmlspecialchars(ucfirst($a['status'])) ?></td></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Client Appointments -->
                <div id="clientAppointments" class="content-section">
                    <h2>My Appointments</h2>
                    
                    <div class="appointments-tabs">
                        <button class="tab-btn active" onclick="showAppointmentTab('upcoming')">Upcoming</button>
                        <button class="tab-btn" onclick="showAppointmentTab('past')">Past</button>
                    </div>

                    <div id="upcomingTab" class="tab-content active">
                        <div class="appointment-list">
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <h4>Regular Checkup</h4>
                                    <span class="status confirmed">Confirmed</span>
                                </div>
                                <div class="appointment-body">
                                    <p><i class="fas fa-user-md"></i> Dr. Sarah Johnson</p>
                                    <p><i class="fas fa-calendar"></i> December 28, 2024</p>
                                    <p><i class="fas fa-clock"></i> 10:00 AM - 11:00 AM</p>
                                </div>
                                <div class="appointment-actions">
                                    <button class="btn btn-secondary">Reschedule</button>
                                    <button class="btn btn-danger">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="pastTab" class="tab-content">
                        <div class="appointment-list">
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <h4>Teeth Cleaning</h4>
                                    <span class="status completed">Completed</span>
                                </div>
                                <div class="appointment-body">
                                    <p><i class="fas fa-user-md"></i> Dr. Sarah Johnson</p>
                                    <p><i class="fas fa-calendar"></i> November 15, 2024</p>
                                    <p><i class="fas fa-clock"></i> 2:00 PM - 3:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Feedback -->
                <div id="clientFeedback" class="content-section">
                    <h2>Send Feedback</h2>
                    
                    <div class="feedback-form">
                        <form>
                            <div class="form-group">
                                <label for="dentistSelect">Select Dentist</label>
                                <select id="dentistSelect">
                                    <option value="">Choose a dentist</option>
                                    <option value="dr-johnson">Dr. Sarah Johnson</option>
                                    <option value="dr-smith">Dr. Michael Smith</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="rating">Rating</label>
                                <div class="rating-stars">
                                    <i class="fas fa-star" data-rating="1"></i>
                                    <i class="fas fa-star" data-rating="2"></i>
                                    <i class="fas fa-star" data-rating="3"></i>
                                    <i class="fas fa-star" data-rating="4"></i>
                                    <i class="fas fa-star" data-rating="5"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="feedbackMessage">Your Feedback</label>
                                <textarea id="feedbackMessage" rows="5" placeholder="Share your experience..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit Feedback</button>
                        </form>
                    </div>
                </div>

                <!-- Client Messages -->
                <div id="clientMessages" class="content-section">
                    <h2>Messages</h2>
                    
                    <div class="messages-container">
                        <div class="message-compose">
                            <button class="btn btn-primary" onclick="showComposeModal()">
                                <i class="fas fa-plus"></i> New Message
                            </button>
                        </div>
                        
                        <div class="message-list">
                            <div class="message-item">
                                <div class="message-header">
                                    <h4>Appointment Reminder</h4>
                                    <span class="message-date">Dec 26, 2024</span>
                                </div>
                                <p class="message-preview">Your appointment with Dr. Johnson is scheduled for...</p>
                                <span class="message-from">From: Dr. Sarah Johnson</span>
                            </div>
                            
                            <div class="message-item unread">
                                <div class="message-header">
                                    <h4>Treatment Plan Update</h4>
                                    <span class="message-date">Dec 25, 2024</span>
                                </div>
                                <p class="message-preview">We have updated your treatment plan based on...</p>
                                <span class="message-from">From: Admin</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../script.js"></script>
</body>
</html>