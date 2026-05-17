<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_db();

if ($method === 'GET') {
    $statement = $pdo->query(
        'SELECT id, username, role, created_at
         FROM users
         ORDER BY FIELD(role, "admin", "user"), username ASC'
    );
    $users = [];

    foreach ($statement->fetchAll() as $user) {
        $users[] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
        ];
    }

    json_response([
        'success' => true,
        'users' => $users,
    ]);
}

if ($method === 'POST') {
    require_csrf();
    $payload = get_json_input();
    $action = (string) ($payload['action'] ?? '');
    $userId = (int) ($payload['user_id'] ?? 0);

    if ($action !== 'delete' || $userId <= 0) {
        json_response([
            'success' => false,
            'message' => 'A valid admin action is required.',
        ], 422);
    }

    if ($userId === (int) $admin['id']) {
        json_response([
            'success' => false,
            'message' => 'You cannot delete your own admin account.',
        ], 422);
    }

    $userStatement = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
    $userStatement->execute(['id' => $userId]);
    $targetUser = $userStatement->fetch();

    if (!$targetUser) {
        json_response([
            'success' => false,
            'message' => 'User not found.',
        ], 404);
    }

    if ($targetUser['role'] === 'admin') {
        json_response([
            'success' => false,
            'message' => 'Admin accounts cannot be deleted here.',
        ], 422);
    }

    $deleteStatement = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $deleteStatement->execute(['id' => $userId]);

    json_response([
        'success' => true,
        'message' => 'User deleted successfully.',
    ]);
}

json_response([
    'success' => false,
    'message' => 'Method not allowed.',
], 405);
