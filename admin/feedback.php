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

// Handle deletion of feedback
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $feedbackId = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, 'DELETE FROM feedback WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $feedbackId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Preserve pagination and filters in redirect
    $redirect_params = array_filter([
        'page' => $_GET['page'] ?? 1,
        'search' => $_GET['search'] ?? '',
        'rating' => $_GET['rating'] ?? '',
        'dentist' => $_GET['dentist'] ?? '',
        'deleted' => 1
    ]);
    header('Location: feedback.php?' . http_build_query($redirect_params));
    exit;
}

// Pagination settings
$items_per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$dentist_filter = isset($_GET['dentist']) ? intval($_GET['dentist']) : 0;

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR d.name LIKE ? OR f.comments LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($rating_filter > 0) {
    $where_conditions[] = "f.rating = ?";
    $params[] = $rating_filter;
    $param_types .= 'i';
}

if ($dentist_filter > 0) {
    $where_conditions[] = "f.dentist_id = ?";
    $params[] = $dentist_filter;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "
SELECT COUNT(*) as total
FROM feedback f
JOIN users c ON f.client_id = c.id
JOIN users d ON f.dentist_id = d.id
$where_clause
";

if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_records);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
    $total_records = mysqli_fetch_row($count_result)[0];
}

$total_pages = ceil($total_records / $items_per_page);

// Get dentists for filter dropdown
$dentists = [];
$dentist_query = "SELECT DISTINCT u.id, u.name FROM users u 
                  JOIN feedback f ON u.id = f.dentist_id 
                  WHERE u.role_id = (SELECT id FROM roles WHERE name='dentist') 
                  ORDER BY u.name";
$dentist_result = mysqli_query($conn, $dentist_query);
while ($row = mysqli_fetch_assoc($dentist_result)) {
    $dentists[] = $row;
}

// Fetch feedback entries with filters and pagination
$sql = "
SELECT f.id, c.name AS client_name, d.name AS dentist_name,
       f.rating, f.comments, f.created_at
FROM feedback f
JOIN users c ON f.client_id = c.id
JOIN users d ON f.dentist_id = d.id
$where_clause
ORDER BY f.created_at DESC
LIMIT ? OFFSET ?
";

$pagination_params = $params;
$pagination_params[] = $items_per_page;
$pagination_params[] = $offset;
$pagination_types = $param_types . 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $pagination_types, ...$pagination_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$feedback_entries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $feedback_entries[] = $row;
}

mysqli_stmt_close($stmt);

// Get statistics
$avg_rating_query = "SELECT AVG(rating) as avg_rating FROM feedback f";
if (!empty($where_conditions)) {
    $avg_rating_query .= " JOIN users c ON f.client_id = c.id JOIN users d ON f.dentist_id = d.id " . $where_clause;
}
if (!empty($params)) {
    $avg_stmt = mysqli_prepare($conn, $avg_rating_query);
    mysqli_stmt_bind_param($avg_stmt, $param_types, ...$params);
    mysqli_stmt_execute($avg_stmt);
    mysqli_stmt_bind_result($avg_stmt, $avg_rating);
    mysqli_stmt_fetch($avg_stmt);
    mysqli_stmt_close($avg_stmt);
} else {
    $avg_result = mysqli_query($conn, $avg_rating_query);
    $avg_rating = mysqli_fetch_row($avg_result)[0];
}

