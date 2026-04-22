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

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Заполните все поля';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, password_hash FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $error = 'Неверный email или пароль';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header('Location: /messages.php');
            exit;
        }
    }
}
?>
<?php include __DIR__ . '/partials/head.php'; ?>
<?php include __DIR__ . '/partials/nav.php'; ?>

<div class="container">
    <div class="card">
        <h1>Вход</h1>

        <form method="POST" action="/login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($email) ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                >
            </div>

            <button type="submit">Войти</button>
        </form>

        <p class="muted" style="margin-top: 16px;">
            Нет аккаунта?
            <a href="/register.php">Регистрация</a>
        </p>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
