<?php
require_once __DIR__ . '/db.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$client_id = 'Ov23liDstARssqOpoQ8F';
$client_secret = 'c252a4cdb0085b1969a4f8f8b87434c418cc7a12';
$redirect_uri = 'https://belyaevubuntu.ru/oauth-callback.php';

if (($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? '')) {
    die('Invalid state — possible CSRF attack');
}

if (empty($_GET['code'])) {
    die('GitHub did not return code');
}

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $_GET['code'],
        'redirect_uri' => $redirect_uri,
    ]),
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $response['access_token'] ?? null;
if (!$access_token) {
    die('GitHub access_token not received');
}

$ch = curl_init('https://api.github.com/user');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $access_token",
        'User-Agent: Boardy'
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$profile = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($profile['id']) || empty($profile['login'])) {
    die('GitHub profile not received');
}

$stmt = $pdo->prepare('SELECT id, name FROM users WHERE github_id = ?');
$stmt->execute([$profile['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, github_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([
        $profile['login'],
        $profile['login'] . '@github.local',
        $profile['id']
    ]);

    $user = [
        'id' => $pdo->lastInsertId(),
        'name' => $profile['login']
    ];
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];

header('Location: /messages.php');
exit;
