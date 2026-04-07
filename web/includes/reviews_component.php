<?php
/**
 * Review Display Component
 * Include this in product.php to display reviews section
 */

if (!isset($id)) {
    return; // Exit if artwork id is not set
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login status
$is_logged_in = isset($_SESSION['user']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
?>

<section class="reviews-section">
    <div class="reviews-container">
        <h2><i class="fas fa-star"></i> Customer Reviews</h2>
        
        <!-- Review Statistics -->
        <div class="review-stats" id="review-stats">
            <div class="stat">
                <span class="average-rating">
                    <span class="rating-number">0</span>
                    <span class="stars"></span>
                </span>
                <span class="total-reviews">Based on <strong>0</strong> reviews</span>
            </div>
        </div>

        <!-- Authenticity Summary Bar -->
        <div class="authenticity-summary" id="authenticity-summary-bar">
            <div class="summary-item">
                <span class="summary-label">Overall Authenticity:</span>
                <span class="summary-value authentic">--%</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Verified Reviews:</span>
                <span class="summary-value verified">--%</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Suspicious Reviews:</span>
                <span class="summary-value suspicious">--%</span>
            </div>
        </div>

        <!-- Review Tabs -->
        <div class="review-tabs">
            <button class="tab-btn active" data-tab="reviews-list">Reviews</button>
            <button class="tab-btn" data-tab="write-review">Write a Review</button>
        </div>

        <!-- Reviews List Tab -->
        <div id="reviews-list" class="tab-content active">
            <div id="reviews-spinner" class="spinner hidden"><i class="fas fa-spinner fa-spin"></i> Loading reviews...</div>
            <div id="reviews-container" class="reviews-list">
                <!-- Reviews will be loaded here -->
            </div>
            <div id="pagination" class="pagination hidden"></div>
        </div>

        <!-- Write Review Tab -->
        <div id="write-review" class="tab-content">
            <div id="login-prompt" class="hidden">
                <p>Please <a href="login.php">log in</a> to write a review.</p>
            </div>

            <div id="not-purchased-prompt" class="hidden">
                <p>You must <a href="shop.php">purchase this artwork</a> before you can write a review.</p>
            </div>

            <div id="already-reviewed-prompt" class="hidden">
                <p>You have already reviewed this artwork. Thank you for your feedback!</p>
            </div>

            <form id="review-form" class="review-form hidden">
                <div class="form-group">
                    <label for="rating">Rating *</label>
                    <div class="rating-input">
                        <div class="stars-input">
                            <input type="radio" name="rating" value="5" id="star5">
                            <label for="star5"><i class="fas fa-star"></i></label>

                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4"><i class="fas fa-star"></i></label>

                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3"><i class="fas fa-star"></i></label>

                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2"><i class="fas fa-star"></i></label>

                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                        <span id="rating-text" class="rating-text"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="comment">Your Review *</label>
                    <textarea 
                        id="comment" 
                        name="comment" 
                        placeholder="Share your experience with this artwork (10-5000 characters)"
                        required
                        minlength="10"
                        maxlength="5000"
                    ></textarea>
                    <small id="char-count">0 / 5000 characters</small>
                </div>

                <div id="detection-status" class="detection-status hidden">
                    <p><i class="fas fa-shield-alt"></i> <strong>Authenticity Check:</strong> <span id="detection-message"></span></p>
                </div>

                <button type="submit" class="btn btn-primary">Submit Review</button>
                <p class="form-note">Your review will be analyzed for authenticity and posted after approval.</p>
            </form>
        </div>
    </div>
</section>

<style>
.reviews-section {
    margin-top: 40px;
    padding: 30px 20px;
    background: #f9f9f9;
    border-radius: 10px;
}

.reviews-container {
    max-width: 800px;
    margin: 0 auto;
}

.reviews-section h2 {
    font-size: 24px;
    margin-bottom: 20px;
    color: #333;
}

.review-stats {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.authenticity-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #fff9e6 0%, #f5f5f5 100%);
    border-radius: 8px;
    border-left: 5px solid #ff9800;
    border-top: 1px solid rgba(255, 152, 0, 0.2);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 15px;
    background: white;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.summary-label {
    font-size: 12px;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 28px;
    font-weight: 800;
    text-align: left;
}

.summary-value.authentic {
    color: #4caf50;
}

.summary-value.verified {
    color: #1976d2;
}

.summary-value.suspicious {
    color: #f44336;
}

.stat {
    display: flex;
    align-items: center;
    gap: 30px;
}

.average-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 32px;
    font-weight: bold;
    color: #ff9800;
}

.stars {
    display: inline-flex;
    gap: 3px;
}

.stars::before {
    content: attr(data-stars);
}

.total-reviews {
    font-size: 14px;
    color: #666;
}

.review-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.tab-btn {
    padding: 12px 20px;
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

.tab-btn:hover {
    color: #ff9800;
}

.tab-btn.active {
    color: #ff9800;
    border-bottom-color: #ff9800;
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.review-item {
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: box-shadow 0.3s ease;
    border-left: 4px solid #ddd;
}

.review-item.authentic-review {
    border-left-color: #4caf50;
}

.review-item.suspicious-review {
    border-left-color: #f44336;
    background: #fafafa;
}

.review-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.reviewer-info {
    flex: 1;
}

.reviewer-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.review-meta {
    font-size: 12px;
    color: #999;
}

.review-rating {
    display: flex;
    gap: 3px;
    margin-bottom: 10px;
}

.review-rating .star {
    color: #ff9800;
    font-size: 14px;
}

.review-rating .star.empty {
    color: #ddd;
}

.review-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    background: #e8f5e9;
    color: #2e7d32;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.review-badge.verified-purchase {
    background: #e3f2fd;
    color: #1976d2;
}

.review-badge.authentic {
    background: #e8f5e9;
    color: #2e7d32;
}

.review-badge.fake {
    background: #ffebee;
    color: #c62828;
}

.review-badge.offline {
    background: #fff3e0;
    color: #e65100;
}

.review-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.authenticity-score {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    border: 4px solid;
    margin: 15px auto;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.7);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    gap: 3px;
    overflow: hidden;
    box-sizing: border-box;
}

.authenticity-score:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.authenticity-score .score-value {
    font-size: 32px;
    font-weight: 900;
    line-height: 0.9;
    letter-spacing: -1px;
    margin: 0;
    padding: 0;
    color: inherit;
}

.authenticity-score .score-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 700;
    margin: 0;
    padding: 0;
    color: inherit;
}

.review-text {
    color: #555;
    line-height: 1.6;
    margin: 15px 0;
    padding: 10px 0;
}

.review-footer {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.helpful-btn {
    padding: 5px 10px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    color: #666;
    transition: all 0.3s ease;
}

.helpful-btn.selected {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e68900;
    box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.12);
}

.helpful-btn.unhelpful.selected {
    background: #ffebee;
    border-color: #dc3545;
    color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.12);
}

