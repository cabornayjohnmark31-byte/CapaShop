<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $db = getDb();
    $user = requireLogin();
    $action = $_GET['action'] ?? '';
    $data = readJsonInput();

    switch ($action) {
        case 'products':
            $stmt = $db->prepare(
                'SELECT p.id, p.name, p.price, p.category, p.image, COALESCE(SUM(pr.quantity), 0) AS sold_count
                 FROM products p
                 LEFT JOIN purchases pr ON pr.product_id = p.id
                 GROUP BY p.id, p.name, p.price, p.category, p.image
                 ORDER BY p.id ASC'
            );
            $stmt->execute();
            $result = $stmt->get_result();
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['price'] = (float) $row['price'];
                $row['sold_count'] = (int) $row['sold_count'];
                $products[] = $row;
            }
            $stmt->close();
            jsonResponse(['success' => true, 'products' => $products]);
            break;

        case 'cart':
            $stmt = $db->prepare(
                'SELECT p.id, p.name, p.price, p.category, p.image, c.quantity,
                        (p.price * c.quantity) AS total
                 FROM cart_items c
                 INNER JOIN products p ON p.id = c.product_id
                 WHERE c.user_id = ?
                 ORDER BY c.id DESC'
            );
            $stmt->bind_param('i', $user['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['quantity'] = (int) $row['quantity'];
                $row['price'] = (float) $row['price'];
                $row['total'] = (float) $row['total'];
                $items[] = $row;
            }
            $stmt->close();
            jsonResponse(['success' => true, 'cart' => $items]);
            break;

        case 'purchases':
            $stmt = $db->prepare(
                'SELECT id, product_id, product_name AS name, product_image AS image, price, quantity, total, purchased_at
                 FROM purchases
                 WHERE user_id = ?
                 ORDER BY purchased_at DESC, id DESC'
            );
            $stmt->bind_param('i', $user['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $purchases = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['product_id'] = $row['product_id'] === null ? null : (int) $row['product_id'];
                $row['quantity'] = (int) $row['quantity'];
                $row['price'] = (float) $row['price'];
                $row['total'] = (float) $row['total'];
                $purchases[] = $row;
            }
            $stmt->close();
            jsonResponse(['success' => true, 'purchases' => $purchases]);
            break;

        case 'sales_summary':
            if ($user['role'] !== 'admin') {
                jsonResponse(['success' => false, 'message' => 'Administrator access only.'], 403);
            }
            $result = $db->query('SELECT COALESCE(SUM(quantity), 0) AS total_sold, COALESCE(SUM(total), 0) AS total_sales FROM purchases');
            $summary = $result->fetch_assoc() ?: ['total_sold' => 0, 'total_sales' => 0];
            jsonResponse([
                'success' => true,
                'summary' => [
                    'total_sold' => (int) $summary['total_sold'],
                    'total_sales' => (float) $summary['total_sales']
                ]
            ]);
            break;

        case 'add_to_cart':
            updateCart($db, $user['user_id'], $data);
            break;

        case 'buy_now':
            purchaseProduct($db, $user['user_id'], (int) ($data['product_id'] ?? 0), (int) ($data['quantity'] ?? 1), true);
            break;

        case 'buy_cart_item':
            buyFromCart($db, $user['user_id'], (int) ($data['product_id'] ?? 0));
            break;

        case 'remove_cart_item':
            $productId = (int) ($data['product_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->bind_param('ii', $user['user_id'], $productId);
            $stmt->execute();
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Item removed from cart.']);
            break;

        case 'delete_purchase':
            $purchaseId = (int) ($data['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM purchases WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $purchaseId, $user['user_id']);
            $stmt->execute();
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Purchase removed.']);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid shop action.'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function updateCart(mysqli $db, int $userId, array $data): void
{
    $productId = (int) ($data['product_id'] ?? 0);
    $quantity = max(1, (int) ($data['quantity'] ?? 1));

    if ($productId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid product selected.'], 422);
    }

    $stmt = $db->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    $stmt->bind_param('iii', $userId, $productId, $quantity);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true, 'message' => 'Item added to cart.']);
}

function purchaseProduct(mysqli $db, int $userId, int $productId, int $quantity, bool $removeFromCart = false): void
{
    if ($productId <= 0 || $quantity <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid purchase request.'], 422);
    }

    $productStmt = $db->prepare('SELECT id, name, price, image FROM products WHERE id = ? LIMIT 1');
    $productStmt->bind_param('i', $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();
    $productStmt->close();

    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'Product not found.'], 404);
    }

    $db->begin_transaction();

    try {
        $price = (float) $product['price'];
        $total = $price * $quantity;

        $purchaseStmt = $db->prepare(
            'INSERT INTO purchases (user_id, product_id, product_name, product_image, price, quantity, total)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $purchaseStmt->bind_param(
            'iissdid',
            $userId,
            $productId,
            $product['name'],
            $product['image'],
            $price,
            $quantity,
            $total
        );
        $purchaseStmt->execute();
        $purchaseStmt->close();

        if ($removeFromCart) {
            $removeStmt = $db->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $removeStmt->bind_param('ii', $userId, $productId);
            $removeStmt->execute();
            $removeStmt->close();
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Purchase completed successfully.']);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function buyFromCart(mysqli $db, int $userId, int $productId): void
{
    if ($productId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid cart item selected.'], 422);
    }

    $stmt = $db->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $cartItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cartItem) {
        jsonResponse(['success' => false, 'message' => 'Cart item not found.'], 404);
    }

    purchaseProduct($db, $userId, $productId, (int) $cartItem['quantity'], true);
}
