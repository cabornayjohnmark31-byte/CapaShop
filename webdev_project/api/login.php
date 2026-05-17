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

$statement = get_db()->prepare(
    'SELECT id, username, password_hash, role, profile_image
     FROM users
     WHERE username = :username
     LIMIT 1'
);
$statement->execute(['username' => $username]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response([
        'success' => false,
        'message' => 'Invalid username or password.',
    ], 401);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

json_response([
    'success' => true,
    'message' => 'Login successful.',
    'user' => [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'profile_image' => $user['profile_image'],
    ],
]);

