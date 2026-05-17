<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_csrf();
$user = require_login();
$payload = get_json_input();
$profileImage = trim((string) ($payload['profile_image'] ?? ''));

if ($profileImage === '') {
    json_response([
        'success' => false,
        'message' => 'Profile image is required.',
    ], 422);
}

$statement = get_db()->prepare(
    'UPDATE users
     SET profile_image = :profile_image
     WHERE id = :id'
);
$statement->execute([
    'profile_image' => $profileImage,
    'id' => $user['id'],
]);

json_response([
    'success' => true,
    'message' => 'Profile picture saved.',
]);

