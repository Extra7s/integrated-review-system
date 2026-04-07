-- Create database if not exists
CREATE DATABASE IF NOT EXISTS artstore;
USE artstore;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table for contact form
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Artworks table
CREATE TABLE IF NOT EXISTS artworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    description TEXT,
    category_id INT DEFAULT NULL,
    medium VARCHAR(100) DEFAULT NULL,
    dimensions VARCHAR(100) DEFAULT NULL,
    year_created YEAR DEFAULT NULL,
    availability ENUM('available', 'sold', 'reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    khalti_token VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    artwork_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (artwork_id) REFERENCES artworks(id)
);

-- Cart table for persistence
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    artwork_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (artwork_id) REFERENCES artworks(id),
    UNIQUE KEY unique_cart (user_id, artwork_id)
);

-- Review tokens (one-time use per order)
-- NOTE: created before `reviews` to avoid FK ordering issues.
-- `used_for_review_id` FK is added after `reviews` table exists.
CREATE TABLE IF NOT EXISTS review_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    artwork_id INT NOT NULL,
    is_used TINYINT DEFAULT 0,
    used_for_review_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_artwork (artwork_id),
    INDEX idx_used (is_used)
);

-- Reviews table for product reviews with fake detection
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artwork_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NOT NULL,
    is_fake TINYINT DEFAULT 0,
    fake_confidence DECIMAL(3,2) DEFAULT 0.00,
    fake_detection_checked TINYINT DEFAULT 0,
    detection_algorithm VARCHAR(50) DEFAULT 'ensemble',
    risk_score INT DEFAULT 0,
    risk_factors JSON,
    status ENUM('approved', 'rejected', 'flagged', 'appealed', 'hidden') DEFAULT 'approved',
    flagged_reason VARCHAR(255),
    duplicate_hash VARCHAR(255),
    approved TINYINT DEFAULT 1,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    verified_purchase TINYINT DEFAULT 0,
    is_authentic TINYINT DEFAULT 0,
    authenticity_score DECIMAL(3,2) DEFAULT 0.00,
    review_token_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (review_token_id) REFERENCES review_tokens(id) ON DELETE SET NULL,
    UNIQUE KEY unique_review (user_id, artwork_id),
    INDEX idx_status (status),
    INDEX idx_risk_score (risk_score),
    INDEX idx_artwork_created (artwork_id, created_at),
    INDEX idx_user_id (user_id),
    INDEX idx_verified (verified_purchase),
    INDEX idx_authentic (is_authentic)
);

-- Review similarities (duplicate/near-duplicate detection)
CREATE TABLE IF NOT EXISTS review_similarities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id_1 INT NOT NULL,
    review_id_2 INT NOT NULL,
    similarity_score DECIMAL(3,2) NOT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id_1) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (review_id_2) REFERENCES reviews(id) ON DELETE CASCADE,
    INDEX idx_similarity (similarity_score),
    INDEX idx_review_1 (review_id_1),
    INDEX idx_review_2 (review_id_2)
);

-- Product review bursts (spike detection)
CREATE TABLE IF NOT EXISTS product_review_bursts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artwork_id INT NOT NULL,
    review_count INT NOT NULL,
    time_window_minutes INT DEFAULT 60,
    average_rating DECIMAL(2,1),
    burst_score INT,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_suspicious TINYINT DEFAULT 0,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    INDEX idx_artwork_suspicious (artwork_id, is_suspicious),
    INDEX idx_detected (detected_at)
);

-- User risk scores and profiles
CREATE TABLE IF NOT EXISTS user_risk_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    risk_score INT DEFAULT 0,
    review_count INT DEFAULT 0,
    flagged_review_count INT DEFAULT 0,
    average_rating DECIMAL(2,1),
    account_age_days INT,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_risk_score (risk_score)
);

-- Review appeals workflow
CREATE TABLE IF NOT EXISTS review_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    appeal_reason TEXT NOT NULL,
    appeal_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_response TEXT,
    admin_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id),
    INDEX idx_status (appeal_status),
    INDEX idx_created (created_at)
);

-- Helpful votes tracking
CREATE TABLE IF NOT EXISTS helpful_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    is_helpful TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voter_review (user_id, review_id),
    INDEX idx_review (review_id),
    INDEX idx_user (user_id)
);

-- Admin training data for ML retraining
CREATE TABLE IF NOT EXISTS admin_training_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    admin_id INT NOT NULL,
    original_ml_prediction TINYINT,
    original_ml_confidence DECIMAL(3,2),
    admin_decision ENUM('approved', 'rejected', 'flagged') NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    INDEX idx_created (created_at),
    INDEX idx_admin (admin_id)
);

-- ML model retraining history
CREATE TABLE IF NOT EXISTS model_retraining_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    training_samples INT,
    accuracy DECIMAL(3,2),
    precision DECIMAL(3,2),
    recall DECIMAL(3,2),
    model_version VARCHAR(50),
    deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_model (model_name), 
    INDEX idx_deployed (deployed_at)
);

-- Insert admin user
INSERT IGNORE INTO users (name, email, password, role) VALUES ('Admin', 'admin@artstore.com', '$2y$10$3.0yfE84fFba/pfa1YZKlu5yhJDXeMoNCTvO9UcSyzyCf7tGyPl2i', 'admin'); --admin123

-- Insert sample categories
INSERT IGNORE INTO categories (name) VALUES ('Painting'), ('Sculpture'), ('Photography'), ('Digital Art');

-- Insert sample artworks
INSERT IGNORE INTO artworks (title, artist, price, image, description, category_id, medium, dimensions, year_created, availability) VALUES
('Sunset Landscape', 'John Doe', 150.00, 'art1.jpg', 'A stunning oil painting depicting a vibrant sunset over rolling hills, capturing the warmth of the golden hour.', 1, 'Oil on Canvas', '24x30 inches', 2022, 'available'),
('Abstract Sculpture', 'Jane Smith', 300.00, 'art2.jpg', 'A modern bronze sculpture featuring abstract forms that evoke movement and emotion, perfect for contemporary spaces.', 2, 'Bronze', '18x12x10 inches', 2021, 'available'),
('Cityscape Photography', 'Mike Johnson', 120.00, 'art3.jpg', 'A high-contrast black and white photograph of a bustling city skyline at dusk, highlighting urban architecture.', 3, 'Digital Print', '20x16 inches', 2023, 'available'),
('Digital Fantasy Art', 'Emily Davis', 200.00, 'art4.jpg', 'An imaginative digital artwork of a mystical forest with glowing creatures, created using advanced graphic software.', 4, 'Digital Print', '24x18 inches', 2024, 'available'),
('Portrait Study', 'Robert Wilson', 180.00, 'art5.jpg', 'A detailed charcoal portrait capturing the essence of human expression and emotion in monochromatic tones.', 1, 'Charcoal on Paper', '18x24 inches', 2020, 'available');