<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $db = getDb();
    $admin = requireAdmin();
    $action = $_GET['action'] ?? '';
    $data = readJsonInput();

    switch ($action) {
        case 'users':
            $result = $db->query("SELECT id, username, role, created_at FROM users ORDER BY CASE WHEN role = 'admin' THEN 0 ELSE 1 END, username ASC");
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $users[] = $row;
            }
            jsonResponse(['success' => true, 'users' => $users]);
            break;

        case 'products':
            $result = $db->query('SELECT id, name, price, category, image FROM products ORDER BY id DESC');
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['price'] = (float) $row['price'];
                $products[] = $row;
            }
            jsonResponse(['success' => true, 'products' => $products]);
            break;

        case 'delete_user':
            $userId = (int) ($data['id'] ?? 0);
            if ($userId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Invalid user selected.'], 422);
            }
            if ($userId === (int) $admin['user_id']) {
                jsonResponse(['success' => false, 'message' => 'You cannot delete the account you are using.'], 422);
            }

            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected < 1) {
                jsonResponse(['success' => false, 'message' => 'Only non-admin users can be deleted.'], 422);
            }

            jsonResponse(['success' => true, 'message' => 'User deleted successfully.']);
            break;

        case 'add_product':
            saveProduct($db, $data);
            break;

        case 'update_product':
            saveProduct($db, $data, true);
            break;

        case 'delete_product':
            $productId = (int) ($data['id'] ?? 0);
            if ($productId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Invalid product selected.'], 422);
            }

            $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            if ($deleted < 1) {
                jsonResponse(['success' => false, 'message' => 'Product not found.'], 404);
            }

            jsonResponse(['success' => true, 'message' => 'Product deleted successfully.']);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid admin action.'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function saveProduct(mysqli $db, array $data, bool $isUpdate = false): void
{
    $productId = (int) ($data['id'] ?? 0);
    $name = trim((string) ($data['name'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $image = trim((string) ($data['image'] ?? ''));
    $price = isset($data['price']) ? (float) $data['price'] : 0;

    if ($name === '' || $category === '' || $image === '' || $price <= 0) {
        jsonResponse(['success' => false, 'message' => 'Name, price, category, and image are required.'], 422);
    }

    if ($isUpdate) {
        if ($productId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid product ID.'], 422);
        }

        $stmt = $db->prepare('UPDATE products SET name = ?, price = ?, category = ?, image = ? WHERE id = ?');
        $stmt->bind_param('sdssi', $name, $price, $category, $image, $productId);
        $stmt->execute();
        $stmt->close();

        jsonResponse(['success' => true, 'message' => 'Product updated successfully.']);
    }

    $stmt = $db->prepare('INSERT INTO products (name, price, category, image) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sdss', $name, $price, $category, $image);

    if (!$stmt->execute()) {
        $message = str_contains(strtolower($stmt->error), 'duplicate')
            ? 'A product with that name already exists.'
            : 'Failed to add product.';
        $stmt->close();
        jsonResponse(['success' => false, 'message' => $message], 422);
    }

    $stmt->close();
    jsonResponse(['success' => true, 'message' => 'Product added successfully.']);
}
