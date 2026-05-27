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

$stmt = $pdo->query('
    SELECT p.id, p.body, p.created_at,
           u.name AS author_name
    FROM posts p
    JOIN users u ON p.author_id = u.id
    ORDER BY p.created_at DESC
');
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/partials/head.php'; ?>
<?php include __DIR__ . '/partials/nav.php'; ?>

<div class="container">
    <div class="card">
        <h1>Все посты</h1>

        <?php if (empty($posts)): ?>
            <p>Постов пока нет.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <div><strong><?= htmlspecialchars($post['author_name']) ?></strong></div>
                    <div class="muted"><?= htmlspecialchars($post['created_at']) ?></div>
                    <p><?= nl2br(htmlspecialchars($post['body'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
