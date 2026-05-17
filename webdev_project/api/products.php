<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = require_login();
    $statement = get_db()->query(
        'SELECT p.id, p.name, p.category, p.price, p.image, COALESCE(SUM(pc.quantity), 0) AS sold_count
         FROM products p
         LEFT JOIN purchases pc ON pc.product_id = p.id
         GROUP BY p.id
         ORDER BY p.id ASC'
    );

    $products = array_map('normalize_product', $statement->fetchAll());
    $summary = [
        'total_sold' => 0,
        'total_sales' => 0,
    ];

    if ($user['role'] === 'admin') {
        foreach ($products as $product) {
            $summary['total_sold'] += $product['sold_count'];
            $summary['total_sales'] += $product['sold_count'] * $product['price'];
        }
    }

    json_response([
        'success' => true,
        'products' => $products,
        'summary' => $summary,
    ]);
}

if ($method === 'POST') {
    require_csrf();
    require_admin();
    $payload = get_json_input();

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
        'INSERT INTO products (name, category, price, image)
         VALUES (:name, :category, :price, :image)'
    );

    try {
        $statement->execute([
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'image' => $image,
        ]);
    } catch (PDOException $exception) {
        json_response([
            'success' => false,
            'message' => 'Unable to add product. Product names must be unique.',
        ], 409);
    }

    json_response([
        'success' => true,
        'message' => 'Product added successfully.',
    ], 201);
}

json_response([
    'success' => false,
    'message' => 'Method not allowed.',
], 405);
