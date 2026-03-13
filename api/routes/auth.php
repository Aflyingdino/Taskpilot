<?php
/*
 * Auth routes: register, login, logout, me
 */

function handleRegister(): never
{
    $data = jsonBody();
    requireFields($data, ['name', 'email', 'password']);

    $name  = clampString($data['name'], 100);
    $email = strtolower(trim($data['email']));
    $pass  = $data['password'];

    if (!validEmail($email)) jsonError('Invalid email address', 422);
    if (mb_strlen($pass) < 6)  jsonError('Password must be at least 6 characters', 422);

    $db = db();

    // Check uniqueness
    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonError('Email already in use', 409);

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);
    $uid = (int) $db->lastInsertId();

    $_SESSION['user_id'] = $uid;

    jsonResponse([
        'id'    => $uid,
        'name'  => $name,
        'email' => $email,
    ], 201);
}

function handleLogin(): never
{
    $data = jsonBody();
    requireFields($data, ['email', 'password']);

    $email = strtolower(trim($data['email']));
    $pass  = $data['password'];

    $stmt = db()->prepare('SELECT user_id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        jsonError('Invalid email or password', 401);
    }

    $_SESSION['user_id'] = (int) $user['user_id'];

    jsonResponse([
        'id'    => (int) $user['user_id'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ]);
}

function handleLogout(): never
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
    jsonSuccess('Logged out');
}

function handleMe(): never
{
    $uid = currentUserId();
    if (!$uid) jsonError('Not authenticated', 401);

    $stmt = db()->prepare('SELECT user_id, name, email, created_at FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user) {
        // Session references a deleted user
        $_SESSION = [];
        jsonError('Not authenticated', 401);
    }

    jsonResponse([
        'id'        => (int) $user['user_id'],
        'name'      => $user['name'],
        'email'     => $user['email'],
        'createdAt' => $user['created_at'],
    ]);
}
