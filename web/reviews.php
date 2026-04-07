<?php
session_start();
require_once "includes/db.php";

// Admin guard
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle approval/disapproval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $review_id = intval($_POST['review_id'] ?? 0);

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    } elseif ($action === 'disapprove') {
        $stmt = $conn->prepare("UPDATE reviews SET approved = 0 WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    }

    header('Location: reviews.php');
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = "1=1";
if ($filter === 'pending') $where = "approved = 0";
elseif ($filter === 'fake') $where = "is_fake = 1";
elseif ($filter === 'approved') $where = "approved = 1";

// Get total count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reviews WHERE $where");
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

// Get reviews
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.artwork_id,
        r.user_id,
        r.rating,
        r.comment,
        r.is_fake,
        r.fake_confidence,
        r.approved,
        r.detection_algorithm,
        r.created_at,
        a.title as artwork_title,
        u.name as user_name,
        u.email as user_email
    FROM reviews r
    JOIN artworks a ON r.artwork_id = a.id
    JOIN users u ON r.user_id = u.id
    WHERE $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management | ArtfyCanvas Admin</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background: #f5f5f5;
        }

        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }

        .admin-content {
            flex: 1;
            padding: 30px;
        }

        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-nav li {
            margin-bottom: 10px;
        }

        .admin-nav a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            background: #ff9800;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .admin-header h1 {
            margin: 0;
            color: #333;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            position: relative;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
        }

        .filter-tab:hover {
            color: #ff9800;
        }

        .filter-tab.active {
            color: #ff9800;
            border-bottom-color: #ff9800;
        }

        .reviews-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9f9f9;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-fake {
            background: #ffebee;
            color: #c62828;
        }

        .badge-real {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-pending {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .stars {
            color: #ff9800;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            border-color: #999;
            background: #f5f5f5;
        }

        .action-btn.danger {
            border-color: #d32f2f;
            color: #d32f2f;
        }

        .action-btn.danger:hover {
            background: #ffebee;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .pagination a:hover {
            background: #f5f5f5;
        }

        .pagination .active {
            background: #ff9800;
            color: white;
            border-color: #ff9800;
        }

        .comment-cell {
            max-width: 300px;
            color: #666;
            word-wrap: break-word;
        }

        .confidence {
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <h2><i class="fas fa-palette"></i> ArtfyCanvas</h2>
            <ul class="admin-nav">
                <li><a href="admin/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="admin/products.php"><i class="fas fa-images"></i> Products</a></li>
                <li><a href="admin/orders.php"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                <li><a href="admin/messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="reviews.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h1><i class="fas fa-star"></i> Review Management</h1>
                    <p style="color: #666; margin-top: 5px;">Total Reviews: <?php echo $total; ?></p>
                </div>
                <a href="index.php" class="btn-add-to-cart" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Shop
                </a>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab<?php echo $filter === 'all' ? ' active' : ''; ?>" onclick="location.href='?filter=all'">
                    All (<?php echo $total; ?>)
                </button>
                <?php
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE approved = 0");
                $stmt->execute();
                $pending_count = $stmt->get_result()->fetch_assoc()['count'];
                ?>
                <button class="filter-tab<?php echo $filter === 'pending' ? ' active' : ''; ?>" onclick="location.href='?filter=pending'">
                    Pending (<?php echo $pending_count; ?>)
                </button>
                <?php
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE is_fake = 1");
                $stmt->execute();
                $fake_count = $stmt->get_result()->fetch_assoc()['count'];
                ?>
                <button class="filter-tab<?php echo $filter === 'fake' ? ' active' : ''; ?>" onclick="location.href='?filter=fake'">
                    Suspicious (<?php echo $fake_count; ?>)
                </button>
                <?php
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE approved = 1");
                $stmt->execute();
                $approved_count = $stmt->get_result()->fetch_assoc()['count'];
                ?>
                <button class="filter-tab<?php echo $filter === 'approved' ? ' active' : ''; ?>" onclick="location.href='?filter=approved'">
                    Approved (<?php echo $approved_count; ?>)
                </button>
            </div>

            <!-- Reviews Table -->
            <div class="reviews-table">
                <?php if (count($reviews) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Artwork</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Authenticity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($review['user_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($review['user_email']); ?></small>
                                    </td>
                                    <td>
                                        <a href="product.php?id=<?php echo $review['artwork_id']; ?>" title="View Product">
                                            <?php echo htmlspecialchars(substr($review['artwork_title'], 0, 30)); ?>...
                                        </a>
                                    </td>
                                    <td>
                                        <span class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : ' fa-star-empty'; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </td>
                                    <td class="comment-cell">
                                        "<?php echo htmlspecialchars(substr($review['comment'], 0, 100)); ?>..."
                                    </td>
                                    <td>
                                        <?php
                                        $is_fake = $review['is_fake'];
                                        $confidence = number_format($review['fake_confidence'] * 100, 1);
                                        $algorithm = htmlspecialchars($review['detection_algorithm']);
                                        ?>
                                        <div class="badge<?php echo $is_fake ? ' badge-fake' : ' badge-real'; ?>">
                                            <?php echo $is_fake ? 'Suspicious' : 'Genuine'; ?> 
                                            <span class="confidence"><?php echo $confidence; ?>%</span>
                                        </div>
                                        <small><?php echo $algorithm; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge<?php echo $review['approved'] ? ' badge-approved' : ' badge-pending'; ?>">
                                            <?php echo $review['approved'] ? '✓ Approved' : '⏳ Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <?php if (!$review['approved']): ?>
                                                <button type="submit" name="action" value="approve" class="action-btn" title="Approve">
                                                    ✓ Approve
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="disapprove" class="action-btn" title="Disapprove">
                                                    ✗ Disapprove
                                                </button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="action-btn danger" title="Delete" onclick="return confirm('Delete this review?');">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <p>No reviews found for this filter.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (ceil($total / $limit) > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= ceil($total / $limit); $i++): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
