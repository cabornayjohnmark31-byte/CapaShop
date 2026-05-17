<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $db = getDb();
    $action = $_GET['action'] ?? '';
    $data = readJsonInput();

    switch ($action) {
        case 'signup':
            handleSignup($db, $data);
            break;

        case 'login':
            handleLogin($db, $data);
            break;

        case 'logout':
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            jsonResponse(['success' => true, 'message' => 'Logged out successfully.']);
            break;

        case 'session':
            if (!empty($_SESSION['user_id'])) {
                jsonResponse([
                    'success' => true,
                    'loggedIn' => true,
                    'user' => [
                        'id' => (int) $_SESSION['user_id'],
                        'username' => (string) $_SESSION['username'],
                        'role' => (string) $_SESSION['role']
                    ]
                ]);
            }

            jsonResponse(['success' => true, 'loggedIn' => false]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid auth action.'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'An unexpected server error occurred.'], 500);
}

function handleSignup(mysqli $db, array $data): void
{
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($username === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Username and password are required.'], 422);
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        jsonResponse(['success' => false, 'message' => 'Use 3-30 letters, numbers, or underscores only.'], 422);
    }

    if (strlen($password) < 4) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 4 characters long.'], 422);
    }

    $checkStmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $checkStmt->bind_param('s', $username);
    $checkStmt->execute();

    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        jsonResponse(['success' => false, 'message' => 'Username already exists.'], 409);
    }
    $checkStmt->close();

    $role = 'user';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $db->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $insertStmt->bind_param('sss', $username, $passwordHash, $role);

    if (!$insertStmt->execute()) {
        $insertStmt->close();
        jsonResponse(['success' => false, 'message' => 'Failed to create account.'], 500);
    }
    $insertStmt->close();

    jsonResponse(['success' => true, 'message' => 'Account created successfully.']);
}

function handleLogin(mysqli $db, array $data): void
{
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($username === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Username and password are required.'], 422);
    }

    $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid username or password.'], 401);
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    jsonResponse([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ]);
}

