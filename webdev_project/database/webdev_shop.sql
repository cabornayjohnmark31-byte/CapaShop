CREATE DATABASE IF NOT EXISTS webdev_projectdb;
USE webdev_projectdb;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    profile_image LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    price_at_purchase DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchase_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO users (username, password_hash, role)
VALUES
    ('Johnmark', '$2y$10$lmF8j28KZ8CuhN7i4FCR4uLMHMh7sYfXbJfjWwfXW7RlTiKQf0F.K', 'admin'),
    ('Christian', '$2y$10$y8647BzSRO4p50fOBg3PPeJGPWyBYVR9WcfkQVF1Dr7WoNI6lhjTm', 'admin')
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    role = VALUES(role);

INSERT INTO products (name, category, price, image)
VALUES
    ('Timeless T-shirt', 'T-Shirt', 420.00, 'assets/1.jpg'),
    ('Fashion Shoes', 'Shoes', 550.00, 'assets/2.jpg'),
    ('Black & White Shoes', 'Shoes', 550.00, 'assets/3.jpg'),
    ('Jordan Short', 'Shorts', 240.00, 'assets/4.jpg'),
    ('Fashion Short', 'Shorts', 310.00, 'assets/5.jpg')
ON DUPLICATE KEY UPDATE
    category = VALUES(category),
    price = VALUES(price),
    image = VALUES(image);

