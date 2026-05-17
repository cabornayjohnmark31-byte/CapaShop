<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_login();

if ($user['role'] === 'admin') {
    json_response([
        'success' => false,
        'message' => 'Admins cannot access the cart.',
    ], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_db();

if ($method === 'GET') {
    $statement = $pdo->prepare(
        'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.category, p.price, p.image
         FROM cart_items c
         INNER JOIN products p ON p.id = c.product_id
         WHERE c.user_id = :user_id
         ORDER BY c.id ASC'
    );
    $statement->execute(['user_id' => $user['id']]);
    $items = [];

    foreach ($statement->fetchAll() as $row) {
        $price = (float) $row['price'];
        $quantity = (int) $row['quantity'];
        $items[] = [
            'id' => (int) $row['id'],
            'product_id' => (int) $row['product_id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => $price,
            'image' => $row['image'],
            'quantity' => $quantity,
            'total' => $price * $quantity,
        ];
    }

    json_response([
        'success' => true,
        'items' => $items,
    ]);
}

if ($method === 'POST') {
    require_csrf();
    $payload = get_json_input();
    $productId = (int) ($payload['product_id'] ?? 0);
    $quantity = max(1, (int) ($payload['quantity'] ?? 1));

    if ($productId <= 0) {
        json_response([
            'success' => false,
            'message' => 'A valid product is required.',
        ], 422);
    }

    $productStatement = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
    $productStatement->execute(['id' => $productId]);
    if (!$productStatement->fetch()) {
        json_response([
            'success' => false,
            'message' => 'Product not found.',
        ], 404);
    }

    $checkStatement = $pdo->prepare(
        'SELECT id, quantity
         FROM cart_items
         WHERE user_id = :user_id AND product_id = :product_id
         LIMIT 1'
    );
    $checkStatement->execute([
        'user_id' => $user['id'],
        'product_id' => $productId,
    ]);
    $existing = $checkStatement->fetch();

    if ($existing) {
        $updateStatement = $pdo->prepare(
            'UPDATE cart_items
             SET quantity = :quantity
             WHERE id = :id'
        );
        $updateStatement->execute([
            'quantity' => (int) $existing['quantity'] + $quantity,
            'id' => $existing['id'],
        ]);
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity)
             VALUES (:user_id, :product_id, :quantity)'
        );
        $insertStatement->execute([
            'user_id' => $user['id'],
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    json_response([
        'success' => true,
        'message' => 'Item added to cart.',
    ], 201);
}

json_response([
    'success' => false,
    'message' => 'Method not allowed.',
], 405);
