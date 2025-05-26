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

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

// Handle deletion of appointment
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM appointments WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $deleteId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Redirect with success message
    $redirect_params = [];
    if ($search) $redirect_params[] = 'search=' . urlencode($search);
    if ($status_filter) $redirect_params[] = 'status=' . urlencode($status_filter);
    if ($date_filter) $redirect_params[] = 'date=' . urlencode($date_filter);
    $redirect_params[] = 'deleted=1';
    
    header('Location: appointments.php?' . implode('&', $redirect_params));
    exit;
}

// Handle status update
if (isset($_GET['update_status'], $_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = in_array($_GET['update_status'], ['booked','finished','cancelled']) ? $_GET['update_status'] : 'booked';
    $stmt = mysqli_prepare($conn, 'UPDATE appointments SET status = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Redirect with success message
    $redirect_params = [];
    if ($search) $redirect_params[] = 'search=' . urlencode($search);
    if ($status_filter) $redirect_params[] = 'status=' . urlencode($status_filter);
    if ($date_filter) $redirect_params[] = 'date=' . urlencode($date_filter);
    $redirect_params[] = 'updated=1';
    
    header('Location: appointments.php?' . implode('&', $redirect_params));
    exit;
}

// Pagination settings
$items_per_page = 12;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Build query with search and filters
$sql = "
    SELECT a.id, c.name AS client_name, d.name AS dentist_name,
           a.start_time, a.end_time, a.status, a.notes, a.created_at
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN users d ON a.dentist_id = d.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND (c.name LIKE ? OR d.name LIKE ? OR a.notes LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_filter) {
    $sql .= " AND DATE(a.start_time) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

// Get total count for pagination
$count_sql = str_replace("SELECT a.id, c.name AS client_name, d.name AS dentist_name, a.start_time, a.end_time, a.status, a.notes, a.created_at FROM", "SELECT COUNT(*) as total FROM", $sql);
$count_sql = str_replace(" ORDER BY a.start_time DESC", "", $count_sql);

if ($params) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_records);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
    $total_records = mysqli_fetch_row($count_result)[0];
}

$total_pages = ceil($total_records / $items_per_page);

$sql .= " ORDER BY a.start_time DESC LIMIT ? OFFSET ?";

$pagination_params = $params;
$pagination_params[] = $items_per_page;
$pagination_params[] = $offset;
$pagination_types = $types . 'ii';

$stmt = mysqli_prepare($conn, $sql);
if ($pagination_params) {
    mysqli_stmt_bind_param($stmt, $pagination_types, ...$pagination_params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$appointments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $appointments[] = $row;
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
    <title>Manage Appointments - Dental Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="adminDashboard" class="page active">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-tooth"></i>
                <span>Dental Clinic - Admin</span>
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
                    <li>
                        <a href="view_requests.php">
                            <i class="fas fa-user-clock"></i>
                            <span>Account Requests</span>
                        </a>
                    </li>
                    <li class="active">
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
                        <h2>Manage Appointments</h2>
                        <a href="appointment_form.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Add New Appointment
                        </a>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>Appointment Deleted Successfully!</h4>
                                <p>The appointment has been removed from the system.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>Appointment Updated Successfully!</h4>
                                <p>The appointment status has been updated.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter -->
                    <div class="search-filter-section">
                        <form method="GET" class="search-form">
                            <div class="search-group">
                                <div class="search-field">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" placeholder="Search by client, dentist, or notes..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="filter-field">
                                    <select name="status">
                                        <option value="">All Statuses</option>
                                        <option value="booked" <?= $status_filter === 'booked' ? 'selected' : '' ?>>Booked</option>
                                        <option value="finished" <?= $status_filter === 'finished' ? 'selected' : '' ?>>Finished</option>
                                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="filter-field">
                                    <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                                </div>
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <?php if ($search || $status_filter || $date_filter): ?>
                                    <a href="appointments.php" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Results Info -->
                    <div class="results-info">
                        <p>
                            <?php if ($search || $status_filter || $date_filter): ?>
                                Found <?= $total_records ?> appointment(s)
                                <?php if ($search): ?>
                                    matching "<?= htmlspecialchars($search) ?>"
                                <?php endif; ?>
                                <?php if ($status_filter): ?>
                                    with status "<?= htmlspecialchars($status_filter) ?>"
                                <?php endif; ?>
                                <?php if ($date_filter): ?>
                                    on <?= date('F j, Y', strtotime($date_filter)) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Showing all <?= $total_records ?> appointments
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Appointments Table -->
                    <?php if (empty($appointments)): ?>
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <h3>No appointments found</h3>
                            <p>
                                <?php if ($search || $status_filter || $date_filter): ?>
                                    Try adjusting your search criteria or filters.
                                <?php else: ?>
                                    No appointments have been scheduled yet.
                                <?php endif; ?>
                            </p>
                            <?php if (!$search && !$status_filter && !$date_filter): ?>
                                <a href="appointment_form.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Schedule First Appointment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Dentist</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $apt): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($apt['id']) ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <i class="fas fa-user"></i>
                                                    <?= htmlspecialchars($apt['client_name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <i class="fas fa-user-md"></i>
                                                    Dr. <?= htmlspecialchars($apt['dentist_name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="datetime-info">
                                                    <div class="date"><?= date('M j, Y', strtotime($apt['start_time'])) ?></div>
                                                    <div class="time"><?= date('g:i A', strtotime($apt['start_time'])) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $duration = (strtotime($apt['end_time']) - strtotime($apt['start_time'])) / 60;
                                                echo $duration . ' min';
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= strtolower($apt['status']) ?>">
                                                    <?= ucfirst($apt['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $apt['notes'] ? htmlspecialchars(substr($apt['notes'], 0, 50)) . (strlen($apt['notes']) > 50 ? '...' : '') : '—' ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="appointment_form.php?id=<?= $apt['id'] ?>" 
                                                       class="btn btn-sm btn-secondary" title="Edit Appointment">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <?php if ($apt['status'] !== 'finished'): ?>
                                                        <a href="appointments.php?update_status=finished&id=<?= $apt['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" 
                                                           class="btn btn-sm btn-success" title="Mark as Finished"
                                                           onclick="return confirm('Mark this appointment as finished?')">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($apt['status'] !== 'cancelled'): ?>
                                                        <a href="appointments.php?update_status=cancelled&id=<?= $apt['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" 
                                                           class="btn btn-sm btn-warning" title="Cancel Appointment"
                                                           onclick="return confirm('Cancel this appointment?')">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="appointments.php?delete=<?= $apt['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" 
                                                       class="btn btn-sm btn-danger" title="Delete Appointment"
                                                       onclick="return confirm('Are you sure you want to delete this appointment? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?= $current_page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            // Display up to 5 page numbers
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            // Adjust start and end page if near the beginning or end
                            if ($current_page - 2 < 1) {
                                $end_page = min(5, $total_pages);
                            }
                            if ($current_page + 2 > $total_pages) {
                                $start_page = max(1, $total_pages - 4);
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
