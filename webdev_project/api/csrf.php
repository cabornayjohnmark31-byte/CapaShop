<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

json_response([
    'success' => true,
    'csrf_token' => csrf_token(),
]);
