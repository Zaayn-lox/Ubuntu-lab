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

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$error = '';
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');

    if ($body === '') {
        $error = 'Введите текст поста';
    } else {
        $title = 'Сообщение';

        $stmt = $pdo->prepare(
            'INSERT INTO posts (title, body, author_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$title, $body, $_SESSION['user_id']]);

        header('Location: /messages.php');
        exit;
    }
}
?>
<?php include __DIR__ . '/partials/head.php'; ?>
<?php include __DIR__ . '/partials/nav.php'; ?>

<div class="container">
    <div class="card">
        <h1>Добавить пост</h1>

        <form method="POST" action="/submit.php">
            <div class="form-group">
                <label for="body">Текст поста</label>
                <textarea id="body" name="body" rows="6"><?= htmlspecialchars($body) ?></textarea>
            </div>

            <button type="submit">Опубликовать</button>
        </form>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