// Get unread message count for badge
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Helper function to build pagination URLs
function buildPaginationUrl($page, $search, $rating, $dentist) {
    $params = array_filter([
        'page' => $page,
        'search' => $search,
        'rating' => $rating,
        'dentist' => $dentist
    ]);
    return 'feedback.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Epoka Clinic</title>
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
                    <li class="active">
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
                        <div class="page-title">
                            <h2><i class="fas fa-star"></i> Feedback Management</h2>
                            <p>Monitor and manage client feedback for all dentists</p>
                        </div>
                    </div>
                    <br>

                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Feedback entry deleted successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Enhanced Filter Section -->
                    <div class="advanced-filters">
                        <div class="filters-header">
                            <i class="fas fa-filter"></i>
                            <h3>Advanced Filters</h3>
                        </div>
                        
                        <form method="GET" class="filter-form">
                            <input type="hidden" name="page" value="1">
                            <div class="filters-grid">
                                <div class="filter-group">
                                    <label for="search">
                                        <i class="fas fa-search"></i>
                                        Search Feedback
                                    </label>
                                    <input type="text" id="search" name="search" 
                                           value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Search by client, dentist, or comments..."
                                           class="filter-input">
                                </div>
                                <div class="filter-group">
                                    <label for="rating">
                                        <i class="fas fa-star"></i>
                                        Filter by Rating
                                    </label>
                                    <select id="rating" name="rating" class="filter-select">
                                        <option value="0">All Ratings</option>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?= $i ?>" <?= $rating_filter == $i ? 'selected' : '' ?>>
                                                <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="dentist">
                                        <i class="fas fa-user-md"></i>
                                        Filter by Dentist
                                    </label>
                                    <select id="dentist" name="dentist" class="filter-select">
                                        <option value="0">All Dentists</option>
                                        <?php foreach ($dentists as $dentist): ?>
                                            <option value="<?= $dentist['id'] ?>" <?= $dentist_filter == $dentist['id'] ? 'selected' : '' ?>>
                                                Dr. <?= htmlspecialchars($dentist['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <a href="feedback.php" class="filter-btn">
                                    <i class="fas fa-times"></i> Clear All
                                </a>
                                <button type="submit" class="filter-btn primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                            </div>
                        </form>

                        <!-- Active Filters Display -->
                        <?php if ($search || $rating_filter || $dentist_filter): ?>
                            <div class="active-filters">
                                <span style="opacity: 0.8; margin-right: 8px;">Active:</span>
                                <?php if ($search): ?>
                                    <span class="filter-tag">
                                        <i class="fas fa-search"></i>
                                        "<?= htmlspecialchars($search) ?>"
                                        <a href="<?= buildPaginationUrl(1, '', $rating_filter, $dentist_filter) ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($rating_filter): ?>
                                    <span class="filter-tag">
                                        <i class="fas fa-star"></i>
                                        <?= $rating_filter ?> Stars
                                        <a href="<?= buildPaginationUrl(1, $search, 0, $dentist_filter) ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($dentist_filter): ?>
                                    <?php 
                                    $dentist_name = '';
                                    foreach ($dentists as $d) {
                                        if ($d['id'] == $dentist_filter) {
                                            $dentist_name = $d['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="filter-tag">
                                        <i class="fas fa-user-md"></i>
                                        Dr. <?= htmlspecialchars($dentist_name) ?>
                                        <a href="<?= buildPaginationUrl(1, $search, $rating_filter, 0) ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-icon feedback">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= $total_records ?></h3>
                                <p>Total Feedback</p>
                            </div>
                        </div>
                        <br>
                        <div class="stat-card">
                            <div class="stat-icon rating">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= $avg_rating ? number_format($avg_rating, 1) : '0.0' ?></h3>
                                <p>Average Rating</p>
                            </div>
                        </div>
                        <br>
                        <div class="stat-card">
                            <div class="stat-icon pages">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= $total_pages ?></h3>
                                <p>Total Pages</p>
                            </div>
                        </div>
                    </div>
                    <br>

                    <!-- Feedback List -->
                    <div class="feedback-container">
                        <?php if (empty($feedback_entries)): ?>
                            <div class="no-data">
                                <div class="no-data-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h3>No feedback found</h3>
                                <p>
                                    <?php if ($search || $rating_filter || $dentist_filter): ?>
                                        No feedback matches your current filters. Try adjusting your search criteria.
                                    <?php else: ?>
                                        No feedback has been submitted yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="feedback-grid">
                                <?php foreach ($feedback_entries as $row): ?>
                                    <div class="feedback-card">
                                        <div class="feedback-header">
                                            <div class="feedback-info">
                                                <h4><?= htmlspecialchars($row['client_name']) ?></h4>
                                                <p class="dentist-name">for Dr. <?= htmlspecialchars($row['dentist_name']) ?></p>
                                            </div>
                                            <div class="feedback-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="<?= $i <= $row['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                                <?php endfor; ?>
                                                <span class="rating-text"><?= $row['rating'] ?>/5</span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($row['comments']): ?>
                                            <div class="feedback-content">
                                                <p><?= nl2br(htmlspecialchars($row['comments'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="feedback-footer">
                                            <div class="feedback-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
                                            </div>
                                            <div class="feedback-actions">
                                                <a href="<?= buildPaginationUrl($current_page, $search, $rating_filter, $dentist_filter) ?>&delete=<?= $row['id'] ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this feedback entry?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Showing <?= ($offset + 1) ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                </div>
                                
                                <div class="pagination">
                                    <?php if ($current_page > 1): ?>
                                        <a href="<?= buildPaginationUrl(1, $search, $rating_filter, $dentist_filter) ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="<?= buildPaginationUrl($current_page - 1, $search, $rating_filter, $dentist_filter) ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled">
                                            <i class="fas fa-angle-double-left"></i>
                                        </span>
                                        <span class="disabled">
                                            <i class="fas fa-angle-left"></i>
                                        </span>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <?php if ($i == $current_page): ?>
                                            <span class="current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="<?= buildPaginationUrl($i, $search, $rating_filter, $dentist_filter) ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="<?= buildPaginationUrl($current_page + 1, $search, $rating_filter, $dentist_filter) ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="<?= buildPaginationUrl($total_pages, $search, $rating_filter, $dentist_filter) ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled">
                                            <i class="fas fa-angle-right"></i>
                                        </span>
                                        <span class="disabled">
                                            <i class="fas fa-angle-double-right"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
</body>
</html>
