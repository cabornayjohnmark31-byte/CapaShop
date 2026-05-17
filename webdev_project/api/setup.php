<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    getDb();
    jsonResponse([
        'success' => true,
        'message' => 'Database, tables, and starter data are ready.',
        'database' => DB_NAME
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
