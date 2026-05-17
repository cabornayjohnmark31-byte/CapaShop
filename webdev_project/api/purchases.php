<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_login();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_db();

if ($method === 'GET') {
    $statement = $pdo->prepare(
        'SELECT pc.id, pc.quantity, pc.price_at_purchase, pc.total_amount, p.name, p.image
         FROM purchases pc
         INNER JOIN products p ON p.id = pc.product_id
         WHERE pc.user_id = :user_id
         ORDER BY pc.purchased_at DESC, pc.id DESC'
    );
    $statement->execute(['user_id' => $user['id']]);
    $purchases = [];

    foreach ($statement->fetchAll() as $row) {
        $purchases[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'image' => $row['image'],
            'price' => (float) $row['price_at_purchase'],
            'quantity' => (int) $row['quantity'],
            'total' => (float) $row['total_amount'],
        ];
    }

    json_response([
        'success' => true,
        'purchases' => $purchases,
    ]);
}

if ($method === 'POST') {
    require_csrf();

    if ($user['role'] === 'admin') {
        json_response([
            'success' => false,
            'message' => 'Admins cannot make purchases.',
        ], 403);
    }

    $payload = get_json_input();
    $productId = (int) ($payload['product_id'] ?? 0);
    $quantity = max(1, (int) ($payload['quantity'] ?? 1));

    if ($productId <= 0) {
        json_response([
            'success' => false,
            'message' => 'A valid product is required.',
        ], 422);
    }

    $productStatement = $pdo->prepare(
        'SELECT id, price
         FROM products
         WHERE id = :id
         LIMIT 1'
    );
    $productStatement->execute(['id' => $productId]);
    $product = $productStatement->fetch();

    if (!$product) {
        json_response([
            'success' => false,
            'message' => 'Product not found.',
        ], 404);
    }

    $insertStatement = $pdo->prepare(
        'INSERT INTO purchases (user_id, product_id, quantity, price_at_purchase, total_amount)
         VALUES (:user_id, :product_id, :quantity, :price, :total_amount)'
    );
    $price = (float) $product['price'];
    $insertStatement->execute([
        'user_id' => $user['id'],
        'product_id' => $productId,
        'quantity' => $quantity,
        'price' => $price,
        'total_amount' => $price * $quantity,
    ]);

    json_response([
        'success' => true,
        'message' => 'Purchase completed successfully.',
    ], 201);
}

json_response([
    'success' => false,
    'message' => 'Method not allowed.',
], 405);
