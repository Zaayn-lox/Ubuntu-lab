<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$client_id = 'Ov23liDstARssqOpoQ8F';
$redirect_uri = 'https://belyaevubuntu.ru/oauth-callback.php';

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'read:user',
    'state' => $state,
]);

header('Location: https://github.com/login/oauth/authorize?' . $params);
exit;