.helpful-btn:hover {
    background: #f5f5f5;
    border-color: #999;
}

.helpful-btn.unhelpful:hover {
    border-color: #dc3545;
    color: #dc3545;
}

.helpful-count {
    font-size: 12px;
    color: #999;
}

.spinner {
    text-align: center;
    padding: 30px;
    color: #999;
}

.spinner.hidden {
    display: none;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

.pagination.hidden {
    display: none;
}

.pagination button {
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.pagination button:hover {
    background: #f5f5f5;
}

.pagination button.active {
    background: #ff9800;
    color: white;
    border-color: #ff9800;
}

.review-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.review-form.hidden {
    display: none;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.stars-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 10px;
    width: fit-content;
}

.stars-input input {
    display: none;
}

.stars-input label {
    margin: 0;
    cursor: pointer;
    font-size: 28px;
    color: #ddd;
    transition: color 0.2s ease;
}

.stars-input input:checked ~ label,
.stars-input label:hover,
.stars-input label:hover ~ label {
    color: #ff9800;
}

.stars-input input:checked ~ label ~ .stars-input label {
    color: #ddd;
}

.rating-text {
    margin-left: 10px;
    font-weight: 500;
    color: #ff9800;
    min-width: 50px;
}

.rating-input {
    display: flex;
    align-items: center;
}

textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
}

textarea:focus {
    outline: none;
    border-color: #ff9800;
    box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.1);
}

#char-count {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #999;
    text-align: right;
}

.detection-status {
    padding: 12px 15px;
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    border-radius: 4px;
    margin: 15px 0;
    font-size: 14px;
    color: #1565c0;
}

.detection-status.hidden {
    display: none;
}

.detection-status i {
    margin-right: 5px;
}

.btn-primary {
    background: #ff9800;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s ease;
}

.btn-primary:hover {
    background: #e68900;
}

