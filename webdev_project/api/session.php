<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = current_user();

json_response([
    'success' => true,
    'authenticated' => $user !== null,
    'user' => $user ? [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'profile_image' => $user['profile_image'],
    ] : null,
]);
