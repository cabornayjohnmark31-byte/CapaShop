<?php

declare(strict_types=1);

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

ini_set('session.use_strict_mode', '1');
session_start();

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['dbname']
    );

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        json_response([
            'success' => false,
            'message' => 'Database connection failed. Import the SQL file and check config/database.php.',
        ], 500);
    }

    return $pdo;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        json_response([
            'success' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = csrf_token();

    if (!is_string($requestToken) || !hash_equals($sessionToken, $requestToken)) {
        json_response([
            'success' => false,
            'message' => 'Invalid security token. Please refresh the page and try again.',
        ], 419);
    }
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response([
            'success' => false,
            'message' => 'Invalid JSON payload.',
        ], 400);
    }

    return $decoded;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = get_db()->prepare(
        'SELECT id, username, role, profile_image, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_response([
            'success' => false,
            'message' => 'You must be logged in first.',
        ], 401);
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        json_response([
            'success' => false,
            'message' => 'Administrator access is required.',
        ], 403);
    }

    return $user;
}

function normalize_product(array $product): array
{
    return [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'category' => $product['category'],
        'price' => (float) $product['price'],
        'image' => $product['image'],
        'sold_count' => isset($product['sold_count']) ? (int) $product['sold_count'] : 0,
    ];
}
