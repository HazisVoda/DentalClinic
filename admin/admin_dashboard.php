<?php
session_start();
require_once __DIR__ . '/../db.php';

// Ensure user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Get admin name
$admin_id = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $admin_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Fetch dashboard statistics
$stats = [];
$queries = [
    'total_users' => 'SELECT COUNT(*) FROM users',
    'total_dentists' => "SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name='dentist')",
    'total_clients' => "SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name='client')",
    'total_appointments' => 'SELECT COUNT(*) FROM appointments',
    'pending_appointments' => "SELECT COUNT(*) FROM appointments WHERE status = 'booked'",
    'completed_appointments' => "SELECT COUNT(*) FROM appointments WHERE status = 'finished'",
    'cancelled_appointments' => "SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'",
    'total_feedback' => 'SELECT COUNT(*) FROM feedback',
    'unread_messages' => "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0",
    'todays_appointments' => "SELECT COUNT(*) FROM appointments WHERE DATE(start_time) = CURDATE()",
    'this_week_appointments' => "SELECT COUNT(*) FROM appointments WHERE WEEK(start_time) = WEEK(NOW())",
    'avg_rating' => "SELECT ROUND(AVG(rating), 1) FROM feedback"
];

foreach ($queries as $key => $sql) {
    if ($key === 'unread_messages') {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        $stats[$key] = $count ?? 0;
        mysqli_stmt_close($stmt);
    } else {
        $result = mysqli_query($conn, $sql);
        $stats[$key] = mysqli_fetch_row($result)[0] ?? 0;
    }
}

// Get recent activities
$recent_appointments = [];
$stmt = mysqli_prepare($conn, "
    SELECT a.id, c.name AS client_name, d.name AS dentist_name, 
           a.start_time, a.status, a.created_at
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN users d ON a.dentist_id = d.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $recent_appointments[] = $row;
}
mysqli_stmt_close($stmt);

// Get recent feedback
$recent_feedback = [];
$stmt = mysqli_prepare($conn, "
    SELECT f.rating, f.comments, c.name AS client_name, 
           d.name AS dentist_name, f.created_at
    FROM feedback f
    JOIN users c ON f.client_id = c.id
    JOIN users d ON f.dentist_id = d.id
    ORDER BY f.created_at DESC
    LIMIT 3
");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $recent_feedback[] = $row;
}
mysqli_stmt_close($stmt);

// Get recent users
$recent_users = [];
$stmt = mysqli_prepare($conn, "
    SELECT u.name, u.email, r.name AS role, u.created_at
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.id != ?
    ORDER BY u.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $recent_users[] = $row;
}
mysqli_stmt_close($stmt);

