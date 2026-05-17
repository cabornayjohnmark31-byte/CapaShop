<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'webdev_projectdb';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function readJsonInput(): array
{
    $rawInput = file_get_contents('php://input') ?: '';

    if ($rawInput === '') {
        return $_POST ?: [];
    }

    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    parse_str($rawInput, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function getDb(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $server = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($server->connect_error) {
        throw new RuntimeException('MySQL is not running. Start MySQL in XAMPP and try again.');
    }

    $server->set_charset('utf8mb4');
    if (!$server->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
        throw new RuntimeException('Failed to create database: ' . $server->error);
    }
    $server->close();

    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new RuntimeException('Failed to open database: ' . $db->connect_error);
    }

    $db->set_charset('utf8mb4');
    initializeSchema($db);

    return $db;
}

function initializeSchema(mysqli $db): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            category VARCHAR(50) NOT NULL,
            image LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, product_id),
            CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NULL,
            product_name VARCHAR(150) NOT NULL,
            product_image LONGTEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            quantity INT NOT NULL DEFAULT 1,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_purchase_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($queries as $query) {
        if (!$db->query($query)) {
            throw new RuntimeException('Schema setup failed: ' . $db->error);
        }
    }

    seedDefaultProducts($db);
}

function seedDefaultProducts(mysqli $db): void
{
    $defaultProducts = [
        ['Timeless T-shirt', 420.00, 'T-Shirt', 'assets/1.jpg'],
        ['Fashion Shoes', 550.00, 'Shoes', 'assets/2.jpg'],
        ['Black & White Shoes', 550.00, 'Shoes', 'assets/3.jpg'],
        ['Jordan Short', 240.00, 'Shorts', 'assets/4.jpg'],
        ['Fashion Short', 310.00, 'Shorts', 'assets/5.jpg']
    ];

    $checkStmt = $db->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
    $insertStmt = $db->prepare('INSERT INTO products (name, price, category, image) VALUES (?, ?, ?, ?)');

    foreach ($defaultProducts as [$name, $price, $category, $image]) {
        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();

        if (!$exists) {
            $insertStmt->bind_param('sdss', $name, $price, $category, $image);
            $insertStmt->execute();
        }
    }

    $checkStmt->close();
    $insertStmt->close();
}

function requireLogin(): array
{
    if (empty($_SESSION['user_id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Please log in first.'
        ], 401);
    }

    return [
        'user_id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'role' => (string) $_SESSION['role']
    ];
}

function requireAdmin(): array
{
    $user = requireLogin();

    if ($user['role'] !== 'admin') {
        jsonResponse([
            'success' => false,
            'message' => 'Administrator access only.'
        ], 403);
    }

    return $user;
}
