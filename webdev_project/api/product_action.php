<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_csrf();
require_admin();

$payload = get_json_input();
$action = (string) ($payload['action'] ?? '');
$productId = (int) ($payload['product_id'] ?? 0);

if ($productId <= 0) {
    json_response([
        'success' => false,
        'message' => 'A valid product is required.',
    ], 422);
}

if ($action === 'delete') {
    $statement = get_db()->prepare('DELETE FROM products WHERE id = :id');
    $statement->execute(['id' => $productId]);

    json_response([
        'success' => true,
        'message' => 'Product deleted successfully.',
    ]);
}

if ($action === 'update') {
    $name = trim((string) ($payload['name'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $price = (float) ($payload['price'] ?? 0);
    $image = trim((string) ($payload['image'] ?? ''));

    if ($name === '' || $category === '' || $price <= 0 || $image === '') {
        json_response([
            'success' => false,
            'message' => 'Name, category, price, and image are required.',
        ], 422);
    }

    $statement = get_db()->prepare(
        'UPDATE products
         SET name = :name, category = :category, price = :price, image = :image
         WHERE id = :id'
    );

    try {
        $statement->execute([
            'id' => $productId,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'image' => $image,
        ]);
    } catch (PDOException $exception) {
        json_response([
            'success' => false,
            'message' => 'Unable to update product. Product names must be unique.',
        ], 409);
    }

    json_response([
        'success' => true,
        'message' => 'Product updated successfully.',
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown product action.',
], 422);

