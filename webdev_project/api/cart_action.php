<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_method('POST');
require_csrf();
$user = require_login();

if ($user['role'] === 'admin') {
    json_response([
        'success' => false,
        'message' => 'Admins cannot access the cart.',
    ], 403);
}

$payload = get_json_input();
$action = (string) ($payload['action'] ?? '');
$cartItemId = (int) ($payload['cart_item_id'] ?? 0);

if ($cartItemId <= 0) {
    json_response([
        'success' => false,
        'message' => 'A valid cart item is required.',
    ], 422);
}

$pdo = get_db();
$statement = $pdo->prepare(
    'SELECT c.id, c.quantity, p.id AS product_id, p.price
     FROM cart_items c
     INNER JOIN products p ON p.id = c.product_id
     WHERE c.id = :id AND c.user_id = :user_id
     LIMIT 1'
);
$statement->execute([
    'id' => $cartItemId,
    'user_id' => $user['id'],
]);
$cartItem = $statement->fetch();

if (!$cartItem) {
    json_response([
        'success' => false,
        'message' => 'Cart item not found.',
    ], 404);
}

if ($action === 'remove') {
    $deleteStatement = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');
    $deleteStatement->execute(['id' => $cartItemId]);

    json_response([
        'success' => true,
        'message' => 'Item removed from cart.',
    ]);
}

if ($action === 'buy') {
    $pdo->beginTransaction();

    try {
        $insertStatement = $pdo->prepare(
            'INSERT INTO purchases (user_id, product_id, quantity, price_at_purchase, total_amount)
             VALUES (:user_id, :product_id, :quantity, :price, :total_amount)'
        );
        $quantity = (int) $cartItem['quantity'];
        $price = (float) $cartItem['price'];
        $insertStatement->execute([
            'user_id' => $user['id'],
            'product_id' => (int) $cartItem['product_id'],
            'quantity' => $quantity,
            'price' => $price,
            'total_amount' => $quantity * $price,
        ]);

        $deleteStatement = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');
        $deleteStatement->execute(['id' => $cartItemId]);

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'success' => false,
            'message' => 'Unable to complete purchase.',
        ], 500);
    }

    json_response([
        'success' => true,
        'message' => 'Purchase completed successfully.',
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown cart action.',
], 422);

