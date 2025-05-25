<?php
session_start();
// 1) Only clients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../login.php');
    exit();
}

include '../db.php';
$client_id = $_SESSION['user_id'];

// Get client name
$stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $client_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Get all dentists this client has had appointments with
$dentists = [];
$stmt = mysqli_prepare($conn, "
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    JOIN appointments a ON u.id = a.dentist_id 
    WHERE a.client_id = ? AND u.role_id = 2
    ORDER BY u.name
");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $dentists[] = $row;
}
mysqli_stmt_close($stmt);

$errors = [];
$success = false;

// 3) Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dentist_id = intval($_POST['dentist_id'] ?? 0);
    $rating     = intval($_POST['rating'] ?? 0);
    $comments   = trim($_POST['comments'] ?? '');

    // validation
    if (!$dentist_id) {
        $errors[] = "Please select a dentist.";
    }
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5.";
    }

    if (empty($errors)) {
        $ins = mysqli_prepare($conn,
            "INSERT INTO feedback (client_id, dentist_id, rating, comments)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins,
            'iiis',
            $client_id,
            $dentist_id,
            $rating,
            $comments
        );
        if (mysqli_stmt_execute($ins)) {
            $success = true;
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}

// Get unread message count for badge
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $unread_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Get previous feedback
$previous_feedback = [];
$stmt = mysqli_prepare($conn, "
    SELECT f.rating, f.comments, f.created_at, u.name as dentist_name
    FROM feedback f
    JOIN users u ON f.dentist_id = u.id
    WHERE f.client_id = ?
    ORDER BY f.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $previous_feedback[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback - Dental Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="clientDashboard" class="page active">
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
                    <li>
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
                    <h2>Give Feedback</h2>
                    
                    <?php if ($success): ?>
                        <div class="success-message">
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3>Thank you for your feedback!</h3>
                            <p>Your feedback has been submitted successfully and will help us improve our services.</p>
                            <div class="success-actions">
                                <a href="client_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                                <button onclick="location.reload()" class="btn btn-secondary">Give More Feedback</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($errors): ?>
                            <div class="error-message">
                                <div class="error-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <ul>
                                    <?php foreach ($errors as $e): ?>
                                        <li><?= htmlspecialchars($e) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($dentists)): ?>
                            <div class="no-dentists">
                                <div class="no-dentists-icon">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <h3>No appointments found</h3>
                                <p>You need to have at least one appointment with a dentist before you can give feedback.</p>
                                <a href="appointments.php" class="btn btn-primary">View Appointments</a>
                            </div>
                        <?php else: ?>
                            <div class="feedback-form">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="dentist_id">Select Dentist</label>
                                        <select name="dentist_id" id="dentist_id" required>
                                            <option value="">Choose a dentist</option>
                                            <?php foreach ($dentists as $dentist): ?>
                                                <option value="<?= $dentist['id'] ?>"
                                                    <?= (isset($dentist_id) && $dentist_id == $dentist['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dentist['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="rating">Rating</label>
                                        <div class="rating-stars" id="ratingStars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="far fa-star" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" id="ratingInput" value="<?= isset($rating) ? $rating : '' ?>">
                                        <div class="rating-text" id="ratingText"></div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="comments">Your Feedback</label>
                                        <textarea name="comments" id="comments" rows="5" 
                                                placeholder="Share your experience with the dentist..."><?= isset($comments) ? htmlspecialchars($comments) : '' ?></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Submit Feedback
                                        </button>
                                        <a href="client_dashboard.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($previous_feedback)): ?>
                        <div class="previous-feedback">
                            <h3>Your Previous Feedback</h3>
                            <div class="feedback-list">
                                <?php foreach ($previous_feedback as $feedback): ?>
                                    <div class="feedback-item">
                                        <div class="feedback-header">
                                            <h4><?= htmlspecialchars($feedback['dentist_name']) ?></h4>
                                            <div class="feedback-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="<?= $i <= $feedback['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="feedback-date"><?= date('M j, Y', strtotime($feedback['created_at'])) ?></span>
                                        </div>
                                        <?php if ($feedback['comments']): ?>
                                            <div class="feedback-content">
                                                <p><?= nl2br(htmlspecialchars($feedback['comments'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // Enhanced rating stars functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('#ratingStars .fa-star');
            const ratingInput = document.getElementById('ratingInput');
            const ratingText = document.getElementById('ratingText');
            
            const ratingTexts = {
                1: 'Poor - Very unsatisfied',
                2: 'Fair - Somewhat unsatisfied', 
                3: 'Good - Neutral',
                4: 'Very Good - Satisfied',
                5: 'Excellent - Very satisfied'
            };

            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = index + 1;
                    ratingInput.value = rating;
                    ratingText.textContent = ratingTexts[rating];
                    
                    // Update star display
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                        }
                    });
                });

                star.addEventListener('mouseenter', function() {
                    const rating = index + 1;
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });

            document.getElementById('ratingStars').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value) || 0;
                stars.forEach((s, i) => {
                    if (i < currentRating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });

            // Set initial rating if exists
            const initialRating = parseInt(ratingInput.value);
            if (initialRating > 0) {
                stars.forEach((s, i) => {
                    if (i < initialRating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    }
                });
                ratingText.textContent = ratingTexts[initialRating];
            }
        });
    </script>
</body>
</html>
