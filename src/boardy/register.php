<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } elseif (mb_strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'Email уже занят';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $email, $hash]);

            $user_id = $pdo->lastInsertId();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;

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
        <h1>Регистрация</h1>

        <form method="POST" action="/register.php">
            <div class="form-group">
                <label for="name">Имя</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="<?= htmlspecialchars($name) ?>"
                >
            </div>

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

            <button type="submit">Зарегистрироваться</button>
        </form>

        <p class="muted" style="margin-top: 16px;">
            Уже есть аккаунт?
            <a href="/login.php">Войти</a>
        </p>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