.form-note {
    margin-top: 10px;
    font-size: 12px;
    color: #999;
}

#login-prompt {
    padding: 30px;
    background: white;
    border-radius: 8px;
    text-align: center;
    color: #666;
}

#login-prompt.hidden {
    display: none;
}

#not-purchased-prompt {
    padding: 30px;
    background: white;
    border-radius: 8px;
    text-align: center;
    color: #666;
}

#not-purchased-prompt.hidden {
    display: none;
}

#already-reviewed-prompt {
    padding: 30px;
    background: white;
    border-radius: 8px;
    text-align: center;
    color: #666;
}

#already-reviewed-prompt.hidden {
    display: none;
}

#login-prompt a,
#not-purchased-prompt a,
#already-reviewed-prompt a {
    color: #ff9800;
    text-decoration: none;
    font-weight: 500;
}

#login-prompt a:hover,
#not-purchased-prompt a:hover,
#already-reviewed-prompt a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .review-stats {
        flex-direction: column;
        align-items: flex-start;
    }

    .stat {
        width: 100%;
        flex-direction: column;
        gap: 10px;
    }

    .review-tabs {
        flex-wrap: wrap;
    }

    .star-input label {
        font-size: 22px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const artworkId = <?php echo json_encode($id); ?>;
    const isLoggedIn = <?php echo json_encode($is_logged_in ?? false); ?>;

    initReviewsSection();

    function initReviewsSection() {
        setupTabs();
        loadReviews();
        setupReviewForm();
        updateLoginPrompt();
    }

    function setupTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;

                tabButtons.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(tabName).classList.add('active');
            });
        });
    }

    function loadReviews(page = 1) {
        const spinner = document.getElementById('reviews-spinner');
        const container = document.getElementById('reviews-container');

        spinner.classList.remove('hidden');
        container.innerHTML = '';

        fetch(`actions/reviews.php?action=get&artwork_id=${artworkId}&page=${page}`)
            .then(r => r.json())
            .then(data => {
                spinner.classList.add('hidden');

                if (data.success && data.reviews.length > 0) {
                    updateStats(data.stats);
                    data.reviews.forEach(review => {
                        container.appendChild(createReviewElement(review));
                    });
                    setupPagination(data.pagination);
                } else {
                    container.innerHTML = '<p style="text-align: center; padding: 30px; color: #999;">No reviews yet. Be the first to review!</p>';
                    updateStats(data.stats);
                }
            })
            .catch(err => {
                spinner.classList.add('hidden');
                container.innerHTML = '<p style="color: #d32f2f; padding: 20px;">Error loading reviews</p>';
                console.error(err);
            });
    }

    function updateStats(stats) {
        const starsHtml = generateStars(Math.round(stats.average_rating));
        document.querySelector('.rating-number').textContent = stats.average_rating;
        document.querySelector('.stars').innerHTML = starsHtml;
        document.querySelector('.total-reviews strong').textContent = stats.total_reviews;
        
        // Update authenticity summary bar (use average authenticity score if available)
        const summaryBar = document.getElementById('authenticity-summary-bar');
        if (summaryBar && stats) {
            let overallAuthenticity = 0;
            let suspiciousPercentage = 0;
            let verifiedPercentage = 0;

            if (typeof stats.average_authenticity !== 'undefined' && stats.average_authenticity !== null) {
                overallAuthenticity = Math.round(stats.average_authenticity * 100);
            } else if (stats.total_reviews > 0) {
                overallAuthenticity = Math.round((stats.authentic_reviews / stats.total_reviews) * 100);
            }

            if (stats.total_reviews > 0) {
                suspiciousPercentage = Math.round((stats.suspicious_reviews / stats.total_reviews) * 100);
                verifiedPercentage = Math.round((stats.verified_purchase_reviews / stats.total_reviews) * 100);
            }

            summaryBar.innerHTML = `
                <div class="summary-item">
                    <span class="summary-label">Overall Authenticity:</span>
                    <span class="summary-value authentic">${overallAuthenticity}%</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Verified Reviews:</span>
                    <span class="summary-value verified">${verifiedPercentage}%</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Suspicious Reviews:</span>
                    <span class="summary-value suspicious">${suspiciousPercentage}%</span>
                </div>
            `;
        }
    }

    function createReviewElement(review) {
        const div = document.createElement('div');
        div.className = 'review-item';
        
        // Add class based on authenticity
        if (review.is_authentic) {
            div.classList.add('authentic-review');
        }
        if (review.is_fake) {
            div.classList.add('suspicious-review');
        }

        let badges = '';
        
        // Verified Purchase Badge
        if (review.verified_purchase) {
            badges += `<span class="review-badge verified-purchase">✓ Verified Purchase</span>`;
        }
        
        // Authenticity Badge
        if (review.is_authentic) {
            badges += `<span class="review-badge authentic">✓ Verified Review</span>`;
        } else if (review.is_fake) {
            badges += `<span class="review-badge fake">⚠️ Suspicious Review</span>`;
        }

        const stars = generateStars(review.rating);
        const date = new Date(review.created_at).toLocaleDateString();

        // Authenticity Score indicator
        let authenticityScoreHtml = '';
        if (review.authenticity_score !== null && review.authenticity_score !== undefined) {
            const score = Math.round(review.authenticity_score * 100);
            const scoreColor = score >= 70 ? '#4caf50' : score >= 40 ? '#ff9800' : '#f44336';
            authenticityScoreHtml = `
                <div class="authenticity-score" style="border-color: ${scoreColor}; color: ${scoreColor};">
                    <span class="score-value">${score}%</span>
                    <span class="score-label">Authenticity</span>
                </div>
            `;
        }

        // helpful buttons only for logged-in users
        let helpfulHtml = '';
        if (isLoggedIn) {
            const userVote = (review.user_vote === 0 || review.user_vote === 1) ? review.user_vote : null;
            const helpfulSelected = userVote === 1 ? 'selected' : '';
            const unhelpfulSelected = userVote === 0 ? 'selected' : '';
            helpfulHtml = `
                <button class="helpful-btn ${helpfulSelected}" data-review-id="${review.id}" data-vote="1" onclick="markHelpful(${review.id}, this)">
                    👍 Helpful (<span class="helpful-count">${review.helpful_count}</span>)
                </button>
                <button class="helpful-btn unhelpful ${unhelpfulSelected}" data-review-id="${review.id}" data-vote="0" onclick="markUnhelpful(${review.id}, this)">
                    👎 Not Helpful (<span class="unhelpful-count">${review.unhelpful_count || 0}</span>)
                </button>
            `;
        }

        div.innerHTML = `
            <div class="review-header">
                <div class="reviewer-info">
                    <div class="reviewer-name">${review.user_name}</div>
                    <div class="review-meta">${date}</div>
                </div>
                <div class="review-badges">
                    ${badges}
                </div>
            </div>
            ${authenticityScoreHtml}
            <div class="review-rating">${stars}</div>
            <div class="review-text">${review.comment}</div>
            <div class="review-footer">
                ${helpfulHtml}
            </div>
        `;

        return div;
    }

    function generateStars(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            html += `<span class="star${i <= rating ? '' : ' empty'}">★</span>`;
        }
        return html;
    }

    function setupPagination(pagination) {
        const container = document.getElementById('pagination');
        if (pagination.pages <= 1) {
            container.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        for (let i = 1; i <= pagination.pages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = i === pagination.page ? 'active' : '';
            btn.onclick = () => loadReviews(i);
            container.appendChild(btn);
        }
    }

    function setupReviewForm() {
        const form = document.getElementById('review-form');
        const ratingInputs = document.querySelectorAll('input[name="rating"]');
        const comment = document.getElementById('comment');
        const charCount = document.getElementById('char-count');

        ratingInputs.forEach(input => {
            input.addEventListener('change', function() {
                const texts = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                document.getElementById('rating-text').textContent = texts[this.value - 1];
            });
        });

        comment.addEventListener('input', function() {
            charCount.textContent = this.value.length + ' / 5000 characters';
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Client-side validation
            const rating = document.querySelector('input[name="rating"]:checked');
            const comment = document.getElementById('comment').value.trim();

            if (!rating) {
                alert('Please select a rating');
                return;
            }

            if (!comment || comment.length < 10) {
                alert('Review must be at least 10 characters long');
                return;
            }

            const formData = new FormData(form);
            formData.append('artwork_id', artworkId);
            formData.append('action', 'submit');

            fetch('actions/reviews.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json().then(data => ({ status: r.status, ok: r.ok, data: data })))
                .then(response => {
                    if (response.data.success) {
                        alert('Review submitted! Thank you for your feedback.');
                        form.reset();
                        document.getElementById('rating-text').textContent = '';
                        charCount.textContent = '0 / 5000 characters';
                        
                        // Switch to reviews tab and reload
                        document.querySelector('[data-tab="reviews-list"]').click();
                        setTimeout(() => loadReviews(), 500);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                        console.error('Review submission error:', response.data);
                    }
                })
                .catch(err => {
                    alert('Error submitting review: ' + err.message + '. Check console for details.');
                    console.error('Review submission error:', err);
                });
        });
    }

    function updateLoginPrompt() {
        const form = document.getElementById('review-form');
        const loginPrompt = document.getElementById('login-prompt');
        const notPurchasedPrompt = document.getElementById('not-purchased-prompt');
        const alreadyReviewedPrompt = document.getElementById('already-reviewed-prompt');

        // Hide all prompts by default
        loginPrompt.classList.add('hidden');
        notPurchasedPrompt.classList.add('hidden');
        alreadyReviewedPrompt.classList.add('hidden');
        form.classList.add('hidden');

        if (!isLoggedIn) {
            loginPrompt.classList.remove('hidden');
            return;
        }

        // User is logged in, check if they can review
        fetch(`actions/reviews.php?action=checkCanReview&artwork_id=${artworkId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    form.classList.add('hidden');
                    return;
                }

                if (data.canReview) {
                    form.classList.remove('hidden');
                } else {
                    if (data.reason === 'not_purchased') {
                        notPurchasedPrompt.classList.remove('hidden');
                    } else if (data.reason === 'already_reviewed') {
                        alreadyReviewedPrompt.classList.remove('hidden');
                    }
                }
            })
            .catch(err => {
                console.error('Error checking review eligibility:', err);
                form.classList.remove('hidden');
            });
    }

    // Helpful/Unhelpful button handlers
    function setVoteButtonsState(container, currentVote) {
        if (!container) return;
        const helpfulBtn = container.querySelector('button[data-vote="1"]');
        const unhelpfulBtn = container.querySelector('button[data-vote="0"]');
        if (helpfulBtn) helpfulBtn.classList.toggle('selected', currentVote === 1);
        if (unhelpfulBtn) unhelpfulBtn.classList.toggle('selected', currentVote === 0);
    }

    function setVoteButtonsDisabled(container, disabled) {
        if (!container) return;
        container.querySelectorAll('button').forEach(b => b.disabled = disabled);
    }

    function updateVoteCounts(container, helpfulCount, unhelpfulCount) {
        if (!container) return;
        const helpfulSpan = container.querySelector('.helpful-count');
        const unhelpfulSpan = container.querySelector('.unhelpful-count');
        if (helpfulSpan && typeof helpfulCount !== 'undefined') helpfulSpan.textContent = String(helpfulCount);
        if (unhelpfulSpan && typeof unhelpfulCount !== 'undefined') unhelpfulSpan.textContent = String(unhelpfulCount);
    }

    window.markHelpful = function(reviewId, btnEl) {
        const formData = new FormData();
        formData.append('action', 'helpful');
        formData.append('review_id', reviewId);
        formData.append('is_helpful', 1);

        const container = btnEl ? btnEl.parentNode : null;
        setVoteButtonsDisabled(container, true);

        fetch('actions/reviews.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateVoteCounts(container, data.helpful_count, data.unhelpful_count);
                    const currentVote = (data.current_vote === 0 || data.current_vote === 1) ? data.current_vote : null;
                    setVoteButtonsState(container, currentVote);
                } else {
                    alert('Error marking helpful: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error marking helpful:', err);
                alert('Error marking helpful. Please try again.');
            })
            .finally(() => {
                setVoteButtonsDisabled(container, false);
            });
    };

    window.markUnhelpful = function(reviewId, btnEl) {
        const formData = new FormData();
        formData.append('action', 'helpful');
        formData.append('review_id', reviewId);
        formData.append('is_helpful', 0);

        const container = btnEl ? btnEl.parentNode : null;
        setVoteButtonsDisabled(container, true);

        fetch('actions/reviews.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateVoteCounts(container, data.helpful_count, data.unhelpful_count);
                    const currentVote = (data.current_vote === 0 || data.current_vote === 1) ? data.current_vote : null;
                    setVoteButtonsState(container, currentVote);
                } else {
                    alert('Error marking unhelpful: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error marking unhelpful:', err);
                alert('Error marking unhelpful. Please try again.');
            })
            .finally(() => {
                setVoteButtonsDisabled(container, false);
            });
    };
});
</script>
