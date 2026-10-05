<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_auth();

$userId = (int) $_SESSION['user_id'];
$postId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);

// Ownership is enforced in SQL: the row is only returned if it belongs to this user.
$stmt = $pdo->prepare('SELECT id, title, content FROM posts WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $postId ?: 0, ':user_id' => $userId]);
$post = $stmt->fetch();

if (!$post) {
    flash('error', 'Post not found or you are not allowed to edit it.');
    redirect('index.php');
}

$errors = [];
$title = $post['title'];
$content = $post['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || mb_strlen($title) > 255) {
        $errors[] = 'Title is required and must be at most 255 characters.';
    }
    if ($content === '' || mb_strlen($content) > POST_CONTENT_MAX) {
        $errors[] = 'Post content is required and must be at most ' . POST_CONTENT_MAX . ' characters.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Re-check ownership and lock the row for the duration of the update.
            $lock = $pdo->prepare('SELECT id FROM posts WHERE id = :id AND user_id = :user_id FOR UPDATE');
            $lock->execute([':id' => $post['id'], ':user_id' => $userId]);
            if (!$lock->fetch()) {
                $pdo->rollBack();
                flash('error', 'Post not found or you are not allowed to edit it.');
                redirect('index.php');
            }

            $update = $pdo->prepare(
                'UPDATE posts
                 SET title = :title, content = :content, is_edited = 1
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                ':title'   => $title,
                ':content' => $content,
                ':id'      => $post['id'],
                ':user_id' => $userId,
            ]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        flash('success', 'Post updated.');
        redirect('index.php#post-' . (int) $post['id']);
    }
}

$pageTitle = 'Edit post';
require __DIR__ . '/partials/header.php';
?>
<section class="card">
    <h1>Edit post</h1>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="edit_post.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= escape($title) ?>" required maxlength="255">

        <label for="content">Content</label>
        <textarea id="content" name="content" rows="8" required maxlength="<?= POST_CONTENT_MAX ?>"><?= escape($content) ?></textarea>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="index.php#post-<?= (int) $post['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
