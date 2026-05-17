<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_csrf();
$user = require_login();

$payload = get_json_input();
$action = (string) ($payload['action'] ?? '');
$purchaseId = (int) ($payload['purchase_id'] ?? 0);

if ($action !== 'delete' || $purchaseId <= 0) {
    json_response([
        'success' => false,
        'message' => 'A valid purchase action is required.',
    ], 422);
}

$statement = get_db()->prepare(
    'DELETE FROM purchases
     WHERE id = :id AND user_id = :user_id'
);
$statement->execute([
    'id' => $purchaseId,
    'user_id' => $user['id'],
]);

json_response([
    'success' => true,
    'message' => 'Purchase removed successfully.',
]);

