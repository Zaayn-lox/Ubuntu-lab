<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

$secret_key = '123';

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generate_jwt(int $user_id, string $user_name, string $secret_key): string
{
    $header = base64url_encode(json_encode([
        'alg' => 'HS256',
        'typ' => 'JWT'
    ], JSON_UNESCAPED_UNICODE));

    $payload = base64url_encode(json_encode([
        'user_id' => $user_id,
        'name' => $user_name,
        'exp' => time() + 3600
    ], JSON_UNESCAPED_UNICODE));

    $signature = base64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, $secret_key, true)
    );

    return $header . '.' . $payload . '.' . $signature;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_name = (string) ($_SESSION['user_name'] ?? '');

$jwt = generate_jwt($user_id, $user_name, $secret_key);

echo json_encode(['token' => $jwt], JSON_UNESCAPED_UNICODE);
