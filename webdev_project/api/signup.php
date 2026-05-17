<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_csrf();

$payload = get_json_input();
$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($username === '' || $password === '') {
    json_response([
        'success' => false,
        'message' => 'Username and password are required.',
    ], 422);
}

if (strlen($username) < 3) {
    json_response([
        'success' => false,
        'message' => 'Username must be at least 3 characters long.',
    ], 422);
}

if (strlen($password) < 4) {
    json_response([
        'success' => false,
        'message' => 'Password must be at least 4 characters long.',
    ], 422);
}

$pdo = get_db();
$checkStatement = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$checkStatement->execute(['username' => $username]);
if ($checkStatement->fetch()) {
    json_response([
        'success' => false,
        'message' => 'Username already exists.',
    ], 409);
}

$insertStatement = $pdo->prepare(
    'INSERT INTO users (username, password_hash, role)
     VALUES (:username, :password_hash, :role)'
);
$insertStatement->execute([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'user',
]);

json_response([
    'success' => true,
    'message' => 'Account created. Please log in.',
]);

