<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='admin'){
    header("Location: ../login.php"); exit;
}
include '../includes/db.php';

$products = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM artworks"))[0] ?? 0;
$orders = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM orders"))[0] ?? 0;
$users = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users WHERE role='user'"))[0] ?? 0;
$messages = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM messages"))[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | ArtfyCanvas</title>
    <link rel="stylesheet" href="../assets/css/style_organized.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="navbar">
    <div class="logo">ArtfyCanvas - Admin</div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Products</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
        <a href="../advanced_reviews_admin.php?tab=ml-training"><i class="fas fa-brain"></i> ML Training</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<section class="admin-section">
    <h2>Admin Dashboard</h2>

    <div class="admin-stats">
        <div class="stat-card">
            <h3>Products</h3>
            <p><?= $products ?></p>
        </div>
        <div class="stat-card">
            <h3>Orders</h3>
            <p><?= $orders ?></p>
        </div>
        <div class="stat-card">
            <h3>Users</h3>
            <p><?= $users ?></p>
        </div>
        <div class="stat-card">
            <h3>Messages</h3>
            <p><?= $messages ?></p>
        </div>
    </div>

    <div class="admin-content">
        <canvas id="stats" height="100"></canvas>
    </div>
</section>

<script>
new Chart(document.getElementById('stats'),{
 type:'bar',
 data:{
  labels:['Products','Orders','Users','Messages'],
  datasets:[{
    label: 'Count',
    data:[<?= $products ?>,<?= $orders ?>,<?= $users ?>,<?= $messages ?>],
    backgroundColor:['#f4b400','#ff6b35','#2c3e50','#6c757d'],
    borderColor:['#e6a800','#ff5722','#1a252f','#5a6268'],
    borderWidth: 2,
    borderRadius: 6,
    borderSkipped: false
  }]
 },
 options: {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
   legend: {
    display: false
   }
  },
  scales: {
   y: {
    beginAtZero: true,
    grid: {
     color: '#e1e5e9'
    },
    ticks: {
     color: '#2c3e50',
     font: {
      weight: '600'
     }
    }
   },
   x: {
    grid: {
     display: false
    },
    ticks: {
     color: '#2c3e50',
     font: {
      weight: '600'
     }
    }
   }
  }
 }
});
</script>

</body>
</html>
