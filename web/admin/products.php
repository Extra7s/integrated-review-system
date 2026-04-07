<?php
include 'admin_guard.php';

// Handle form submissions
$message = '';
$message_type = '';
$editing_product = null;

// Check if we're editing a product
if(isset($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT a.*, c.name as category_name FROM artworks a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editing_product = $stmt->get_result()->fetch_assoc();
}

if(isset($_POST['add'])){
    $title = trim($_POST['title']);
    $artist = trim($_POST['artist']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $image = trim($_POST['image']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $medium = trim($_POST['medium']);
    $dimensions = trim($_POST['dimensions']);
    $year_created = !empty($_POST['year_created']) ? intval($_POST['year_created']) : null;
    $availability = $_POST['availability'];

    if(empty($title) || empty($artist) || $price <= 0 || empty($description) || empty($image)) {
        $message = "Please fill in all required fields correctly.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO artworks(title, artist, price, description, image, category_id, medium, dimensions, year_created, availability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssissss", $title, $artist, $price, $description, $image, $category_id, $medium, $dimensions, $year_created, $availability);
        if($stmt->execute()) {
            $message = "Product added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding product: " . $conn->error;
            $message_type = "error";
        }
    }
}

if(isset($_POST['edit'])){
    $edit_id = intval($_POST['edit_id']);
    $title = trim($_POST['title']);
    $artist = trim($_POST['artist']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $image = trim($_POST['image']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $medium = trim($_POST['medium']);
    $dimensions = trim($_POST['dimensions']);
    $year_created = !empty($_POST['year_created']) ? intval($_POST['year_created']) : null;
    $availability = $_POST['availability'];

    if(empty($title) || empty($artist) || $price <= 0 || empty($description) || empty($image)) {
        $message = "Please fill in all required fields correctly.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE artworks SET title=?, artist=?, price=?, description=?, image=?, category_id=?, medium=?, dimensions=?, year_created=?, availability=? WHERE id=?");
        $stmt->bind_param("ssdssissssi", $title, $artist, $price, $description, $image, $category_id, $medium, $dimensions, $year_created, $availability, $edit_id);
        if($stmt->execute()) {
            $message = "Product updated successfully!";
            $message_type = "success";
            $editing_product = null; // Clear editing state
        } else {
            $message = "Error updating product: " . $conn->error;
            $message_type = "error";
        }
    }
}

if(isset($_GET['del'])){
    $del_id = intval($_GET['del']);
    $stmt = $conn->prepare("DELETE FROM artworks WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    if($stmt->execute()) {
        $message = "Product deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting product.";
        $message_type = "error";
    }
}

// Get all products with category information
$stmt = $conn->prepare("SELECT a.*, c.name as category_name FROM artworks a LEFT JOIN categories c ON a.category_id = c.id ORDER BY a.created_at DESC");
$stmt->execute();
$products_result = $stmt->get_result();

// Get all categories for the dropdown
$categories_result = $conn->query("SELECT MIN(id) as id, name FROM categories GROUP BY name ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Scroll to form when editing
        <?php if($editing_product): ?>
        window.onload = function() {
            document.querySelector('.admin-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };
        <?php endif; ?>
    </script>
</head>
<body>

<header class="navbar">
    <div class="logo">
        <i class="fas fa-palette"></i>
        ArtfyCanvas - Admin
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php" class="active"><i class="fas fa-box"></i> Products</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<section class="admin-section">
    <h2><i class="fas fa-box"></i> Manage Products</h2>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="admin-content">
        <h3><i class="fas fa-<?= $editing_product ? 'edit' : 'plus' ?>"></i> <?= $editing_product ? 'Edit Product' : 'Add New Product' ?></h3>
        <form method="POST" class="admin-form">
            <div class="form-group">
                <input name="title" placeholder="Title *" value="<?= $editing_product ? htmlspecialchars($editing_product['title']) : '' ?>" required>
                <input name="artist" placeholder="Artist *" value="<?= $editing_product ? htmlspecialchars($editing_product['artist']) : '' ?>" required>
                <input name="price" placeholder="Price *" type="number" step="0.01" min="0.01" value="<?= $editing_product ? $editing_product['price'] : '' ?>" required>
                <input name="image" placeholder="Image filename (e.g., artwork.jpg) *" value="<?= $editing_product ? htmlspecialchars($editing_product['image']) : '' ?>" required>
            </div>

            <div class="form-group">
                <select name="category_id">
                    <option value="">Select Category (Optional)</option>
                    <?php
                    // Reset categories result pointer
                    $categories_result->data_seek(0);
                    while($cat = $categories_result->fetch_assoc()):
                    ?>
                        <option value="<?= $cat['id'] ?>" <?= $editing_product && $editing_product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input name="medium" placeholder="Medium (e.g., Oil on Canvas)" value="<?= $editing_product ? htmlspecialchars($editing_product['medium']) : '' ?>">
                <input name="dimensions" placeholder="Dimensions (e.g., 24x30 inches)" value="<?= $editing_product ? htmlspecialchars($editing_product['dimensions']) : '' ?>">
                <input name="year_created" placeholder="Year Created" type="number" min="1000" max="<?= date('Y') ?>" value="<?= $editing_product ? $editing_product['year_created'] : '' ?>">

                <select name="availability">
                    <option value="available" <?= $editing_product && $editing_product['availability'] == 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="sold" <?= $editing_product && $editing_product['availability'] == 'sold' ? 'selected' : '' ?>>Sold</option>
                    <option value="reserved" <?= $editing_product && $editing_product['availability'] == 'reserved' ? 'selected' : '' ?>>Reserved</option>
                </select>
            </div>

            <div class="form-group full-width">
                <textarea name="description" placeholder="Description *" required><?= $editing_product ? htmlspecialchars($editing_product['description']) : '' ?></textarea>
            </div>

            <div class="form-actions">
                <?php if($editing_product): ?>
                    <input type="hidden" name="edit_id" value="<?= $editing_product['id'] ?>">
                    <button name="edit" type="submit"><i class="fas fa-save"></i> Update Product</button>
                    <a href="products.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                <?php else: ?>
                    <button name="add" type="submit"><i class="fas fa-plus"></i> Add Product</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-content">
        <h3><i class="fas fa-list"></i> Current Products (<?= $products_result->num_rows ?>)</h3>
        <?php if($products_result->num_rows > 0): ?>
            <?php while($product = $products_result->fetch_assoc()): ?>
            <div class="admin-item">
                <div class="product-preview">
                    <?php
                    $imagePath = "../assets/images/" . $product['image'];
                    if (!file_exists($imagePath) || empty($product['image'])) {
                        $imagePath = "../assets/images/default.jpg";
                    }
                    ?>
                    <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($product['title']) ?>" class="product-thumbnail" onerror="this.src='../assets/images/default.jpg'">
                </div>

                <div class="product-info">
                    <h3>
                        <?= htmlspecialchars($product['title']) ?> by <?= htmlspecialchars($product['artist']) ?>
                        <span class="product-price">($<?= number_format($product['price'], 2) ?>)</span>
                    </h3>

                    <div class="product-details">
                        <?php if($product['image']): ?>
                            <div><strong>Image:</strong> <?= htmlspecialchars($product['image']) ?></div>
                        <?php endif; ?>

                        <?php if($product['category_name']): ?>
                            <div><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></div>
                        <?php endif; ?>

                        <?php if($product['medium']): ?>
                            <div><strong>Medium:</strong> <?= htmlspecialchars($product['medium']) ?></div>
                        <?php endif; ?>

                        <?php if($product['dimensions']): ?>
                            <div><strong>Dimensions:</strong> <?= htmlspecialchars($product['dimensions']) ?></div>
                        <?php endif; ?>

                        <?php if($product['year_created']): ?>
                            <div><strong>Year:</strong> <?= $product['year_created'] ?></div>
                        <?php endif; ?>

                        <div><strong>Status:</strong>
                            <span class="status-<?= $product['availability'] ?>">
                                <?= ucfirst($product['availability']) ?>
                            </span>
                        </div>

                        <div><strong>Added:</strong> <?= date('M j, Y', strtotime($product['created_at'])) ?></div>
                    </div>

                    <?php if($product['description']): ?>
                        <p><strong>Description:</strong> <?= htmlspecialchars(substr($product['description'], 0, 150)) ?><?= strlen($product['description']) > 150 ? '...' : '' ?></p>
                    <?php endif; ?>

                    <div class="admin-actions">
                        <a href="?edit=<?= $product['id'] ?>" class="edit-link">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="?del=<?= $product['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found. Add your first product above!</p>
        <?php endif; ?>
    </div>
</section>

</body>
</html>
