<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_auth();

// Deleting is POST-only (with CSRF) so a link alone cannot delete anything.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$userId    = (int) $_SESSION['user_id'];
$commentId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

try {
    $pdo->beginTransaction();

    // Ownership is enforced in SQL; lock the row until the delete finishes.
    $lock = $pdo->prepare('SELECT id, post_id FROM comments WHERE id = :id AND user_id = :user_id FOR UPDATE');
    $lock->execute([':id' => $commentId ?: 0, ':user_id' => $userId]);
    $comment = $lock->fetch();
    if (!$comment) {
        $pdo->rollBack();
        flash('error', 'Comment not found or you are not allowed to delete it.');
        redirect('index.php');
    }

    $delete = $pdo->prepare('DELETE FROM comments WHERE id = :id AND user_id = :user_id');
    $delete->execute([':id' => $commentId, ':user_id' => $userId]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

flash('success', 'Comment deleted.');
redirect('index.php#post-' . (int) $comment['post_id']);
