<?php
session_start();
require_once "includes/db.php";

// User guard - must be logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$user_name = $_SESSION['user']['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews & Appeals | ArtfyCanvas</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container-custom {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 40px 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            text-align: center;
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 15px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            margin-bottom: -2px;
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

        .review-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .review-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .review-title {
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }

        .review-meta {
            font-size: 13px;
            color: #999;
            margin-top: 5px;
        }

        .stars {
            color: #ff9800;
            font-size: 14px;
            margin: 10px 0;
        }

        .review-comment {
            color: #555;
            line-height: 1.6;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-flagged {
            background: #ffebee;
            color: #c62828;
        }

        .status-rejected {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .risk-info {
            background: #fff3e0;
            padding: 15px;
            border-left: 4px solid #ff9800;
            margin: 15px 0;
            border-radius: 4px;
        }

        .risk-info h4 {
            margin: 0 0 10px 0;
            color: #d32f2f;
            font-size: 14px;
        }

        .risk-item {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 5px;
            color: #555;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-danger {
            background: #d32f2f;
            color: white;
        }

        .btn-danger:hover {
            background: #b71c1c;
        }

        .btn-primary {
            background: #ff9800;
            color: white;
        }

        .btn-primary:hover {
            background: #e68900;
        }

        .appeal-card {
            background: white;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .appeal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .appeal-title {
            font-weight: 600;
            font-size: 16px;
            color: #1976d2;
        }

        .appeal-status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
        }

        .appeal-pending {
            background: #fff3cd;
            color: #856404;
        }

        .appeal-approved {
            background: #d4edda;
            color: #155724;
        }

        .appeal-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .appeal-section {
            margin: 15px 0;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .appeal-section:last-child {
            border-bottom: none;
        }

        .appeal-label {
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .appeal-text {
            color: #555;
            margin-top: 5px;
            line-height: 1.5;
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
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 120px;
        }

        .char-count {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f9f9f9;
            border-radius: 8px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 25px 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .review-card {
                padding: 15px;
            }

            .tabs {
                margin-bottom: 20px;
            }

            .tab-btn {
                padding: 12px 15px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="page-header">
        <h1><i class="fas fa-star"></i> My Reviews & Appeals</h1>
        <p>Manage your reviews, track their status, and appeal if flagged</p>
    </div>

    <div class="container-custom">
        <div id="alert-container"></div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="reviews">My Reviews</button>
            <button class="tab-btn" data-tab="appeals">My Appeals</button>
            <button class="tab-btn" data-tab="rules">Appeal Guidelines</button>
        </div>

        <!-- My Reviews Tab -->
        <div id="reviews-tab" class="tab-content active">
            <div id="reviews-container"></div>
        </div>

        <!-- My Appeals Tab -->
        <div id="appeals-tab" class="tab-content">
            <div id="appeals-container"></div>
        </div>

        <!-- Guidelines Tab -->
        <div id="rules-tab" class="tab-content">
            <div style="background: white; padding: 30px; border-radius: 8px;">
                <h2>Appeal Guidelines & Flagging Reasons</h2>
                
                <div style="margin-top: 30px;">
                    <h3 style="color: #ff9800; margin-bottom: 15px;">Why Reviews Get Flagged</h3>
                    
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">📝 Text Similarity</h4>
                            <p style="color: #666;">Your review text is very similar to other reviews (copy-pasted content).</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">🆕 New Account</h4>
                            <p style="color: #666;">Your account is very new and posting reviews frequently.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">⚡ Review Burst</h4>
                            <p style="color: #666;">Multiple suspicious reviews were posted for a product in a short timeframe.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">🔄 User Burst</h4>
                            <p style="color: #666;">You posted multiple reviews in a very short time window.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">📊 Suspicious Pattern</h4>
                            <p style="color: #666;">Your review pattern (e.g., only 5-star or 1-star ratings) appears unusual.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #333; margin-bottom: 8px;">🤖 AI Detection</h4>
                            <p style="color: #666;">Our machine learning model detected suspicious linguistic markers.</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <h3 style="color: #ff9800; margin-bottom: 15px;">How to Appeal a Flagged Review</h3>
                    
                    <ol style="color: #666; line-height: 1.8;">
                        <li><strong>Go to your flagged review</strong> in the "My Reviews" tab</li>
                        <li><strong>Click "Appeal"</strong> to explain why you believe the flag is incorrect</li>
                        <li><strong>Be specific</strong> - explain your genuine experience with the product</li>
                        <li><strong>Wait for review</strong> - our team typically responds within 24-48 hours</li>
                        <li><strong>We'll reinstate</strong> genuine reviews and maintain integrity</li>
                    </ol>
                </div>

                <div style="margin-top: 30px; background: #e3f2fd; padding: 20px; border-radius: 5px; border-left: 4px solid #2196f3;">
                    <p style="margin: 0; color: #1565c0;"><strong>💡 Tip:</strong> Write detailed, specific reviews that describe your genuine experience. Avoid generic language and copy-pasting. Authentic reviews help the community make better purchasing decisions!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Appeal Modal -->
    <div id="appeal-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Appeal Flagged Review</span>
                <button class="modal-close" onclick="closeModal('appeal-modal')">&times;</button>
            </div>
            
            <div style="background: #fff3cd; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 13px; border-left: 4px solid #ff9800;">
                <strong>⚠️ Why was this flagged?</strong><br>
                <span id="flag-reason"></span>
            </div>

            <form id="appeal-form">
                <div class="form-group">
                    <label for="appeal-text">Explain Why You Believe This Review Should Be Approved</label>
                    <textarea 
                        id="appeal-text" 
                        placeholder="Tell us why you believe this is a genuine review. Be specific about your experience with the product..."
                        required
                        minlength="20"
                        maxlength="1000"
                    ></textarea>
                    <div class="char-count"><span id="char-count">0</span>/1000 characters</div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Submit Appeal</button>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        const userId = <?php echo $user_id; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            setupTabs();
            loadUserReviews();
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
                    
                    if (tabName === 'appeals') {
                        loadUserAppeals();
                    }
                });
            });
        }

        function loadUserReviews() {
            fetch(`actions/user_reviews.php?action=getMyReviews&user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderUserReviews(data.reviews);
                    } else {
                        showAlert('Error: ' + data.message, 'error');
                    }
                })
                .catch(err => showAlert('Error loading reviews: ' + err.message, 'error'));
        }

        function renderUserReviews(reviews) {
            const container = document.getElementById('reviews-container');
            container.innerHTML = '';

            if (reviews.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <h3>No Reviews Yet</h3>
                        <p>You haven't posted any reviews yet. <a href="shop.php" style="color: #ff9800;">Browse and purchase artwork</a> to write reviews!</p>
                    </div>
                `;
                return;
            }

            reviews.forEach(review => {
                const card = document.createElement('div');
                card.className = 'review-card';

                const statusClass = `status-${review.status}`;
                const starsHtml = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);

                let riskSection = '';
                if (review.status === 'flagged' && review.risk_factors) {
                    riskSection = '<div class="risk-info">';
                    riskSection += '<h4>⚠️ Flagged Reason:</h4>';
                    review.risk_factors.forEach(factor => {
                        riskSection += `<div class="risk-item"><span>${escapeHtml(factor.description)}</span><span>+${factor.score}</span></div>`;
                    });
                    riskSection += '</div>';
                }

                card.innerHTML = `
                    <div class="review-header">
                        <div>
                            <div class="review-title">${escapeHtml(review.artwork_title)}</div>
                            <div class="review-meta">${new Date(review.created_at).toLocaleDateString()}</div>
                        </div>
                        <span class="status-badge ${statusClass}">${review.status.toUpperCase()}</span>
                    </div>
                    
                    <div class="stars">${starsHtml}</div>
                    
                    <div class="review-comment">
                        "${escapeHtml(review.comment)}"
                    </div>

                    ${riskSection}

                    <div class="review-actions">
                        ${review.status === 'flagged' ? `<button class="btn btn-primary" onclick="openAppealModal(${review.id}, '${review.flagged_reason ? escapeHtml(review.flagged_reason).replace(/'/g, "\\'") : 'Review flagged'}')">Appeal Review</button>` : ''}
                        <button class="btn btn-secondary" onclick="viewReviewDetail(${review.id})">View Detail</button>
                    </div>
                `;

                container.appendChild(card);
            });
        }

        function loadUserAppeals() {
            fetch(`actions/user_reviews.php?action=getMyAppeals&user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderUserAppeals(data.appeals);
                    }
                })
                .catch(err => showAlert('Error loading appeals: ' + err.message, 'error'));
        }

        function renderUserAppeals(appeals) {
            const container = document.getElementById('appeals-container');
            container.innerHTML = '';

            if (appeals.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔔</div>
                        <h3>No Appeals Yet</h3>
                        <p>You haven't submitted any appeals. If a review gets flagged, you can appeal it here.</p>
                    </div>
                `;
                return;
            }

            appeals.forEach(appeal => {
                const statusClass = `appeal-${appeal.appeal_status}`;
                const card = document.createElement('div');
                card.className = 'appeal-card';

                let adminResponse = '';
                if (appeal.admin_response) {
                    adminResponse = `
                        <div class="appeal-section">
                            <div class="appeal-label">Admin Response</div>
                            <div class="appeal-text">${escapeHtml(appeal.admin_response)}</div>
                        </div>
                    `;
                }

                card.innerHTML = `
                    <div class="appeal-header">
                        <div class="appeal-title">${escapeHtml(appeal.artwork_title)}</div>
                        <span class="appeal-status-badge ${statusClass}">${appeal.appeal_status.toUpperCase()}</span>
                    </div>

                    <div class="appeal-section">
                        <div class="appeal-label">Your Appeal</div>
                        <div class="appeal-text">${escapeHtml(appeal.appeal_reason)}</div>
                    </div>

                    ${adminResponse}

                    <div class="appeal-section">
                        <div class="appeal-label">Submitted</div>
                        <div class="appeal-text">${new Date(appeal.created_at).toLocaleString()}</div>
                    </div>
                `;

                container.appendChild(card);
            });
        }

        function openAppealModal(reviewId, flagReason) {
            document.getElementById('flag-reason').textContent = flagReason;
            document.getElementById('appeal-modal').dataset.reviewId = reviewId;
            document.getElementById('appeal-text').value = '';
            document.getElementById('char-count').textContent = '0';
            document.getElementById('appeal-modal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        document.getElementById('appeal-text')?.addEventListener('input', function() {
            document.getElementById('char-count').textContent = this.value.length;
        });

        document.getElementById('appeal-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reviewId = document.getElementById('appeal-modal').dataset.reviewId;
            const appealText = document.getElementById('appeal-text').value;

            fetch('actions/user_reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=submitAppeal&review_id=${reviewId}&appeal_reason=${encodeURIComponent(appealText)}&user_id=${userId}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Appeal submitted successfully. Admins will review within 24-48 hours.', 'success');
                        closeModal('appeal-modal');
                        loadUserReviews();
                    } else {
                        showAlert('Error: ' + data.message, 'error');
                    }
                })
                .catch(err => showAlert('Error submitting appeal: ' + err.message, 'error'));
        });

        function viewReviewDetail(reviewId) {
            // This can be expanded to show full review details modal
            alert('Review #' + reviewId + ' - Full detail view coming soon!');
        }

        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
