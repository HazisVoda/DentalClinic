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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle deletion of request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM client_requests WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $deleteId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: view_requests.php?deleted=1' . ($search ? '&search=' . urlencode($search) : ''));
    exit;
}

// Build query with search
$sql = "SELECT id, name, email, created_at FROM client_requests WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = $row;
}
mysqli_stmt_close($stmt);

// Get unread message count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
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
    <title>Client Account Requests - Epoka Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                    <li>
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
                    <li class="active">
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
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main-content">
                <div class="content-section active">
                    <div class="page-header">
                        <h2>
                            <i class="fas fa-user-clock"></i>
                            Account Requests
                        </h2>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                    <br>

                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>Request Deleted Successfully!</h4>
                                <p>The client account request has been removed from the system.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Search Section -->
                    <div class="search-section">
                        <form method="GET" class="search-form">
                            <div class="search-group">
                                <div class="search-field">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" placeholder="Search by name or email..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($search): ?>
                                    <a href="view_requests.php" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Results Info -->
                    <div class="results-info">
                        <div class="results-count">
                            <i class="fas fa-user-clock"></i>
                            <?php if ($search): ?>
                                Found <?= count($requests) ?> request(s) matching "<?= htmlspecialchars($search) ?>"
                            <?php else: ?>
                                Showing all <?= count($requests) ?> pending requests
                            <?php endif; ?>
                        </div>
                        <?php if (count($requests) > 0): ?>
                            <div class="results-actions">
                                <span class="info-text">
                                    <i class="fas fa-info-circle"></i>
                                    Click "Activate" to create user accounts
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Requests Table -->
                    <?php if (empty($requests)): ?>
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h3>No account requests found</h3>
                            <p>
                                <?php if ($search): ?>
                                    No requests match your search criteria. Try adjusting your search terms.
                                <?php else: ?>
                                    There are currently no pending client account requests.
                                <?php endif; ?>
                            </p>
                            <?php if ($search): ?>
                                <a href="view_requests.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i> View All Requests
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <i class="fas fa-user"></i>
                                            Name
                                        </th>
                                        <th>
                                            <i class="fas fa-envelope"></i>
                                            Email
                                        </th>
                                        <th>
                                            <i class="fas fa-calendar"></i>
                                            Requested
                                        </th>
                                        <th>
                                            <i class="fas fa-cogs"></i>
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <div class="user-details">
                                                        <span class="user-name"><?= htmlspecialchars($request['name']) ?></span>
                                                        <span class="user-status">Pending Activation</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="email-info">
                                                    <i class="fas fa-envelope"></i>
                                                    <?= htmlspecialchars($request['email']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-info">
                                                    <i class="fas fa-clock"></i>
                                                    <span class="date-primary"><?= date('M j, Y', strtotime($request['created_at'])) ?></span>
                                                    <span class="date-secondary"><?= date('g:i A', strtotime($request['created_at'])) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="user_form.php?request_id=<?= $request['id'] ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       title="Activate Account">
                                                        <i class="fas fa-user-check"></i>
                                                        Activate
                                                    </a>
                                                    <a href="view_requests.php?delete=<?= $request['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Delete Request"
                                                       onclick="return confirm('Are you sure you want to delete this account request? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary Stats -->
                        <div class="summary-stats">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-number"><?= count($requests) ?></span>
                                    <span class="stat-label">Pending Requests</span>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-number">
                                        <?php 
                                        $today_requests = array_filter($requests, function($r) {
                                            return date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d');
                                        });
                                        echo count($today_requests);
                                        ?>
                                    </span>
                                    <span class="stat-label">Today's Requests</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="../script.js"></script>
</body>
</html>
