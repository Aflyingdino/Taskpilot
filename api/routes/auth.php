<?php
/*
 * Auth routes: register, login, logout, me
 */

function handleRegister(): never
{
    $data = jsonBody();
    requireFields($data, ['name', 'email', 'password']);

    $name  = clampString($data['name'], 100);
    $email = normalizeEmail($data['email']);
    $pass  = $data['password'];

    validateUsername($name);

    if (!validEmail($email)) jsonError('Invalid email address', 422);
    if (!validPassword($pass)) jsonError('Password must be at least 10 characters and include letters and numbers', 422);

    enforceRateLimit('register-email:' . sha1($email), RATE_LIMIT_AUTH, RATE_LIMIT_AUTH_WINDOW);

    $db = db();

    // Check uniqueness
    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonError('Email already in use', 409);

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);
    $uid = (int) $db->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    $_SESSION['last_activity_at'] = time();
    rotateCsrfToken();

    securityLog('register_success', ['userId' => $uid]);

    jsonResponse([
        'id'    => $uid,
        'name'  => $name,
        'email' => $email,
        'csrfToken' => csrfToken(),
    ], 201);
}

function handleLogin(): never
{
    $data = jsonBody();
    requireFields($data, ['email', 'password']);

    $email = normalizeEmail($data['email']);
    $pass  = $data['password'];

    enforceRateLimit('login-email:' . sha1($email), RATE_LIMIT_AUTH, RATE_LIMIT_AUTH_WINDOW);

    $stmt = db()->prepare('SELECT user_id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        securityLog('login_failed', ['emailHash' => sha1($email)]);
        jsonError('Invalid email or password', 401);
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = password_hash($pass, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
            ->execute([$rehash, (int) $user['user_id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['last_activity_at'] = time();
    rotateCsrfToken();

    securityLog('login_success', ['userId' => (int) $user['user_id']]);

    jsonResponse([
        'id'    => (int) $user['user_id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'csrfToken' => csrfToken(),
    ]);
}

function handleLogout(): never
{
    $uid = currentUserId();
    clearSessionState();
    securityLog('logout', ['userId' => $uid]);
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