// Get today's appointments for quick overview
$todays_appointments = [];
$stmt = mysqli_prepare($conn, "
    SELECT a.id, c.name AS client_name, d.name AS dentist_name, 
           a.start_time, a.status
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN users d ON a.dentist_id = d.id
    WHERE DATE(a.start_time) = CURDATE()
    ORDER BY a.start_time ASC
    LIMIT 5
");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $todays_appointments[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .dashboard-header h1 {
            font-size: 2.5rem;
            margin: 0;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .dashboard-header p {
            text-align: center;
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card-enhanced {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card-enhanced.users { border-left-color: #3498db; }
        .stat-card-enhanced.dentists { border-left-color: #2ecc71; }
        .stat-card-enhanced.clients { border-left-color: #e74c3c; }
        .stat-card-enhanced.appointments { border-left-color: #f39c12; }
        .stat-card-enhanced.pending { border-left-color: #e67e22; }
        .stat-card-enhanced.completed { border-left-color: #27ae60; }
        .stat-card-enhanced.feedback { border-left-color: #9b59b6; }
        .stat-card-enhanced.messages { border-left-color: #34495e; }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon-enhanced {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon-enhanced.users { background: linear-gradient(45deg, #3498db, #2980b9); }
        .stat-icon-enhanced.dentists { background: linear-gradient(45deg, #2ecc71, #27ae60); }
        .stat-icon-enhanced.clients { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .stat-icon-enhanced.appointments { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .stat-icon-enhanced.pending { background: linear-gradient(45deg, #e67e22, #d35400); }
        .stat-icon-enhanced.completed { background: linear-gradient(45deg, #27ae60, #229954); }
        .stat-icon-enhanced.feedback { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
        .stat-icon-enhanced.messages { background: linear-gradient(45deg, #34495e, #2c3e50); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-change {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .stat-change.positive {
            color: #27ae60;
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .action-icon.users { background: linear-gradient(45deg, #3498db, #2980b9); }
        .action-icon.appointments { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .action-icon.messages { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
        .action-icon.feedback { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .action-description {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin: 0;
        }
        
        .activity-item-enhanced {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .activity-item-enhanced:hover {
            background: #f8f9fa;
            border-left-color: #667eea;
        }
        
        .activity-icon-enhanced {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .activity-content-enhanced {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin: 0 0 0.25rem 0;
            color: #2c3e50;
        }
        
        .activity-subtitle {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin: 0 0 0.25rem 0;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.booked {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-badge.finished {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-badge.cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .rating-stars {
            color: #f39c12;
            margin-right: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div id="adminDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Epoka Clinic - Admin</span>
            </div>
            <div class="nav-user">
                <span>Welcome, <?= htmlspecialchars($admin_name) ?>!</span>
                <a href="../logout.php?token=<?php echo $_SESSION['token']; ?>" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <div class="dashboard-container">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li class="active">
                        <a href="admin_dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="view_requests.php">
                            <i class="fas fa-user-clock"></i>
                            <span>Account Requests</span>
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
                            <?php if ($stats['unread_messages'] > 0): ?>
                                <span class="badge"><?= $stats['unread_messages'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <div class="content-section active">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1><i class="fas fa-chart-line"></i> Admin Dashboard</h1>
                        <p>Welcome back! Here's what's happening at your dental clinic today.</p>
                    </div>
                    
                    <!-- Enhanced Statistics Grid -->
                    <div class="stats-overview">
                        <div class="stat-card-enhanced users">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced users">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['total_users'] ?></h3>
                                    <p class="stat-label">Total Users</p>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> Active system users
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced dentists">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced dentists">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['total_dentists'] ?></h3>
                                    <p class="stat-label">Dentists</p>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-check-circle"></i> Professional staff
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced clients">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced clients">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['total_clients'] ?></h3>
                                    <p class="stat-label">Clients</p>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-heart"></i> Registered patients
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced appointments">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced appointments">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['total_appointments'] ?></h3>
                                    <p class="stat-label">Total Appointments</p>
                                </div>
                            </div>
                            <div class="stat-change">
                                <i class="fas fa-calendar"></i> <?= $stats['todays_appointments'] ?> today
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced pending">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced pending">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['pending_appointments'] ?></h3>
                                    <p class="stat-label">Pending</p>
                                </div>
                            </div>
                            <div class="stat-change">
                                <i class="fas fa-hourglass-half"></i> Awaiting confirmation
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced completed">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced completed">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['completed_appointments'] ?></h3>
                                    <p class="stat-label">Completed</p>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-thumbs-up"></i> Successfully finished
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced feedback">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced feedback">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['total_feedback'] ?></h3>
                                    <p class="stat-label">Feedback Entries</p>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-star"></i> <?= $stats['avg_rating'] ?: 'N/A' ?> avg rating
                            </div>
                        </div>
                        
                        <div class="stat-card-enhanced messages">
                            <div class="stat-header">
                                <div class="stat-icon-enhanced messages">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h3 class="stat-number"><?= $stats['unread_messages'] ?></h3>
                                    <p class="stat-label">Unread Messages</p>
                                </div>
                            </div>
                            <div class="stat-change">
                                <i class="fas fa-bell"></i> Requires attention
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Grid -->
                    <div class="dashboard-grid">
                        <!-- Today's Appointments -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h3 class="section-title">Today's Appointments</h3>
                                <a href="appointments.php" class="btn btn-sm btn-outline">View All</a>
                            </div>
                            <div class="activity-list">
                                <?php if (empty($todays_appointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-day"></i>
                                        <p>No appointments scheduled for today</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($todays_appointments as $apt): ?>
                                        <div class="activity-item-enhanced">
                                            <div class="activity-icon-enhanced" style="background: linear-gradient(45deg, #f39c12, #e67e22);">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                            <div class="activity-content-enhanced">
                                                <div class="activity-title"><?= htmlspecialchars($apt['client_name']) ?></div>
                                                <div class="activity-subtitle">with Dr. <?= htmlspecialchars($apt['dentist_name']) ?></div>
                                                <div class="activity-time">
                                                    <?= date('g:i A', strtotime($apt['start_time'])) ?>
                                                    <span class="status-badge <?= strtolower($apt['status']) ?>">
                                                        <?= ucfirst($apt['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Feedback -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h3 class="section-title">Recent Feedback</h3>
                                <a href="feedback.php" class="btn btn-sm btn-outline">View All</a>
                            </div>
                            <div class="feedback-list">
                                <?php if (empty($recent_feedback)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-star"></i>
                                        <p>No recent feedback</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_feedback as $fb): ?>
                                        <div class="activity-item-enhanced">
                                            <div class="activity-icon-enhanced" style="background: linear-gradient(45deg, #9b59b6, #8e44ad);">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div class="activity-content-enhanced">
                                                <div class="activity-title"><?= htmlspecialchars($fb['client_name']) ?></div>
                                                <div class="activity-subtitle">
                                                    <span class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="<?= $i <= $fb['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                                        <?php endfor; ?>
                                                    </span>
                                                    for Dr. <?= htmlspecialchars($fb['dentist_name']) ?>
                                                </div>
                                                <?php if ($fb['comments']): ?>
                                                    <div class="activity-time">
                                                        "<?= htmlspecialchars(substr($fb['comments'], 0, 60)) ?><?= strlen($fb['comments']) > 60 ? '...' : '' ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="dashboard-grid">
                        <!-- Recent Appointments -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h3 class="section-title">Recent Appointments</h3>
                                <a href="appointments.php" class="btn btn-sm btn-outline">Manage All</a>
                            </div>
                            <div class="activity-list">
                                <?php if (empty($recent_appointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <p>No recent appointments</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_appointments as $apt): ?>
                                        <div class="activity-item-enhanced">
                                            <div class="activity-icon-enhanced" style="background: linear-gradient(45deg, #3498db, #2980b9);">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                            <div class="activity-content-enhanced">
                                                <div class="activity-title"><?= htmlspecialchars($apt['client_name']) ?></div>
                                                <div class="activity-subtitle">with Dr. <?= htmlspecialchars($apt['dentist_name']) ?></div>
                                                <div class="activity-time">
                                                    <?= date('M j, Y g:i A', strtotime($apt['start_time'])) ?>
                                                    <span class="status-badge <?= strtolower($apt['status']) ?>">
                                                        <?= ucfirst($apt['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Users -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h3 class="section-title">Recent Users</h3>
                                <a href="users.php" class="btn btn-sm btn-outline">Manage All</a>
                            </div>
                            <div class="user-list">
                                <?php if (empty($recent_users)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-times"></i>
                                        <p>No recent users</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <div class="activity-item-enhanced">
                                            <div class="activity-icon-enhanced" style="background: linear-gradient(45deg, #2ecc71, #27ae60);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="activity-content-enhanced">
                                                <div class="activity-title"><?= htmlspecialchars($user['name']) ?></div>
                                                <div class="activity-subtitle"><?= htmlspecialchars($user['email']) ?></div>
                                                <div class="activity-time">
                                                    <span class="status-badge <?= strtolower($user['role']) ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
