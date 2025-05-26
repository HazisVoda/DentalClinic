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
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Handle deletion with cascading removal of related data
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);

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
        
        // Redirect with success message
        header('Location: users.php?deleted=1' . ($search ? '&search=' . urlencode($search) : '') . ($role_filter ? '&role=' . urlencode($role_filter) : ''));
        exit;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        die('Error deleting user and related data: ' . $e->getMessage());
    }
}

// Pagination settings
$items_per_page = 15;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Build query with search and filter
$sql = "
    SELECT u.id, u.name, u.email, r.name AS role,
           CASE WHEN u.dentist_id IS NOT NULL THEN d.name ELSE '—' END AS dentist_name,
           u.created_at
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN users d ON u.dentist_id = d.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($role_filter) {
    $sql .= " AND r.name = ?";
    $params[] = $role_filter;
    $types .= 's';
}

// Get total count for pagination
$count_sql = str_replace("SELECT u.id, u.name, u.email, r.name AS role, CASE WHEN u.dentist_id IS NOT NULL THEN d.name ELSE '—' END AS dentist_name, u.created_at FROM", "SELECT COUNT(*) as total FROM", $sql);
$count_sql = str_replace(" ORDER BY r.name ASC, u.name ASC", "", $count_sql);

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

$sql .= " ORDER BY r.name ASC, u.name ASC LIMIT ? OFFSET ?";

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
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}
mysqli_stmt_close($stmt);

// Get roles for filter dropdown
$roles = [];
$role_result = mysqli_query($conn, "SELECT DISTINCT name FROM roles ORDER BY name");
while ($row = mysqli_fetch_assoc($role_result)) {
    $roles[] = $row['name'];
}

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
    <title>Manage Users - Dental Clinic</title>
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
                    <li class="active">
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
                        <h2>Manage Users</h2>
                        <div class="header-actions">
                            <a href="view_requests.php" class="btn btn-secondary">
                                <i class="fas fa-user-clock"></i> View Requests
                            </a>
                            <a href="user_form.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add New User
                            </a>
                        </div>
                    </div>

                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>User Deleted Successfully!</h4>
                                <p>The user and all related data have been removed from the system.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Enhanced Search and Filter -->
                    <div class="filters-container">
                        <form method="GET" class="filters-form">
                            <div class="filters-grid">
                                <div class="filter-group">
                                    <label for="search">
                                        <i class="fas fa-search"></i>
                                        Search Users
                                    </label>
                                    <input type="text" 
                                           id="search" 
                                           name="search" 
                                           placeholder="Search by name or email..." 
                                           value="<?= htmlspecialchars($search) ?>"
                                           class="search-input">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="role">
                                        <i class="fas fa-user-tag"></i>
                                        Filter by Role
                                    </label>
                                    <select name="role" id="role" class="filter-select">
                                        <option value="">All Roles</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= htmlspecialchars($role) ?>" 
                                                    <?= $role_filter === $role ? 'selected' : '' ?>>
                                                <?= ucfirst(htmlspecialchars($role)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <?php if ($search || $role_filter): ?>
                                        <a href="users.php" class="btn btn-outline">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($search || $role_filter): ?>
                                <div class="active-filters">
                                    <span class="filter-label">Active Filters:</span>
                                    <?php if ($search): ?>
                                        <span class="filter-tag">
                                            <i class="fas fa-search"></i>
                                            Search: "<?= htmlspecialchars($search) ?>"
                                            <a href="users.php?<?= $role_filter ? 'role=' . urlencode($role_filter) : '' ?>" class="remove-filter">×</a>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($role_filter): ?>
                                        <span class="filter-tag">
                                            <i class="fas fa-user-tag"></i>
                                            Role: <?= htmlspecialchars($role_filter) ?>
                                            <a href="users.php?<?= $search ? 'search=' . urlencode($search) : '' ?>" class="remove-filter">×</a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Results Info -->
                    <div class="results-info">
                        <p>
                            <?php if ($search || $role_filter): ?>
                                Found <?= count($users) ?> user(s)
                                <?php if ($search): ?>
                                    matching "<?= htmlspecialchars($search) ?>"
                                <?php endif; ?>
                                <?php if ($role_filter): ?>
                                    with role "<?= htmlspecialchars($role_filter) ?>"
                                <?php endif; ?>
                            <?php else: ?>
                                Showing all <?= count($users) ?> users
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Users Table -->
                    <?php if (empty($users)): ?>
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>No users found</h3>
                            <p>
                                <?php if ($search || $role_filter): ?>
                                    Try adjusting your search criteria or filters.
                                <?php else: ?>
                                    No users have been created yet.
                                <?php endif; ?>
                            </p>
                            <?php if (!$search && !$role_filter): ?>
                                <a href="user_form.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Add First User
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Assigned Dentist</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['id']) ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <i class="fas fa-user"></i>
                                                    <?= htmlspecialchars($user['name']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <span class="role-badge <?= strtolower($user['role']) ?>">
                                                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($user['dentist_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="user_form.php?id=<?= $user['id'] ?>" 
                                                       class="btn btn-sm btn-secondary" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="users.php?delete=<?= $user['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Delete User"
                                                       onclick="return confirm('Are you sure you want to delete this user and all related data? This action cannot be undone.')">
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
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= ($offset + 1) ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                        </div>
                        
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="users.php?page=1&<?= http_build_query(['search' => $search, 'role' => $role_filter]) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="users.php?page=<?= $current_page - 1 ?>&<?= http_build_query(['search' => $search, 'role' => $role_filter]) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                                <span class="disabled"><i class="fas fa-angle-left"></i></span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="users.php?page=<?= $i ?>&<?= http_build_query(['search' => $search, 'role' => $role_filter]) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="users.php?page=<?= $current_page + 1 ?>&<?= http_build_query(['search' => $search, 'role' => $role_filter]) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="users.php?page=<?= $total_pages ?>&<?= http_build_query(['search' => $search, 'role' => $role_filter]) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="disabled"><i class="fas fa-angle-right"></i></span>
                                <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <style>
.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.filters-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.filters-grid {
    display: grid;
    grid-template-columns: 1fr 200px auto;
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label i {
    color: #6b7280;
    width: 16px;
}

.search-input, .filter-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
}

.search-input:focus, .filter-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.active-filters {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 14px;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    border: 1px solid #bfdbfe;
}

.filter-tag i {
    font-size: 12px;
}

.remove-filter {
    color: #6b7280;
    text-decoration: none;
    margin-left: 4px;
    font-weight: bold;
    font-size: 16px;
    line-height: 1;
}

.remove-filter:hover {
    color: #dc2626;
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .filter-actions {
        justify-content: stretch;
    }
    
    .filter-actions .btn {
        flex: 1;
    }
    
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .header-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f9fafb;
    border-radius: 12px;
    margin-top: 20px;
    border: 1px solid #e5e7eb;
}

.pagination-info {
    font-size: 14px;
    color: #6b7280;
}

.pagination {
    display: flex;
    gap: 8px;
}

.pagination a, .pagination span {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #374151;
    background: white;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 32px;
}

.pagination a:hover {
    background: #eff2f7;
}

.pagination a.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination span.current {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination span.disabled {
    color: #9ca3af;
    border-color: #e5e7eb;
    pointer-events: none;
}

.pagination i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }
}
</style>
</body>
</html>
