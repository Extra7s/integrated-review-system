<?php
session_start();
require_once "includes/db.php";

// Admin guard
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$page = intval($_GET['page'] ?? 1);
$tab = $_GET['tab'] ?? 'reviews';
$limit = 20;
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Review Management | Admin</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h3 {
            margin-bottom: 20px;
            font-size: 18px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #ff9800;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab-btn:hover,
        .tab-btn.active {
            color: #ff9800;
            border-bottom-color: #ff9800;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .reviews-grid {
            display: grid;
            gap: 15px;
        }

        .review-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .review-card.high-risk {
            border-left-color: #d32f2f;
            background: #ffebee;
        }

        .review-card.medium-risk {
            border-left-color: #ff9800;
            background: #fff3e0;
        }

        .review-card.low-risk {
            border-left-color: #4caf50;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .reviewer-info {
            flex: 1;
        }

        .reviewer-name {
            font-weight: 600;
            color: #333;
        }

        .review-meta {
            font-size: 12px;
            color: #999;
        }

        .risk-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .risk-high {
            background: #d32f2f;
            color: white;
        }

        .risk-medium {
            background: #ff9800;
            color: white;
        }

        .risk-low {
            background: #4caf50;
            color: white;
        }

        .risk-breakdown {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
            font-size: 12px;
        }

        .risk-factor {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .review-comment {
            margin: 10px 0;
            color: #555;
            line-height: 1.5;
            font-size: 14px;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-approve {
            background: #4caf50;
            color: white;
        }

        .btn-approve:hover {
            background: #45a049;
        }

        .btn-reject {
            background: #d32f2f;
            color: white;
        }

        .btn-reject:hover {
            background: #b71c1c;
        }

        .btn-flag {
            background: #ff9800;
            color: white;
        }

        .btn-flag:hover {
            background: #e68900;
        }

        .btn-profile {
            background: #2196f3;
            color: white;
        }

        .btn-profile:hover {
            background: #1976d2;
        }

        .checkbox-col {
            width: 20px;
        }

        .checkbox-col input {
            cursor: pointer;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .bulk-actions select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .bulk-actions button {
            padding: 10px 20px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #ff9800;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            font-weight: 500;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .pagination button.active {
            background: #ff9800;
            color: white;
            border-color: #ff9800;
        }

        .pagination button:hover {
            background: #f5f5f5;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .appeal-card {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .appeal-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }

        .product-card {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-top: 3px solid #ff9800;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h3><i class="fas fa-shield-alt"></i> Review Admin</h3>
            <ul class="sidebar-menu">
                <li><a href="admin/dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="advanced_reviews_admin.php?tab=reviews" class="nav-link active"><i class="fas fa-comments"></i> All Reviews</a></li>
                <li><a href="advanced_reviews_admin.php?tab=flagged" class="nav-link"><i class="fas fa-flag"></i> Flagged</a></li>
                <li><a href="advanced_reviews_admin.php?tab=appeals" class="nav-link"><i class="fas fa-bell"></i> Appeals</a></li>
                <li><a href="advanced_reviews_admin.php?tab=products" class="nav-link"><i class="fas fa-box"></i> At-Risk Products</a></li>
                <li><a href="advanced_reviews_admin.php?tab=duplicates" class="nav-link"><i class="fas fa-clone"></i> Duplicates</a></li>
                <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-shield-alt"></i> Advanced Review Management</h1>
                <p>Monitor, analyze, and manage product reviews with AI-powered risk detection</p>
            </div>

            <div id="alert-container"></div>

            <div class="tabs">
                <button class="tab-btn active" data-tab="reviews">Reviews</button>
                <button class="tab-btn" data-tab="flagged">Flagged</button>
                <button class="tab-btn" data-tab="appeals">Appeals</button>
                <button class="tab-btn" data-tab="products">At-Risk Products</button>
                <button class="tab-btn" data-tab="duplicates">Near-Duplicates</button>
                <button class="tab-btn" data-tab="ml-training">ML Training</button>
            </div>

            <!-- Reviews Tab -->
            <div id="reviews-tab" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Reviews</div>
                        <div class="stat-value" id="total-reviews">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">High Risk</div>
                        <div class="stat-value" id="high-risk-count" style="color: #d32f2f;">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Medium Risk</div>
                        <div class="stat-value" id="medium-risk-count" style="color: #ff9800;">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Flagged</div>
                        <div class="stat-value" id="flagged-count">0</div>
                    </div>
                </div>

                <div class="filter-group">
                    <select id="risk-filter" onchange="filterReviews()">
                        <option value="">All Risk Levels</option>
                        <option value="high">High Risk (70+)</option>
                        <option value="medium">Medium Risk (40-69)</option>
                        <option value="low">Low Risk (0-39)</option>
                    </select>
                    <select id="status-filter" onchange="filterReviews()">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="flagged">Flagged</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="bulk-actions">
                    <label><input type="checkbox" id="select-all"> Select All</label>
                    <select id="bulk-action-select">
                        <option value="">Bulk Action...</option>
                        <option value="approve">Approve Selected</option>
                        <option value="reject">Reject Selected</option>
                        <option value="flag">Flag Selected</option>
                    </select>
                    <button onclick="executeBulkAction()" id="bulk-btn">Execute</button>
                </div>

                <div class="reviews-grid" id="reviews-container"></div>
                <div class="pagination" id="pagination"></div>
            </div>

            <!-- Flagged Reviews Tab -->
            <div id="flagged-tab" class="tab-content">
                <div class="reviews-grid" id="flagged-container"></div>
            </div>

            <!-- Appeals Tab -->
            <div id="appeals-tab" class="tab-content">
                <div id="appeals-container"></div>
            </div>

            <!-- At-Risk Products Tab -->
            <div id="products-tab" class="tab-content">
                <div id="products-container"></div>
            </div>

            <!-- Duplicates Tab -->
            <div id="duplicates-tab" class="tab-content">
                <div id="duplicates-container"></div>
            </div>

            <!-- ML Training Tab -->
            <div id="ml-training-tab" class="tab-content">
                <div class="stat-card" style="margin-bottom: 15px;">
                    <div class="stat-label">ML API Status</div>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                        <button class="btn btn-profile" onclick="checkMlStatus()">Check Status</button>
                        <div id="ml-status-text" style="font-weight:600; color:#333;">Not checked</div>
                    </div>
                </div>

                <div class="stat-card" style="margin-bottom: 15px;">
                    <div class="stat-label">Upload Dataset (.csv)</div>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
                        <input type="file" id="dataset-file" accept=".csv" style="padding:10px; border:1px solid #ddd; border-radius:4px; background:white;">
                        <button class="btn btn-profile" onclick="uploadDataset()">Upload</button>
                        <div id="upload-status-text" style="font-size:12px; color:#666;"></div>
                    </div>
                    <div style="font-size:12px; color:#999; margin-top:10px;">
                        Dataset must be a CSV with the columns your ML service expects (commonly: <strong>text</strong>, <strong>label</strong>).
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Train Model</div>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
                        <button class="btn btn-approve" onclick="trainModel()">Start Training</button>
                        <div id="train-status-text" style="font-size:12px; color:#666;"></div>
                    </div>
                    <div id="train-output" style="margin-top:15px; font-size:12px; background:#f5f5f5; padding:12px; border-radius:6px; white-space:pre-wrap; display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reviewer Profile Modal -->
    <div id="profile-modal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('profile-modal')">&times;</button>
            <div class="modal-header">Reviewer Profile</div>
            <div id="profile-content"></div>
        </div>
    </div>

    <!-- Appeal Response Modal -->
    <div id="appeal-modal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('appeal-modal')">&times;</button>
            <div class="modal-header">Respond to Appeal</div>
            <div class="form-group">
                <label>Review Issue</label>
                <textarea id="appeal-issue" readonly></textarea>
            </div>
            <div class="form-group">
                <label>Your Response</label>
                <textarea id="appeal-response" placeholder="Explain your decision..."></textarea>
            </div>
            <div class="form-group">
                <label>Decision</label>
                <select id="appeal-decision">
                    <option value="">Choose...</option>
                    <option value="approved">Approve Appeal (reinstate review)</option>
                    <option value="rejected">Reject Appeal (keep flagged)</option>
                </select>
            </div>
            <button class="btn btn-approve" onclick="submitAppealResponse()" style="width: 100%; padding: 12px;">Submit Response</button>
        </div>
    </div>

    <script>
        let currentPage = <?php echo $page; ?>;
        let selectedReviews = new Set();
        const initialTab = <?php echo json_encode($tab); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            setupTabs();
            // Open requested tab from query string (?tab=...)
            const initialBtn = document.querySelector(`.tab-btn[data-tab="${initialTab}"]`);
            if (initialBtn) {
                initialBtn.click();
            } else {
                loadReviews();
            }
            setupSelectAll();
        });

        function setupTabs() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    tabBtns.forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(tabName + '-tab').classList.add('active');
                    
                    loadTabData(tabName);
                });
            });
        }

        function loadTabData(tab) {
            switch(tab) {
                case 'reviews':
                    loadReviews();
                    break;
                case 'flagged':
                    loadFlaggedReviews();
                    break;
                case 'appeals':
                    loadAppeals();
                    break;
                case 'products':
                    loadFlaggedProducts();
                    break;
                case 'duplicates':
                    loadDuplicates();
                    break;
                case 'ml-training':
                    // No auto-fetch. Buttons handle status/upload/train.
                    break;
            }
        }

        function loadReviews(page = 1) {
            const offset = (page - 1) * 20;
            fetch(`actions/admin_reviews.php?action=getReviews&offset=${offset}&limit=20`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderReviews(data.reviews);
                        updateStats(data.stats);
                        renderPagination(data.pagination);
                    }
                })
                .catch(err => showAlert('Error loading reviews: ' + err.message, 'error'));
        }

        function renderReviews(reviews, containerId = 'reviews-container', showCheckboxes = true) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            if (reviews.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 30px; color: #999;">No reviews found</p>';
                return;
            }
            
            reviews.forEach(review => {
                const riskLevel = review.risk_score >= 70 ? 'high' : review.risk_score >= 40 ? 'medium' : 'low';
                const card = document.createElement('div');
                card.className = `review-card ${riskLevel}-risk`;
                card.id = `review-${review.id}`;
                
                let factorsHtml = '';
                if (review.risk_factors && review.risk_factors.length > 0) {
                    factorsHtml = '<div class="risk-breakdown">';
                    review.risk_factors.forEach(factor => {
                        factorsHtml += `<div class="risk-factor"><span>${escapeHtml(factor.description)}</span><span>+${factor.score}</span></div>`;
                    });
                    factorsHtml += '</div>';
                }
                
                const checkboxHtml = showCheckboxes
                    ? `<input type="checkbox" class="review-checkbox" value="${review.id}" onchange="updateSelectedReviews()">`
                    : '';

                card.innerHTML = `
                    ${checkboxHtml}
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-name"><a href="javascript:viewReviewerProfile(${review.user_id})" style="color: #333; text-decoration: none; cursor: pointer;">${escapeHtml(review.user_name)}</a></div>
                            <div class="review-meta">${escapeHtml(review.artwork_title)} • ${new Date(review.created_at).toLocaleDateString()}</div>
                        </div>
                        <span class="risk-badge risk-${riskLevel}">${review.risk_score}/100</span>
                    </div>
                    <div style="color: #ff9800; margin-bottom: 10px;">${'★'.repeat(review.rating)}${'☆'.repeat(5-review.rating)}</div>
                    <div class="review-comment">${escapeHtml(review.comment.substring(0, 200))}...</div>
                    ${factorsHtml}
                    <div class="review-actions">
                        <button class="btn btn-approve" onclick="updateReviewStatus(${review.id}, 'approved')">Approve</button>
                        <button class="btn btn-reject" onclick="updateReviewStatus(${review.id}, 'rejected')">Reject</button>
                        <button class="btn btn-flag" onclick="updateReviewStatus(${review.id}, 'flagged')">Flag</button>
                        <button class="btn btn-profile" onclick="viewReviewerProfile(${review.user_id})">Reviewer Profile</button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function updateStats(stats) {
            document.getElementById('total-reviews').textContent = stats.total || 0;
            document.getElementById('high-risk-count').textContent = stats.high_risk || 0;
            document.getElementById('medium-risk-count').textContent = stats.medium_risk || 0;
            document.getElementById('flagged-count').textContent = stats.flagged || 0;
        }

        function renderPagination(pagination) {
            const container = document.getElementById('pagination');
            container.innerHTML = '';
            
            if (pagination.pages <= 1) return;
            
            for (let i = 1; i <= pagination.pages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = i === pagination.page ? 'active' : '';
                btn.onclick = () => loadReviews(i);
                container.appendChild(btn);
            }
        }

        function loadFlaggedReviews() {
            fetch('actions/admin_reviews.php?action=getFlaggedReviews')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderReviews(data.reviews, 'flagged-container', false);
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function loadAppeals() {
            fetch('actions/admin_reviews.php?action=getAppeals')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('appeals-container');
                        container.innerHTML = '';
                        
                        if (data.appeals.length === 0) {
                            container.innerHTML = '<p style="text-align: center; padding: 30px;">No pending appeals</p>';
                            return;
                        }
                        
                        data.appeals.forEach(appeal => {
                            const card = document.createElement('div');
                            card.className = 'appeal-card';
                            card.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div>
                                        <div style="font-weight: 600; color: #333;">${escapeHtml(appeal.user_name)}</div>
                                        <div style="font-size: 12px; color: #999;">${escapeHtml(appeal.artwork_title)}</div>
                                    </div>
                                    <span class="appeal-status">${appeal.appeal_status.toUpperCase()}</span>
                                </div>
                                <div style="margin: 10px 0;">
                                    <strong>Review:</strong> "${escapeHtml(appeal.review_comment.substring(0, 100))}..."
                                </div>
                                <div style="margin: 10px 0;">
                                    <strong>Appeal Reason:</strong> <br>${escapeHtml(appeal.appeal_reason)}
                                </div>
                                <button class="btn btn-profile" onclick="respondToAppeal(${appeal.id}, '${escapeHtml(appeal.appeal_reason).replace(/'/g, "\\'")}')">Review Appeal</button>
                            `;
                            container.appendChild(card);
                        });
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function loadFlaggedProducts() {
            fetch('actions/admin_reviews.php?action=getFlaggedProducts')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('products-container');
                        container.innerHTML = '';
                        
                        data.products.forEach(product => {
                            const card = document.createElement('div');
                            card.className = 'product-card';
                            card.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 16px; color: #333;">${escapeHtml(product.title)}</div>
                                        <div style="font-size: 12px; color: #999;">Price: $${parseFloat(product.price).toFixed(2)}</div>
                                    </div>
                                    <span class="risk-badge risk-high">${product.flagged_reviews} Flagged</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                                    <div>
                                        <div style="font-size: 12px; color: #999;">Avg Risk Score</div>
                                        <div style="font-size: 18px; font-weight: 700; color: #ff9800;">${Math.round(product.avg_risk_score)}/100</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #999;">Total Reviews</div>
                                        <div style="font-size: 18px; font-weight: 700;">${product.review_count}</div>
                                    </div>
                                </div>
                                ${product.burst_data ? `<div style="margin-top: 10px; padding: 10px; background: #ffebee; border-radius: 4px; font-size: 12px;">⚠️ ${product.burst_data.burst_count} suspicious review bursts detected</div>` : ''}
                            `;
                            container.appendChild(card);
                        });
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function loadDuplicates() {
            fetch('actions/admin_reviews.php?action=getDuplicates')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('duplicates-container');
                        container.innerHTML = '';
                        
                        data.similarities.forEach(sim => {
                            const card = document.createElement('div');
                            card.className = 'review-card medium-risk';
                            card.innerHTML = `
                                <div style="margin-bottom: 15px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <div style="font-weight: 600;">Review Pair Match</div>
                                        <span class="risk-badge risk-medium">${(sim.similarity_score * 100).toFixed(0)}% Similar</span>
                                    </div>
                                    <div style="font-size: 12px; color: #999; margin-bottom: 10px;">Detected: ${new Date(sim.detected_at).toLocaleDateString()}</div>
                                </div>
                                <div style="background: #fff3e0; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                    <div><strong>Review 1:</strong> "${escapeHtml(sim.comment_1.substring(0, 100))}..."</div>
                                    <div style="margin-top: 10px;"><strong>Review 2:</strong> "${escapeHtml(sim.comment_2.substring(0, 100))}..."</div>
                                </div>
                                <div class="review-actions">
                                    <button class="btn btn-reject" onclick="updateReviewStatus(${sim.review_id_1}, 'rejected')">Reject Review 1</button>
                                    <button class="btn btn-reject" onclick="updateReviewStatus(${sim.review_id_2}, 'rejected')">Reject Review 2</button>
                                </div>
                            `;
                            container.appendChild(card);
                        });
                        
                        if (data.similarities.length === 0) {
                            container.innerHTML = '<p style="text-align: center; padding: 30px; color: #999;">No duplicate reviews detected</p>';
                        }
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function updateReviewStatus(reviewId, status) {
            fetch('actions/admin_reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=updateReviewStatus&review_id=${reviewId}&status=${status}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert(`Review ${status} successfully`, 'success');
                        loadReviews();
                    } else {
                        showAlert('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function viewReviewerProfile(userId) {
            fetch(`actions/admin_reviews.php?action=getReviewerProfile&user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderProoflerProfile(data);
                        document.getElementById('profile-modal').classList.add('active');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function renderProoflerProfile(data) {
            const profile = data.user;
            const risk = data.risk_profile;
            
            let reviewsHtml = '';
            data.reviews.forEach(review => {
                reviewsHtml += `<div style="padding: 10px; border-bottom: 1px solid #eee; font-size: 12px;">
                    <div><strong>${escapeHtml(review.artwork_title)}</strong> - ${review.rating}★</div>
                    <div style="color: #999; margin-top: 3px;">${new Date(review.created_at).toLocaleDateString()}</div>
                </div>`;
            });
            
            const content = document.getElementById('profile-content');
            content.innerHTML = `
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: #999;">Reviewer Name</div>
                            <div style="font-weight: 600; color: #333;">${escapeHtml(profile.name)}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #999;">Email</div>
                            <div style="font-weight: 600; color: #333;">${escapeHtml(profile.email)}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #999;">Account Age</div>
                            <div style="font-weight: 600; color: #333;">${profile.account_age_days} days</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #999;">Risk Score</div>
                            <div style="font-weight: 600; color: #d32f2f;">${risk.risk_score}/100</div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 20px; font-weight: 700;">${risk.review_count}</div>
                            <div style="font-size: 12px; color: #999;">Total Reviews</div>
                        </div>
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 20px; font-weight: 700;">${risk.flagged_review_count}</div>
                            <div style="font-size: 12px; color: #999;">Flagged</div>
                        </div>
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 20px; font-weight: 700;">${data.purchase_info.total_purchased}</div>
                            <div style="font-size: 12px; color: #999;">Purchased</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 600; margin-bottom: 10px;">Recent Reviews</div>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px;">
                        ${reviewsHtml || '<p style="padding: 20px; text-align: center; color: #999;">No reviews</p>'}
                    </div>
                </div>
            `;
        }

        function respondToAppeal(appealId, reason) {
            document.getElementById('appeal-issue').value = reason;
            document.getElementById('appeal-response').value = '';
            document.getElementById('appeal-decision').value = '';
            document.getElementById('appeal-modal').dataset.appealId = appealId;
            document.getElementById('appeal-modal').classList.add('active');
        }

        function submitAppealResponse() {
            const appealId = document.getElementById('appeal-modal').dataset.appealId;
            const response = document.getElementById('appeal-response').value;
            const decision = document.getElementById('appeal-decision').value;
            
            if (!response || !decision) {
                showAlert('Please fill in all fields', 'error');
                return;
            }
            
            fetch('actions/admin_reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=respondToAppeal&appeal_id=${appealId}&response=${encodeURIComponent(response)}&decision=${decision}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Appeal response submitted', 'success');
                        closeModal('appeal-modal');
                        loadAppeals();
                    } else {
                        showAlert('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function setupSelectAll() {
            document.getElementById('select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.review-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedReviews();
            });
        }

        function updateSelectedReviews() {
            selectedReviews.clear();
            document.querySelectorAll('.review-checkbox:checked').forEach(cb => {
                selectedReviews.add(parseInt(cb.value));
            });
        }

        function executeBulkAction() {
            const action = document.getElementById('bulk-action-select').value;
            if (!action || selectedReviews.size === 0) {
                showAlert('Please select an action and reviews', 'error');
                return;
            }
            
            fetch('actions/admin_reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'bulkAction',
                    review_ids: Array.from(selectedReviews),
                    bulk_action: action
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        selectedReviews.clear();
                        loadReviews();
                    } else {
                        showAlert('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function filterReviews() {
            // This can be expanded with more filtering logic
            loadReviews(1);
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 4000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function checkMlStatus() {
            const el = document.getElementById('ml-status-text');
            el.textContent = 'Checking...';
            try {
                const r = await fetch('actions/ml_training.php?action=status');
                const data = await r.json();
                if (data.success) {
                    el.textContent = 'Online';
                    el.style.color = '#2e7d32';
                } else {
                    el.textContent = 'Offline';
                    el.style.color = '#c62828';
                }
            } catch (e) {
                el.textContent = 'Offline';
                el.style.color = '#c62828';
            }
        }

        async function uploadDataset() {
            const fileInput = document.getElementById('dataset-file');
            const statusEl = document.getElementById('upload-status-text');
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                showAlert('Please choose a .csv file first', 'error');
                return;
            }
            statusEl.textContent = 'Uploading...';
            try {
                const fd = new FormData();
                fd.append('action', 'uploadDataset');
                fd.append('dataset', file);
                const r = await fetch('actions/ml_training.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.success) {
                    statusEl.textContent = 'Uploaded successfully';
                    showAlert('Dataset uploaded to ML service', 'success');
                } else {
                    statusEl.textContent = 'Upload failed';
                    showAlert(data.message || 'Upload failed', 'error');
                    console.error(data);
                }
            } catch (e) {
                statusEl.textContent = 'Upload failed';
                showAlert('Upload failed: ' + e.message, 'error');
            }
        }

        async function trainModel() {
            const statusEl = document.getElementById('train-status-text');
            const outEl = document.getElementById('train-output');
            statusEl.textContent = 'Training... (this may take a few minutes)';
            outEl.style.display = 'none';
            outEl.textContent = '';
            try {
                const fd = new FormData();
                fd.append('action', 'train');
                const r = await fetch('actions/ml_training.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.success) {
                    statusEl.textContent = 'Training complete';
                    showAlert('Training complete', 'success');
                } else {
                    statusEl.textContent = 'Training failed';
                    showAlert(data.message || 'Training failed', 'error');
                }
                outEl.textContent = JSON.stringify(data, null, 2);
                outEl.style.display = 'block';
            } catch (e) {
                statusEl.textContent = 'Training failed';
                showAlert('Training failed: ' + e.message, 'error');
            }
        }
    </script>
</body>
</html>
