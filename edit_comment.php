<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_auth();

$userId    = (int) $_SESSION['user_id'];
$commentId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);

// Ownership is enforced in SQL: the row is only returned if it belongs to this user.
$stmt = $pdo->prepare(
    'SELECT c.id, c.post_id, c.comment, p.title AS post_title
     FROM comments c
     INNER JOIN posts p ON p.id = c.post_id
     WHERE c.id = :id AND c.user_id = :user_id'
);
$stmt->execute([':id' => $commentId ?: 0, ':user_id' => $userId]);
$comment = $stmt->fetch();

if (!$comment) {
    flash('error', 'Comment not found or you are not allowed to edit it.');
    redirect('index.php');
}

$error = '';
$text = $comment['comment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $text = trim($_POST['comment'] ?? '');

    if ($text === '' || mb_strlen($text) > COMMENT_MAX) {
        $error = 'Comment is required and must be at most ' . COMMENT_MAX . ' characters.';
    } else {
        try {
            $pdo->beginTransaction();

            // Re-check ownership and lock the row for the duration of the update.
            $lock = $pdo->prepare('SELECT id FROM comments WHERE id = :id AND user_id = :user_id FOR UPDATE');
            $lock->execute([':id' => $comment['id'], ':user_id' => $userId]);
            if (!$lock->fetch()) {
                $pdo->rollBack();
                flash('error', 'Comment not found or you are not allowed to edit it.');
                redirect('index.php');
            }

            $update = $pdo->prepare(
                'UPDATE comments
                 SET comment = :comment, is_edited = 1
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                ':comment' => $text,
                ':id'      => $comment['id'],
                ':user_id' => $userId,
            ]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        flash('success', 'Comment updated.');
        redirect('index.php#post-' . (int) $comment['post_id']);
    }
}

$pageTitle = 'Edit comment';
require __DIR__ . '/partials/header.php';
?>
<section class="card">
    <h1>Edit comment</h1>
    <p class="muted">On post: <strong><?= escape($comment['post_title']) ?></strong></p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="edit_comment.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">

        <label for="comment">Comment</label>
        <textarea id="comment" name="comment" rows="4" required maxlength="<?= COMMENT_MAX ?>"><?= escape($text) ?></textarea>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="index.php#post-<?= (int) $comment['post_id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
